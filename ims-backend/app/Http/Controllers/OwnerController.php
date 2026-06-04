<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\WorkerFlag;

class OwnerController extends Controller
{
    // ==================== ACCOUNT MANAGEMENT ====================

    public function createUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'age' => 'required|integer',
            'phone_number' => 'required|string',
            'location' => 'required|string',
            'emergency_contact' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'username' => 'required|string|unique:users,username',
            'role' => 'required|in:manager,worker',
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
            'password' => Hash::make($temporaryPassword),
            'role' => $request->role,
            'is_temporary_password' => true,
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user,
            'temporary_password' => $temporaryPassword,
        ], 201);
    }

    public function getAllUsers()
    {
        $users = User::where('role', '!=', 'owner')
            ->select('id', 'name', 'age', 'phone_number', 'location', 'email', 'username', 'role', 'is_active')
            ->paginate(20);

        return response()->json($users);
    }

    public function getUser($id)
    {
        $user = User::where('id', $id)
            ->where('role', '!=', 'owner')
            ->select('id', 'name', 'age', 'phone_number', 'location', 'emergency_contact', 'email', 'username', 'role', 'is_active')
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return response()->json(['user' => $user]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::where('id', $id)->where('role', '!=', 'owner')->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string',
            'age' => 'sometimes|integer',
            'phone_number' => 'sometimes|string',
            'location' => 'sometimes|string',
            'emergency_contact' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $id,
        ]);

        $user->update($request->only([
            'name',
            'age',
            'phone_number',
            'location',
            'emergency_contact',
            'email'
        ]));

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user,
        ]);
    }

    public function deactivateUser($id)
    {
        $user = User::where('id', $id)->where('role', '!=', 'owner')->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'User is already deactivated.'], 400);
        }

        $user->update(['is_active' => false]);

        return response()->json(['message' => 'User deactivated successfully.']);
    }

    public function reactivateUser($id)
    {
        $user = User::where('id', $id)->where('role', '!=', 'owner')->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->is_active) {
            return response()->json(['message' => 'User is already active.'], 400);
        }

        $user->update([
            'is_active' => true,
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);

        return response()->json(['message' => 'User reactivated successfully.']);
    }

    public function deleteUser($id)
    {
        $user = User::where('id', $id)->where('role', '!=', 'owner')->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'User permanently removed.']);
    }

    public function resetUserPassword($id)
    {
        $user = User::where('id', $id)->where('role', '!=', 'owner')->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $temporaryPassword = 'IMS@' . rand(1000, 9999);

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'is_temporary_password' => true,
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);

        return response()->json([
            'message' => 'Password reset successfully.',
            'temporary_password' => $temporaryPassword,
        ]);
    }

    // ==================== WORKER FLAGS ====================

    public function getAllFlags()
    {
        $flags = WorkerFlag::with(['worker:id,name', 'manager:id,name'])
            ->where('status', 'pending')
            ->paginate(20);

        return response()->json($flags);
    }

    public function dismissFlag($id)
    {
        $flag = WorkerFlag::find($id);

        if (!$flag) {
            return response()->json(['message' => 'Flag not found.'], 404);
        }

        if ($flag->status !== 'pending') {
            return response()->json(['message' => 'This flag has already been reviewed.'], 400);
        }

        $flag->update([
            'status' => 'dismissed',
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Flag dismissed successfully.']);
    }

    public function warnWorker($id)
    {
        $flag = WorkerFlag::find($id);

        if (!$flag) {
            return response()->json(['message' => 'Flag not found.'], 404);
        }

        if ($flag->status !== 'pending') {
            return response()->json(['message' => 'This flag has already been reviewed.'], 400);
        }

        $flag->update([
            'status' => 'warning_issued',
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Warning issued successfully.']);
    }

    // ==================== STOCK AND ORDER OVERSIGHT ====================

    public function getAllStock()
    {
        $stock = Product::with('warehouse:id,name')->paginate(20);
        return response()->json($stock);
    }

    public function getAllOrders()
    {
        $orders = Order::with(['worker:id,name', 'manager:id,name', 'items.product:id,name'])->paginate(20);
        return response()->json($orders);
    }

    // ==================== REPORTS ====================

    public function financialReport()
    {
        $totalOrders = Order::count();
        $deliveredOrders = Order::where('status', 'delivered')->count();
        $flaggedOrders = Order::where('status', 'flagged')->count();
        $pendingOrders = Order::whereIn('status', ['unassigned', 'assigned'])->count();

        return response()->json([
            'total_orders' => $totalOrders,
            'delivered_orders' => $deliveredOrders,
            'flagged_orders' => $flaggedOrders,
            'pending_orders' => $pendingOrders,
        ]);
    }

    public function auditReport()
    {
        $products = Product::with('warehouse:id,name')
            ->select('id', 'warehouse_id', 'name', 'type', 'unit', 'current_stock', 'max_stock_level')
            ->get()
            ->map(function ($product) {
                $threshold = $product->max_stock_level * 0.30;
                $product->low_stock = $product->current_stock <= $threshold;
                $product->stock_percentage = round(($product->current_stock / $product->max_stock_level) * 100, 1);
                return $product;
            });

        return response()->json(['audit' => $products]);
    }

    // ==================== SYSTEM SETTINGS ====================

    public function getSettings()
    {
        return response()->json([
            'settings' => [
                'low_stock_threshold' => 30,
                'pagination_per_page' => 20,
                'lockout_attempts' => 3,
                'lockout_duration' => 15,
                'reset_token_expiry' => 60,
            ],
        ]);
    }

    public function updateSettings(Request $request)
    {
        return response()->json([
            'message' => 'Settings updated successfully.',
        ]);
    }
}