<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffTaskPriority;
use App\Enums\StaffTaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffTaskRequest;
use App\Http\Requests\Admin\UpdateStaffTaskRequest;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\StaffTaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffTaskController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(StaffTaskStatus::class)],
            'priority' => ['nullable', Rule::enum(StaffTaskPriority::class)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'overdue' => ['nullable', Rule::in(['1'])],
        ]);
        $closed = [StaffTaskStatus::Completed->value, StaffTaskStatus::Cancelled->value];
        $tasks = StaffTask::query()
            ->select(['id', 'title', 'task_type', 'priority', 'assigned_to', 'assigned_by', 'due_at', 'started_at', 'completed_at', 'status', 'created_at'])
            ->when($filters['q'] ?? null, function (Builder $tasks, string $search) {
                $needle = '%'.$this->escapeLike($search).'%';
                $tasks->whereRaw("title LIKE ? ESCAPE '!'", [$needle]);
            })
            ->when($filters['status'] ?? null, fn (Builder $tasks, string $status) => $tasks->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $tasks, string $priority) => $tasks->where('priority', $priority))
            ->when($filters['assigned_to'] ?? null, fn (Builder $tasks, int|string $staff) => $tasks->where('assigned_to', $staff))
            ->when(($filters['overdue'] ?? null) === '1', fn (Builder $tasks) => $tasks
                ->whereNotIn('status', $closed)->where('due_at', '<', now()))
            ->with(['assignee:id,name,employee_number', 'assigner:id,name'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->paginate(40)
            ->appends($filters);
        $assignees = User::query()
            ->select(['id', 'name', 'employee_number'])
            ->whereHas('assignedStaffTasks')
            ->orderBy('name')
            ->get();

        return view('admin.staff.tasks.index', [
            'tasks' => $tasks,
            'assignees' => $assignees,
            'statuses' => StaffTaskStatus::cases(),
            'priorities' => StaffTaskPriority::cases(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreStaffTaskRequest $request, string $staff, StaffTaskService $tasks)
    {
        $tasks->create(
            $request->user(),
            (int) $staff,
            $request->validated('title'),
            $request->validated('description'),
            $request->validated('task_type'),
            StaffTaskPriority::from($request->validated('priority')),
            $request->validated('due_at'),
            $request->validated('notes'),
            $request->integer('related_id') ?: null,
            $request->validated('reason'),
        );

        return redirect()->route('admin.staff.show', $staff)->with('success', 'تم إنشاء المهمة وإسنادها.');
    }

    public function update(UpdateStaffTaskRequest $request, string $task, StaffTaskService $tasks)
    {
        $updated = $tasks->update(
            $request->user(),
            (int) $task,
            $request->integer('assigned_to'),
            StaffTaskPriority::from($request->validated('priority')),
            $request->validated('due_at'),
            StaffTaskStatus::from($request->validated('status')),
            $request->integer('expected_version'),
            $request->validated('reason'),
        );

        return redirect()->route('admin.staff.show', $updated->assigned_to)
            ->with('success', 'تم تحديث المهمة وتسجيل الإجراء.');
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($value));
    }
}
