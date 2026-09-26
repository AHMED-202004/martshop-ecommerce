<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'account_status' => AccountStatus::Active->value,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'password_changed_at',
        'gender',
        'dob',
        'governorate',
        'city',
        'address',
        'mobile',
        'alt_mobile',
        'remember_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_changed_at',
        'remember_token',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'gender',
        'dob',
        'governorate',
        'city',
        'address',
        'mobile',
        'alt_mobile',
        'account_status_changed_by',
        'last_login_at',
        'login_count',
        'failed_login_count',
        'last_failed_login_at',
        'employee_number',
        'job_title',
        'staff_department_id',
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
            'dob' => 'date',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'account_status' => AccountStatus::class,
            'account_status_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'login_count' => 'integer',
            'failed_login_count' => 'integer',
            'last_failed_login_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')->withTimestamps();
    }

    public function staffDepartment(): BelongsTo
    {
        return $this->belongsTo(StaffDepartment::class);
    }

    public function managedStaffDepartments(): HasMany
    {
        return $this->hasMany(StaffDepartment::class, 'manager_id');
    }

    public function assignedStaffTasks(): HasMany
    {
        return $this->hasMany(StaffTask::class, 'assigned_to');
    }

    public function assignedByMeStaffTasks(): HasMany
    {
        return $this->hasMany(StaffTask::class, 'assigned_by');
    }

    public function staffNotes(): HasMany
    {
        return $this->hasMany(StaffNote::class, 'staff_id');
    }

    public function authoredStaffNotes(): HasMany
    {
        return $this->hasMany(StaffNote::class, 'author_id');
    }

    public function staffLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'staff_location')->withTimestamps();
    }

    public function staffWorkSchedules(): HasMany
    {
        return $this->hasMany(StaffWorkSchedule::class);
    }

    public function merchant(): HasOne
    {
        return $this->hasOne(Merchant::class);
    }

    public function hasRole(string $role): bool
    {
        if (! Schema::hasTable('roles')) {
            return false;
        }

        return $this->roles()->where('slug', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->account_status !== AccountStatus::Active) {
            return false;
        }

        if (! Schema::hasTable('permissions')) {
            return false;
        }

        if (Schema::hasTable('permission_user')
            && $this->directPermissions()->where('slug', $permission)->exists()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function assignRole(Role|string $role): void
    {
        $roleModel = is_string($role) ? Role::where('slug', $role)->firstOrFail() : $role;
        $this->roles()->syncWithoutDetaching([$roleModel->getKey()]);
    }

    public function deliveryAssignments(): HasMany
    {
        return $this->hasMany(Delivery::class, 'delivery_worker_id');
    }

    public function deliveryWorkerProfile(): HasOne
    {
        return $this->hasOne(DeliveryWorkerProfile::class);
    }
}
