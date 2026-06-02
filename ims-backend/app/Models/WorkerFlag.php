<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerFlag extends Model
{
    protected $fillable = [
        'manager_id',
        'worker_id',
        'reason',
        'status',
        'reviewed_at',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_id');
    }
}