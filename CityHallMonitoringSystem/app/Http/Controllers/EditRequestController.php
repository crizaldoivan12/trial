<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Document;
use App\Models\EditRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EditRequestController extends Controller
{
    /**
     * Create a new edit request for a document owned by another user.
     */
    public function store(Request $request, Document $document)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->role !== 'Encoder') {
            abort(403, 'Only encoders can submit edit requests.');
        }

        // Cannot request edit on own document
        if ((int) $document->encoded_by_id === (int) $user->id) {
            abort(422, 'You already own this document.');
        }

        $owner = $document->encodedBy;
        if (! $owner) {
            abort(422, 'Document has no recorded encoder.');
        }

        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $editRequest = EditRequest::create([
            'document_id' => $document->id,
            'requested_by_user_id' => $user->id,
            'requested_to_user_id' => $owner->id,
            'status' => 'pending',
            'remarks' => $data['remarks'] ?? null,
        ]);

        AuditTrail::create([
            'user_id' => $user->id,
            'document_id' => $document->id,
            'action' => 'edit_request_created',
            'payload' => [
                'request_id' => $editRequest->id,
                'requested_by_user_id' => $user->id,
                'requested_by_name' => $user->name,
                'requested_to_user_id' => $owner->id,
                'requested_to_name' => $owner->name,
                'document_code' => $document->document_code,
                'remarks' => $editRequest->remarks,
                'requested_at' => now()->toIso8601String(),
            ],
        ]);

        return response()->json($editRequest, 201);
    }

    /**
     * List incoming edit requests for the current user (document owner).
     */
    public function incoming(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if ($user->role === 'Admin') {
            $requests = EditRequest::with(['document', 'requestedBy', 'requestedTo'])
                ->whereHas('requestedBy', function ($q) {
                    $q->where('role', 'Encoder');
                })
                ->orderByDesc('created_at')
                ->get();
        } else {
            $requests = EditRequest::with(['document', 'requestedBy'])
                ->where('requested_to_user_id', $user->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->orderByDesc('created_at')
                ->get();
        }

        return response()->json(['data' => $requests]);
    }

    /**
     * List outgoing edit requests created by the current user.
     * Used for notifying the requester when a request is approved/rejected.
     */
    public function outgoing(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $requests = EditRequest::with(['document', 'requestedTo'])
            ->where('requested_by_user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $requests]);
    }

    /**
     * Combined notifications list with unread count.
     */
    public function notifications(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $isAdmin = $user->role === 'Admin';
        $isEncoder = $user->role === 'Encoder';

        $incomingQuery = EditRequest::with(['document', 'requestedBy', 'requestedTo'])
            ->whereHas('requestedBy', function ($q) {
                $q->where('role', 'Encoder');
            });

        if ($isAdmin) {
            $incoming = $incomingQuery->orderByDesc('created_at')->get();
        } else {
            $incoming = $incomingQuery
                ->where('requested_to_user_id', $user->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->orderByDesc('created_at')
                ->get();
        }

        $outgoing = EditRequest::with(['document', 'requestedTo'])
            ->where('requested_by_user_id', $user->id)
            ->where('status', '!=', 'pending')
            ->orderByDesc('created_at')
            ->get();

        $notifications = new Collection();

        foreach ($incoming as $requestItem) {
            $isUnread = $isAdmin
                ? $requestItem->read_by_admin_at === null
                : $requestItem->read_by_requested_to_at === null;

            $notifications->push([
                'id' => 'in-' . $requestItem->id,
                'kind' => 'incoming',
                'request' => $requestItem,
                'created_at' => $requestItem->created_at,
                'unread' => $isUnread,
            ]);
        }

        foreach ($outgoing as $requestItem) {
            $notifications->push([
                'id' => 'out-' . $requestItem->id,
                'kind' => 'outgoing',
                'request' => $requestItem,
                'created_at' => $requestItem->created_at,
                'unread' => $requestItem->read_by_requested_by_at === null,
            ]);
        }

        if ($isEncoder) {
            $now = now();
            $threshold = $now->copy()->subDays(3);
            // Same exclusion rules as the main inactivity helpers:
            // finalized or out-of-encoder-flow documents are not tracked.
            $excludedStatuses = ['For Review', 'For Signature', 'Signed', 'Completed', 'Released'];
            $inactiveDocs = Document::with(['routedDepartment'])
                ->where('encoded_by_id', $user->id)
                ->whereNotIn('status', $excludedStatuses)
                ->where('updated_at', '<=', $threshold)
                ->get();

            $toAlert = [];

            foreach ($inactiveDocs as $doc) {
                if ($doc->inactive_alerted_at === null) {
                    $doc->inactive_alerted_at = $now;
                    $toAlert[] = $doc->id;
                }

                $lastAction = $doc->updated_at ?? $doc->created_at;
                $inactiveDays = $lastAction ? $lastAction->diffInDays($now) : 0;
                $createdAt = $doc->inactive_alerted_at ?? $doc->updated_at ?? $doc->created_at ?? $now;

                $notifications->push([
                    'id' => 'doc-' . $doc->id,
                    'kind' => 'inactivity',
                    'document' => $doc,
                    'created_at' => $createdAt,
                    'inactive_days' => max($inactiveDays, 3),
                    'unread' => $doc->inactive_read_at === null,
                ]);
            }

            if (! empty($toAlert)) {
                Document::withoutTimestamps(function () use ($toAlert, $now) {
                    Document::whereIn('id', $toAlert)->update([
                        'inactive_alerted_at' => $now,
                    ]);
                });
            }
        }

        $notifications = $notifications
            ->sortByDesc('created_at')
            ->values();

        $unreadCount = $notifications->where('unread', true)->count();

        return response()->json([
            'data' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark notifications as read for the current user.
     */
    public function markRead(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $data = $request->validate([
            'kind' => ['nullable', 'in:incoming,outgoing,inactivity,all'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        $kind = $data['kind'] ?? 'all';
        $ids = $data['ids'] ?? null;
        $now = now();
        $isAdmin = $user->role === 'Admin';

        $updated = 0;

        if ($kind === 'incoming' || $kind === 'all') {
            $incomingQuery = EditRequest::query()
                ->whereHas('requestedBy', function ($q) {
                    $q->where('role', 'Encoder');
                });

            if ($isAdmin) {
                if ($ids) {
                    $incomingQuery->whereIn('id', $ids);
                }
                $updated += $incomingQuery->update(['read_by_admin_at' => $now]);
            } else {
                $incomingQuery
                    ->where('requested_to_user_id', $user->id)
                    ->whereIn('status', ['pending', 'accepted']);
                if ($ids) {
                    $incomingQuery->whereIn('id', $ids);
                }
                $updated += $incomingQuery->update(['read_by_requested_to_at' => $now]);
            }
        }

        if ($kind === 'outgoing' || $kind === 'all') {
            $outgoingQuery = EditRequest::query()
                ->where('requested_by_user_id', $user->id)
                ->where('status', '!=', 'pending');
            if ($ids) {
                $outgoingQuery->whereIn('id', $ids);
            }
            $updated += $outgoingQuery->update(['read_by_requested_by_at' => $now]);
        }

        if (($kind === 'inactivity' || $kind === 'all') && $user->role === 'Encoder') {
            Document::withoutTimestamps(function () use ($user, $ids, $now, &$updated) {
                $inactiveQuery = Document::query()->where('encoded_by_id', $user->id);
                if ($ids) {
                    $inactiveQuery->whereIn('id', $ids);
                }
                $updated += $inactiveQuery->update(['inactive_read_at' => $now]);
            });
        }

        return response()->json(['updated' => $updated]);
    }

    public function accept(Request $request, EditRequest $editRequest)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if ($user->role !== 'Admin' && $user->id !== $editRequest->requested_to_user_id) {
            abort(403, 'Only administrators or the document owner can approve this request.');
        }

        if ($editRequest->status !== 'pending') {
            abort(422, 'This request is no longer pending.');
        }

        $updates = [
            'status' => 'accepted',
            'accepted_at' => now(),
            'expires_at' => now()->addHours(2),
            'read_by_requested_by_at' => null,
        ];

        if ((int) $user->id === (int) $editRequest->requested_to_user_id) {
            $updates['read_by_requested_to_at'] = now();
        }

        if ($user->role === 'Admin') {
            $updates['read_by_admin_at'] = now();
        }

        $editRequest->update($updates);

        AuditTrail::create([
            'user_id' => $user->id,
            'document_id' => $editRequest->document_id,
            'action' => 'edit_request_approved',
            'payload' => [
                'request_id' => $editRequest->id,
                'requested_by_user_id' => $editRequest->requested_by_user_id,
                'requested_to_user_id' => $editRequest->requested_to_user_id,
                'accepted_at' => $editRequest->accepted_at?->toIso8601String(),
                'expires_at' => $editRequest->expires_at?->toIso8601String(),
            ],
        ]);

        return response()->json($editRequest);
    }

    public function reject(Request $request, EditRequest $editRequest)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if ($user->role !== 'Admin' && $user->id !== $editRequest->requested_to_user_id) {
            abort(403, 'Only administrators or the document owner can reject this request.');
        }

        if ($editRequest->status !== 'pending') {
            abort(422, 'This request is no longer pending.');
        }

        $updates = [
            'status' => 'rejected',
            'read_by_requested_by_at' => null,
        ];

        if ((int) $user->id === (int) $editRequest->requested_to_user_id) {
            $updates['read_by_requested_to_at'] = now();
        }

        if ($user->role === 'Admin') {
            $updates['read_by_admin_at'] = now();
        }

        $editRequest->update($updates);

        AuditTrail::create([
            'user_id' => $user->id,
            'document_id' => $editRequest->document_id,
            'action' => 'edit_request_rejected',
            'payload' => [
                'request_id' => $editRequest->id,
                'requested_by_user_id' => $editRequest->requested_by_user_id,
                'requested_to_user_id' => $editRequest->requested_to_user_id,
                'rejected_at' => now()->toIso8601String(),
            ],
        ]);

        return response()->json($editRequest);
    }
}
