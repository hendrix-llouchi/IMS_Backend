<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;

class OrderAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function broadcastOn()
    {
        // Fire on the specific worker's private channel
        return new Channel('worker.' . $this->order->worker_id);
    }

    public function broadcastAs()
    {
        return 'order.assigned';
    }

    public function broadcastWith()
    {
        return [
            'message' => 'You have been assigned a new order.',
            'order_id' => $this->order->id,
            'deadline' => $this->order->delivery_deadline,
        ];
    }
}