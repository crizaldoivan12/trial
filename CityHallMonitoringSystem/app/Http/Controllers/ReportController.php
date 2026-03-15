<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\ReportHistory;
use App\Models\ScheduledReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function analytics(Request $request)
    {
        $this->authorizeRole($request, ['Admin']);

        $query = $this->buildAnalyticsQuery($request);

        $total = (clone $query)->count();

        // Raw status counts
        $statusCounts = (clone $query)
            ->select('documents.status', DB::raw('COUNT(*) as total'))
            ->groupBy('documents.status')
            ->pluck('total', 'documents.status');

        $groupMap = $this->statusGroupMap();
        $groupSummary = [];
        foreach ($groupMap as $group => $statuses) {
            $groupSummary[$group] = 0;
            foreach ($statuses as $status) {
                $groupSummary[$group] += (int) ($statusCounts[$status] ?? 0);
            }
        }

        $completion = $total > 0 ? ($groupSummary['Completed'] ?? 0) / $total : 0.0;

        // By department
        $byDepartment = (clone $query)
            ->leftJoin('departments', 'documents.routed_department_id', '=', 'departments.id')
            ->select(
                'departments.id as department_id',
                'departments.name as department_name',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('departments.name')
            ->get();

        // Monthly trend
        $monthlyTrend = (clone $query)
            ->select(
                DB::raw("DATE_FORMAT(documents.date, '%Y-%m-01') as month"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Distinct categories (request types)
        $categories = (clone $query)
            ->whereNotNull('documents.type_of_document')
            ->distinct()
            ->orderBy('documents.type_of_document')
            ->limit(50)
            ->pluck('documents.type_of_document')
            ->values();

        return response()->json([
            'summary' => [
                'total_requests' => $total,
                'in_progress' => $groupSummary['In Progress'] ?? 0,
                'completed' => $groupSummary['Completed'] ?? 0,
                'on_hold' => $groupSummary['On Hold'] ?? 0,
                'completion_rate' => $completion,
            ],
            'charts' => [
                'by_status_groups' => collect($groupSummary)
                    ->map(fn ($value, $key) => ['status_group' => $key, 'total' => $value])
                    ->values(),
                'by_department' => $byDepartment,
                'monthly_trend' => $monthlyTrend,
            ],
            'meta' => [
                'categories' => $categories,
            ],
        ]);
    }

    public function history(Request $request)
    {
        $this->authorizeRole($request, ['Admin']);

        $perPage = (int) $request->get('per_page', 10);

        $paginator = ReportHistory::query()
            ->with('generatedBy:id,name,role')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $data = collect($paginator->items())->map(function (ReportHistory $history) {
            $filters = $history->filters ?? [];

            $parts = [];
            if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
                $from = $filters['date_from'] ?? 'Any';
                $to = $filters['date_to'] ?? 'Any';
                $parts[] = "Date: {$from} → {$to}";
            }
            $deptId = $filters['routed_department_id'] ?? $filters['department_id'] ?? null;
            if (! empty($deptId)) {
                $parts[] = 'Department Out ID: ' . $deptId;
            }
            if (! empty($filters['status_groups'])) {
                $parts[] = 'Status: ' . implode(', ', (array) $filters['status_groups']);
            }
            if (! empty($filters['requested_by'])) {
                $parts[] = 'Requested By ID: ' . $filters['requested_by'];
            }
            if (! empty($filters['category'])) {
                $parts[] = 'Category: ' . $filters['category'];
            }

            return [
                'id' => $history->id,
                'type' => $history->type,
                'format' => $history->format,
                'filters' => $history->filters,
                'filters_summary' => implode(' | ', $parts),
                'generated_by' => $history->generatedBy?->name,
                'generated_by_role' => $history->generatedBy?->role,
                'created_at' => $history->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function scheduleShow(Request $request)
    {
        $this->authorizeRole($request, ['Admin']);

        $config = ScheduledReport::query()->first();

        return response()->json([
            'enabled' => (bool) ($config->enabled ?? false),
            'frequency' => $config->frequency ?? 'monthly',
            'email' => $config->email,
            'last_generated_at' => $config->last_generated_at?->toIso8601String(),
            'last_report_type' => $config->last_report_type,
        ]);
    }

    public function scheduleUpdate(Request $request)
    {
        $this->authorizeRole($request, ['Admin']);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'email' => ['nullable', 'email'],
        ]);

        $config = ScheduledReport::query()->firstOrNew();
        $config->enabled = $validated['enabled'];
        $config->email = $validated['email'] ?? null;
        $config->frequency = 'monthly';
        $config->save();

        return response()->json([
            'enabled' => $config->enabled,
            'frequency' => $config->frequency,
            'email' => $config->email,
            'last_generated_at' => $config->last_generated_at?->toIso8601String(),
            'last_report_type' => $config->last_report_type,
        ]);
    }

    public function scheduleRun(Request $request)
    {
        $this->authorizeRole($request, ['Admin']);

        // Generate a summary report for the previous full month.
        $end = Carbon::now()->subMonthNoOverflow()->endOfMonth();
        $start = (clone $end)->startOfMonth();

        $requestWithRange = $request->duplicate([
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
        ] + $request->all());

        return $this->exportSummaryPdf($requestWithRange, scheduled: true);
    }

    public function exportSummaryPdf(Request $request, bool $scheduled = false)
    {
        $this->authorizeRole($request, ['Admin']);

        if (! class_exists(\Dompdf\Dompdf::class)) {
            return response()->json([
                'message' => 'PDF export is not available on this server.',
                'error' => 'Missing dependency: barryvdh/laravel-dompdf. Run composer install on the backend.',
            ], 500);
        }

        $analytics = $this->analytics($request)->getData(true);

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper')->loadView('exports.summary', [
            'title' => 'City Hall Monitoring System — Summary Report',
            'generatedAt' => now(),
            'summary' => $analytics['summary'] ?? [],
            'charts' => $analytics['charts'] ?? [],
            'filters' => $this->collectFilters($request),
        ])->setPaper('a4', 'landscape');

        $history = $this->logHistory($request, 'summary', 'pdf');

        if ($scheduled) {
            $config = ScheduledReport::query()->firstOrNew();
            $config->enabled = $config->enabled ?? true;
            $config->frequency = 'monthly';
            $config->last_generated_at = now();
            $config->last_report_type = 'summary';
            $config->save();
        }

        return response()->streamDownload(
            static function () use ($pdf) {
                echo $pdf->output();
            },
            'summary-report.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="summary-report.pdf"',
                'X-Report-History-Id' => (string) ($history->id ?? ''),
            ]
        );
    }

    public function exportDepartmentPdf(Request $request)
    {
        $this->authorizeRole($request, ['Admin']);

        if (! class_exists(\Dompdf\Dompdf::class)) {
            return response()->json([
                'message' => 'PDF export is not available on this server.',
                'error' => 'Missing dependency: barryvdh/laravel-dompdf. Run composer install on the backend.',
            ], 500);
        }

        $query = $this->buildAnalyticsQuery($request);

        $rows = (clone $query)
            ->leftJoin('departments', 'documents.routed_department_id', '=', 'departments.id')
            ->select(
                'departments.name as department_name',
                DB::raw('COUNT(*) as total_amount'),
                DB::raw('SUM(documents.amount) as sum_amount')
            )
            ->groupBy('departments.name')
            ->orderBy('departments.name')
            ->get();

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper')->loadView('exports.departments', [
            'title' => 'City Hall Monitoring System — Department Report',
            'generatedAt' => now(),
            'rows' => $rows,
            'filters' => $this->collectFilters($request),
        ])->setPaper('a4', 'landscape');

        $history = $this->logHistory($request, 'department', 'pdf');

        return response()->streamDownload(
            static function () use ($pdf) {
                echo $pdf->output();
            },
            'department-report.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="department-report.pdf"',
                'X-Report-History-Id' => (string) ($history->id ?? ''),
            ]
        );
    }

    public function exportMonthlyPdf(Request $request)
    {
        $this->authorizeRole($request, ['Admin']);

        if (! class_exists(\Dompdf\Dompdf::class)) {
            return response()->json([
                'message' => 'PDF export is not available on this server.',
                'error' => 'Missing dependency: barryvdh/laravel-dompdf. Run composer install on the backend.',
            ], 500);
        }

        $query = $this->buildAnalyticsQuery($request);

        $monthlyTrend = (clone $query)
            ->select(
                DB::raw("DATE_FORMAT(documents.date, '%Y-%m-01') as month"),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(documents.amount) as sum_amount')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper')->loadView('exports.monthly', [
            'title' => 'City Hall Monitoring System — Monthly Report',
            'generatedAt' => now(),
            'rows' => $monthlyTrend,
            'filters' => $this->collectFilters($request),
        ])->setPaper('a4', 'landscape');

        $history = $this->logHistory($request, 'monthly', 'pdf');

        return response()->streamDownload(
            static function () use ($pdf) {
                echo $pdf->output();
            },
            'monthly-report.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="monthly-report.pdf"',
                'X-Report-History-Id' => (string) ($history->id ?? ''),
            ]
        );
    }

    /**
     * Shared base query for analytics endpoints.
     */
    protected function buildAnalyticsQuery(Request $request)
    {
        $query = Document::query();

        if ($from = $request->get('date_from')) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('date', '<=', $to);
        }

        $departmentId = $request->get('routed_department_id') ?? $request->get('department_id');
        if ($departmentId) {
            $query->where('routed_department_id', $departmentId);
        }

        if ($requestedBy = $request->get('requested_by')) {
            $query->where('encoded_by_id', $requestedBy);
        }

        if ($category = $request->get('category')) {
            $query->where('type_of_document', $category);
        }

        $statusGroups = $request->input('status_groups', []);
        $statusList = $this->statusesForGroups((array) $statusGroups);
        if (! empty($statusList)) {
            $query->whereIn('status', $statusList);
        }

        return $query;
    }

    /**
     * Map grouped filter values to concrete statuses in the documents table.
     *
     * @param  array<int, string>  $groups
     * @return array<int, string>
     */
    protected function statusesForGroups(array $groups): array
    {
        $map = $this->statusGroupMap();

        $selected = [];
        foreach ($groups as $group) {
            if (isset($map[$group])) {
                $selected = array_merge($selected, $map[$group]);
            }
        }

        return array_values(array_unique($selected));
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function statusGroupMap(): array
    {
        return [
            'In Progress' => ['For Initial', 'For Schedule'],
            'Completed' => ['Signed', 'Filed'],
            'On Hold' => ['Hold', 'Returned'],
        ];
    }

    protected function collectFilters(Request $request): array
    {
        return [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'routed_department_id' => $request->get('routed_department_id') ?? $request->get('department_id'),
            'status_groups' => $request->input('status_groups', []),
            'requested_by' => $request->get('requested_by'),
            'category' => $request->get('category'),
        ];
    }

    protected function logHistory(Request $request, string $type, string $format): ReportHistory
    {
        $user = $request->user();

        return ReportHistory::create([
            'type' => $type,
            'format' => $format,
            'filters' => $this->collectFilters($request),
            'generated_by_id' => $user?->id ?? User::query()->first()?->id ?? 1,
        ]);
    }

    protected function authorizeRole(Request $request, array $roles): void
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'You are not authorized to perform this action.');
        }
    }
}

