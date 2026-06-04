<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\WorkerFlag;
use App\Events\OrderAssigned;
use App\Events\LowStockAlert;
use App\Events\OrderStatusUpdated;

class ManagerController extends Controller
{
    // ==================== USER MANAGEMENT ====================

    public function createWorker(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'age' => 'required|integer',
            'phone_number' => 'required|string',
            'location' => 'required|string',
            'emergency_contact' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'username' => 'required|string|unique:users,username',
        ]);

        $temporaryPassword = 'IMS@' . rand(1000, 9999);

        $user = User::create([
            'name' => $request->name,
            'age' => $request->age,
            'phone_number' => $request->phone_number,
            'location' => $request->location,
            'emergency_contact' => $request->emergency_contact,
            'email' => $request->email,
            'username' => $request->username,
            'password' => bcrypt($temporaryPassword),
            'role' => 'worker',
            'is_temporary_password' => true,
        ]);

        return response()->json([
            'message' => 'Worker created successfully.',
            'user' => $user,
            'temporary_password' => $temporaryPassword,
        ], 201);
    }

    public function getAllWorkers()
    {
        $workers = User::where('role', 'worker')
            ->select('id', 'name', 'age', 'phone_number', 'location', 'email', 'username', 'is_active')
            ->paginate(20);

        return response()->json($workers);
    }

    public function getWorkersStatus()
    {
        $workers = User::where('role', 'worker')
            ->where('is_active', true)
            ->select('id', 'name')
            ->get()
            ->map(function ($worker) {
                $hasActiveOrder = Order::where('worker_id', $worker->id)
                    ->where('status', 'assigned')
                    ->exists();

                $worker->status = $hasActiveOrder ? 'Busy' : 'Available';
                return $worker;
            });

        return response()->json(['workers' => $workers]);
    }

    // ==================== WAREHOUSE ====================

    public function createWarehouse(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'location' => 'required|string',
        ]);

        $warehouse = Warehouse::create([
            'name' => $request->name,
            'location' => $request->location,
        ]);

        return response()->json([
            'message' => 'Warehouse created successfully.',
            'warehouse' => $warehouse,
        ], 201);
    }

    public function getAllWarehouses()
    {
        $warehouses = Warehouse::paginate(20);
        return response()->json($warehouses);
    }

    public function getWarehouse($id)
    {
        $warehouse = Warehouse::find($id);
        if (!$warehouse) {
            return response()->json(['message' => 'Warehouse not found.'], 404);
        }
        return response()->json(['warehouse' => $warehouse]);
    }

    public function updateWarehouse(Request $request, $id)
    {
        $warehouse = Warehouse::find($id);
        if (!$warehouse) {
            return response()->json(['message' => 'Warehouse not found.'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string',
            'location' => 'sometimes|string',
        ]);

        $warehouse->update($request->only(['name', 'location']));

        return response()->json([
            'message' => 'Warehouse updated successfully.',
            'warehouse' => $warehouse,
        ]);
    }

    // ==================== PRODUCTS ====================

    public function createProduct(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'name' => 'required|string',
            'type' => 'required|string',
            'description' => 'nullable|string',
            'unit' => 'required|string',
            'current_stock' => 'required|integer|min:0',
            'max_stock_level' => 'required|integer|min:1',
        ]);

        $product = Product::create($request->all());

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    public function getAllProducts()
    {
        $products = Product::with('warehouse:id,name')->paginate(20);
        return response()->json($products);
    }

    public function getProduct($id)
    {
        $product = Product::with('warehouse:id,name')->find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        return response()->json(['product' => $product]);
    }

    public function updateProduct(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string',
            'type' => 'sometimes|string',
            'description' => 'sometimes|nullable|string',
            'unit' => 'sometimes|string',
            'current_stock' => 'sometimes|integer|min:0',
            'max_stock_level' => 'sometimes|integer|min:1',
        ]);

        $product->update($request->only([
            'name',
            'type',
            'description',
            'unit',
            'current_stock',
            'max_stock_level'
        ]));

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product,
        ]);
    }

    // ==================== STOCK ====================

    public function getAllStock()
    {
        $stock = Product::with('warehouse:id,name')->paginate(20);
        return response()->json($stock);
    }

    public function updateStock(Request $request, $id)
    {
        $request->validate([
            'current_stock' => 'required|integer|min:0',
        ]);

        $product = DB::transaction(function () use ($request, $id) {
            $product = Product::lockForUpdate()->find($id);
            if (!$product) {
                return null;
            }

            $product->update(['current_stock' => $request->current_stock]);
            $product->refresh();

            $threshold = $product->max_stock_level * 0.30;
            if ($product->current_stock <= $threshold) {
                broadcast(new LowStockAlert($product));
            }

            return $product;
        });

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return response()->json([
            'message' => 'Stock updated successfully.',
            'product' => $product,
        ]);
    }

    public function getLowStock()
    {
        $products = Product::with('warehouse:id,name')
            ->whereRaw('current_stock <= max_stock_level * 0.30')
            ->get();

        return response()->json(['low_stock_products' => $products]);
    }

    // ==================== ORDERS ====================

    public function createOrder(Request $request)
    {
        $request->validate([
            'recipient_name' => 'required|string',
            'recipient_contact' => 'required|string',
            'delivery_deadline' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $managerId = auth()->id();

        $order = Order::create([
            'manager_id' => $managerId,
            'recipient_name' => $request->recipient_name,
            'recipient_contact' => $request->recipient_contact,
            'delivery_deadline' => $request->delivery_deadline,
            'status' => 'unassigned',
        ]);

        foreach ($request->items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => $order->load('items'),
        ], 201);
    }

    public function getAllOrders()
    {
        $orders = Order::with(['worker:id,name', 'items.product:id,name'])->paginate(20);
        return response()->json($orders);
    }

    public function getOrder($id)
    {
        $order = Order::with(['worker:id,name', 'items.product:id,name'])->find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }
        return response()->json(['order' => $order]);
    }

    public function assignOrder(Request $request, $id)
    {
        $request->validate([
            'worker_id' => 'required|exists:users,id',
        ]);

        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->status !== 'unassigned') {
            return response()->json(['message' => 'Only unassigned orders can be assigned.'], 400);
        }

        $worker = User::where('id', $request->worker_id)->where('role', 'worker')->first();
        if (!$worker) {
            return response()->json(['message' => 'Worker not found.'], 404);
        }

        $order->update([
            'worker_id' => $request->worker_id,
            'status' => 'assigned',
        ]);

        broadcast(new OrderAssigned($order))->toOthers();

        return response()->json([
            'message' => 'Order assigned successfully.',
            'order' => $order,
        ]);
    }

    public function flagOrder(Request $request, $id)
    {
        $request->validate([
            'flag_reason' => 'required|string',
        ]);

        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $order->update([
            'status' => 'flagged',
            'flag_reason' => $request->flag_reason,
        ]);

        return response()->json([
            'message' => 'Order flagged successfully.',
            'order' => $order,
        ]);
    }

    public function resolveOrder($id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->status !== 'flagged') {
            return response()->json(['message' => 'Only flagged orders can be resolved.'], 400);
        }

        $order->update([
            'status' => 'assigned',
            'flag_reason' => null,
        ]);

        return response()->json([
            'message' => 'Order resolved successfully.',
            'order' => $order,
        ]);
    }

    // ==================== PURCHASE ORDERS ====================

    public function createPurchaseOrder(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'supplier_name' => 'required|string',
            'expected_delivery_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'manager_id' => auth()->id(),
            'warehouse_id' => $request->warehouse_id,
            'supplier_name' => $request->supplier_name,
            'expected_delivery_date' => $request->expected_delivery_date,
            'status' => 'pending',
        ]);

        foreach ($request->items as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $item['product_id'],
                'quantity_ordered' => $item['quantity_ordered'],
            ]);
        }

        return response()->json([
            'message' => 'Purchase order created successfully.',
            'purchase_order' => $purchaseOrder->load('items'),
        ], 201);
    }

    public function getAllPurchaseOrders()
    {
        $purchaseOrders = PurchaseOrder::with(['warehouse:id,name', 'items.product:id,name'])->paginate(20);
        return response()->json($purchaseOrders);
    }

    public function getPurchaseOrder($id)
    {
        $purchaseOrder = PurchaseOrder::with(['warehouse:id,name', 'items.product:id,name'])->find($id);
        if (!$purchaseOrder) {
            return response()->json(['message' => 'Purchase order not found.'], 404);
        }
        return response()->json(['purchase_order' => $purchaseOrder]);
    }

    public function updatePurchaseOrderStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:complete,incomplete',
            'actual_arrival_date' => 'required|date',
            'items' => 'sometimes|array',
            'items.*.purchase_order_item_id' => 'required_with:items|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required_with:items|integer|min:0',
        ]);

        $purchaseOrder = PurchaseOrder::find($id);
        if (!$purchaseOrder) {
            return response()->json(['message' => 'Purchase order not found.'], 404);
        }

        if ($purchaseOrder->status !== 'pending') {
            return response()->json(['message' => 'Only pending purchase orders can be updated.'], 400);
        }

        $purchaseOrder->update([
            'status' => $request->status,
            'actual_arrival_date' => $request->actual_arrival_date,
        ]);

        if ($request->has('items')) {
            DB::transaction(function () use ($request) {
                foreach ($request->items as $item) {
                    $poItem = PurchaseOrderItem::find($item['purchase_order_item_id']);
                    if ($poItem) {
                        $poItem->update(['quantity_received' => $item['quantity_received']]);

                        $product = Product::lockForUpdate()->find($poItem->product_id);
                        if ($product) {
                            $product->increment('current_stock', $item['quantity_received']);
                            $product->refresh();

                            $threshold = $product->max_stock_level * 0.30;
                            if ($product->current_stock <= $threshold) {
                                broadcast(new LowStockAlert($product));
                            }
                        }
                    }
                }
            });
        }

        return response()->json([
            'message' => 'Purchase order updated successfully.',
            'purchase_order' => $purchaseOrder->load('items'),
        ]);
    }

    // ==================== WORKER FLAGS ====================

    public function flagWorker(Request $request)
    {
        $request->validate([
            'worker_id' => 'required|exists:users,id',
            'reason' => 'required|string',
        ]);

        $worker = User::where('id', $request->worker_id)->where('role', 'worker')->first();
        if (!$worker) {
            return response()->json(['message' => 'Worker not found.'], 404);
        }

        $flag = WorkerFlag::create([
            'manager_id' => auth()->id(),
            'worker_id' => $request->worker_id,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Worker flagged successfully.',
            'flag' => $flag,
        ], 201);
    }

    public function getAllFlags()
    {
        $flags = WorkerFlag::with([
            'worker:id,name',
            'manager:id,name',
        ])->paginate(20);

        return response()->json($flags);
    }
}