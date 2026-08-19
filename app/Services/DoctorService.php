<?php

namespace App\Services;

use App\Constants\DoctorMessage;
use App\Models\Doctor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handle doctor profile queries and business rules.
 */
class DoctorService
{
    /**
     * Paginate doctor profiles with validated filters and eager-loaded context.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Doctor::query()->with(['user', 'specialty']);

        if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
            $term = mb_strtolower(trim((string) $filters['q']));
            $pattern = "%{$term}%";

            $query->where(function ($query) use ($pattern): void {
                $query
                    ->whereRaw('LOWER(license_number) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(bio) LIKE ?', [$pattern])
                    ->orWhereHas('user', function ($query) use ($pattern): void {
                        $query
                            ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
                    });
            });
        }

        if (isset($filters['specialty_id'])) {
            $query->where('specialty_id', $filters['specialty_id']);
        }

        return $query
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a doctor profile after verifying the selected user's eligibility.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Doctor
    {
        return DB::transaction(function () use ($data): Doctor {
            $this->assertDoctorUser((int) $data['user_id']);

            return Doctor::query()
                ->create($data)
                ->load(['user', 'specialty']);
        });
    }

    /**
     * Load the relationships required by the detail resource.
     */
    public function load(Doctor $doctor): Doctor
    {
        return $doctor->loadMissing(['user', 'specialty']);
    }

    /**
     * Update a doctor profile while preserving the doctor-role invariant.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Doctor $doctor, array $data): Doctor
    {
        return DB::transaction(function () use ($doctor, $data): Doctor {
            $lockedDoctor = Doctor::query()
                ->lockForUpdate()
                ->findOrFail($doctor->getKey());
            $userId = (int) ($data['user_id'] ?? $lockedDoctor->user_id);

            $this->assertDoctorUser($userId, (int) $lockedDoctor->getKey());
            $lockedDoctor->update($data);

            return $lockedDoctor->refresh()->load(['user', 'specialty']);
        });
    }

    /**
     * Delete a doctor profile without deleting its user or specialty.
     */
    public function delete(Doctor $doctor): void
    {
        $doctor->delete();
    }

    /**
     * Reject ineligible users and users that already own a doctor profile.
     *
     * @throws ValidationException
     */
    private function assertDoctorUser(int $userId, ?int $ignoredDoctorId = null): void
    {
        $user = User::query()
            ->with('role')
            ->lockForUpdate()
            ->findOrFail($userId);

        if ($user->role->name === Role::DOCTOR) {
            $existingDoctor = Doctor::query()
                ->where('user_id', $userId)
                ->when(
                    $ignoredDoctorId !== null,
                    fn ($query) => $query->whereKeyNot($ignoredDoctorId),
                )
                ->exists();

            if (! $existingDoctor) {
                return;
            }

            throw ValidationException::withMessages([
                'user_id' => [DoctorMessage::USER_ALREADY_HAS_PROFILE],
            ]);
        }

        throw ValidationException::withMessages([
            'user_id' => [DoctorMessage::USER_MUST_HAVE_DOCTOR_ROLE],
        ]);
    }
}
