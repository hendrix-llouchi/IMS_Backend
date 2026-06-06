<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\PurchaseOrder;

class ShortDeliveryAlert implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $purchaseOrder;

    public function __construct(PurchaseOrder $purchaseOrder)
    {
        $this->purchaseOrder = $purchaseOrder;
    }

    public function broadcastOn()
    {
        return [
            new Channel('management.alerts'),
            new Channel('worker.alerts'),
        ];
    }

    public function broadcastAs()
    {
        return 'short.delivery.alert';
    }

    public function broadcastWith()
    {
        return [
            'message'             => "Short delivery detected for purchase order #{$this->purchaseOrder->id} from {$this->purchaseOrder->supplier_name}",
            'purchase_order_id'   => $this->purchaseOrder->id,
            'supplier_name'       => $this->purchaseOrder->supplier_name,
            'status'              => $this->purchaseOrder->status,
            'actual_arrival_date' => $this->purchaseOrder->actual_arrival_date,
        ];
    }
}
