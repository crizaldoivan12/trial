<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentRoutingHistory;
use App\Models\EditRequest;
use App\Models\ReportHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class DocumentController extends Controller
{
    /**
     * List documents with filtering, search, and pagination.
     *
     * Supported query params:
     * - code: partial/full document_code
     * - routed_department_id or routed_department_code
     * - status
     * - type (type_of_document)
     * - date_from, date_to (YYYY-MM-DD)
     * - per_page
     */
    public function index(Request $request)
    {
        $query = Document::with(['routedDepartment', 'encodedBy'])->orderByDesc('created_at');
        $user = $request->user();

        if ($code = $request->get('code')) {
            $query->where('document_code', 'like', '%' . $code . '%');
        }

        if ($search = $request->get('search')) {
            $term = trim((string) $search);
            if ($term !== '') {
                $like = '%' . $term . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('document_code', 'like', $like)
                        ->orWhere('document_number', 'like', $like)
                        ->orWhere('type_of_document', 'like', $like)
                        ->orWhere('pay_claimant', 'like', $like)
                        ->orWhere('particular', 'like', $like)
                        ->orWhere('remarks', 'like', $like)
                        ->orWhere('status', 'like', $like)
                        ->orWhereHas('routedDepartment', function ($dq) use ($like) {
                            $dq->where('name', 'like', $like)->orWhere('code', 'like', $like);
                        })
                        ->orWhereHas('encodedBy', function ($uq) use ($like) {
                            $uq->where('name', 'like', $like);
                        });
                });
            }
        }

        $routedDepartmentId = $request->get('routed_department_id') ?? $request->get('department_id');
        if ($routedDepartmentId) {
            if (Schema::hasColumn('documents', 'routed_department_id')) {
                $query->where('routed_department_id', $routedDepartmentId);
            }
        }

        $routedDepartmentCode = $request->get('routed_department_code') ?? $request->get('department_code');
        if ($routedDepartmentCode) {
            $query->whereHas('routedDepartment', function ($q) use ($routedDepartmentCode) {
                $q->where('code', $routedDepartmentCode);
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->get('type')) {
            $query->where('type_of_document', $type);
        }

        // Filter by encoder (for \"My Documents\" views in the frontend)
        if ($encodedBy = $request->get('encoded_by')) {
            $query->where('encoded_by_id', $encodedBy);
        }

        if ($user && $user->role === 'Encoder') {
            $query->where('status', '!=', 'Pending');
        }

        $from = $request->get('date_from');
        $to = $request->get('date_to');

        if ($from) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        $createdDate = $request->get('created_date');
        $createdMonth = $request->get('created_month');
        if ($createdDate && $createdMonth) {
            abort(422, 'Only one date filter may be applied at a time.');
        }
        if ($createdDate) {
            $query->whereDate('created_at', $createdDate);
        } elseif ($createdMonth) {
            try {
                $start = Carbon::createFromFormat('Y-m', $createdMonth)->startOfMonth();
                $end = (clone $start)->endOfMonth();
                $query->whereBetween('created_at', [$start, $end]);
            } catch (\Throwable $e) {
                // Ignore invalid month format
            }
        }

        $perPage = (int) $request->get('per_page', 20);

        $paginator = $query->paginate($perPage);
        $now = now();
        $items = collect($paginator->items())->map(function (Document $document) use ($now) {
            $isInactive = $this->isDocumentInactive($document, $now);
            $document->setAttribute('is_inactive', $isInactive);
            $document->setAttribute('inactive_days', $isInactive ? max($this->inactivityDays($document, $now), 3) : 0);
            $document->setAttribute('inactive_reason', $document->inactive_reason);
            return $document;
        })->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        // Encoders and Admins can create documents
        $this->authorizeRole($request, ['Admin', 'Encoder']);

        $validated = $this->validateDocument($request);
        if (! Schema::hasColumn('documents', 'routed_department_id')) {
            unset($validated['routed_department_id']);
        }

        $user = $request->user();

        if ($user && $user->role === 'Encoder' && ($validated['status'] ?? null) === 'Pending') {
            abort(422, 'Encoders are not allowed to set status to Pending.');
        }

        // Set encoded_by to current user
        $validated['encoded_by_id'] = $user->id;

        // Generate backend-only document code using Department Out
        $departmentOut = Department::findOrFail($validated['routed_department_id']);
        $validated['document_code'] = $this->generateDocumentCode($departmentOut);

        // Auto-generate document_number if not provided, using the same value as document_code.
        // This ensures uniqueness and provides a manual reference when needed.
        if (empty($validated['document_number'])) {
            $validated['document_number'] = $validated['document_code'];
        }
        if (! isset($validated['amount']) || $validated['amount'] === null) {
            $validated['amount'] = 0;
        }
        if (! isset($validated['pay_claimant']) || $validated['pay_claimant'] === null) {
            $validated['pay_claimant'] = '';
        }
        if (! isset($validated['particular']) || $validated['particular'] === null) {
            $validated['particular'] = '';
        }

        $document = Document::create($validated);

        if (Schema::hasColumn('documents', 'routed_department_id') &&
            Schema::hasTable('document_routing_histories')) {
            $toDept = $document->routed_department_id;
            if ($toDept) {
                DocumentRoutingHistory::create([
                    'document_id' => $document->id,
                    'from_department_id' => null,
                    'to_department_id' => $toDept,
                    'routed_by_id' => $user?->id,
                    'status' => $document->status,
                    'remarks' => $document->remarks,
                    'routed_at' => now(),
                ]);
            }
        }

        AuditTrail::create([
            'user_id' => $user->id,
            'document_id' => $document->id,
            'action' => 'document_created',
            'payload' => $document->toArray(),
        ]);

        return response()->json($document, 201);
    }

    public function show(Request $request, Document $document)
    {
        // All authenticated users can view any document details.
        $document->load(['routedDepartment', 'encodedBy']);
        $now = now();
        $isInactive = $this->isDocumentInactive($document, $now);
        $document->setAttribute('is_inactive', $isInactive);
        $document->setAttribute('inactive_days', $isInactive ? max($this->inactivityDays($document, $now), 3) : 0);
        $document->setAttribute('inactive_reason', $document->inactive_reason);

        return response()->json($document);
    }

    /**
     * Document history (audit trail) for admin only. Read-only, ordered oldest to newest.
     * Each entry includes action type, previous/new status, user name & role, department, remarks, timestamp.
     */
    public function history(Request $request, Document $document)
    {
        $user = $request->user();
        if (! $user || $user->role !== 'Admin') {
            abort(403, 'Only administrators can view document history.');
        }

        $trails = AuditTrail::where('document_id', $document->id)
            ->with('user:id,name,role')
            ->orderBy('created_at', 'asc')
            ->get();

        $entries = $trails->map(function (AuditTrail $trail) {
            $payload = $trail->payload ?? [];
            $before = $payload['before'] ?? null;
            $after = $payload['after'] ?? $payload;
            $snapshot = is_array($after) ? $after : (is_array($before) ? $before : []);

            $actionType = $this->resolveHistoryActionType($trail->action, $before, $after);
            $previousStatus = $this->getStatusFromPayload($before);
            $newStatus = $this->getStatusFromPayload($after);
            $departmentName = $this->getDepartmentNameFromPayload($snapshot);
            $remarks = $this->getRemarksFromPayload($snapshot);

            return [
                'id' => $trail->id,
                'action_type' => $actionType,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'user_name' => $trail->user ? $trail->user->name : null,
                'user_role' => $trail->user ? $trail->user->role : null,
                'department' => $departmentName,
                'remarks' => $remarks,
                'created_at' => $trail->created_at->toIso8601String(),
            ];
        });

        return response()->json(['data' => $entries]);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|mixed  $after
     */
    private function resolveHistoryActionType(string $action, ?array $before, $after): string
    {
        return match ($action) {
            'document_created' => 'Created',
            'document_deleted' => 'Deleted',
            'edit_request_created' => 'Edit Request',
            'edit_request_approved' => 'Edit Request Approved',
            'edit_request_rejected' => 'Edit Request Rejected',
            'edit_request_used' => 'Edit Session Completed',
            default => $this->resolveUpdateActionType($action, $before, $after),
        };
    }

    private function resolveUpdateActionType(string $action, ?array $before, $after): string
    {
        if ($action === 'document_updated' && is_array($before) && is_array($after)) {
            $prevStatus = $before['status'] ?? null;
            $nextStatus = $after['status'] ?? null;
            if ($prevStatus !== null && $nextStatus !== null && $prevStatus !== $nextStatus) {
                if ($nextStatus === 'Returned') {
                    return 'Returned';
                }
                if ($nextStatus === 'Released') {
                    return 'Released';
                }
                if ($nextStatus === 'Completed') {
                    return 'Completed';
                }
                return 'Status Changed';
            }
        }
        return 'Updated';
    }

    private function getStatusFromPayload($payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        return $payload['status'] ?? null;
    }

    private function getDepartmentNameFromPayload(array $payload): ?string
    {
        $departmentId = $payload['routed_department_id'] ?? $payload['department_id'] ?? null;
        if ($departmentId === null) {
            return null;
        }
        $department = Department::find($departmentId);

        return $department ? $department->name : null;
    }

    private function getRemarksFromPayload(array $payload): ?string
    {
        $remarks = $payload['remarks'] ?? null;

        return $remarks ? (string) $remarks : null;
    }

    private function updateRoutingAction(
        Document $document,
        ?string $previousStatus,
        ?string $newStatus,
        ?int $userId
    ): void {
        if ($previousStatus === $newStatus || ! $newStatus) {
            return;
        }

        if (! Schema::hasTable('document_routing_histories')) {
            return;
        }

        $action = $this->resolveRoutingAction($previousStatus, $newStatus);
        if (! $action) {
            return;
        }

        $latest = DocumentRoutingHistory::where('document_id', $document->id)
            ->orderByDesc('routed_at')
            ->first();

        if (! $latest) {
            return;
        }

        $now = now();
        $updates = [
            'action_taken' => $action['action'],
            'action_at' => $now,
            'action_by_id' => $userId,
        ];

        if (! empty($action['reviewed']) && $latest->reviewed_at === null) {
            $updates['reviewed_at'] = $now;
        }

        if (! empty($action['signed']) && $latest->signed_at === null) {
            $updates['signed_at'] = $now;
        }

        $latest->fill($updates)->save();
    }

    private function resolveRoutingAction(?string $previousStatus, ?string $newStatus): ?array
    {
        if (! $newStatus || $newStatus === $previousStatus) {
            return null;
        }

        if ($newStatus === 'Returned') {
            return ['action' => 'Returned'];
        }

        if ($newStatus === 'Signed') {
            return ['action' => 'Signed', 'signed' => true];
        }

        if (in_array($newStatus, ['Completed', 'Released', 'Filed'], true)) {
            return ['action' => 'Approved'];
        }

        if ($previousStatus === 'For Review' && $newStatus !== 'For Review') {
            return ['action' => 'Reviewed', 'reviewed' => true];
        }

        return null;
    }

    public function update(Request $request, Document $document)
    {
        $this->authorizeRole($request, ['Admin', 'Encoder']);

        $user = $request->user();

        if (! $this->canEditDocument($user, $document)) {
            abort(403, 'You are not allowed to edit this document.');
        }

        $validated = $this->validateDocument($request, $document->id);
        if (! Schema::hasColumn('documents', 'routed_department_id')) {
            unset($validated['routed_department_id']);
        }

        if ($user && $user->role === 'Encoder' && ($validated['status'] ?? null) === 'Pending') {
            abort(422, 'Encoders are not allowed to set status to Pending.');
        }

        // Document code is NON-editable; never overwrite it from client-provided data
        unset($validated['document_code'], $validated['encoded_by_id']);

        $before = $document->toArray();
        $previousRouted = Schema::hasColumn('documents', 'routed_department_id')
            ? $document->routed_department_id
            : null;
        if (! isset($validated['amount']) || $validated['amount'] === null) {
            $validated['amount'] = 0;
        }
        if (! isset($validated['pay_claimant']) || $validated['pay_claimant'] === null) {
            $validated['pay_claimant'] = '';
        }
        if (! isset($validated['particular']) || $validated['particular'] === null) {
            $validated['particular'] = '';
        }

        $document->update($validated);

        AuditTrail::create([
            'user_id' => $user?->id,
            'document_id' => $document->id,
            'action' => 'document_updated',
            'payload' => [
                'before' => $before,
                'after' => $document->toArray(),
            ],
        ]);

        if (Schema::hasColumn('documents', 'routed_department_id') &&
            Schema::hasTable('document_routing_histories')) {
            $currentRouted = $document->routed_department_id;
            if ($previousRouted && $currentRouted && (int) $previousRouted !== (int) $currentRouted) {
                $latest = DocumentRoutingHistory::where('document_id', $document->id)
                    ->orderByDesc('routed_at')
                    ->first();
                $isDuplicate = $latest
                    && (int) ($latest->from_department_id ?? 0) === (int) $previousRouted
                    && (int) $latest->to_department_id === (int) $currentRouted;
                if (! $isDuplicate) {
                    DocumentRoutingHistory::create([
                        'document_id' => $document->id,
                        'from_department_id' => $previousRouted,
                        'to_department_id' => $currentRouted,
                        'routed_by_id' => $user?->id,
                        'status' => $document->status,
                        'remarks' => $document->remarks,
                        'routed_at' => now(),
                    ]);
                }
            }
        }

        $this->updateRoutingAction($document, $before['status'] ?? null, $document->status, $user?->id);

        // If this edit used a temporary permission, mark the request as used.
        if ($user && $user->role === 'Encoder' && (int) $document->encoded_by_id !== (int) $user->id) {
            $now = now();
            EditRequest::where('document_id', $document->id)
                ->where('requested_by_user_id', $user->id)
                ->where('status', 'accepted')
                ->where(function ($q) use ($now) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                })
                ->update([
                    'status' => 'used',
                    'read_by_requested_by_at' => null,
                ]);

            AuditTrail::create([
                'user_id' => $user->id,
                'document_id' => $document->id,
                'action' => 'edit_request_used',
                'payload' => [
                    'edited_by_user_id' => $user->id,
                ],
            ]);
        }

        $document->refresh();
        if (! $this->isDocumentInactive($document, now())) {
            $document->inactive_alerted_at = null;
            $document->inactive_read_at = null;
            $document->inactive_reason = null;
            $document->save();
        }

        return response()->json($document);
    }

    /**
     * Bulk update document status for Admin and Encoder roles.
     */
    public function bulkStatusUpdate(Request $request)
    {
        $this->authorizeRole($request, ['Admin', 'Encoder']);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $user = $request->user();

        if ($user && $user->role === 'Encoder' && ($data['status'] ?? null) === 'Pending') {
            abort(422, 'Encoders are not allowed to set status to Pending.');
        }

        $ids = array_values(array_unique(array_filter(array_map(static function ($v) {
            if (is_numeric($v)) {
                $n = (int) $v;
                return $n > 0 ? $n : null;
            }
            return null;
        }, $data['ids'] ?? []))));

        if (empty($ids)) {
            abort(422, 'No valid document IDs provided.');
        }

        $documents = Document::whereIn('id', $ids)->get();
        if ($documents->count() !== count($ids)) {
            abort(404, 'One or more documents were not found.');
        }

        $denied = $documents->filter(fn (Document $doc) => ! $this->canEditDocument($user, $doc));
        if ($denied->isNotEmpty()) {
            abort(403, 'You are not allowed to update one or more selected documents.');
        }

        DB::transaction(function () use ($documents, $data, $user) {
            foreach ($documents as $document) {
                $before = $document->toArray();
                $document->status = $data['status'];
                $document->save();

                AuditTrail::create([
                    'user_id' => $user?->id,
                    'document_id' => $document->id,
                    'action' => 'document_updated',
                    'payload' => [
                        'before' => $before,
                        'after' => $document->toArray(),
                    ],
                ]);

                $this->updateRoutingAction($document, $before['status'] ?? null, $document->status, $user?->id);

                if ($user && $user->role === 'Encoder' && (int) $document->encoded_by_id !== (int) $user->id) {
                    $now = now();
                    EditRequest::where('document_id', $document->id)
                        ->where('requested_by_user_id', $user->id)
                        ->where('status', 'accepted')
                        ->where(function ($q) use ($now) {
                            $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                        })
                        ->update([
                            'status' => 'used',
                            'read_by_requested_by_at' => null,
                        ]);

                    AuditTrail::create([
                        'user_id' => $user->id,
                        'document_id' => $document->id,
                        'action' => 'edit_request_used',
                        'payload' => [
                            'edited_by_user_id' => $user->id,
                        ],
                    ]);
                }

                $document->refresh();
                if (! $this->isDocumentInactive($document, now())) {
                    $document->inactive_alerted_at = null;
                    $document->inactive_read_at = null;
                    $document->inactive_reason = null;
                    $document->save();
                }
            }
        });

        $count = count($ids);

        return response()->json([
            'updated' => $ids,
            'status' => $data['status'],
            'updated_count' => $count,
            'message' => "Updated {$count} document" . ($count === 1 ? '' : 's') . ' successfully.',
        ]);
    }

    /**
     * Store an optional inactivity reason for encoders.
     */
    public function setInactivityReason(Request $request, Document $document)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if ($user->role !== 'Encoder') {
            abort(403, 'Only encoders can submit inactivity reasons.');
        }

        if ((int) $document->encoded_by_id !== (int) $user->id) {
            abort(403, 'You are not allowed to update this document.');
        }

        if (! $this->isDocumentInactive($document, now())) {
            abort(422, 'This document is not inactive.');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($document->inactive_alerted_at === null) {
            $document->inactive_alerted_at = now();
        }

        $document->inactive_reason = $data['reason'] ?? null;

        // Saving inactivity metadata should not reset the "last action" timestamp
        // used for inactivity detection. Otherwise, the document would stop being
        // considered inactive as soon as a reason is entered.
        Document::withoutTimestamps(function () use ($document) {
            $document->save();
        });

        return response()->json([
            'document_id' => $document->id,
            'inactive_reason' => $document->inactive_reason,
        ]);
    }

    /**
     * Determine whether the given user can edit the document directly.
     * Admins can always edit. Encoders can edit their own documents or
     * documents with an accepted, non-expired edit request.
     */
    protected function canEditDocument($user, Document $document): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'Admin') {
            return true;
        }

        if ($user->role !== 'Encoder') {
            return false;
        }

        // Original encoder can always edit
        if ((int) $document->encoded_by_id === (int) $user->id) {
            return true;
        }

        // Check for accepted, non-expired edit request
        $now = now();

        return EditRequest::where('document_id', $document->id)
            ->where('requested_by_user_id', $user->id)
            ->where('status', 'accepted')
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->exists();
    }

    public function destroy(Request $request, Document $document)
    {
        $this->authorizeRole($request, ['Admin']);

        $snapshot = $document->toArray();
        $document->delete();

        AuditTrail::create([
            'user_id' => $request->user()->id,
            'document_id' => $snapshot['id'] ?? null,
            'action' => 'document_deleted',
            'payload' => $snapshot,
        ]);

        return response()->json([
            'message' => 'Document deleted.',
        ]);
    }

    /**
     * Dashboard metrics: totals, by status, by department, optional date range.
     *
     * Sample JSON response:
     * {
     *   "total_documents": 120,
     *   "by_status": { "For Signature": 40, "Signed": 60, "Filed": 20 },
     *   "by_department": [
     *     { "department_id": 1, "department_name": "Budget", "total": 35 }
     *   ]
     * }
     */
    public function metrics(Request $request)
    {
        $query = Document::query();

        if ($from = $request->get('date_from')) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('date', '<=', $to);
        }

        $totalDocuments = $query->count();

        $byStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        if ($byStatus->has('Pending')) {
            $byStatus->forget('Pending');
        }

        $routingColumn = 'documents.routed_department_id';

        $byDepartmentQuery = Department::query()
            ->select(
                'departments.id as department_id',
                'departments.name as department_name',
                DB::raw('COUNT(documents.id) as total')
            )
            ->leftJoin('documents', function ($join) use ($routingColumn, $from, $to) {
                $join->on('departments.id', '=', $routingColumn);
                if ($from) {
                    $join->whereDate('documents.date', '>=', $from);
                }
                if ($to) {
                    $join->whereDate('documents.date', '<=', $to);
                }
            })
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('departments.name');

        if (Schema::hasColumn('departments', 'is_active')) {
            $byDepartmentQuery->where('departments.is_active', true);
        }

        $byDepartment = $byDepartmentQuery->get();

        return response()->json([
            'total_documents' => $totalDocuments,
            'by_status' => $byStatus,
            'by_department' => $byDepartment,
        ]);
    }

    /**
     * Recent transactions for dashboard table.
     */
    public function recent(Request $request)
    {
        $limit = (int) $request->get('limit', 10);

        $query = Document::with(['routedDepartment', 'encodedBy'])
            ->orderByDesc('created_at');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $user = $request->user();
        if ($user && $user->role === 'Admin') {
            $query->whereIn('status', ['For Review', 'For Signature']);
        } elseif ($user && $user->role === 'Encoder') {
            $query->where('status', '!=', 'Pending');
        }

        $documents = $query->get();

        return response()->json($documents);
    }

    /**
     * Routing history list for Department tab (admin only).
     */
    public function routingHistory(Request $request)
    {
        $this->authorizeRole($request, ['Admin']);

        if (! Schema::hasTable('document_routing_histories')) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 0,
                    'total' => 0,
                ],
            ]);
        }

        $perPage = (int) $request->get('per_page', 50);
        $query = DocumentRoutingHistory::query()->with([
            'document:id,document_code,document_number,type_of_document,particular,encoded_by_id,status,remarks,inactive_reason,created_at',
            'document.encodedBy:id,name',
            'fromDepartment:id,name,code',
            'toDepartment:id,name,code',
            'routedBy:id,name',
            'actionBy:id,name',
        ]);

        if ($documentId = $request->get('document_id')) {
            $query->where('document_id', $documentId);
        }

        if ($routedDepartmentId = $request->get('routed_department_id')) {
            $query->where('to_department_id', $routedDepartmentId);
        }

        if ($status = $request->get('status')) {
            $query->whereHas('document', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        if ($search = $request->get('search')) {
            $term = trim((string) $search);
            if ($term !== '') {
                $like = '%' . $term . '%';
                $query->whereHas('document', function ($q) use ($like) {
                    $q->where('document_code', 'like', $like)
                        ->orWhere('document_number', 'like', $like);
                });
            }
        }

        if ($from = $request->get('date_from')) {
            $query->whereDate('routed_at', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('routed_at', '<=', $to);
        }

        $query->orderByDesc('routed_at');

        $paginator = $query->paginate($perPage);
        $items = $this->formatRoutingHistoryItems(collect($paginator->items()), now());

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Routing history timeline for a specific document (admin only).
     */
    public function documentRoutingHistory(Request $request, Document $document)
    {
        $this->authorizeRole($request, ['Admin']);

        if (! Schema::hasTable('document_routing_histories')) {
            return response()->json(['data' => []]);
        }

        $rows = DocumentRoutingHistory::query()
            ->where('document_id', $document->id)
            ->with([
                'document:id,document_code,document_number,type_of_document,particular,encoded_by_id,status,remarks,inactive_reason,created_at',
                'document.encodedBy:id,name',
                'fromDepartment:id,name,code',
                'toDepartment:id,name,code',
                'routedBy:id,name',
                'actionBy:id,name',
            ])
            ->orderBy('routed_at')
            ->get();

        return response()->json([
            'data' => $this->formatRoutingHistoryItems($rows, now(), $rows),
        ]);
    }

    private function formatRoutingHistoryItems($rows, Carbon $now, $allRows = null): array
    {
        if (! $rows || $rows->isEmpty()) {
            return [];
        }

        $source = $allRows;
        if (! $source) {
            $docIds = $rows->pluck('document_id')->unique()->values();
            $source = DocumentRoutingHistory::whereIn('document_id', $docIds)
                ->orderBy('routed_at')
                ->get();
        }

        $nextMap = $this->buildNextRoutingMap($source);

        return $rows->map(function (DocumentRoutingHistory $row) use ($now, $nextMap) {
            $doc = $row->document;
            $nextRoutedAt = $nextMap[$row->id] ?? null;
            $daysInDept = null;
            if ($row->routed_at) {
                $end = $nextRoutedAt ?: $now;
                $daysInDept = $row->routed_at->diffInDays($end);
            }

            $inactive = $daysInDept !== null && $daysInDept >= 3
                && (! $row->action_taken || trim((string) $row->action_taken) === '');

            $documentTitle = $doc?->particular ?: $doc?->type_of_document;

            return [
                'id' => $row->id,
                'document_id' => $row->document_id,
                'document_code' => $doc?->document_code,
                'document_number' => $doc?->document_number,
                'document_title' => $documentTitle,
                'type_of_document' => $doc?->type_of_document,
                'created_by' => $doc?->encodedBy?->name,
                'document_created_at' => $doc?->created_at?->toIso8601String(),
                'from_department' => $row->fromDepartment
                    ? [
                        'id' => $row->fromDepartment->id,
                        'name' => $row->fromDepartment->name,
                        'code' => $row->fromDepartment->code,
                    ]
                    : null,
                'to_department' => $row->toDepartment
                    ? [
                        'id' => $row->toDepartment->id,
                        'name' => $row->toDepartment->name,
                        'code' => $row->toDepartment->code,
                    ]
                    : null,
                'routed_by' => $row->routedBy?->name,
                'routed_at' => $row->routed_at?->toIso8601String(),
                'received_at' => $row->routed_at?->toIso8601String(),
                'reviewed_at' => $row->reviewed_at?->toIso8601String(),
                'signed_at' => $row->signed_at?->toIso8601String(),
                'action_taken' => $row->action_taken,
                'action_at' => $row->action_at?->toIso8601String(),
                'action_by' => $row->actionBy?->name,
                'status' => $row->status ?? $doc?->status,
                'remarks' => $row->remarks ?? $doc?->remarks,
                'days_in_department' => $daysInDept,
                'inactive' => $inactive,
                'inactive_reason' => $doc?->inactive_reason,
            ];
        })->all();
    }

    private function buildNextRoutingMap($rows): array
    {
        $nextMap = [];
        $grouped = $rows->groupBy('document_id');

        foreach ($grouped as $items) {
            $sorted = $items->sortBy('routed_at')->values();
            $count = $sorted->count();
            for ($i = 0; $i < $count; $i++) {
                $current = $sorted[$i];
                $next = $sorted[$i + 1] ?? null;
                $nextMap[$current->id] = $next?->routed_at ?? null;
            }
        }

        return $nextMap;
    }

    /**
     * Export documents as a properly formatted XLSX file.
     *
     * - Includes headers + all filtered rows (not just paginated page)
     * - Uses a streaming writer for large datasets
     * - Applies clean formatting (bold headers, column widths)
     */
    public function exportExcel(Request $request)
    {
        // Allow any authenticated role; access is already restricted via Sanctum.
        $this->authorizeRole($request, ['Admin', 'Encoder', 'Viewer']);

        try {
            if (! class_exists(\OpenSpout\Writer\XLSX\Writer::class)) {
                return response()->json([
                    'message' => 'Excel export is not available on this server.',
                    'error' => 'Missing dependency: openspout/openspout. Run composer install on the backend.',
                ], 500);
            }

            $history = ReportHistory::create([
                'type' => 'filtered_data',
                'format' => 'excel',
                'filters' => $this->collectExportFilters($request),
                'generated_by_id' => $request->user()?->id,
            ]);

            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="report.xlsx"',
                'X-Report-History-Id' => (string) ($history->id ?? ''),
            ];
            $query = $this->buildExportQuery($request);

            $tmpDir = storage_path('app/tmp/exports');
            File::ensureDirectoryExists($tmpDir);

            $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . Str::uuid()->toString() . '.xlsx';

            $options = new XlsxOptions();
            $options->setTempFolder($tmpDir);

            $defaultStyle = (new Style())
                ->setFontName('Calibri')
                ->setFontSize(11);
            $options->DEFAULT_ROW_STYLE = $defaultStyle;

            $writer = new XlsxWriter($options);
            $writer->openToFile($tmpPath);

            $columnHeaders = $this->exportColumns();

            $sheetView = new SheetView();
            $sheetView->setFreezeRow(2); // keep header row visible
            $writer->getCurrentSheet()->setSheetView($sheetView);

            $headerStyle = (new Style())
                ->setFontBold()
                ->setShouldWrapText(true)
                ->setCellAlignment(CellAlignment::CENTER);

            $writer->addRow(Row::fromValues(array_values($columnHeaders), $headerStyle));

            $maxCharsPerColumn = array_map(static fn ($h) => mb_strlen((string) $h), array_values($columnHeaders));
            $rowCount = 1; // header row

            foreach ($query->cursor() as $row) {
                $values = $this->mapExportRow($row);

                foreach ($values as $i => $value) {
                    $len = mb_strlen((string) $value);
                    if ($len > ($maxCharsPerColumn[$i] ?? 0)) {
                        $maxCharsPerColumn[$i] = $len;
                    }
                }

                $writer->addRow(Row::fromValues($values));
                $rowCount++;
            }

            // Rough "auto-fit": translate max character count into Excel column widths.
            // Caps prevent unusably wide columns for long text fields.
            foreach ($maxCharsPerColumn as $i => $maxChars) {
                $width = min(max((int) $maxChars + 2, 12), 60);
                $writer->getCurrentSheet()->setColumnWidth($width, $i + 1); // 1-indexed
            }

            // Enable column filters (header row + all data rows)
            $writer->getCurrentSheet()->setAutoFilter(
                new AutoFilter(0, count($columnHeaders) - 1, 1, max($rowCount, 1))
            );

            $writer->close();

            return response()->streamDownload(
                static function () use ($tmpPath) {
                    readfile($tmpPath);
                    @unlink($tmpPath);
                },
                'report.xlsx',
                $headers
            );
        } catch (\Throwable $e) {
            \Log::error('Export Excel failed: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Failed to export to Excel.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Export documents as a real PDF file with a title, generation date,
     * and a complete data table.
     */
    public function exportPdf(Request $request)
    {
        // Allow any authenticated role; access is already restricted via Sanctum.
        $this->authorizeRole($request, ['Admin', 'Encoder', 'Viewer']);

        try {
            if (! class_exists(\Dompdf\Dompdf::class)) {
                return response()->json([
                    'message' => 'PDF export is not available on this server.',
                    'error' => 'Missing dependency: barryvdh/laravel-dompdf. Run composer install on the backend.',
                ], 500);
            }

            $maxPdfRows = (int) config('exports.pdf_max_rows', 3000);
            $query = $this->buildExportQuery($request);

            $rows = $query->limit($maxPdfRows + 1)->get();
            if ($rows->count() > $maxPdfRows) {
                return response()->json([
                    'message' => 'Too many rows to export as PDF. Please narrow filters or export to Excel instead.',
                    'max_rows' => $maxPdfRows,
                ], 422);
            }

            $generatedAt = now();
            /** @var \Barryvdh\DomPDF\PDF $pdf */
            $pdf = app('dompdf.wrapper')->loadView('exports.documents', [
                'title' => 'City Hall Monitoring System — Documents Report',
                'generatedAt' => $generatedAt,
                'columns' => $this->exportColumns(),
                'rows' => $rows,
            ])->setPaper('a4', 'landscape');

            $history = ReportHistory::create([
                'type' => 'filtered_data',
                'format' => 'pdf',
                'filters' => $this->collectExportFilters($request),
                'generated_by_id' => $request->user()?->id,
            ]);

            return response()->streamDownload(
                static function () use ($pdf) {
                    echo $pdf->output();
                },
                'report.pdf',
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="report.pdf"',
                    'X-Report-History-Id' => (string) ($history->id ?? ''),
                ]
            );
        } catch (\Throwable $e) {
            \Log::error('Export PDF failed: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Failed to export to PDF.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Shared export filters (reusing same query rules as index).
     */
    protected function buildExportQuery(Request $request)
    {
        $hasRouted = Schema::hasColumn('documents', 'routed_department_id');

        $query = DB::table('documents')
            ->leftJoin('departments as routed_departments', 'documents.routed_department_id', '=', 'routed_departments.id')
            ->leftJoin('users as encoders', 'documents.encoded_by_id', '=', 'encoders.id');

        $select = [
            'documents.id',
            'documents.date',
            'encoders.name as encoded_by_name',
            'documents.type_of_document',
            'documents.document_code',
            'documents.document_number',
            'documents.pay_claimant',
            'documents.particular',
            'documents.amount',
            'routed_departments.name as department_out_name',
            'routed_departments.code as department_out_code',
            'documents.status',
            'documents.remarks',
            'documents.date_out',
        ];

        $query->select($select)
            ->orderBy('documents.date')
            ->orderBy('documents.id');

        // Same filters as list endpoint (plus encoded_by support)
        if ($code = $request->get('code')) {
            $query->where('documents.document_code', 'like', '%' . $code . '%');
        }

        if ($search = $request->get('search')) {
            $term = trim((string) $search);
            if ($term !== '') {
                $like = '%' . $term . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('documents.document_code', 'like', $like)
                        ->orWhere('documents.document_number', 'like', $like)
                        ->orWhere('documents.type_of_document', 'like', $like)
                        ->orWhere('documents.pay_claimant', 'like', $like)
                        ->orWhere('documents.particular', 'like', $like)
                        ->orWhere('documents.remarks', 'like', $like)
                        ->orWhere('documents.status', 'like', $like)
                        ->orWhere('routed_departments.name', 'like', $like)
                        ->orWhere('routed_departments.code', 'like', $like)
                        ->orWhere('encoders.name', 'like', $like);
                });
            }
        }

        $routedDepartmentId = $request->get('routed_department_id') ?? $request->get('department_id');
        if ($routedDepartmentId && $hasRouted) {
            $query->where('documents.routed_department_id', $routedDepartmentId);
        }

        $routedDepartmentCode = $request->get('routed_department_code') ?? $request->get('department_code');
        if ($routedDepartmentCode) {
            $query->where('routed_departments.code', $routedDepartmentCode);
        }

        if ($status = $request->get('status')) {
            $query->where('documents.status', $status);
        }

        $statusGroups = $request->input('status_groups', []);
        $statusList = $this->statusesForGroups((array) $statusGroups);
        if (! empty($statusList)) {
            $query->whereIn('documents.status', $statusList);
        }

        if ($type = $request->get('type')) {
            $query->where('documents.type_of_document', $type);
        }

        if ($encodedBy = $request->get('encoded_by')) {
            $query->where('documents.encoded_by_id', $encodedBy);
        }

        if ($from = $request->get('date_from')) {
            $query->whereDate('documents.date', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('documents.date', '<=', $to);
        }

        $createdDate = $request->get('created_date');
        $createdMonth = $request->get('created_month');
        if ($createdDate && $createdMonth) {
            abort(422, 'Only one date filter may be applied at a time.');
        }
        if ($createdDate) {
            $query->whereDate('documents.created_at', $createdDate);
        } elseif ($createdMonth) {
            try {
                $start = Carbon::createFromFormat('Y-m', $createdMonth)->startOfMonth();
                $end = (clone $start)->endOfMonth();
                $query->whereBetween('documents.created_at', [$start, $end]);
            } catch (\Throwable $e) {
                // Ignore invalid month format
            }
        }

        // Optional: export only selected documents
        $ids = $request->query('ids');
        $idList = [];
        if (is_string($ids) && $ids !== '') {
            $idList = array_filter(array_map('trim', explode(',', $ids)));
        } elseif (is_array($ids)) {
            $idList = $ids;
        }
        $idList = array_values(array_unique(array_filter(array_map(static function ($v) {
            if (is_numeric($v)) {
                $n = (int) $v;
                return $n > 0 ? $n : null;
            }
            return null;
        }, $idList))));
        if (! empty($idList)) {
            $query->whereIn('documents.id', $idList);
        }

        return $query;
    }

    /**
     * Normalize shared export/report filters for logging.
     */
    protected function collectExportFilters(Request $request): array
    {
        return [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'created_date' => $request->get('created_date'),
            'created_month' => $request->get('created_month'),
            'search' => $request->get('search'),
            'routed_department_id' => $request->get('routed_department_id') ?? $request->get('department_id'),
            'status' => $request->get('status'),
            'status_groups' => $request->input('status_groups', []),
            'encoded_by' => $request->get('encoded_by'),
            'category' => $request->get('type'),
            'ids' => $request->get('ids'),
        ];
    }

    /**
     * @param  array<int, string>  $groups
     * @return array<int, string>
     */
    protected function statusesForGroups(array $groups): array
    {
        $map = [
            'In Progress' => ['For Initial', 'For Schedule'],
            'Completed' => ['Signed', 'Filed'],
            'On Hold' => ['Hold', 'Returned'],
        ];

        $selected = [];
        foreach ($groups as $group) {
            if (isset($map[$group])) {
                $selected = array_merge($selected, $map[$group]);
            }
        }

        return array_values(array_unique($selected));
    }

    /**
     * @return array<string, string>
     */
    protected function exportColumns(): array
    {
        $columns = [
            'date' => 'Date',
            'encoded_by_name' => 'Encoded By',
            'type_of_document' => 'Type',
            'document_code' => 'Document Code',
            'document_number' => 'Document Number',
            'pay_claimant' => 'Pay Claimant',
            'particular' => 'Particular',
            'amount' => 'Amount',
            'department_out_name' => 'Department Out',
            'status' => 'Status',
            'remarks' => 'Remarks',
            'date_out' => 'Date Out',
        ];
        return $columns;
    }

    /**
     * @param  object  $row
     * @return array<int, string>
     */
    protected function mapExportRow(object $row): array
    {
        $date = isset($row->date) && $row->date ? Carbon::parse($row->date)->format('Y-m-d') : '';
        $dateOut = isset($row->date_out) && $row->date_out ? Carbon::parse($row->date_out)->format('Y-m-d') : '';

        $amount = '';
        if (isset($row->amount) && $row->amount !== null && $row->amount !== '') {
            $amount = number_format((float) $row->amount, 2, '.', '');
        }

        $values = [
            $date,
            (string) ($row->encoded_by_name ?? ''),
            (string) ($row->type_of_document ?? ''),
            (string) ($row->document_code ?? ''),
            (string) ($row->document_number ?? ''),
            (string) ($row->pay_claimant ?? ''),
            (string) ($row->particular ?? ''),
            $amount,
            (string) ($row->department_name ?? ''),
            (string) ($row->status ?? ''),
            (string) ($row->remarks ?? ''),
            $dateOut,
        ];
        if (Schema::hasColumn('documents', 'routed_department_id')) {
            array_splice($values, 9, 0, (string) ($row->routed_department_name ?? ''));
        }
        return $values;
    }

    /**
     * Validate document payload from request.
     */
    protected function validateDocument(Request $request, ?int $documentId = null): array
    {
        $type = strtoupper(trim((string) $request->input('type_of_document', '')));
        $requirements = $this->documentTypeRequirements($type);

        $rules = [
            'date' => ['required', 'date'],
            'type_of_document' => ['required', 'string', 'max:255'],
            'document_number' => [
                'nullable',
                'string',
                'max:255',
                // Prevent duplicate document numbers (except for the current record on update)
                'unique:documents,document_number' . ($documentId ? ',' . $documentId : ''),
            ],
            'pay_claimant' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'name_of_business' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
            'particular' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
            'date_out' => ['nullable', 'date'],
        ];

        if (Schema::hasColumn('documents', 'routed_department_id')) {
            $rules['routed_department_id'] = ['required', 'exists:departments,id'];
        } else {
            $rules['routed_department_id'] = ['nullable'];
        }

        if ($requirements['document_number'] ?? false) {
            $rules['document_number'][] = 'required';
        }
        if ($requirements['pay_claimant'] ?? false) {
            $rules['pay_claimant'][] = 'required';
        }
        if ($requirements['contact_number'] ?? false) {
            $rules['contact_number'][] = 'required';
        }
        if ($requirements['name_of_business'] ?? false) {
            $rules['name_of_business'][] = 'required';
        }
        if ($requirements['reason'] ?? false) {
            $rules['reason'][] = 'required';
        }
        if ($requirements['particular'] ?? false) {
            $rules['particular'][] = 'required';
        }
        if ($requirements['amount'] ?? false) {
            $rules['amount'][] = 'required';
            if (($requirements['amount_min'] ?? 0) > 0) {
                $rules['amount'][] = 'min:' . (string) $requirements['amount_min'];
            }
        }

        return $request->validate($rules);
    }

    /**
     * Document-type-specific required fields.
     *
     * @return array<string, mixed>
     */
    protected function documentTypeRequirements(string $type): array
    {
        $map = [
            'LETTER REQUEST' => [
                'pay_claimant' => true,
                'contact_number' => true,
            ],
            'PURCHASE REQUEST' => [
                'document_number' => true,
                'amount' => true,
                'amount_min' => 1000000,
            ],
            'PURCHASE ORDER' => [
                'document_number' => true,
                'amount' => true,
                'amount_min' => 1000000,
            ],
            'PO ATTACHMENTS' => [
                'amount' => true,
                'amount_min' => 1000000,
            ],
            'OBR' => [
                'pay_claimant' => true,
                'document_number' => true,
                'particular' => true,
                'amount' => true,
                'amount_min' => 1000000,
            ],
            'VOUCHER' => [
                'pay_claimant' => true,
                'document_number' => true,
                'particular' => true,
                'amount' => true,
                'amount_min' => 1000000,
            ],
            'CHEQUE' => [
                'pay_claimant' => true,
                'document_number' => true,
                'particular' => true,
                'amount' => true,
                'amount_min' => 1000000,
            ],
            'MOA' => [
                'particular' => true,
            ],
            'DEED OF DONATION' => [
                'particular' => true,
            ],
            'DEED OF SALE' => [
                'particular' => true,
            ],
            'CONTRACT OF LEASE' => [
                'particular' => true,
            ],
            'CONTRACT OF SERVICE (SUPPLIER)' => [
                'particular' => true,
            ],
            'HR' => [
                'particular' => true,
            ],
            'CERTIFICATION' => [
                'particular' => true,
            ],
            'INVITATION/COURTESY' => [],
            'POW' => [
                'document_number' => true,
                'particular' => true,
                'amount' => true,
            ],
            'SWA' => [
                'particular' => true,
            ],
            'BPLO DOCS' => [
                'name_of_business' => true,
                'reason' => true,
            ],
            'BPMP' => [
                'particular' => true,
            ],
            'LEGAL DOCS' => [
                'particular' => true,
            ],
        ];

        return $map[$type] ?? [];
    }

    /**
     * Generate automatic document code on the backend only.
     *
     * Format: CH-YYYY-DEPT-XXXX
     * - CH is fixed prefix for City Hall
     * - YYYY is the document year
     * - DEPT is the destination department code (Department Out)
     * - XXXX is a zero-padded running sequence per year+department
     */
    protected function generateDocumentCode(Department $departmentOut): string
    {
        $year = now()->year;

        // Use the document code prefix (CH-YYYY-DEPT-) as the reliable source
        // for sequencing, rather than the date column. This avoids issues where
        // the stored document date might not match the current year but the
        // document code still uses the current year.
        $prefix = sprintf('CH-%d-%s-', $year, $departmentOut->code);

        // Find how many existing codes already use this prefix, including
        // soft-deleted rows, then increment to get the next sequence number.
        $sequence = Document::withTrashed()
            ->where('document_code', 'like', $prefix . '%')
            ->count() + 1;

        $sequencePadded = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

        return $prefix . $sequencePadded;
    }

    protected function authorizeRole(Request $request, array $roles): void
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'You are not authorized to perform this action.');
        }
    }

    private function inactivityExcludedStatuses(): array
    {
        // These statuses are considered "final" or outside the encoder's
        // responsibility for inactivity monitoring. They are excluded from
        // 3-day inactivity checks and notifications.
        return ['For Review', 'For Signature', 'Signed', 'Completed', 'Released'];
    }

    private function isDocumentInactive(Document $document, Carbon $now): bool
    {
        if (in_array($document->status, $this->inactivityExcludedStatuses(), true)) {
            return false;
        }

        $lastAction = $document->updated_at ?? $document->created_at;
        if (! $lastAction) {
            return false;
        }

        return $lastAction->lte($now->copy()->subDays(3));
    }

    private function inactivityDays(Document $document, Carbon $now): int
    {
        $lastAction = $document->updated_at ?? $document->created_at;
        if (! $lastAction) {
            return 0;
        }

        return $lastAction->diffInDays($now);
    }
}
