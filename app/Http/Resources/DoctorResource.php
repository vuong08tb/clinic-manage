<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a doctor profile and its clinical context for API responses.
 */
class DoctorResource extends JsonResource
{
    /**
     * Convert the doctor profile into a public representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'specialty_id' => $this->specialty_id,
            'license_number' => $this->license_number,
            'bio' => $this->bio,
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'specialty' => $this->whenLoaded('specialty', fn (): array => [
                'id' => $this->specialty->id,
                'name' => $this->specialty->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
