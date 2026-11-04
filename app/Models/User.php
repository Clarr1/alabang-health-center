<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'first_name', 'last_name', 'middle_name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /** @var array<string, string> */
    public const ACCOUNT_TYPES = [
        'Staff' => 'Local Health Unit Staff',
        'Healthcare Personnel' => 'Doctor',
        'Admin' => 'Admin',
    ];

    public function hasAuthorizedRole(): bool
    {
        return in_array($this->role, self::ACCOUNT_TYPES, true);
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->exists && $user->isDirty('name') && ! $user->isDirty(['first_name', 'last_name', 'middle_name'])) {
                $user->first_name = null;
                $user->last_name = null;
                $user->middle_name = null;
            }
        });
    }

    protected $attributes = ['is_active' => true];

    protected $primaryKey = 'user_id';

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
            'is_active' => 'boolean',
        ];
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'handled_by', 'user_id');
    }

    public function recordedVitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class, 'recorded_by', 'user_id');
    }
}
