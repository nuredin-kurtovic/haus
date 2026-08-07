<?php

namespace App\Services;

use App\Models\Package;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Rok izlaska ide iz paketa. Racuna se pri kreiranju naloga i nikad se ne mijenja.
 */
class DeadlineService
{
    public function computeDeadline(Package $package, bool $emergency, ?CarbonInterface $from = null): Carbon
    {
        $from = $from ? Carbon::instance($from) : Carbon::now();

        $hours = $emergency
            ? $package->emergency_deadline_hours
            : $package->deadline_hours;

        return $from->copy()->addHours((int) $hours);
    }

    /**
     * Hitno je uz doplatu za pakete koji hitnost nemaju ukljucenu.
     */
    public function emergencySurcharged(Package $package): bool
    {
        return ! $package->emergency_included;
    }

    public function deadlineHours(Package $package, bool $emergency): int
    {
        return (int) ($emergency ? $package->emergency_deadline_hours : $package->deadline_hours);
    }
}
