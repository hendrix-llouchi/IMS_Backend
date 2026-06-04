<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Events\OrderStatusUpdated;


class WorkerController extends Controller
{
    // Get all orders assigned to this worker
    public function getMyOrders()
    {
        $workerId = auth()->id();

        $orders = Order::with(['items.product:id,name,unit'])
            ->where('worker_id', $workerId)
            ->get();

        return response()->json(['orders' => $orders]);
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

        // Deduct stock for each item in the order
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->decrement('current_stock', $item->quantity);
            }
        }

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
}