<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;

/**
 * Compute the clinic overview figures.
 *
 * Every figure is produced by a single SQL aggregate; no query loads rows into PHP to be
 * counted or summed there.
 */
class StatsService
{
    /**
     * Return the four overview figures.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            'total_patients' => $this->totalPatients(),
            'appointments_today' => $this->appointmentsToday(),
            'revenue_this_month' => $this->revenueThisMonth(),
            'low_stock_medicines' => $this->lowStockMedicines(),
        ];
    }

    /**
     * Count patient records, excluding the soft-deleted ones.
     */
    private function totalPatients(): int
    {
        return Patient::query()->count();
    }

    /**
     * Count appointments scheduled for the current day.
     *
     * The application runs on UTC, so the day boundary is 00:00 UTC rather than local clinic
     * time. See skills/docker.md section 9.
     */
    private function appointmentsToday(): int
    {
        return Appointment::query()
            ->whereDate('scheduled_at', today())
            ->count();
    }

    /**
     * Sum the money actually collected during the current month.
     *
     * Measured on settled payments rather than paid invoices: an invoice issued last month but
     * settled this month belongs to this month's revenue, and partial payments count towards
     * the month they were collected in.
     *
     * A range comparison is used instead of whereMonth/whereYear so the predicate stays usable
     * by an index on paid_at.
     */
    private function revenueThisMonth(): string
    {
        $total = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * Count medicines whose stock has reached the configured low-stock threshold.
     */
    private function lowStockMedicines(): int
    {
        return Medicine::query()->lowStock()->count();
    }
}
