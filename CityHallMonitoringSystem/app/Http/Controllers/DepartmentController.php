<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DepartmentController extends Controller
{
    /**
     * List departments (for dropdowns and management).
     *
     * Sample response:
     * {
     *   "data": [{ "id": 1, "name": "Budget Office", "code": "BUD" }],
     *   "meta": { "total": 1 }
     * }
     */
    public function index(Request $request)
    {
        $query = Department::query()->orderBy('name');

        if (Schema::hasColumn('departments', 'is_active') && ! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $perPage = (int) $request->get('per_page', 50);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeRole($request, ['Admin']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', 'unique:departments,code'],
            'office' => ['required', 'string', 'max:255'],
            'department_head' => ['required', 'string', 'max:255'],
        ]);

        $department = Department::create($validated);

        AuditTrail::create([
            'user_id' => $request->user()->id,
            'action' => 'department_created',
            'payload' => $department->toArray(),
        ]);

        return response()->json($department, 201);
    }

    public function show(Department $department)
    {
        return response()->json($department);
    }

    public function update(Request $request, Department $department)
    {
        $this->authorizeRole($request, ['Admin']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', 'unique:departments,code,' . $department->id],
            'office' => ['required', 'string', 'max:255'],
            'department_head' => ['required', 'string', 'max:255'],
        ]);

        $before = $department->toArray();
        $department->update($validated);

        AuditTrail::create([
            'user_id' => $request->user()->id,
            'action' => 'department_updated',
            'payload' => [
                'before' => $before,
                'after' => $department->toArray(),
            ],
        ]);

        return response()->json($department);
    }

    public function destroy(Request $request, Department $department)
    {
        $this->authorizeRole($request, ['Admin']);

        $inUse = $department->documents()->exists();
        if ($inUse && Schema::hasColumn('departments', 'is_active')) {
            $snapshot = $department->toArray();
            $department->update(['is_active' => false]);

            AuditTrail::create([
                'user_id' => $request->user()->id,
                'action' => 'department_deactivated',
                'payload' => [
                    'before' => $snapshot,
                    'after' => $department->toArray(),
                ],
            ]);

            return response()->json([
                'message' => 'Department is in use and was deactivated instead of deleted.',
                'status' => 'deactivated',
            ]);
        }

        $snapshot = $department->toArray();
        $department->delete();

        AuditTrail::create([
            'user_id' => $request->user()->id,
            'action' => 'department_deleted',
            'payload' => $snapshot,
        ]);

        return response()->json([
            'message' => 'Department deleted.',
        ]);
    }

    /**
     * Bulk delete departments (admin only).
     */
    public function bulkDelete(Request $request)
    {
        $this->authorizeRole($request, ['Admin']);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_filter(array_map(static function ($v) {
            if (is_numeric($v)) {
                $n = (int) $v;
                return $n > 0 ? $n : null;
            }
            return null;
        }, $data['ids'] ?? []))));

        if (empty($ids)) {
            abort(422, 'No valid department IDs provided.');
        }

        $departments = Department::whereIn('id', $ids)->get();
        if ($departments->count() !== count($ids)) {
            abort(404, 'One or more departments were not found.');
        }

        $deletedIds = [];
        $deactivatedIds = [];

        \DB::transaction(function () use ($departments, $request, &$deletedIds, &$deactivatedIds) {
            foreach ($departments as $department) {
                $snapshot = $department->toArray();
                $inUse = $department->documents()->exists();
                if ($inUse && Schema::hasColumn('departments', 'is_active')) {
                    $department->update(['is_active' => false]);
                    $deactivatedIds[] = $department->id;

                    AuditTrail::create([
                        'user_id' => $request->user()->id,
                        'action' => 'department_deactivated',
                        'payload' => [
                            'before' => $snapshot,
                            'after' => $department->toArray(),
                        ],
                    ]);
                    continue;
                }

                $department->delete();
                $deletedIds[] = $department->id;

                AuditTrail::create([
                    'user_id' => $request->user()->id,
                    'action' => 'department_deleted',
                    'payload' => $snapshot,
                ]);
            }
        });

        return response()->json([
            'message' => 'Departments processed.',
            'deleted_ids' => $deletedIds,
            'deactivated_ids' => $deactivatedIds,
        ]);
    }

    /**
     * Simple role check helper for controllers.
     */
    protected function authorizeRole(Request $request, array $roles): void
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'You are not authorized to perform this action.');
        }
    }
}
