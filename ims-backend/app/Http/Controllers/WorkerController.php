<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Events\OrderStatusUpdated;
use App\Events\LowStockAlert;
use Illuminate\Support\Facades\DB;

class WorkerController extends Controller
{
    // View all orders in the system (read-only)
    public function getAllOrders()
    {
        $orders = Order::with(['worker:id,name', 'items.product:id,name,unit'])->paginate(20);
        return response()->json($orders);
    }

    // Get only orders assigned to this worker
    public function getAssignedOrders()
    {
        $workerId = auth()->id();

        $orders = Order::with(['items.product:id,name,unit'])
            ->where('worker_id', $workerId)
            ->paginate(20);

        return response()->json($orders);
    }

    // Get a single order assigned to this worker
    public function getMyOrder($id)
    {
        $workerId = auth()->id();

        $order = Order::with(['items.product:id,name,unit'])
            ->where('id', $id)
            ->where('worker_id', $workerId)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json(['order' => $order]);
    }

    // Mark an order as delivered
    public function markDelivered($id)
    {
        $workerId = auth()->id();

        $order = Order::where('id', $id)
            ->where('worker_id', $workerId)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->status !== 'assigned') {
            return response()->json(['message' => 'Only assigned orders can be marked as delivered.'], 400);
        }

        // Deduct stock with pessimistic locking
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                if ($product) {
                    $product->decrement('current_stock', $item->quantity);
                    $product->refresh();

                    // Check low stock threshold
                    $threshold = $product->max_stock_level * 0.30;
                    if ($product->current_stock <= $threshold) {
                        broadcast(new LowStockAlert($product));
                    }
                }
            }
        });

        $order->update(['status' => 'delivered']);

        // Fire real-time notification to the manager
        broadcast(new OrderStatusUpdated($order))->toOthers();

        return response()->json([
            'message' => 'Order marked as delivered successfully.',
            'order' => $order,
        ]);
    }

    // Flag an order as problematic
    public function flagOrder(Request $request, $id)
    {
        $request->validate([
            'flag_reason' => 'required|string',
        ]);

        $workerId = auth()->id();

        $order = Order::where('id', $id)
            ->where('worker_id', $workerId)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->status !== 'assigned') {
            return response()->json(['message' => 'Only assigned orders can be flagged.'], 400);
        }

        $order->update([
            'status' => 'flagged',
            'flag_reason' => $request->flag_reason,
        ]);

        // Fire real-time notification to the manager
        broadcast(new OrderStatusUpdated($order))->toOthers();

        return response()->json([
            'message' => 'Order flagged successfully.',
            'order' => $order,
        ]);
    }

    // View all stock levels (read-only)
    public function getAllStock()
    {
        $stock = Product::with('warehouse:id,name')->paginate(20);
        return response()->json($stock);
    }
}