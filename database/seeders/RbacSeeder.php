<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = DB::table('permissions')->pluck('id', 'name');

        $matrix = [
            'ADMIN' => $allPermissions->keys()->all(),
            'RECEPTIONIST' => [
                'SPECIALTIES.FINDALL', 'SPECIALTIES.FINDONE',
                'DOCTORS.FINDALL', 'DOCTORS.FINDONE',
                'PATIENTS.CREATE', 'PATIENTS.UPDATE','PATIENTS.FINDALL','PATIENTS.FINDONE',
                'APPOINTMENTS.FINDALL', 'APPOINTMENTS.CREATE', 'APPOINTMENTS.FINDONE', 'APPOINTMENTS.UPDATE', 'APPOINTMENTS.UPDATESTATUS',
            ],
            'DOCTOR' => [
                'SPECIALTIES.FINDALL', 'SPECIALTIES.FINDONE',
                'DOCTORS.FINDALL', 'DOCTORS.FINDONE',
                'PATIENTS.FINDALL', 'PATIENTS.FINDONE',
                'APPOINTMENTS.FINDALL', 'APPOINTMENTS.FINDONE',
                'EXAMINATIONS.FINDALL', 'EXAMINATIONS.CREATE', 'EXAMINATIONS.FINDONE', 'EXAMINATIONS.UPDATE',
                'MEDICINES.FINDALL', 'MEDICINES.FINDONE',
                'PRESCRIPTIONS.FINDALL', 'PRESCRIPTIONS.CREATE', 'PRESCRIPTIONS.FINDONE', 'PRESCRIPTIONS.UPDATE',
                'PRESCRIPTIONS.ADDITEM', 'PRESCRIPTIONS.UPDATEITEM', 'PRESCRIPTIONS.REMOVEITEM',
            ],
            'PHARMACIST' => [
                'MEDICINES.FINDALL', 'MEDICINES.CREATE', 'MEDICINES.FINDONE', 'MEDICINES.UPDATE', 'MEDICINES.DELETE', 'MEDICINES.ADJUSTSTOCK',
                'PRESCRIPTIONS.FINDALL', 'PRESCRIPTIONS.FINDONE',
            ],
            'CASHIER' => [
                'PATIENTS.FINDALL', 'PATIENTS.FINDONE',
                'APPOINTMENTS.FINDALL', 'APPOINTMENTS.FINDONE',
                'EXAMINATIONS.FINDALL', 'EXAMINATIONS.FINDONE',
                'INVOICES.FINDALL', 'INVOICES.CREATE', 'INVOICES.FINDONE', 'INVOICES.UPDATE', 'INVOICES.UPDATESTATUS',
                'PAYMENTS.FINDALL', 'PAYMENTS.CREATE', 'PAYMENTS.CAPTURE',
            ],
        ];

        DB::transaction(function () use ($allPermissions, $matrix): void {
            $roles = DB::table('roles')->pluck('id', 'name');

            foreach ($matrix as $roleName => $permissionNames) {
                $roleId = $roles->get($roleName);
                if ($roleId === null) {
                    throw new RuntimeException("Role [{$roleName}] does not exist.");
                }

                $missing = array_diff($permissionNames, $allPermissions->keys()->all());
                if ($missing !== []) {
                    throw new RuntimeException('Missing permissions: '.implode(', ', $missing));
                }

                DB::table('role_permissions')->where('role_id', $roleId)->delete();

                $rows = array_map(
                    fn (string $permissionName): array => [
                        'role_id' => $roleId,
                        'permission_id' => $allPermissions->get($permissionName),
                    ],
                    $permissionNames,
                );

                DB::table('role_permissions')->insert($rows);
            }
        });
    }
}