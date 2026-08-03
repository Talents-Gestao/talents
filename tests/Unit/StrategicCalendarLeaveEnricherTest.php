<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EmployeeLeaveStatus;
use App\Models\Company;
use App\Models\EmployeeLeave;
use App\Support\StrategicCalendarLeaveEnricher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategicCalendarLeaveEnricherTest extends TestCase
{
    use RefreshDatabase;

    public function test_expands_leave_across_start_and_end_inclusive(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Leave',
            'is_active' => true,
        ]);

        EmployeeLeave::query()->create([
            'company_id' => $company->id,
            'employee_name' => 'Carla',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'status' => EmployeeLeaveStatus::Completed,
        ]);

        $rows = StrategicCalendarLeaveEnricher::occurrencesForRange(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
            $company->id,
        );

        $this->assertCount(3, $rows);
        $this->assertSame(['2026-07-01', '2026-07-02', '2026-07-03'], $rows->pluck('occurs_on')->all());
        $this->assertSame('Férias — Carla', $rows[0]['title']);
        $this->assertSame('leave', $rows[0]['kind']);
        $this->assertSame('2026-07-03', $rows[0]['ends_on']);
        $this->assertSame('2026-07-01', $rows[0]['range_starts_on']);
        $this->assertSame('2026-07-01', $rows[0]['leave_starts_on']);
        $this->assertSame('2026-07-03', $rows[0]['leave_ends_on']);
    }

    public function test_multi_week_leave_keeps_stable_span_fields_on_every_day(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Multi Semana',
            'is_active' => true,
        ]);

        $leave = EmployeeLeave::query()->create([
            'company_id' => $company->id,
            'employee_name' => 'Bruno Lima',
            'start_date' => '2026-07-13',
            'end_date' => '2026-07-31',
            'status' => EmployeeLeaveStatus::Scheduled,
        ]);

        $rows = StrategicCalendarLeaveEnricher::occurrencesForRange(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
            $company->id,
        );

        // 13→31 = 19 dias (inclui a semana do meio 19–25).
        $this->assertCount(19, $rows);
        $this->assertSame('2026-07-13', $rows->first()['occurs_on']);
        $this->assertSame('2026-07-31', $rows->last()['occurs_on']);
        $this->assertTrue($rows->contains(fn (array $row) => $row['occurs_on'] === '2026-07-20'));

        foreach ($rows as $row) {
            $this->assertSame($leave->id, $row['source_id']);
            $this->assertSame('leave', $row['kind']);
            $this->assertSame('2026-07-13', $row['range_starts_on']);
            $this->assertSame('2026-07-31', $row['ends_on']);
            $this->assertSame('2026-07-13', $row['leave_starts_on']);
            $this->assertSame('2026-07-31', $row['leave_ends_on']);
            $this->assertSame('leave-'.$leave->id.'-'.$row['occurs_on'], $row['id']);
        }
    }

    public function test_single_day_leave_has_null_ends_on(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Dia Único',
            'is_active' => true,
        ]);

        EmployeeLeave::query()->create([
            'company_id' => $company->id,
            'employee_name' => 'Ana',
            'start_date' => '2026-07-15',
            'end_date' => '2026-07-15',
            'status' => EmployeeLeaveStatus::InProgress,
            'notes' => 'Folga',
        ]);

        $rows = StrategicCalendarLeaveEnricher::occurrencesForRange(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
            $company->id,
        );

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['ends_on']);
        $this->assertSame('2026-07-15', $rows[0]['range_starts_on']);
        $this->assertSame('2026-07-15', $rows[0]['occurs_on']);
        $this->assertStringContainsString('Em férias', $rows[0]['description']);
        $this->assertStringContainsString('Folga', $rows[0]['description']);
    }

    public function test_clips_occurrences_to_requested_range(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Clip',
            'is_active' => true,
        ]);

        EmployeeLeave::query()->create([
            'company_id' => $company->id,
            'employee_name' => 'Diego',
            'start_date' => '2026-06-28',
            'end_date' => '2026-07-03',
            'status' => EmployeeLeaveStatus::Scheduled,
        ]);

        $rows = StrategicCalendarLeaveEnricher::occurrencesForRange(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
            $company->id,
        );

        $this->assertSame(['2026-07-01', '2026-07-02', '2026-07-03'], $rows->pluck('occurs_on')->all());
        $this->assertSame('2026-06-28', $rows[0]['range_starts_on']);
        $this->assertSame('2026-07-03', $rows[0]['ends_on']);
        $this->assertSame('2026-06-28', $rows[0]['leave_starts_on']);
    }

    public function test_filters_by_company_id(): void
    {
        $companyA = Company::query()->create(['name' => 'A', 'is_active' => true]);
        $companyB = Company::query()->create(['name' => 'B', 'is_active' => true]);

        EmployeeLeave::query()->create([
            'company_id' => $companyA->id,
            'employee_name' => 'Da A',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
            'status' => EmployeeLeaveStatus::Scheduled,
        ]);
        EmployeeLeave::query()->create([
            'company_id' => $companyB->id,
            'employee_name' => 'Da B',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
            'status' => EmployeeLeaveStatus::Scheduled,
        ]);

        $rows = StrategicCalendarLeaveEnricher::occurrencesForRange(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            $companyA->id,
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Férias — Da A', $rows[0]['title']);
        $this->assertSame($companyA->id, $rows[0]['company_id']);
    }

    public function test_enrich_concatenates_and_sorts_with_existing_items(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Enrich',
            'is_active' => true,
        ]);

        EmployeeLeave::query()->create([
            'company_id' => $company->id,
            'employee_name' => 'Zelia',
            'start_date' => '2026-08-02',
            'end_date' => '2026-08-02',
            'status' => EmployeeLeaveStatus::Scheduled,
        ]);

        $existing = collect([
            [
                'id' => '1-2026-08-01',
                'source_id' => 1,
                'kind' => 'event',
                'occurs_on' => '2026-08-01',
                'title' => 'Evento',
            ],
            [
                'id' => '2-2026-08-03',
                'source_id' => 2,
                'kind' => 'ritual',
                'occurs_on' => '2026-08-03',
                'title' => 'Ritual',
            ],
        ]);

        $rows = StrategicCalendarLeaveEnricher::enrich(
            $existing,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            $company->id,
        );

        $this->assertCount(3, $rows);
        $this->assertSame(
            ['2026-08-01', '2026-08-02', '2026-08-03'],
            $rows->pluck('occurs_on')->all(),
        );
        $this->assertSame('leave', $rows[1]['kind']);
    }

    public function test_includes_all_registration_statuses(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Status',
            'is_active' => true,
        ]);

        foreach (EmployeeLeaveStatus::all() as $i => $status) {
            EmployeeLeave::query()->create([
                'company_id' => $company->id,
                'employee_name' => 'Pessoa '.$status->value,
                'start_date' => sprintf('2026-10-%02d', $i + 1),
                'end_date' => sprintf('2026-10-%02d', $i + 1),
                'status' => $status,
            ]);
        }

        $rows = StrategicCalendarLeaveEnricher::enrich(
            collect(),
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            $company->id,
        );

        $this->assertCount(4, $rows);
        $this->assertSame(
            ['cancelled', 'completed', 'in_progress', 'scheduled'],
            $rows->pluck('status')->sort()->values()->all(),
        );
    }

    public function test_merge_kind_labels_adds_leave(): void
    {
        $labels = StrategicCalendarLeaveEnricher::mergeKindLabels([
            'event' => 'Evento',
            'ritual' => 'Ritual',
        ]);

        $this->assertSame('Férias', $labels['leave']);
        $this->assertSame('Evento', $labels['event']);
    }

    public function test_can_manage_is_false_for_leave_occurrences(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa ReadOnly',
            'is_active' => true,
        ]);

        EmployeeLeave::query()->create([
            'company_id' => $company->id,
            'employee_name' => 'Somente Leitura',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'status' => EmployeeLeaveStatus::Scheduled,
        ]);

        $rows = StrategicCalendarLeaveEnricher::occurrencesForRange(
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-30'),
            $company->id,
        );

        $this->assertFalse($rows[0]['can_manage']);
        $this->assertSame('leave', $rows[0]['source_type']);
    }
}
