<?php

namespace App\Constants;

/**
 * Define response, validation, and business messages for appointments.
 */
final class AppointmentMessage
{
    public const LIST_RETRIEVED = 'Appointments retrieved';

    public const CREATED = 'Appointment created';

    public const RETRIEVED = 'Appointment retrieved';

    public const UPDATED = 'Appointment updated';

    public const STATUS_UPDATED = 'Appointment status updated';

    public const SELECTED_DOCTOR_NOT_FOUND = 'The selected doctor does not exist.';

    public const SELECTED_PATIENT_NOT_FOUND = 'The selected patient does not exist.';

    public const INVALID_STATUS = 'The status must be scheduled, confirmed, cancelled, or completed.';

    public const INVALID_DATE_FORMAT = 'The date must use the Y-m-d format.';

    public const PAGE_SIZE_TOO_LARGE = 'The page size may not be greater than 100.';

    public const APPOINTMENT_TIME_MUST_BE_FUTURE = 'The appointment time must be in the future.';

    public const STATUS_ASSIGNED_AUTOMATICALLY = 'The appointment status is assigned automatically.';

    public const REASON_TOO_LONG = 'The reason may not be greater than 255 characters.';

    public const PATIENT_CANNOT_BE_CHANGED = 'The appointment patient cannot be changed.';

    public const DOCTOR_CANNOT_BE_CHANGED = 'The appointment doctor cannot be changed.';

    public const USE_STATUS_ENDPOINT = 'Use the appointment status endpoint to change status.';

    public const UPDATE_FIELD_REQUIRED = 'At least one appointment field must be provided.';

    public const ONLY_SCHEDULED_CAN_BE_UPDATED = 'Only scheduled appointments may be updated.';

    public const DOCTOR_SCHEDULE_CONFLICT = 'The doctor already has an appointment overlapping this 30-minute time slot.';

    public const INVALID_STATUS_TRANSITION = 'The appointment status cannot transition from %s to %s.';
}
