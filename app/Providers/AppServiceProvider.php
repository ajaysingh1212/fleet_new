<?php

namespace App\Providers;

use App\Interfaces\Repositories\ActivityLogRepositoryInterface;
use App\Interfaces\Repositories\AuditLogRepositoryInterface;
use App\Interfaces\Repositories\LicensePlanRepositoryInterface;
use App\Interfaces\Repositories\UserLicenseRepositoryInterface;
use App\Interfaces\Repositories\UserRepositoryInterface;
use App\Models\CallSession;
use App\Models\City;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\DeviceSession;
use App\Models\Language;
use App\Models\LicensePlan;
use App\Models\Message;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Models\State;
use App\Models\SupportTicket;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Models\UserLicense;
use App\Observers\AuditableObserver;
use App\Observers\UserObserver;
use App\Policies\CallSessionPolicy;
use App\Policies\CityPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\CountryPolicy;
use App\Policies\DeviceSessionPolicy;
use App\Policies\LanguagePolicy;
use App\Policies\LicensePlanPolicy;
use App\Policies\MessagePolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingPolicy;
use App\Policies\StatePolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\TrackingRelationPolicy;
use App\Policies\UserLicensePolicy;
use App\Policies\UserPolicy;
use App\Repositories\ActivityLogRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\LicensePlanRepository;
use App\Repositories\UserLicenseRepository;
use App\Repositories\UserRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
        $this->app->bind(LicensePlanRepositoryInterface::class, LicensePlanRepository::class);
        $this->app->bind(UserLicenseRepositoryInterface::class, UserLicenseRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
        Paginator::useBootstrapFive();

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        User::observe(UserObserver::class);
        User::observe(AuditableObserver::class);

        LicensePlan::observe(AuditableObserver::class);
        NotificationTemplate::observe(AuditableObserver::class);
        Setting::observe(AuditableObserver::class);
        TrackingRelation::observe(AuditableObserver::class);
        UserLicense::observe(AuditableObserver::class);
        Country::observe(AuditableObserver::class);
        State::observe(AuditableObserver::class);
        City::observe(AuditableObserver::class);
        Language::observe(AuditableObserver::class);
        SupportTicket::observe(AuditableObserver::class);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(LicensePlan::class, LicensePlanPolicy::class);
        Gate::policy(UserLicense::class, UserLicensePolicy::class);
        Gate::policy(TrackingRelation::class, TrackingRelationPolicy::class);
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(State::class, StatePolicy::class);
        Gate::policy(City::class, CityPolicy::class);
        Gate::policy(Language::class, LanguagePolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(DeviceSession::class, DeviceSessionPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(CallSession::class, CallSessionPolicy::class);
    }
}
