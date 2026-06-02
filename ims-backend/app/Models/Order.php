<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'manager_id',
        'worker_id',
        'recipient_name',
        'recipient_contact',
        'delivery_deadline',
        'status',
        'flag_reason',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}