<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_AGENT = 'agent';

    protected $fillable = [
        'name', 'username', 'email', 'phone', 'password', 'role', 'role_profile_id', 'is_active', 'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function profile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RoleProfile::class, 'role_profile_id');
    }

    public function allows(string $module, string $action = 'view'): bool
    {
        return \App\Support\Permissions::allows($this, $module, $action);
    }

    public function isAgent(): bool
    {
        return $this->role === self::ROLE_AGENT;
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->isAdmin() ? 'مدير النظام' : (\App\Support\Permissions::profileFor($this)?->name ?? 'خدمة عملاء');
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name));
        return mb_substr($parts[0] ?? '?', 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '');
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function customersCreated(): HasMany
    {
        return $this->hasMany(Customer::class, 'created_by');
    }

    public function customersAssigned(): HasMany
    {
        return $this->hasMany(Customer::class, 'assigned_to');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(Followup::class, 'assigned_to');
    }
}
