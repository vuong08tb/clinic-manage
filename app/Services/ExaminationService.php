<?php

namespace App\Services;

use App\Constants\ExaminationMessage;
use App\Models\Appointment;
use App\Models\Examination;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handle examination queries and clinical business rules.
 */
class ExaminationService
{
    /**
     * Paginate examinations with validated filters and eager-loaded context.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Examination::query()
            ->with(['patient', 'doctor.user'])
            ->when(
                isset($filters['doctor_id']),
                fn ($query) => $query->where('doctor_id', $filters['doctor_id']),
            )
            ->when(
                isset($filters['patient_id']),
                fn ($query) => $query->where('patient_id', $filters['patient_id']),
            )
            ->orderByDesc('examined_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create an examination from a confirmed appointment and complete the appointment atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromAppointment(array $data): Examination
    {
        return DB::transaction(function () use ($data): Examination {
            $appointment = Appointment::query()
                ->lockForUpdate()
                ->findOrFail($data['appointment_id']);

            if (Examination::query()
                ->where('appointment_id', $appointment->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'appointment' => [ExaminationMessage::APPOINTMENT_ALREADY_EXAMINED],
                ]);
            }

            if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'appointment' => [ExaminationMessage::ONLY_CONFIRMED_APPOINTMENTS],
                ]);
            }

            $examination = Examination::query()->create([
                'appointment_id' => $appointment->getKey(),
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
                'diagnosis' => $data['diagnosis'],
                'notes' => $data['notes'] ?? null,
                'examined_at' => now(),
            ]);

            $appointment->update([
                'status' => Appointment::STATUS_COMPLETED,
            ]);

            return $examination->load(['patient', 'doctor.user']);
        });
    }

    /**
     * Load the relationships required by the examination resource.
     */
    public function load(Examination $examination): Examination
    {
        return $examination->loadMissing(['patient', 'doctor.user']);
    }

    /**
     * Update the mutable clinical fields of an examination.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Examination $examination, array $data): Examination
    {
        $examination->update($data);

        return $examination->refresh()->load(['patient', 'doctor.user']);
    }
}
