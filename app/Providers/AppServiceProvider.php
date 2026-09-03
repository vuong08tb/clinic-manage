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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        // Login is throttled in two layers: 5/minute per (email, IP) pair slows password guessing
        // against one account without letting a stranger lock its owner out from another address;
        // 20/minute per IP catches one source spraying a password across many accounts.
        RateLimiter::for('login', function (Request $request): array {
            // This closure runs in middleware, before LoginRequest validates anything, so the
            // input is still raw. The type guard keeps a malformed payload from turning the
            // limiter itself into an uncounted server error, and lowercasing matches the
            // case-insensitive collation on users.email so spelling cannot buy fresh attempts.
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }
}
