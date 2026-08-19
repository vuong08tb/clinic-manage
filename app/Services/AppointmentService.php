<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handle appointment queries and business rules.
 */
class AppointmentService
{
    /**
     * Define valid transitions for each appointment lifecycle status.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        Appointment::STATUS_SCHEDULED => [
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_CANCELLED,
        ],
        Appointment::STATUS_CONFIRMED => [
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CANCELLED,
        ],
        Appointment::STATUS_COMPLETED => [],
        Appointment::STATUS_CANCELLED => [],
    ];

    /**
     * Paginate appointments with validated filters and eager-loaded context.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Appointment::query()
            ->with(['patient', 'doctor.user'])
            ->search($filters['q'] ?? null)
            ->when(
                isset($filters['doctor_id']),
                fn ($query) => $query->where('doctor_id', $filters['doctor_id']),
            )
            ->when(
                isset($filters['patient_id']),
                fn ($query) => $query->where('patient_id', $filters['patient_id']),
            )
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                isset($filters['date']),
                fn ($query) => $query->whereDate('scheduled_at', $filters['date']),
            )
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a scheduled appointment with its clinical context.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Appointment
    {
        $appointment = Appointment::query()->create([
            ...$data,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        return $appointment->load(['patient', 'doctor.user']);
    }

    /**
     * Load the relationships required by the detail resource.
     */
    public function load(Appointment $appointment): Appointment
    {
        return $appointment->loadMissing(['patient', 'doctor.user']);
    }

    /**
     * Update an appointment only while it remains scheduled.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Appointment $appointment, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $data): Appointment {
            $lockedAppointment = Appointment::query()
                ->lockForUpdate()
                ->findOrFail($appointment->getKey());

            if ($lockedAppointment->status !== Appointment::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'appointment' => ['Only scheduled appointments may be updated.'],
                ]);
            }

            $lockedAppointment->update($data);

            return $lockedAppointment->refresh()->load(['patient', 'doctor.user']);
        });
    }

    /**
     * Transition an appointment to an allowed lifecycle status.
     */
    public function updateStatus(Appointment $appointment, string $status): Appointment
    {
        return DB::transaction(function () use ($appointment, $status): Appointment {
            $lockedAppointment = Appointment::query()
                ->lockForUpdate()
                ->findOrFail($appointment->getKey());
            $allowedStatuses = self::ALLOWED_TRANSITIONS[$lockedAppointment->status] ?? [];

            if (! in_array($status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => [sprintf(
                        'The appointment status cannot transition from %s to %s.',
                        $lockedAppointment->status,
                        $status,
                    )],
                ]);
            }

            $lockedAppointment->update(['status' => $status]);

            return $lockedAppointment->refresh()->load(['patient', 'doctor.user']);
        });
    }
}
