<?php

/**
 * Care Gaps Worklist for OpenEMR.
 *
 * @package   Vicert\CareGaps
 * @link      https://vicert.ai
 * @copyright Copyright (c) 2026 Vicert
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Vicert\CareGaps\Repository;

use DateTimeImmutable;
use OpenEMR\Common\Database\QueryUtils;
use Vicert\CareGaps\Domain\PatientRecord;
use Vicert\CareGaps\Measures\A1cMeasure;
use Vicert\CareGaps\Measures\BloodPressureMeasure;
use Vicert\CareGaps\Measures\MeasureCodes;
use Vicert\CareGaps\Service\RecordAssembler;

/**
 * Reads everything the measures need with a fixed number of queries across all patients at once
 * (never one query per patient). Every value goes in as a bound parameter; the only SQL built at run
 * time is "?" placeholder lists and constant fragments.
 */
final class ClinicalDataRepository
{
    /** Enough to say "last BP was ..." for a patient who has not been checked in 12 months. */
    private const VITALS_LOOKBACK = '-3 years';
    private const SCREENING_LOOKBACK = '-11 years';

    /** Lab dates in order of preference: when the sample was taken, then when it was resulted. */
    private const LAB_DATE_COLUMNS = ['report_collected', 'result_date', 'report_date', 'order_collected', 'date_ordered'];

    /** @return list<PatientRecord> */
    public function loadRecords(DateTimeImmutable $today): array
    {
        return (new RecordAssembler($today))->assemble(
            $this->patients($today),
            $this->problems(),
            $this->a1cResults(),
            $this->vitals($today),
            $this->screenings($today),
        );
    }

    /** Living patients in the widest age band any measure uses (A1C/CRC up to 75, BP 18-85). */
    private function patients(DateTimeImmutable $today): array
    {
        $youngestDob = $today->modify('-' . A1cMeasure::MIN_AGE . ' years')->format('Y-m-d');
        $oldestDob = $today->modify('-' . (BloodPressureMeasure::MAX_AGE + 1) . ' years')->format('Y-m-d');

        return QueryUtils::fetchRecords(
            "SELECT p.pid, p.fname, p.lname, p.DOB, p.providerID, p.phone_cell, p.phone_home,
                    TRIM(CONCAT(COALESCE(u.fname, ''), ' ', COALESCE(u.lname, ''))) AS provider_name
               FROM patient_data p
               LEFT JOIN users u ON u.id = p.providerID
              WHERE p.DOB <= ? AND p.DOB > ?
                AND (p.deceased_date IS NULL OR p.deceased_date < '1900-01-01')",
            [$youngestDob, $oldestDob],
        );
    }

    /** Problem-list rows that mention any code a measure looks for; RecordAssembler parses them. */
    private function problems(): array
    {
        $prefixes = array_merge(MeasureCodes::DIABETES, MeasureCodes::HYPERTENSION, MeasureCodes::COLORECTAL_EXCLUSIONS);
        [$where, $binds] = $this->diagnosisLike($prefixes, 'l');

        return QueryUtils::fetchRecords(
            "SELECT l.pid, l.diagnosis, l.activity, l.enddate
               FROM lists l
              WHERE l.type = 'medical_problem' AND ($where)",
            $binds,
        );
    }

    /** A1C results, only for patients with diabetes on their problem list. */
    private function a1cResults(): array
    {
        [$where, $binds] = $this->diagnosisLike(MeasureCodes::DIABETES, 'dl');
        $diabetic = "SELECT dl.pid FROM lists dl WHERE dl.type = 'medical_problem' AND ($where)";

        return array_map(
            static fn (array $row): array => RowNormalizer::labRow($row, self::LAB_DATE_COLUMNS),
            $this->labRows(MeasureCodes::A1C_LOINC, null, [$diabetic, $binds]),
        );
    }

    /** Blood pressure readings, only for patients with hypertension on their problem list. */
    private function vitals(DateTimeImmutable $today): array
    {
        [$where, $binds] = $this->diagnosisLike(MeasureCodes::HYPERTENSION, 'hl');

        return QueryUtils::fetchRecords(
            "SELECT v.pid, v.date, v.bps, v.bpd
               FROM form_vitals v
               JOIN forms f ON f.form_id = v.id AND f.formdir = 'vitals' AND f.deleted = 0 AND f.pid = v.pid
              WHERE v.activity = 1 AND v.date >= ? AND v.bps <> '' AND v.bpd <> ''
                AND v.pid IN (SELECT hl.pid FROM lists hl WHERE hl.type = 'medical_problem' AND ($where))",
            [$today->modify(self::VITALS_LOOKBACK)->format('Y-m-d'), ...$binds],
        );
    }

    /** Screening tests from lab results and procedure orders, billing codes, and surgery history. */
    private function screenings(DateTimeImmutable $today): array
    {
        $codes = MeasureCodes::allScreeningCodes();
        $since = $today->modify(self::SCREENING_LOOKBACK)->format('Y-m-d');

        $labs = array_map(
            static fn (array $row): array => RowNormalizer::labRow($row, self::LAB_DATE_COLUMNS),
            $this->labRows($codes, $since, null),
        );

        // The date of service is the encounter date; billing.date is when the charge was entered.
        $billing = QueryUtils::fetchRecords(
            "SELECT b.pid, COALESCE(fe.date, b.date) AS date, b.code, b.code_text AS title
               FROM billing b
               LEFT JOIN form_encounter fe ON fe.pid = b.pid AND fe.encounter = b.encounter
              WHERE b.activity = 1 AND b.code_type IN ('CPT4', 'HCPCS')
                AND b.code IN (" . RowNormalizer::placeholders($codes) . ")
                AND COALESCE(fe.date, b.date) >= ?",
            [...$codes, $since],
        );

        $surgery = array_map(
            RowNormalizer::surgeryRow(...),
            QueryUtils::fetchRecords(
                "SELECT l.pid, l.begdate, l.date, l.diagnosis, l.title
                   FROM lists l
                  WHERE l.type = 'surgery' AND l.activity = 1
                    AND (l.title LIKE '%colonoscop%' OR l.title LIKE '%sigmoidoscop%' OR l.title LIKE '%colonograph%'
                         OR l.diagnosis LIKE '%CPT4:%' OR l.diagnosis LIKE '%HCPCS:%')",
            ),
        );

        return [...$labs, ...$billing, ...$surgery];
    }

    /**
     * Lab results and completed procedure orders for the given codes, from two index-friendly queries:
     * results coded with one of the codes, and orders for one of the codes whose results carry no code
     * of their own. Cancelled orders and cancelled or erroneous results are left out.
     *
     * @param list<string>                     $codes
     * @param array{0: string, 1: list<string>}|null $pidSubquery limits rows to patients this subquery returns
     */
    private function labRows(array $codes, ?string $since, ?array $pidSubquery): array
    {
        $in = RowNormalizer::placeholders($codes);
        $filters = '';
        $filterBinds = [];
        if ($since !== null) {
            $filters .= ' AND po.date_ordered >= ?';
            $filterBinds[] = $since;
        }
        if ($pidSubquery !== null) {
            $filters .= " AND po.patient_id IN ({$pidSubquery[0]})";
            $filterBinds = [...$filterBinds, ...$pidSubquery[1]];
        }

        $select = "SELECT po.patient_id AS pid, pr.result_code, poc.procedure_code AS order_code, pr.result, pr.units,
                          rep.date_collected AS report_collected, pr.date AS result_date, rep.date_report AS report_date,
                          po.date_collected AS order_collected, po.date_ordered
                     FROM procedure_order po
                     JOIN procedure_order_code poc ON poc.procedure_order_id = po.procedure_order_id";
        $valid = "po.activity = 1 AND (po.order_status IS NULL OR po.order_status NOT IN ('canceled', 'cancelled'))";

        return QueryUtils::fetchRecords(
            "$select
               JOIN procedure_report rep ON rep.procedure_order_id = po.procedure_order_id
                    AND rep.procedure_order_seq = poc.procedure_order_seq
               JOIN procedure_result pr ON pr.procedure_report_id = rep.procedure_report_id
              WHERE pr.result_code IN ($in) AND (pr.result_status IS NULL OR pr.result_status NOT IN ('cancel', 'error'))
                AND $valid $filters
             UNION ALL
             $select
               LEFT JOIN procedure_report rep ON rep.procedure_order_id = po.procedure_order_id
                    AND rep.procedure_order_seq = poc.procedure_order_seq
               LEFT JOIN procedure_result pr ON pr.procedure_report_id = rep.procedure_report_id
              WHERE poc.procedure_code IN ($in) AND (pr.result_code IS NULL OR pr.result_code = '')
                AND (pr.result_status IS NULL OR pr.result_status NOT IN ('cancel', 'error'))
                AND (rep.procedure_report_id IS NOT NULL OR po.order_status = 'complete')
                AND $valid $filters",
            [...$codes, ...$filterBinds, ...$codes, ...$filterBinds],
        );
    }

    /**
     * "alias.diagnosis LIKE ? OR ..." with one "%ICD10:<prefix>%" bind per prefix.
     *
     * @param list<string> $prefixes
     * @return array{0: string, 1: list<string>}
     */
    private function diagnosisLike(array $prefixes, string $alias): array
    {
        return [
            implode(' OR ', array_fill(0, count($prefixes), "$alias.diagnosis LIKE ?")),
            array_map(static fn (string $prefix): string => "%ICD10:{$prefix}%", $prefixes),
        ];
    }
}
