<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform an authenticated user and their RBAC context for API responses.
 */
class UserResource extends JsonResource
{
    /**
     * Convert the user into a public, password-free representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleIsLoaded = $this->resource->relationLoaded('role');
        $role = $roleIsLoaded ? $this->role : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'role' => $role === null ? null : [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ],
            // Permissions are returned as names so clients can evaluate UI capabilities.
            'permissions' => $role !== null && $role->relationLoaded('permissions')
                ? $role->permissions->pluck('name')->sort()->values()->all()
                : [],
        ];
    }
}
