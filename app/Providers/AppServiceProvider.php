<?php

namespace App\Providers;

use App\Constants\ActivityLogSubject;
use App\Models\Appointment;
use App\Models\Examination;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Observers\AppointmentObserver;
use App\Observers\ExaminationObserver;
use App\Observers\InvoiceObserver;
use App\Observers\PaymentObserver;
use App\Observers\PrescriptionItemObserver;
use App\Observers\PrescriptionObserver;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Audit entries store a short alias instead of a fully qualified class name, so
        // moving a model between namespaces cannot invalidate historical rows. The map is
        // enforced: morphing a model absent from it fails loudly instead of silently
        // writing a class name.
        Relation::enforceMorphMap([
            ActivityLogSubject::USER => User::class,
            ActivityLogSubject::APPOINTMENT => Appointment::class,
            ActivityLogSubject::EXAMINATION => Examination::class,
            ActivityLogSubject::PRESCRIPTION => Prescription::class,
            ActivityLogSubject::PRESCRIPTION_ITEM => PrescriptionItem::class,
            ActivityLogSubject::MEDICINE => Medicine::class,
            ActivityLogSubject::INVOICE => Invoice::class,
            ActivityLogSubject::PAYMENT => Payment::class,
        ]);

        // Observers run inline rather than after commit: Model::finishSave() overwrites the
        // original attributes once a save completes, so a deferred handler would read the
        // new values as "before". ActivityLogger builds its payload eagerly and defers only
        // the insert, which keeps the audit write out of the business transaction.
        User::observe(UserObserver::class);
        Appointment::observe(AppointmentObserver::class);
        Examination::observe(ExaminationObserver::class);
        Prescription::observe(PrescriptionObserver::class);
        PrescriptionItem::observe(PrescriptionItemObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);
    }
}
