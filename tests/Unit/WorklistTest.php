<?php

declare(strict_types=1);

namespace Vicert\CareGaps\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vicert\CareGaps\Domain\MeasureKey;
use Vicert\CareGaps\Domain\ScreeningType;
use Vicert\CareGaps\Service\RecordAssembler;
use Vicert\CareGaps\Service\WorklistFilter;
use Vicert\CareGaps\Service\WorklistService;

final class WorklistTest extends TestCase
{
    public function testAssemblerGroupsRowsByPatientAndSkipsBadRows(): void
    {
        $records = (new RecordAssembler())->assemble(
            patients: [
                ['pid' => '1', 'fname' => 'Ana', 'lname' => 'Recent', 'DOB' => '1961-03-14', 'providerID' => '7', 'provider_name' => 'Dr Lee', 'phone_cell' => '', 'phone_home' => '555-0100'],
                ['pid' => '2', 'fname' => 'No', 'lname' => 'Dob', 'DOB' => '0000-00-00', 'providerID' => '0', 'provider_name' => ''],
            ],
            problems: [
                ['pid' => '1', 'diagnosis' => 'ICD10:E11.9;ICD10:I10', 'activity' => '1', 'enddate' => null],
                ['pid' => '1', 'diagnosis' => 'ICD10:C18.9', 'activity' => '1', 'enddate' => '2020-01-01'],
                ['pid' => '99', 'diagnosis' => 'ICD10:E11.9', 'activity' => '1', 'enddate' => null],
            ],
            a1cResults: [
                ['pid' => '1', 'date' => '2026-09-01 12:00:00', 'result' => '6.8'],
                ['pid' => '1', 'date' => '0000-00-00 00:00:00', 'result' => '7.0'],
            ],
            vitals: [
                ['pid' => '1', 'date' => '2026-10-01 09:00:00', 'bps' => '128', 'bpd' => '82'],
                ['pid' => '1', 'date' => '2026-10-02 09:00:00', 'bps' => '', 'bpd' => '80'],
            ],
            screenings: [
                ['pid' => '1', 'date' => '2024-01-10', 'code' => '45378', 'title' => ''],
                ['pid' => '1', 'date' => '2023-01-10', 'code' => '', 'title' => 'Colonoscopy'],
                ['pid' => '1', 'date' => '2023-01-10', 'code' => '99213', 'title' => 'Office visit'],
            ],
        );

        self::assertCount(1, $records, 'patient without a valid DOB is skipped');
        $ana = $records[0];
        self::assertSame('Ana Recent', $ana->patient->fullName());
        self::assertSame(7, $ana->patient->providerId);
        self::assertSame('555-0100', $ana->patient->phone, 'home phone is used when there is no cell phone');
        self::assertSame(['E11.9', 'I10', 'C18.9'], array_map(static fn ($p) => $p->code, $ana->problems));
        self::assertFalse($ana->problems[2]->active, 'problem with an end date is not active');
        self::assertCount(1, $ana->a1cResults);
        self::assertCount(1, $ana->bpReadings);
        self::assertCount(2, $ana->screenings);
        self::assertSame(ScreeningType::Colonoscopy, $ana->screenings[0]->type);
    }

    public function testFutureDatedRowsAreIgnored(): void
    {
        $records = (new RecordAssembler(Fixtures::today()))->assemble(
            patients: [['pid' => '5', 'fname' => 'A', 'lname' => 'B', 'DOB' => '1970-01-01', 'providerID' => '1', 'provider_name' => 'X']],
            problems: [],
            a1cResults: [['pid' => '5', 'date' => '2062-01-01', 'result' => '6.0']],
            vitals: [['pid' => '5', 'date' => '2027-01-01', 'bps' => '120', 'bpd' => '80']],
            screenings: [['pid' => '5', 'date' => '2026-12-20', 'code' => '', 'title' => 'Colonoscopy']],
        );

        self::assertSame([], $records[0]->a1cResults);
        self::assertSame([], $records[0]->bpReadings);
        self::assertSame([], $records[0]->screenings);
    }

    public function testImplausibleOrNonPercentA1cValuesAreTreatedAsMissing(): void
    {
        $records = (new RecordAssembler())->assemble(
            patients: [['pid' => '5', 'fname' => 'A', 'lname' => 'B', 'DOB' => '1970-01-01']],
            problems: [],
            a1cResults: [
                ['pid' => '5', 'date' => '2026-10-01', 'result' => '53', 'units' => 'mmol/mol'],
                ['pid' => '5', 'date' => '2026-10-02', 'result' => '53', 'units' => ''],
                ['pid' => '5', 'date' => '2026-10-03', 'result' => '7.1', 'units' => '%'],
            ],
            vitals: [],
            screenings: [],
        );

        self::assertSame([null, null, 7.1], array_map(static fn ($r) => $r->value, $records[0]->a1cResults));
    }

    public function testInactiveFlagMakesProblemInactive(): void
    {
        $records = (new RecordAssembler())->assemble(
            patients: [['pid' => '3', 'fname' => 'A', 'lname' => 'B', 'DOB' => '1970-01-01', 'providerID' => null, 'provider_name' => null]],
            problems: [['pid' => '3', 'diagnosis' => 'ICD10:I10', 'activity' => '0', 'enddate' => null]],
            a1cResults: [],
            vitals: [],
            screenings: [],
        );

        self::assertFalse($records[0]->problems[0]->active);
        self::assertNull($records[0]->patient->providerId);
        self::assertSame('', $records[0]->patient->providerName);
    }

    public function testWorklistCombinesMeasuresCountsAndFilters(): void
    {
        $records = [
            Fixtures::record(age: 35, problems: [Fixtures::active('E10.9')], pid: 1, providerId: 7),
            Fixtures::record(age: 54, problems: [Fixtures::active('I10')], pid: 2, providerId: 8),
            Fixtures::record(age: 30, pid: 3, providerId: 7),
        ];

        $worklist = WorklistService::withDefaultMeasures()->build($records, Fixtures::today());

        // pid 1: A1C overdue + poor control; pid 2: BP missing + colorectal due; pid 3: nothing.
        self::assertCount(4, $worklist->rows);
        self::assertSame(1, $worklist->counts()[MeasureKey::A1cOverdue->value]);
        self::assertSame(1, $worklist->counts()[MeasureKey::BpMissing->value]);
        self::assertSame(0, $worklist->counts()[MeasureKey::BpUncontrolled->value]);
        self::assertSame(2, $worklist->patientCount());

        $byMeasure = $worklist->filter(new WorklistFilter(MeasureKey::BpMissing, null, ''));
        self::assertSame([2], array_map(static fn ($row) => $row->patient->pid, $byMeasure->rows));

        $byProvider = $worklist->filter(new WorklistFilter(null, 7, ''));
        self::assertSame([1, 1], array_map(static fn ($row) => $row->patient->pid, $byProvider->rows));

        self::assertCount(4, $worklist->rows, 'filtering returns a new worklist and leaves the original intact');
    }

    public function testSearchMatchesPatientNameCaseInsensitively(): void
    {
        $records = [Fixtures::record(age: 35, problems: [Fixtures::active('E10.9')], pid: 1)];
        $worklist = WorklistService::withDefaultMeasures()->build($records, Fixtures::today());

        self::assertCount(2, $worklist->filter(new WorklistFilter(null, null, 'test pat'))->rows);
        self::assertCount(0, $worklist->filter(new WorklistFilter(null, null, 'nobody'))->rows);
    }

    public function testFilterFromQueryValidatesInput(): void
    {
        $filter = WorklistFilter::fromQuery(['measure' => 'bp_missing', 'provider' => '8', 'q' => '  Bob  ']);
        self::assertSame(MeasureKey::BpMissing, $filter->measure);
        self::assertSame(8, $filter->providerId);
        self::assertSame('Bob', $filter->search);

        $bad = WorklistFilter::fromQuery(['measure' => 'drop table', 'provider' => '8 OR 1=1', 'q' => str_repeat('x', 300)]);
        self::assertNull($bad->measure);
        self::assertNull($bad->providerId);
        self::assertSame(64, mb_strlen($bad->search));

        $arrays = WorklistFilter::fromQuery(['measure' => ['x'], 'provider' => ['1'], 'q' => ['y']]);
        self::assertNull($arrays->measure);
        self::assertNull($arrays->providerId);
        self::assertSame('', $arrays->search);
    }

    public function testFilterRoundTripsThroughQueryParameters(): void
    {
        $filter = new WorklistFilter(MeasureKey::A1cOverdue, 7, 'bob');
        self::assertSame(['measure' => 'a1c_overdue', 'provider' => '7', 'q' => 'bob'], $filter->toQuery());
        self::assertEquals($filter, WorklistFilter::fromQuery($filter->toQuery()));
        self::assertSame([], (new WorklistFilter(null, null, ''))->toQuery());
    }

    public function testProvidersAreListedOnceAndSortedByName(): void
    {
        $records = [
            Fixtures::record(age: 35, problems: [Fixtures::active('E10.9')], pid: 1, providerId: 7),
            Fixtures::record(age: 54, problems: [Fixtures::active('I10')], pid: 2, providerId: null),
        ];

        $worklist = WorklistService::withDefaultMeasures()->build($records, Fixtures::today());

        self::assertSame([7 => 'Dr Test'], $worklist->providers());
    }

    public function testEveryMeasureHasALabelAndDescription(): void
    {
        foreach (MeasureKey::cases() as $index => $key) {
            self::assertNotSame('', $key->label());
            self::assertNotSame('', $key->description());
            self::assertSame($index, $key->order());
        }
        self::assertSame('stool DNA test', ScreeningType::FitDna->label());
        self::assertSame(3, ScreeningType::FitDna->validityYears());
    }

    public function testRowsAreSortedByMeasureThenPatientName(): void
    {
        $zed = Fixtures::record(age: 54, problems: [Fixtures::active('I10')], pid: 5);
        $records = [$zed, Fixtures::record(age: 35, problems: [Fixtures::active('E10.9')], pid: 4)];

        $rows = WorklistService::withDefaultMeasures()->build($records, Fixtures::today())->rows;

        self::assertSame(
            [MeasureKey::A1cOverdue, MeasureKey::A1cPoorControl, MeasureKey::BpMissing, MeasureKey::ColorectalScreeningDue],
            array_map(static fn ($row) => $row->gap->measure, $rows),
        );
    }
}
