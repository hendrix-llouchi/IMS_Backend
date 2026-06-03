<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class OwnerController extends Controller
{
    // Create a new manager or worker
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

        // Generate a temporary password
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

    // View all users
    public function getAllUsers()
    {
        $users = User::where('role', '!=', 'owner')
            ->select('id', 'name', 'age', 'phone_number', 'location', 'email', 'username', 'role', 'is_active')
            ->get();

        return response()->json(['users' => $users]);
    }

    // View a single user
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

    // Deactivate a user
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

    // Reactivate a user
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

    // Reset a user's password (set temporary password)
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
}