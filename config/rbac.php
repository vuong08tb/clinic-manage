<?php

return [
    'controllers' => [
        'UserController' => 'USERS',
        'RoleController' => 'ROLES',
        'SpecialtyController' => 'SPECIALTIES',
        'DoctorController' => 'DOCTORS',
        'PatientController' => 'PATIENTS',
        'AppointmentController' => 'APPOINTMENTS',
        'ExaminationController' => 'EXAMINATIONS',
        'MedicineController' => 'MEDICINES',
        'PrescriptionController' => 'PRESCRIPTIONS',
        'InvoiceController' => 'INVOICES',
        'PaymentController' => 'PAYMENTS',
        'StatsController' => 'STATS',
    ],

    'actions' => [
        'index' => 'FINDALL',
        'store' => 'CREATE',
        'show' => 'FINDONE',
        'update' => 'UPDATE',
        'destroy' => 'DELETE',
        'updateStatus' => 'UPDATESTATUS',
        'addItem' => 'ADDITEM',
        'updateItem' => 'UPDATEITEM',
        'removeItem' => 'REMOVEITEM',
        'adjustStock' => 'ADJUSTSTOCK',
        'capture' => 'CAPTURE',
        'clientToken' => 'CREATE',
    ],

    'overrides' => [
        'StatsController@show' => 'STATS.SHOW',
    ],
];
