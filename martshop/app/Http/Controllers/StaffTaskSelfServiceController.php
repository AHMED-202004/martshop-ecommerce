<?php

namespace App\Http\Controllers;

use App\Enums\StaffTaskStatus;
use App\Http\Requests\UpdateOwnStaffTaskRequest;
use App\Models\StaffTask;
use App\Services\StaffTaskService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffTaskSelfServiceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('staff-tasks.view-own'), 403);
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(StaffTaskStatus::class)],
            'overdue' => ['nullable', Rule::in(['1'])],
        ]);
        $closedStatuses = [StaffTaskStatus::Completed->value, StaffTaskStatus::Cancelled->value];
        $tasks = StaffTask::query()
            ->select(['id', 'title', 'description', 'task_type', 'priority', 'assigned_to', 'assigned_by', 'related_id', 'due_at', 'started_at', 'completed_at', 'status', 'version', 'created_at'])
            ->where('assigned_to', $request->user()->getKey())
            ->when($filters['status'] ?? null, fn ($tasks, string $status) => $tasks->where('status', $status))
            ->when(($filters['overdue'] ?? null) === '1', fn ($tasks) => $tasks
                ->whereNotIn('status', $closedStatuses)
                ->where('due_at', '<', now()))
            ->with('assigner:id,name')
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->appends($filters);
        $workLocations = $request->user()->staffLocations()
            ->select(['locations.id', 'locations.name'])
            ->orderBy('locations.name')
            ->get();
        $workSchedule = $request->user()->staffWorkSchedules()
            ->select(['id', 'user_id', 'day_of_week', 'is_working', 'starts_at', 'ends_at', 'timezone'])
            ->orderBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');
        $taskCounts = StaffTask::query()
            ->where('assigned_to', $request->user()->getKey())
            ->selectRaw('SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) AS open_count', $closedStatuses)
            ->selectRaw('SUM(CASE WHEN status NOT IN (?, ?) AND due_at < ? THEN 1 ELSE 0 END) AS overdue_count', [...$closedStatuses, now()])
            ->selectRaw('SUM(CASE WHEN status = ? AND completed_at >= ? THEN 1 ELSE 0 END) AS completed_today_count', [StaffTaskStatus::Completed->value, today()])
            ->first();

        return view('staff-tasks.index', [
            'tasks' => $tasks,
            'statuses' => StaffTaskStatus::cases(),
            'filters' => $filters,
            'workLocations' => $workLocations,
            'workSchedule' => $workSchedule,
            'scheduleTimezone' => $workSchedule->first()?->timezone,
            'taskSummary' => [
                'open' => (int) ($taskCounts?->open_count ?? 0),
                'overdue' => (int) ($taskCounts?->overdue_count ?? 0),
                'completed_today' => (int) ($taskCounts?->completed_today_count ?? 0),
            ],
        ]);
    }

    public function update(UpdateOwnStaffTaskRequest $request, string $task, StaffTaskService $tasks)
    {
        $tasks->updateOwnStatus(
            $request->user(),
            (int) $task,
            StaffTaskStatus::from($request->validated('status')),
            $request->integer('expected_version'),
            $request->validated('progress_note'),
        );

        return back()->with('success', 'تم تحديث حالة المهمة.');
    }
}
