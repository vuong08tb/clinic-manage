<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Collection;

/**
 * Handle role catalog queries.
 */
class RoleService
{
    /**
     * Return every seeded role ordered by name.
     *
     * @return Collection<int, Role>
     */
    public function all(): Collection
    {
        return Role::query()->orderBy('name')->get();
    }
}
