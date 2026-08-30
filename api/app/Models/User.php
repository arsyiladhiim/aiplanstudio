<?php

namespace App\Models;

use App\Notifications\ResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'accent_color', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
        ];
    }

    /**
     * Role+status bootstrap untuk user pertama (registrasi manual & OAuth).
     * Dev: SEED_ADMIN_EMAIL kosong → first user admin+active.
     * Prod: set SEED_ADMIN_EMAIL — hanya email itu yang instan admin,
     * user pertama lain tetap pending. Cegat: siapa pun yang register
     * duluan di instance publik tidak otomatis jadi admin.
     *
     * @return array{role: string, status: string}
     */
    public static function bootstrapRole(string $email): array
    {
        if (self::query()->exists()) {
            return ['role' => 'member', 'status' => 'pending'];
        }
        $adminEmail = (string) env('SEED_ADMIN_EMAIL', '');
        $isAdmin = $adminEmail === '' || strcasecmp($adminEmail, $email) === 0;

        return $isAdmin ? ['role' => 'admin', 'status' => 'active'] : ['role' => 'member', 'status' => 'pending'];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->isAdmin() && $this->two_factor_confirmed_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
