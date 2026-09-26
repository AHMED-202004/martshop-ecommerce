<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Enums\StaffTaskStatus;
use App\Models\StaffDepartment;
use App\Models\StaffTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManagedStaffDepartmentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('staff-departments.view-managed'), 403);
        $filters = $request->validate([
            'overdue' => ['nullable', Rule::in(['1'])],
        ]);
        $closed = [StaffTaskStatus::Completed->value, StaffTaskStatus::Cancelled->value];
        $operationalStaff = static fn (Builder $users) => $users
            ->whereHas('roles', fn (Builder $roles) => $roles
                ->whereIn('slug', ['admin', 'delivery-worker'])
                ->orWhereHas('permissions'))
            ->orWhereHas('directPermissions');
        $departments = StaffDepartment::query()
            ->select(['id', 'name', 'slug', 'is_active', 'manager_id'])
            ->where('manager_id', $request->user()->getKey())
            ->with(['staff' => fn ($staff) => $staff
                ->select(['id', 'name', 'employee_number', 'job_title', 'staff_department_id', 'account_status'])
                ->where($operationalStaff)
                ->withCount([
                    'assignedStaffTasks as active_task_count' => fn (Builder $tasks) => $tasks->whereNotIn('status', $closed),
                    'assignedStaffTasks as overdue_task_count' => fn (Builder $tasks) => $tasks
                        ->whereNotIn('status', $closed)
                        ->where('due_at', '<', now()),
                    'deliveryAssignments as deliveries_assigned_month_count' => fn (Builder $deliveries) => $deliveries
                        ->where('assigned_at', '>=', now()->startOfMonth()),
                    'deliveryAssignments as deliveries_delivered_month_count' => fn (Builder $deliveries) => $deliveries
                        ->where('assigned_at', '>=', now()->startOfMonth())
                        ->where('status', DeliveryStatus::Delivered->value),
                    'deliveryAssignments as active_delivery_count' => fn (Builder $deliveries) => $deliveries
                        ->whereNotIn('status', collect(DeliveryStatus::cases())->filter->isTerminal()->map->value),
                ])
                ->orderBy('name')])
            ->orderBy('name')
            ->get();
        $openTaskQuery = StaffTask::query()
            ->whereHas('assignee', fn (Builder $users) => $users
                ->whereHas('staffDepartment', fn (Builder $managed) => $managed
                    ->where('manager_id', $request->user()->getKey()))
                ->where($operationalStaff))
            ->whereNotIn('status', $closed)
            ->when(($filters['overdue'] ?? null) === '1', fn (Builder $tasks) => $tasks
                ->where('due_at', '<', now()));
        $openTaskTotal = (clone $openTaskQuery)->count();
        $openTasks = $openTaskQuery
            ->select(['id', 'title', 'task_type', 'priority', 'assigned_to', 'due_at', 'status', 'created_at'])
            ->with('assignee:id,name,employee_number')
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('staff-departments.managed', compact('departments', 'openTasks', 'openTaskTotal', 'filters'));
    }
}
