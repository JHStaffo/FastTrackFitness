<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A person on the employee roster. In production this is Draw Box's users
 * table, read through the roster connection, and this app never writes to it.
 */
#[Fillable(['name', 'email', 'employee_number', 'role', 'active'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const PROGRAM_ROLES = ['employee', 'wellness_admin', 'system_admin'];

    protected static function booted(): void
    {
        // Returning false cancels the save, so the shared roster stays read-only here.
        static::saving(fn () => self::rosterIsLocal());
    }

    public static function rosterIsLocal(): bool
    {
        return config('wellness.roster_connection') === null;
    }

    public function getConnectionName()
    {
        return config('wellness.roster_connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function participant(): HasOne
    {
        return $this->hasOne(Participant::class);
    }

    /**
     * Program roles rank in order. Draw Box admins are system admins here too,
     * so whoever runs the roster can run this program without extra setup.
     */
    public function hasProgramRole(string $role): bool
    {
        $mine = $this->role === 'admin' ? 'system_admin' : ($this->participant?->program_role ?? 'employee');

        return array_search($mine, self::PROGRAM_ROLES, true) >= array_search($role, self::PROGRAM_ROLES, true);
    }

    public function loginCodes(): HasMany
    {
        return $this->hasMany(LoginCode::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function manualEntries(): HasMany
    {
        return $this->hasMany(ManualEntry::class);
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }
}
