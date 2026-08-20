<?php

namespace App\Models;

use App\Services\UserAbilityService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nombre',
        'email',
        'password',
        'role',
        'activo',
        'dashboard_access',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'activo' => 'boolean',
        'dashboard_access' => 'boolean',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->activo !== false;
    }

    public function getAbilities(): array
    {
        $abilities = UserAbilityService::getAbilitiesForRole($this->role ?? 'viewer');

        if ($this->hasDashboardAccess()) {
            $abilities[] = ['action' => 'read', 'subject' => 'DashboardAnalytics'];
        }

        return $abilities;
    }

    public function hasDashboardAccess(): bool
    {
        return $this->isAdmin() || $this->dashboard_access === true;
    }

    public function toUserData(): object
    {
        $userData = new \stdClass();
        $userData->ability = array_map(function ($item) {
            return (object) $item;
        }, $this->getAbilities());
        $userData->id = $this->id;
        $userData->fullName = $this->nombre;
        $userData->username = $this->email;
        $userData->email = $this->email;
        $userData->role = $this->role ?? 'viewer';

        return $userData;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
