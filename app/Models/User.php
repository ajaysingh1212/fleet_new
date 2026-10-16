<?php

namespace App\Models;

use App\Enums\ThemeMode;
use App\Enums\UserStatus;
use App\Traits\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasRoles;
    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    protected $primaryKey = 'id';

    protected string $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'employee_id',
        'name',
        'email',
        'phone',
        'email_verified_at',
        'password',
        'department',
        'designation',
        'company',
        'avatar',
        'gender',
        'dob',
        'address',
        'country_id',
        'state_id',
        'city_id',
        'zip_code',
        'timezone',
        'language_id',
        'theme',
        'status',
        'last_login_at',
        'last_login_ip',
        'last_activity_at',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dob' => 'date',
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'status' => UserStatus::class,
            'theme' => ThemeMode::class,
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function deviceSessions(): HasMany
    {
        return $this->hasMany(DeviceSession::class);
    }

    public function currentActiveSession(): HasOne
    {
        return $this->hasOne(DeviceSession::class)
            ->where('is_current', true)
            ->whereNull('logged_out_at')
            ->latestOfMany('last_activity_at');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function failedLogins(): HasMany
    {
        return $this->hasMany(FailedLogin::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function trackedUsers(): HasMany
    {
        return $this->hasMany(TrackingRelation::class, 'tracker_user_id');
    }

    public function trackerRelations(): HasMany
    {
        return $this->hasMany(TrackingRelation::class, 'tracked_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }

    public function userLicenses(): HasMany
    {
        return $this->hasMany(UserLicense::class);
    }

    public function licenseTransactions(): HasMany
    {
        return $this->hasMany(LicenseTransaction::class);
    }

    public function licenseTransfersSent(): HasMany
    {
        return $this->hasMany(LicenseTransfer::class, 'from_user_id');
    }

    public function licenseTransfersReceived(): HasMany
    {
        return $this->hasMany(LicenseTransfer::class, 'to_user_id');
    }

    public function gpsLocations(): HasMany
    {
        return $this->hasMany(GpsLocation::class);
    }

    public function trackingSessions(): HasMany
    {
        return $this->hasMany(TrackingSession::class);
    }

    public function diagnosticLogs(): HasMany
    {
        return $this->hasMany(DiagnosticLog::class);
    }

    public function offlineSyncLogs(): HasMany
    {
        return $this->hasMany(OfflineSyncLog::class);
    }

    public function trackingPreference(): HasOne
    {
        return $this->hasOne(UserTrackingPreference::class);
    }

    public function createdFieldTasks(): HasMany
    {
        return $this->hasMany(FieldTask::class, 'creator_id');
    }

    public function assignedFieldTasks(): HasMany
    {
        return $this->hasMany(FieldTask::class, 'assignee_id');
    }

    public function conversationMemberships(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }
}
