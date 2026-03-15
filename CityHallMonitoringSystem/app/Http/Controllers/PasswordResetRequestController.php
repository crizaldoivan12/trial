<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class PasswordResetRequestController extends Controller
{
    public function store(Request $request)
    {
        if (! Schema::hasTable('password_reset_requests')) {
            return response()->json([
                'message' => 'Password reset requests table is missing. Run the latest database migrations.',
            ], 500);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'new_password' => ['required', 'string', 'min:8'],
            'confirm_password' => ['required', 'string', 'same:new_password'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (! $user || $user->role !== 'Encoder') {
            return response()->json([
                'message' => 'Only registered encoder accounts can request a reset.',
            ], 422);
        }


        $existing = PasswordResetRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();
        if ($existing) {
            return response()->json([
                'message' => 'You already have a pending reset request.',
            ], 409);
        }

        PasswordResetRequest::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'requested_password' => Crypt::encryptString($validated['new_password']),
            'requested_password_length' => strlen($validated['new_password']),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Password reset request submitted. Awaiting admin approval.',
        ], 201);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $status = $request->get('status', 'pending');
        $query = PasswordResetRequest::query()
            ->with(['user:id,name,email'])
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $items = $query->get()->map(function (PasswordResetRequest $row) {
            $masked = str_repeat('•', max(0, (int) $row->requested_password_length));
            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'encoder_name' => $row->user?->name,
                'email' => $row->email,
                'requested_password_masked' => $masked,
                'requested_at' => $row->created_at?->toIso8601String(),
                'status' => $row->status,
            ];
        })->all();

        return response()->json(['data' => $items]);
    }

    public function reveal(Request $request, PasswordResetRequest $passwordResetRequest)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'password' => Crypt::decryptString($passwordResetRequest->requested_password),
        ]);
    }

    public function approve(Request $request, PasswordResetRequest $passwordResetRequest)
    {
        $this->authorizeAdmin($request);

        if ($passwordResetRequest->status !== 'pending') {
            abort(422, 'This request has already been processed.');
        }

        $plain = Crypt::decryptString($passwordResetRequest->requested_password);
        $user = $passwordResetRequest->user;
        if (! $user) {
            abort(404, 'User not found.');
        }

        $user->password = $plain;
        $user->save();

        $passwordResetRequest->status = 'approved';
        $passwordResetRequest->approved_by_id = $request->user()?->id;
        $passwordResetRequest->approved_at = now();
        $passwordResetRequest->read_by_user_at = null;
        $passwordResetRequest->save();

        return response()->json([
            'message' => 'Password reset approved and updated.',
        ]);
    }

    public function reject(Request $request, PasswordResetRequest $passwordResetRequest)
    {
        $this->authorizeAdmin($request);

        if ($passwordResetRequest->status !== 'pending') {
            abort(422, 'This request has already been processed.');
        }

        $passwordResetRequest->status = 'rejected';
        $passwordResetRequest->rejected_by_id = $request->user()?->id;
        $passwordResetRequest->rejected_at = now();
        $passwordResetRequest->read_by_user_at = null;
        $passwordResetRequest->save();

        return response()->json([
            'message' => 'Password reset request rejected.',
        ]);
    }

    public function notifications(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $items = PasswordResetRequest::where('user_id', $user->id)
            ->whereIn('status', ['approved', 'rejected'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (PasswordResetRequest $row) {
                return [
                    'id' => $row->id,
                    'status' => $row->status,
                    'requested_at' => $row->created_at?->toIso8601String(),
                    'read' => $row->read_by_user_at !== null,
                ];
            })->all();

        $unreadCount = collect($items)->where('read', false)->count();

        return response()->json([
            'data' => $items,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        PasswordResetRequest::where('user_id', $user->id)
            ->whereNull('read_by_user_at')
            ->whereIn('status', ['approved', 'rejected'])
            ->update(['read_by_user_at' => now()]);

        return response()->json(['message' => 'Notifications marked as read.']);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || $user->role !== 'Admin') {
            abort(403, 'Only administrators can perform this action.');
        }
    }
}
