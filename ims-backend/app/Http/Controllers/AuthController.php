<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Find user by username
        $user = User::where('username', $request->username)->first();

        // Generic error - never reveal which field is wrong
        if (!$user) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        // Check if account is active
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Contact your administrator.'
            ], 403);
        }

        // Check if account is locked
        // $lockedUntil = $user->locked_until ? Carbon::parse($user->locked_until) : null;
        // if ($lockedUntil && Carbon::now()->lessThan($lockedUntil)) {
        //     return response()->json([
        //         'message' => 'Account temporarily locked.',
        //         'locked_until' => $lockedUntil->toIso8601String()
        //     ], 423);
        // }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            // $user->increment('failed_attempts');

            // if ($user->failed_attempts >= 3) {
            //     $lockedUntil = Carbon::now()->addMinutes(15);
            //     $user->update([
            //         'locked_until' => $lockedUntil
            //     ]);
            //     return response()->json([
            //         'message' => 'Account temporarily locked.',
            //         'locked_until' => $lockedUntil->toIso8601String()
            //     ], 423);
            // }

            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        // Reset failed attempts on successful login
        $user->update([
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        // Store token in HttpOnly cookie
        $cookie = cookie('token', $token, 60 * 24, null, null, false, true);

        return response()->json([
            'message' => 'Login successful.',
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'phone_number' => $user->phone_number,
            'location' => $user->location,
            'emergency_contact' => $user->emergency_contact,
            'is_temporary_password' => $user->is_temporary_password,
        ])->withCookie($cookie);
    }

    public function logout(Request $request)
    {
        $cookie = cookie()->forget('token');

        return response()->json([
            'message' => 'Logged out successfully.'
        ])->withCookie($cookie);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
            'is_temporary_password' => false,
        ]);

        return response()->json([
            'message' => 'Password changed successfully. Please log in with your new password.'
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Always return the same message - never reveal if email exists
        $genericMessage = 'If this email exists in our system you will receive a password reset link shortly.';

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => $genericMessage]);
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));

        $user->update([
            'reset_token' => $token,
            'reset_token_expires_at' => Carbon::now()->addHour(),
        ]);

        // TODO: Send email with reset link in a later step
        // Mail::to($user->email)->send(new ResetPasswordMail($token));

        return response()->json(['message' => $genericMessage]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $user = User::where('reset_token', $request->token)->first();

        if (!$user || Carbon::now()->greaterThan($user->reset_token_expires_at)) {
            return response()->json([
                'message' => 'This reset link has expired or is invalid. Please request a new one.'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
            'reset_token' => null,
            'reset_token_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Password reset successful. Please log in with your new password.'
        ]);
    }
}