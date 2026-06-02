<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $fillable = [
        'name',
        'age',
        'phone_number',
        'location',
        'emergency_contact',
        'email',
        'username',
        'password',
        'role',
        'is_active',
        'is_temporary_password',
        'failed_attempts',
        'locked_until',
        'reset_token',
        'reset_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'reset_token',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'worker_id');
    }

    public function managedOrders()
    {
        return $this->hasMany(Order::class, 'manager_id');
    }

    public function workerFlags()
    {
        return $this->hasMany(WorkerFlag::class, 'worker_id');
    }

    public function raisedFlags()
    {
        return $this->hasMany(WorkerFlag::class, 'manager_id');
    }
}