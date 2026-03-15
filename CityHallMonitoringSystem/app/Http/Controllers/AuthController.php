<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Self-registration (pending by default).
     *
     * - Creates a user with role Viewer (treated as "User")
     * - Sets is_active=false so the account is Pending and cannot login yet
     * - Admin will later approve and assign role (Admin or Encoder) + activate
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:6'],
            ]);

            $createData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                // Self-registered accounts are "User" by default → mapped to Viewer
                'role' => 'Viewer',
                // Pending until admin approves
                'is_active' => false,
            ];

            // Backward-compatible: only set if column exists (requires migration)
            if (Schema::hasColumn('users', 'account_status')) {
                $createData['account_status'] = 'Pending';
            }

            $user = User::create($createData);

            // Log for admin auditing
            try {
                AuditTrail::create([
                    'user_id' => $user->id,
                    'action' => 'user_self_registered',
                    'payload' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                        'account_status' => $user->account_status ?? 'Pending',
                    ],
                ]);
            } catch (\Exception $e) {
                \Log::warning('Audit trail (user_self_registered) failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Registration submitted. Your account is pending admin approval.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Register error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred during registration. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Authenticate the user and issue a Sanctum token.
     *
     * Sample request:
     * {
     *   "email": "admin@example.com",
     *   "password": "secret"
     * }
     */
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            if (! Auth::attempt($credentials)) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.',
                    'errors' => [
                        'email' => ['The provided credentials are incorrect.'],
                    ],
                ], 401);
            }

            /** @var User $user */
            $user = Auth::user();

            // Enforce approval status. Only Approved accounts can login.
            $status = Schema::hasColumn('users', 'account_status') ? ($user->account_status ?? null) : null;
            // Only non-admin accounts require explicit approval status.
            if ($user->role !== 'Admin' && $status !== null && $status !== 'Approved') {
                Auth::logout();
                return response()->json([
                    'message' => match ($status) {
                        'Pending' => 'Your account is pending admin approval.',
                        'Rejected' => 'Your registration was rejected. Please contact the system administrator.',
                        'Deactivated' => 'Your account is deactivated. Please contact the system administrator.',
                        default => 'Your account is not allowed to login.',
                    },
                ], 403);
            }

            // Backward compatibility: block inactive users even if status is missing
            if (property_exists($user, 'is_active') && ! $user->is_active) {
                Auth::logout();
                return response()->json([
                    'message' => 'Your account is deactivated. Please contact the system administrator.',
                ], 403);
            }

            // Create a personal access token for API calls
            $token = $user->createToken('city-hall-monitoring')->plainTextToken;

            // Log audit trail if possible (don't fail if table doesn't exist)
            try {
                AuditTrail::create([
                    'user_id' => $user->id,
                    'action' => 'login',
                    'payload' => ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
                ]);
            } catch (\Exception $e) {
                // Audit trail creation failed, but continue with login
                \Log::warning('Audit trail creation failed: ' . $e->getMessage());
            }

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred during login. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    /**
     * Revoke the current token.
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            $user?->currentAccessToken()?->delete();

            // Log audit trail if possible
            try {
                AuditTrail::create([
                    'user_id' => $user->id,
                    'action' => 'logout',
                    'payload' => ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
                ]);
            } catch (\Exception $e) {
                \Log::warning('Audit trail creation failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Logged out successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred during logout.',
            ], 500);
        }
    }
}

