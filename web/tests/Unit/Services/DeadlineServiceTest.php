<?php

namespace Tests\Unit\Services;

use App\Models\Package;
use App\Services\DeadlineService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class DeadlineServiceTest extends TestCase
{
    private DeadlineService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DeadlineService;
    }

    public function test_plus_sa_hitnim_daje_12_sati(): void
    {
        $from = Carbon::parse('2026-08-07 10:00:00');

        $deadline = $this->service->computeDeadline($this->plus(), true, $from);

        $this->assertSame('2026-08-07 22:00:00', $deadline->toDateTimeString());
        $this->assertSame(12.0, $from->diffInHours($deadline));
    }

    public function test_plus_bez_hitnog_daje_48_sati(): void
    {
        $from = Carbon::parse('2026-08-07 10:00:00');

        $deadline = $this->service->computeDeadline($this->plus(), false, $from);

        $this->assertSame('2026-08-09 10:00:00', $deadline->toDateTimeString());
    }

    public function test_mini_rokovi(): void
    {
        $from = Carbon::parse('2026-08-07 10:00:00');
        $mini = $this->mini();

        $this->assertSame('2026-08-10 10:00:00', $this->service->computeDeadline($mini, false, $from)->toDateTimeString());
        $this->assertSame('2026-08-08 10:00:00', $this->service->computeDeadline($mini, true, $from)->toDateTimeString());
    }

    public function test_pro_rokovi(): void
    {
        $from = Carbon::parse('2026-08-07 10:00:00');
        $pro = new Package([
            'slug' => 'haus-pro',
            'deadline_hours' => 24,
            'emergency_deadline_hours' => 6,
            'emergency_included' => true,
        ]);

        $this->assertSame('2026-08-08 10:00:00', $this->service->computeDeadline($pro, false, $from)->toDateTimeString());
        $this->assertSame('2026-08-07 16:00:00', $this->service->computeDeadline($pro, true, $from)->toDateTimeString());
    }

    public function test_polazna_tacka_se_ne_mijenja(): void
    {
        $from = Carbon::parse('2026-08-07 10:00:00');

        $this->service->computeDeadline($this->plus(), true, $from);

        $this->assertSame('2026-08-07 10:00:00', $from->toDateTimeString());
    }

    public function test_hitno_je_uz_doplatu_samo_za_mini(): void
    {
        $this->assertTrue($this->service->emergencySurcharged($this->mini()));
        $this->assertFalse($this->service->emergencySurcharged($this->plus()));
    }

    public function test_broj_sati_roka(): void
    {
        $this->assertSame(12, $this->service->deadlineHours($this->plus(), true));
        $this->assertSame(48, $this->service->deadlineHours($this->plus(), false));
    }

    private function plus(): Package
    {
        return new Package([
            'slug' => 'haus-plus',
            'deadline_hours' => 48,
            'emergency_deadline_hours' => 12,
            'emergency_included' => true,
        ]);
    }

    private function mini(): Package
    {
        return new Package([
            'slug' => 'haus-mini',
            'deadline_hours' => 72,
            'emergency_deadline_hours' => 24,
            'emergency_included' => false,
        ]);
    }
}
