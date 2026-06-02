<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'warehouse_id',
        'name',
        'type',
        'description',
        'unit',
        'current_stock',
        'max_stock_level',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}