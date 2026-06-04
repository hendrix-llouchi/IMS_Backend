<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Product;

class LowStockAlert implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function broadcastOn()
    {
        // Fire on a general management channel
        // Both manager and owner listen to this channel
        return new Channel('management.alerts');
    }

    public function broadcastAs()
    {
        return 'low.stock.alert';
    }

    public function broadcastWith()
    {
        return [
            'message' => 'Low stock alert: ' . $this->product->name . ' is running low.',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'current_stock' => $this->product->current_stock,
            'max_stock_level' => $this->product->max_stock_level,
            'threshold' => round($this->product->max_stock_level * 0.30),
        ];
    }
}