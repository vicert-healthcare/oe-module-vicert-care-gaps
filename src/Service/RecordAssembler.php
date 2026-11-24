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

namespace Vicert\CareGaps\Service;

use DateTimeImmutable;
use Vicert\CareGaps\Domain\BpReading;
use Vicert\CareGaps\Domain\LabResult;
use Vicert\CareGaps\Domain\Patient;
use Vicert\CareGaps\Domain\PatientRecord;
use Vicert\CareGaps\Domain\Problem;
use Vicert\CareGaps\Domain\Screening;
use Vicert\CareGaps\Measures\MeasureCodes;
use Vicert\CareGaps\Support\CodeParser;

/**
 * Turns the raw database rows (one bulk query per data type) into one record per patient.
 * Rows for unknown patients, or with unusable dates or values, are skipped rather than guessed.
 */
final readonly class RecordAssembler
{
    /** @param ?DateTimeImmutable $today when given, rows dated after it (planned or mistyped) are dropped */
    public function __construct(private ?DateTimeImmutable $today = null)
    {
    }

    /**
     * @param list<array<string, mixed>> $patients   pid, fname, lname, DOB, providerID, provider_name,
     *                                               phone_cell, phone_home
     * @param list<array<string, mixed>> $problems   pid, diagnosis, activity, enddate
     * @param list<array<string, mixed>> $a1cResults pid, date, result
     * @param list<array<string, mixed>> $vitals     pid, date, bps, bpd
     * @param list<array<string, mixed>> $screenings pid, date, code, title
     * @return list<PatientRecord>
     */
    public function assemble(
        array $patients,
        array $problems,
        array $a1cResults,
        array $vitals,
        array $screenings,
    ): array {
        $problemsByPid = $this->group($problems, $this->problemsFromRow(...));
        $a1cByPid = $this->group($a1cResults, $this->a1cFromRow(...));
        $bpByPid = $this->group($vitals, $this->bpFromRow(...));
        $screeningsByPid = $this->group($screenings, $this->screeningFromRow(...));

        $records = [];
        foreach ($patients as $row) {
            $patient = $this->patientFromRow($row);
            if ($patient === null) {
                continue;
            }
            $records[] = new PatientRecord(
                $patient,
                $problemsByPid[$patient->pid] ?? [],
                $a1cByPid[$patient->pid] ?? [],
                $bpByPid[$patient->pid] ?? [],
                $screeningsByPid[$patient->pid] ?? [],
            );
        }
        return $records;
    }

    private function patientFromRow(array $row): ?Patient
    {
        $dob = CodeParser::parseDate($this->str($row, 'DOB'));
        $pid = (int) ($row['pid'] ?? 0);
        if ($dob === null || $pid <= 0) {
            return null;
        }
        $providerId = (int) ($row['providerID'] ?? 0);
        return new Patient(
            $pid,
            $this->str($row, 'fname'),
            $this->str($row, 'lname'),
            $dob,
            $providerId > 0 ? $providerId : null,
            trim($this->str($row, 'provider_name')),
            trim($this->str($row, 'phone_cell')) ?: trim($this->str($row, 'phone_home')),
        );
    }

    /** @return list<Problem> one per ICD-10 code in the row */
    private function problemsFromRow(array $row): array
    {
        $active = $this->str($row, 'activity') === '1' && CodeParser::parseDate($this->str($row, 'enddate')) === null;
        return array_map(
            static fn (string $code): Problem => new Problem($code, $active),
            CodeParser::icd10Codes($this->str($row, 'diagnosis')),
        );
    }

    /**
     * A1C in percent only: values reported in mmol/mol (IFCC) or outside a plausible percent range count as
     * a result without a usable value, never as a very high A1C.
     *
     * @return list<LabResult>
     */
    private function a1cFromRow(array $row): array
    {
        $date = $this->pastDate($row);
        if ($date === null) {
            return [];
        }
        $value = CodeParser::parseNumber($this->str($row, 'result'));
        $isPercent = !str_contains(strtolower($this->str($row, 'units')), 'mol');
        $plausible = $value !== null && $value >= MeasureCodes::A1C_MIN_PERCENT && $value <= MeasureCodes::A1C_MAX_PERCENT;
        return [new LabResult($date, $isPercent && $plausible ? $value : null)];
    }

    /** @return list<BpReading> */
    private function bpFromRow(array $row): array
    {
        $date = $this->pastDate($row);
        $systolic = (int) CodeParser::parseNumber($this->str($row, 'bps'));
        $diastolic = (int) CodeParser::parseNumber($this->str($row, 'bpd'));
        $reading = $date === null ? null : new BpReading($date, $systolic, $diastolic);
        return $reading !== null && $reading->isValid() ? [$reading] : [];
    }

    /** @return list<Screening> */
    private function screeningFromRow(array $row): array
    {
        $date = $this->pastDate($row);
        $type = MeasureCodes::screeningTypeForCode($this->str($row, 'code'))
            ?? MeasureCodes::screeningTypeForTitle($this->str($row, 'title'));
        return $date === null || $type === null ? [] : [new Screening($type, $date)];
    }

    /**
     * @template T
     * @param list<array<string, mixed>>        $rows
     * @param callable(array<string, mixed>): list<T> $map
     * @return array<int, list<T>>
     */
    private function group(array $rows, callable $map): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['pid'] ?? 0);
            $items = $map($row);
            if ($pid > 0 && $items !== []) {
                $grouped[$pid] = array_merge($grouped[$pid] ?? [], $items);
            }
        }
        return $grouped;
    }

    /** The row's date, or null when it is missing, malformed or after today. */
    private function pastDate(array $row): ?DateTimeImmutable
    {
        $date = CodeParser::parseDate($this->str($row, 'date'));
        if ($date === null || ($this->today !== null && $date > $this->today)) {
            return null;
        }
        return $date;
    }

    private function str(array $row, string $key): string
    {
        $value = $row[$key] ?? '';
        return is_scalar($value) ? (string) $value : '';
    }
}
