<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            'USERS.FINDALL' => 'Xem danh sách người dùng',
            'USERS.CREATE' => 'Tạo người dùng',
            'USERS.FINDONE' => 'Xem chi tiết người dùng',
            'USERS.UPDATE' => 'Cập nhật người dùng',
            'USERS.DELETE' => 'Xóa người dùng',
            'USERS.UPDATESTATUS' => 'Cập nhật trạng thái người dùng',
            'ROLES.FINDALL' => 'Xem danh sách vai trò',
            'SPECIALTIES.FINDALL' => 'Xem danh sách chuyên khoa',
            'SPECIALTIES.CREATE' => 'Tạo chuyên khoa',
            'SPECIALTIES.FINDONE' => 'Xem chi tiết chuyên khoa',
            'SPECIALTIES.UPDATE' => 'Cập nhật chuyên khoa',
            'SPECIALTIES.DELETE' => 'Xóa chuyên khoa',
            'DOCTORS.FINDALL' => 'Xem danh sách bác sĩ',
            'DOCTORS.CREATE' => 'Tạo bác sĩ',
            'DOCTORS.FINDONE' => 'Xem chi tiết bác sĩ',
            'DOCTORS.UPDATE' => 'Cập nhật bác sĩ',
            'DOCTORS.DELETE' => 'Xóa bác sĩ',
            'PATIENTS.FINDALL' => 'Xem danh sách bệnh nhân',
            'PATIENTS.CREATE' => 'Tạo bệnh nhân',
            'PATIENTS.FINDONE' => 'Xem chi tiết bệnh nhân',
            'PATIENTS.UPDATE' => 'Cập nhật bệnh nhân',
            'PATIENTS.DELETE' => 'Xóa bệnh nhân',
            'APPOINTMENTS.FINDALL' => 'Xem danh sách lịch khám',
            'APPOINTMENTS.CREATE' => 'Tạo lịch khám',
            'APPOINTMENTS.FINDONE' => 'Xem chi tiết lịch khám',
            'APPOINTMENTS.UPDATE' => 'Cập nhật lịch khám',
            'APPOINTMENTS.UPDATESTATUS' => 'Cập nhật trạng thái lịch khám',
            'EXAMINATIONS.FINDALL' => 'Xem danh sách phiếu khám',
            'EXAMINATIONS.CREATE' => 'Tạo phiếu khám',
            'EXAMINATIONS.FINDONE' => 'Xem chi tiết phiếu khám',
            'EXAMINATIONS.UPDATE' => 'Cập nhật phiếu khám',
            'MEDICINES.FINDALL' => 'Xem danh sách thuốc',
            'MEDICINES.CREATE' => 'Tạo thuốc',
            'MEDICINES.FINDONE' => 'Xem chi tiết thuốc',
            'MEDICINES.UPDATE' => 'Cập nhật thuốc',
            'MEDICINES.DELETE' => 'Xóa thuốc',
            'MEDICINES.ADJUSTSTOCK' => 'Điều chỉnh tồn kho',
            'PRESCRIPTIONS.FINDALL' => 'Xem danh sách đơn thuốc',
            'PRESCRIPTIONS.CREATE' => 'Tạo đơn thuốc',
            'PRESCRIPTIONS.FINDONE' => 'Xem chi tiết đơn thuốc',
            'PRESCRIPTIONS.UPDATE' => 'Cập nhật đơn thuốc',
            'PRESCRIPTIONS.ADDITEM' => 'Thêm thuốc vào đơn',
            'PRESCRIPTIONS.UPDATEITEM' => 'Cập nhật thuốc trong đơn',
            'PRESCRIPTIONS.REMOVEITEM' => 'Xóa thuốc khỏi đơn',
            'INVOICES.FINDALL' => 'Xem danh sách hóa đơn',
            'INVOICES.CREATE' => 'Tạo hóa đơn',
            'INVOICES.FINDONE' => 'Xem chi tiết hóa đơn',
            'INVOICES.UPDATE' => 'Cập nhật hóa đơn',
            'INVOICES.UPDATESTATUS' => 'Cập nhật trạng thái hóa đơn',
            'PAYMENTS.FINDALL' => 'Xem danh sách thanh toán',
            'PAYMENTS.CREATE' => 'Tạo thanh toán',
            'PAYMENTS.CAPTURE' => 'Xác nhận thanh toán',
            'STATS.SHOW' => 'Xem thống kê',
        ];

        $rows = [];
        foreach ($permissions as $name => $displayName) {
            $rows[] = [
                'name' => $name,
                'display_name' => $displayName,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('permissions')->upsert(
            $rows,
            ['name'],
            ['display_name', 'updated_at'],
        );
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', [
            'USERS.FINDALL', 'USERS.CREATE', 'USERS.FINDONE', 'USERS.UPDATE', 'USERS.DELETE', 'USERS.UPDATESTATUS',
            'ROLES.FINDALL',
            'SPECIALTIES.FINDALL', 'SPECIALTIES.CREATE', 'SPECIALTIES.FINDONE', 'SPECIALTIES.UPDATE', 'SPECIALTIES.DELETE',
            'DOCTORS.FINDALL', 'DOCTORS.CREATE', 'DOCTORS.FINDONE', 'DOCTORS.UPDATE', 'DOCTORS.DELETE',
            'PATIENTS.FINDALL', 'PATIENTS.CREATE', 'PATIENTS.FINDONE', 'PATIENTS.UPDATE', 'PATIENTS.DELETE',
            'APPOINTMENTS.FINDALL', 'APPOINTMENTS.CREATE', 'APPOINTMENTS.FINDONE', 'APPOINTMENTS.UPDATE', 'APPOINTMENTS.UPDATESTATUS',
            'EXAMINATIONS.FINDALL', 'EXAMINATIONS.CREATE', 'EXAMINATIONS.FINDONE', 'EXAMINATIONS.UPDATE',
            'MEDICINES.FINDALL', 'MEDICINES.CREATE', 'MEDICINES.FINDONE', 'MEDICINES.UPDATE', 'MEDICINES.DELETE', 'MEDICINES.ADJUSTSTOCK',
            'PRESCRIPTIONS.FINDALL', 'PRESCRIPTIONS.CREATE', 'PRESCRIPTIONS.FINDONE', 'PRESCRIPTIONS.UPDATE', 'PRESCRIPTIONS.ADDITEM', 'PRESCRIPTIONS.UPDATEITEM', 'PRESCRIPTIONS.REMOVEITEM',
            'INVOICES.FINDALL', 'INVOICES.CREATE', 'INVOICES.FINDONE', 'INVOICES.UPDATE', 'INVOICES.UPDATESTATUS',
            'PAYMENTS.FINDALL', 'PAYMENTS.CREATE', 'PAYMENTS.CAPTURE',
            'STATS.SHOW',
        ])->delete();
    }
};
