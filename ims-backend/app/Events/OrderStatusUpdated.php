<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;

class OrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function broadcastOn()
    {
        // Fire on the manager's channel
        return new Channel('manager.' . $this->order->manager_id);
    }

    public function broadcastAs()
    {
        return 'order.status.updated';
    }

    public function broadcastWith()
    {
        return [
            'message' => 'Order #' . $this->order->id . ' status has been updated to ' . $this->order->status . '.',
            'order_id' => $this->order->id,
            'status' => $this->order->status,
            'reason' => $this->order->flag_reason,
        ];
    }
}