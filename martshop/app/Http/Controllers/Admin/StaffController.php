<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\DeliveryStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\StaffTaskPriority;
use App\Enums\StaffTaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeStaffStatusRequest;
use App\Http\Requests\Admin\ResendStaffInvitationRequest;
use App\Http\Requests\Admin\RevokeStaffSessionsRequest;
use App\Http\Requests\Admin\StoreStaffInvitationRequest;
use App\Http\Requests\Admin\TerminateStaffRequest;
use App\Http\Requests\Admin\UpdateStaffEmailRequest;
use App\Http\Requests\Admin\UpdateStaffPermissionsRequest;
use App\Http\Requests\Admin\UpdateStaffProfileRequest;
use App\Http\Requests\Admin\UpdateStaffRolesRequest;
use App\Http\Requests\Admin\UpdateStaffScopeScheduleRequest;
use App\Http\Requests\Admin\BulkUpdateStaffRolesRequest;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\ContactMessage;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffDepartment;
use App\Models\StaffNote;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\DeliveryPerformanceService;
use App\Services\SupportPerformanceService;
use App\Services\ProductModeratorPerformanceService;
use App\Services\FinancePerformanceService;
use App\Services\PasswordRecoveryService;
use App\Services\StaffInvitationService;
use App\Services\StaffProfileService;
use App\Services\StaffScopeScheduleService;
use App\Services\StaffSecurityService;
use App\Services\BulkStaffRoleService;
use App\Services\StaffTerminationReassignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function create(Request $request, PasswordRecoveryService $passwordRecovery)
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);

        $roles = Role::query()
            ->select(['id', 'name', 'slug'])
            ->where(fn (Builder $query) => $query
                ->whereIn('slug', ['admin', 'delivery-worker'])
                ->orWhereHas('permissions'))
            ->orderBy('name')
            ->get();
        $permissions = Permission::query()
            ->select(['id', 'name', 'slug', 'group'])
            ->orderBy('group')
            ->orderBy('name')
            ->get();
        $departments = StaffDepartment::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.staff.create', [
            'roles' => $roles,
            'permissions' => $permissions,
            'departments' => $departments,
            'mailReady' => $passwordRecovery->mailReady(),
        ]);
    }

    public function store(StoreStaffInvitationRequest $request, StaffInvitationService $invitations)
    {
        $result = $invitations->invite(
            $request->user(),
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('job_title'),
            $request->integer('staff_department_id') ?: null,
            $request->validated('role_ids'),
            $request->validated('permission_ids'),
            $request->validated('reason'),
        );

        $redirect = redirect()->route('admin.staff.show', $result['user']);
        if (! $result['delivered']) {
            return $redirect->withErrors([
                'email' => 'تم إنشاء الحساب، لكن تعذر إرسال الدعوة ولم يبقَ رابط صالح. استخدم إعادة الإرسال بعد إصلاح البريد.',
            ]);
        }

        return $redirect->with('success', 'تم إنشاء حساب الموظف وإرسال دعوة اختيار كلمة المرور.');
    }

    public function resendInvitation(
        ResendStaffInvitationRequest $request,
        string $staff,
        StaffInvitationService $invitations,
    ) {
        $delivered = $invitations->resend(
            $request->user(),
            (int) $staff,
            $request->validated('reason'),
        );

        $redirect = redirect()->route('admin.staff.show', $staff);
        if (! $delivered) {
            return $redirect->withErrors([
                'email' => 'تعذر إرسال الدعوة ولم يبقَ رابط صالح. حاول بعد إصلاح نقل البريد.',
            ]);
        }

        return $redirect->with('success', 'تم إبطال الرابط السابق وإرسال دعوة جديدة.');
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:100', Rule::exists('roles', 'slug')],
            'status' => ['nullable', Rule::enum(AccountStatus::class)],
            'department' => ['nullable', 'integer', Rule::exists('staff_departments', 'id')],
            'sort' => ['nullable', Rule::in(['name', 'employee_number', 'newest'])],
        ]);

        $staff = User::query()
            ->select(['id', 'name', 'email', 'employee_number', 'job_title', 'staff_department_id', 'account_status', 'created_at'])
            ->where(fn (Builder $users) => $this->operationalStaff($users))
            ->when($filters['role'] ?? null, fn (Builder $users, string $role) => $users
                ->whereHas('roles', fn (Builder $roles) => $roles->where('slug', $role)))
            ->when($filters['status'] ?? null, fn (Builder $users, string $status) => $users
                ->where('account_status', $status))
            ->when($filters['department'] ?? null, fn (Builder $users, int|string $department) => $users
                ->where('staff_department_id', $department))
            ->when($filters['q'] ?? null, function (Builder $users, string $search) {
                $needle = '%'.$this->escapeLike($search).'%';
                $users->where(fn (Builder $query) => $query
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("employee_number LIKE ? ESCAPE '!'", [$needle]));
            })
            ->with([
                'roles' => fn ($roles) => $roles->select(['roles.id', 'roles.name', 'roles.slug'])->orderBy('roles.name'),
                'staffDepartment:id,name',
                'staffLocations' => fn ($locations) => $locations
                    ->select(['locations.id', 'locations.name', 'locations.slug'])
                    ->orderBy('locations.name'),
                'staffWorkSchedules' => fn ($schedule) => $schedule->orderBy('day_of_week'),
            ])
            ->withCount(['assignedStaffTasks as active_tasks_count' => fn (Builder $tasks) => $tasks
                ->whereNotIn('status', [StaffTaskStatus::Completed->value, StaffTaskStatus::Cancelled->value])])
            ->when(($filters['sort'] ?? 'name') === 'newest', fn (Builder $users) => $users->orderByDesc('created_at'))
            ->when(($filters['sort'] ?? 'name') === 'employee_number', fn (Builder $users) => $users->orderBy('employee_number'))
            ->when(($filters['sort'] ?? 'name') === 'name', fn (Builder $users) => $users->orderBy('name'))
            ->orderBy('id')
            ->paginate(25)
            ->appends($filters);

        $roles = Role::query()
            ->select(['id', 'name', 'slug'])
            ->where(fn (Builder $query) => $query
                ->whereIn('slug', ['admin', 'delivery-worker'])
                ->orWhereHas('permissions'))
            ->orderBy('name')
            ->get();
        $statuses = AccountStatus::cases();
        $departments = StaffDepartment::query()->orderBy('name')->get(['id', 'name', 'is_active']);
        $bulkRoles = Role::query()
            ->where('slug', '!=', 'admin')->whereHas('permissions')
            ->whereDoesntHave('permissions', fn (Builder $permissions) => $permissions->where(fn (Builder $sensitive) => $sensitive
                ->where('slug', 'like', 'roles.%')->orWhere('slug', 'like', 'payments.%')
                ->orWhere('slug', 'like', 'withdrawals.%')->orWhere('slug', 'like', 'settings.%')->orWhere('slug', 'like', 'audit.%')))
            ->orderBy('name')->get(['id', 'name', 'slug']);

        return view('admin.staff.index', compact('staff', 'roles', 'bulkRoles', 'statuses', 'departments', 'filters'));
    }

    public function show(Request $request, string $staff, DeliveryPerformanceService $deliveryPerformance, SupportPerformanceService $supportPerformance, ProductModeratorPerformanceService $productModeratorPerformance, FinancePerformanceService $financePerformance)
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);
        $showFilters = $request->validate([
            'performance_period' => ['nullable', Rule::in(['today', 'yesterday', 'week', 'month', 'custom'])],
            'performance_from' => ['nullable', 'required_if:performance_period,custom', 'date_format:Y-m-d'],
            'performance_to' => ['nullable', 'required_if:performance_period,custom', 'date_format:Y-m-d', 'after_or_equal:performance_from'],
            'delivery_area' => ['nullable', 'string', 'max:120'],
            'delivery_status' => ['nullable', Rule::enum(DeliveryStatus::class)],
            'compare_staff' => ['nullable', 'integer', 'min:1'],
        ]);

        $employee = User::query()
            ->select(['id', 'name', 'email', 'phone', 'employee_number', 'job_title', 'staff_department_id', 'account_status', 'account_status_changed_at', 'account_status_changed_by', 'last_login_at', 'login_count', 'failed_login_count', 'last_failed_login_at', 'password_changed_at', 'created_at', 'updated_at'])
            ->where(fn (Builder $users) => $this->operationalStaff($users))
            ->with(['roles' => fn ($roles) => $roles
                ->select(['roles.id', 'roles.name', 'roles.slug'])
                ->with(['permissions' => fn ($permissions) => $permissions
                    ->select(['permissions.id', 'permissions.name', 'permissions.slug', 'permissions.group'])
                    ->orderBy('permissions.group')
                    ->orderBy('permissions.name')])
                ->orderBy('roles.name'),
                'directPermissions' => fn ($permissions) => $permissions
                    ->select(['permissions.id', 'permissions.name', 'permissions.slug', 'permissions.group'])
                    ->orderBy('permissions.group')
                    ->orderBy('permissions.name'),
                'staffDepartment:id,name',
            ])
            ->findOrFail($staff);

        $rolePermissions = $employee->roles
            ->flatMap(fn (Role $role) => $role->permissions->map(fn ($permission) => [
                'slug' => $permission->slug,
                'name' => $permission->name,
                'group' => $permission->group,
                'source' => $role->name,
            ]));
        $directPermissions = $employee->directPermissions->map(fn (Permission $permission) => [
            'slug' => $permission->slug,
            'name' => $permission->name,
            'group' => $permission->group,
            'source' => 'مباشرة',
        ]);
        $effectivePermissions = $rolePermissions
            ->concat($directPermissions)
            ->groupBy('slug')
            ->map(fn ($permissions) => [
                'slug' => $permissions->first()['slug'],
                'name' => $permissions->first()['name'],
                'group' => $permissions->first()['group'],
                'sources' => $permissions->pluck('source')->unique()->sort()->values(),
            ])
            ->sortBy([['group', 'asc'], ['name', 'asc']])
            ->values();

        $sessionSummary = ['active_count' => null, 'last_active_at' => null];
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            $sessions = DB::table('sessions')->where('user_id', $employee->id);
            $lastActivity = (clone $sessions)->max('last_activity');
            $sessionSummary = [
                'active_count' => (clone $sessions)
                    ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)
                    ->count(),
                'last_active_at' => $lastActivity ? Carbon::createFromTimestampUTC((int) $lastActivity) : null,
            ];
        }
        $activeDeliveryCount = Delivery::query()
            ->where('delivery_worker_id', $employee->id)
            ->whereNotIn('status', collect(DeliveryStatus::cases())->filter->isTerminal()->map->value)
            ->count();

        $securityEvents = AuditLog::query()
            ->select(['id', 'actor_id', 'action', 'reason', 'created_at'])
            ->where('subject_type', $employee->getMorphClass())
            ->where('subject_id', $employee->id)
            ->whereIn('action', ['staff.invited', 'staff.invitation_resent', 'staff.invitation_delivery_failed', 'staff.invitation_accepted', 'staff.terminated', 'staff.sessions_revoked', 'staff.account_status_changed', 'staff.operational_roles_changed', 'staff.direct_permissions_changed', 'staff.profile_updated', 'staff.email_changed', 'staff.scope_schedule_updated'])
            ->with(['actor:id,name'])
            ->latest('id')
            ->limit(50)
            ->get();
        $staffCreator = AuditLog::query()
            ->select(['id', 'actor_id', 'created_at'])
            ->where('subject_type', $employee->getMorphClass())
            ->where('subject_id', $employee->id)
            ->where('action', 'staff.invited')
            ->with('actor:id,name')
            ->oldest('id')
            ->first();
        $activityEvents = AuditLog::query()
            ->select(['id', 'action', 'subject_type', 'subject_id', 'created_at'])
            ->where('actor_id', $employee->id)
            ->latest('id')
            ->limit(50)
            ->get();
        $permissionHistory = AuditLog::query()
            ->select(['id', 'actor_id', 'action', 'before', 'after', 'reason', 'created_at'])
            ->where('subject_type', $employee->getMorphClass())
            ->where('subject_id', $employee->id)
            ->whereIn('action', ['staff.operational_roles_changed', 'staff.direct_permissions_changed', 'staff.profile_updated', 'staff.account_status_changed', 'staff.scope_schedule_updated'])
            ->with('actor:id,name')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (AuditLog $event) => [
                'id' => $event->id,
                'created_at' => $event->created_at,
                'action' => $event->action,
                'actor_name' => $event->actor?->name ?? 'النظام',
                'reason' => $event->reason,
                'summary' => $this->staffHistorySummary($event),
            ]);
        $manageableRoles = Role::query()
            ->select(['id', 'name', 'slug'])
            ->where(fn (Builder $roles) => $roles
                ->whereIn('slug', ['admin', 'delivery-worker'])
                ->orWhereHas('permissions'))
            ->orderBy('name')
            ->get();
        $availablePermissions = Permission::query()
            ->select(['id', 'name', 'slug', 'group'])
            ->orderBy('group')
            ->orderBy('name')
            ->get();
        $availableDepartments = StaffDepartment::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $availableLocations = Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
        $workSchedule = $employee->staffWorkSchedules->keyBy('day_of_week');
        $scheduleTimezone = $employee->staffWorkSchedules->first()?->timezone ?? config('app.timezone');
        $staffTasks = StaffTask::query()
            ->where('assigned_to', $employee->id)
            ->with('assigner:id,name')
            ->latest('id')
            ->limit(50)
            ->get();
        $closedTaskStatuses = [StaffTaskStatus::Completed->value, StaffTaskStatus::Cancelled->value];
        $taskWorkload = [
            'active' => StaffTask::query()->where('assigned_to', $employee->id)->whereNotIn('status', $closedTaskStatuses)->count(),
            'overdue' => StaffTask::query()->where('assigned_to', $employee->id)->whereNotIn('status', $closedTaskStatuses)->where('due_at', '<', now())->count(),
            'completed_today' => StaffTask::query()->where('assigned_to', $employee->id)->where('status', StaffTaskStatus::Completed->value)->whereDate('completed_at', today())->count(),
            'completed_week' => StaffTask::query()->where('assigned_to', $employee->id)->where('status', StaffTaskStatus::Completed->value)->where('completed_at', '>=', now()->startOfWeek())->count(),
        ];
        $terminationSummary = [
            'active_tasks' => $taskWorkload['active'],
            'active_chats' => ContactMessage::query()->where('assigned_to', $employee->id)
                ->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value])->count(),
            'active_deliveries' => $activeDeliveryCount,
            'sensitive_role' => $employee->hasRole('admin') || $effectivePermissions->contains(fn ($permission) => preg_match('/^(roles|payments|withdrawals|settings|audit)\./', $permission['slug'])),
            'active_sessions' => $sessionSummary['active_count'],
        ];
        $taskAssignees = User::query()
            ->select(['id', 'name', 'employee_number'])
            ->where('account_status', AccountStatus::Active->value)
            ->where(fn (Builder $staff) => $this->operationalStaff($staff))
            ->orderBy('name')
            ->get();
        $taskPriorities = StaffTaskPriority::cases();
        $taskStatuses = StaffTaskStatus::cases();
        $canViewStaffNotes = $request->user()->hasPermission('staff-notes.view');
        $canManageStaffNotes = $request->user()->hasPermission('staff-notes.manage');
        $staffNotes = $canViewStaffNotes
            ? StaffNote::query()
                ->select(['id', 'staff_id', 'author_id', 'body', 'created_at'])
                ->where('staff_id', $employee->id)
                ->with('author:id,name')
                ->latest('id')
                ->limit(50)
                ->get()
            : collect();
        $deliveryPerformanceSummary = $employee->roles->contains('slug', 'delivery-worker')
            ? $deliveryPerformance->summary(
                $request->user(),
                $employee,
                $showFilters['performance_period'] ?? 'week',
                $showFilters['performance_from'] ?? null,
                $showFilters['performance_to'] ?? null,
                $showFilters['delivery_area'] ?? null,
                isset($showFilters['delivery_status']) ? DeliveryStatus::from($showFilters['delivery_status']) : null,
            )
            : null;
        $supportPerformanceSummary = $employee->hasPermission('contact-messages.manage')
            ? $supportPerformance->summary(
                $request->user(),
                $employee,
                $showFilters['performance_period'] ?? 'week',
                $showFilters['performance_from'] ?? null,
                $showFilters['performance_to'] ?? null,
            )
            : null;
        $productModeratorPerformanceSummary = $employee->hasPermission('products.moderate')
            ? $productModeratorPerformance->summary($request->user(), $employee, $showFilters['performance_period'] ?? 'week', $showFilters['performance_from'] ?? null, $showFilters['performance_to'] ?? null)
            : null;
        $financePerformanceSummary = $employee->hasPermission('payments.verify')
            ? $financePerformance->summary($request->user(), $employee, $showFilters['performance_period'] ?? 'week', $showFilters['performance_from'] ?? null, $showFilters['performance_to'] ?? null)
            : null;

        $performanceType = $deliveryPerformanceSummary ? 'delivery'
            : ($supportPerformanceSummary ? 'support'
                : ($productModeratorPerformanceSummary ? 'catalog' : ($financePerformanceSummary ? 'finance' : null)));
        $comparisonPeers = collect();
        $performanceComparison = null;
        if ($performanceType && $employee->staff_department_id) {
            $comparisonPeers = User::query()
                ->select(['id', 'name', 'employee_number', 'staff_department_id', 'account_status'])
                ->where('staff_department_id', $employee->staff_department_id)
                ->whereKeyNot($employee->id)
                ->where(fn (Builder $users) => $this->operationalStaff($users))
                ->with(['roles:id,name,slug', 'directPermissions:id,name,slug,group', 'roles.permissions:id,name,slug,group'])
                ->orderBy('name')->get()
                ->filter(fn (User $peer) => match ($performanceType) {
                    'delivery' => $peer->hasRole('delivery-worker'),
                    'support' => $peer->hasPermission('contact-messages.manage'),
                    'catalog' => $peer->hasPermission('products.moderate'),
                    'finance' => $peer->hasPermission('payments.verify'),
                })->values();
        }
        if (isset($showFilters['compare_staff'])) {
            $peer = $comparisonPeers->firstWhere('id', (int) $showFilters['compare_staff']);
            if (! $peer) throw ValidationException::withMessages(['compare_staff' => 'المقارنة متاحة فقط مع موظف من القسم ونوع العمل نفسيهما.']);
            $args = [$request->user(), $peer, $showFilters['performance_period'] ?? 'week', $showFilters['performance_from'] ?? null, $showFilters['performance_to'] ?? null];
            $peerSummary = match ($performanceType) {
                'delivery' => $deliveryPerformance->summary(...[...$args, $showFilters['delivery_area'] ?? null, isset($showFilters['delivery_status']) ? DeliveryStatus::from($showFilters['delivery_status']) : null]),
                'support' => $supportPerformance->summary(...$args),
                'catalog' => $productModeratorPerformance->summary(...$args),
                'finance' => $financePerformance->summary(...$args),
            };
            $primarySummary = match ($performanceType) {
                'delivery' => $deliveryPerformanceSummary, 'support' => $supportPerformanceSummary,
                'catalog' => $productModeratorPerformanceSummary, 'finance' => $financePerformanceSummary,
            };
            $metrics = match ($performanceType) {
                'delivery' => ['assigned' => 'المسندة', 'delivered' => 'المسلّمة', 'completion_rate' => 'نسبة الإكمال %', 'on_time_rate' => 'ضمن الوقت %', 'average_rating' => 'متوسط التقييم', 'score' => 'المؤشر الداخلي'],
                'support' => ['assigned' => 'المسندة', 'handled' => 'المعالجة', 'resolved' => 'المحلولة', 'sla_breaches' => 'تجاوزات SLA', 'average_first_response_minutes' => 'متوسط الاستجابة بالدقائق'],
                'catalog' => ['reviewed' => 'المراجعة', 'approved' => 'المقبولة', 'rejected' => 'المرفوضة', 'changes_requested' => 'طلبات التعديل', 'average_review_minutes' => 'متوسط المراجعة بالدقائق'],
                'finance' => ['reviewed' => 'المراجعة', 'accepted' => 'المقبولة', 'rejected' => 'المرفوضة', 'reconciliation_discrepancies' => 'فروقات المطابقة', 'average_review_minutes' => 'متوسط المراجعة بالدقائق'],
            };
            $performanceComparison = compact('peer', 'peerSummary', 'primarySummary', 'metrics', 'performanceType');
        }

        return view('admin.staff.show', compact('employee', 'effectivePermissions', 'sessionSummary', 'activeDeliveryCount', 'securityEvents', 'staffCreator', 'activityEvents', 'permissionHistory', 'manageableRoles', 'availablePermissions', 'availableDepartments', 'availableLocations', 'workSchedule', 'scheduleTimezone', 'staffTasks', 'taskWorkload', 'taskAssignees', 'taskPriorities', 'taskStatuses', 'staffNotes', 'canViewStaffNotes', 'canManageStaffNotes', 'deliveryPerformanceSummary', 'supportPerformanceSummary', 'productModeratorPerformanceSummary', 'financePerformanceSummary', 'comparisonPeers', 'performanceComparison', 'terminationSummary'));
    }

    public function revokeSessions(
        RevokeStaffSessionsRequest $request,
        string $staff,
        StaffSecurityService $security,
    ) {
        $revoked = $security->revokeSessions(
            $request->user(),
            (int) $staff,
            $request->validated('reason'),
        );

        return redirect()->route('admin.staff.show', $staff)
            ->with('success', "تم إنهاء {$revoked} جلسة وتدوير رمز تذكر الدخول.");
    }

    public function changeStatus(
        ChangeStaffStatusRequest $request,
        string $staff,
        StaffSecurityService $security,
    ) {
        $status = AccountStatus::from($request->validated('status'));
        $revoked = $security->changeStatus(
            $request->user(),
            (int) $staff,
            $status,
            $request->validated('reason'),
        );

        $message = match ($status) {
            AccountStatus::Suspended => "تم تعليق الحساب وإنهاء {$revoked} جلسة.",
            AccountStatus::OnLeave => "تم وضع الحساب في إجازة وإنهاء {$revoked} جلسة.",
            default => 'تمت إعادة تفعيل الحساب.',
        };

        return redirect()->route('admin.staff.show', $staff)->with('success', $message);
    }

    public function updateRoles(
        UpdateStaffRolesRequest $request,
        string $staff,
        StaffSecurityService $security,
    ) {
        $revoked = $security->updateOperationalRoles(
            $request->user(),
            (int) $staff,
            $request->validated('role_ids'),
            $request->validated('reason'),
        );

        return redirect()->route('admin.staff.show', $staff)
            ->with('success', "تم تحديث الأدوار وإنهاء {$revoked} جلسة.");
    }

    public function bulkUpdateRoles(BulkUpdateStaffRolesRequest $request, BulkStaffRoleService $service)
    {
        $revoked = $service->update(
            $request->user(), $request->validated('staff_ids'),
            $request->validated('role_ids'), $request->validated('reason'),
        );
        return back()->with('success', "تم تحديث أدوار الموظفين المحددين وإنهاء {$revoked} جلسة.");
    }

    public function updatePermissions(
        UpdateStaffPermissionsRequest $request,
        string $staff,
        StaffSecurityService $security,
    ) {
        $revoked = $security->updateDirectPermissions(
            $request->user(),
            (int) $staff,
            $request->validated('permission_ids'),
            $request->validated('reason'),
        );

        return redirect()->route('admin.staff.show', $staff)
            ->with('success', "تم تحديث الصلاحيات المباشرة وإنهاء {$revoked} جلسة.");
    }

    public function updateProfile(
        UpdateStaffProfileRequest $request,
        string $staff,
        StaffProfileService $profiles,
    ) {
        $profiles->update(
            $request->user(),
            (int) $staff,
            $request->validated('name'),
            $request->validated('phone'),
            $request->validated('job_title'),
            $request->integer('staff_department_id') ?: null,
            $request->validated('reason'),
        );

        return redirect()->route('admin.staff.show', $staff)
            ->with('success', 'تم تحديث بيانات الموظف وتسجيل التغيير.');
    }

    public function updateEmail(
        UpdateStaffEmailRequest $request,
        string $staff,
        StaffSecurityService $security,
    ) {
        $revoked = $security->updateEmail(
            $request->user(),
            (int) $staff,
            $request->validated('email'),
            $request->validated('reason'),
        );

        return redirect()->route('admin.staff.show', $staff)
            ->with('success', "تم تغيير البريد وإنهاء {$revoked} جلسة وإبطال روابط الاستعادة السابقة.");
    }

    public function updateScopeSchedule(
        UpdateStaffScopeScheduleRequest $request,
        string $staff,
        StaffScopeScheduleService $scopeSchedule,
    ) {
        $scopeSchedule->update(
            $request->user(),
            (int) $staff,
            $request->validated('location_ids'),
            $request->validated('schedule'),
            $request->validated('timezone'),
            $request->validated('reason'),
        );

        return redirect()->route('admin.staff.show', $staff)
            ->with('success', 'تم تحديث نطاق المواقع وجدول الدوام وتسجيل التغيير.');
    }

    public function terminate(
        TerminateStaffRequest $request,
        string $staff,
        StaffSecurityService $security,
        StaffTerminationReassignmentService $reassignment,
    ) {
        $revoked = DB::transaction(function () use ($request, $staff, $security, $reassignment) {
            $employee = User::query()->whereKey((int) $staff)->lockForUpdate()->firstOrFail();
            $reassignment->redistribute($request->user(), $employee, $request->validated(), $request->validated('reason'));
            return $security->terminate($request->user(), (int) $staff, $request->validated('confirmation'), $request->validated('reason'));
        }, 3);

        return redirect()->route('admin.staff.show', $staff)
            ->with('success', "تم إنهاء الخدمة وإنهاء {$revoked} جلسة مع حفظ السجل التاريخي.");
    }

    private function operationalStaff(Builder $users): Builder
    {
        return $users->where(fn (Builder $staff) => $staff
            ->whereHas('roles', fn (Builder $roles) => $roles
                ->whereIn('slug', ['admin', 'delivery-worker'])
                ->orWhereHas('permissions'))
            ->orWhereHas('directPermissions'));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($value));
    }

    /** @return list<string> */
    private function staffHistorySummary(AuditLog $event): array
    {
        if ($event->action === 'staff.operational_roles_changed') {
            return $this->listChanges('دور', $event->before['roles'] ?? [], $event->after['roles'] ?? []);
        }
        if ($event->action === 'staff.direct_permissions_changed') {
            return $this->listChanges('صلاحية', $event->before['permissions'] ?? [], $event->after['permissions'] ?? []);
        }
        if ($event->action === 'staff.account_status_changed') {
            return ['الحالة: '.($event->before['account_status'] ?? '—').' ← '.($event->after['account_status'] ?? '—')];
        }
        if ($event->action === 'staff.scope_schedule_updated') {
            $locations = count($event->after['location_ids'] ?? []);
            $workingDays = collect($event->after['schedule'] ?? [])->where('is_working', true)->count();

            return ["نطاق المواقع: {$locations}", "أيام العمل الأسبوعية: {$workingDays}"];
        }

        $summary = [];
        foreach (['name' => 'الاسم', 'job_title' => 'المسمى', 'staff_department_id' => 'القسم'] as $field => $label) {
            if (($event->before[$field] ?? null) !== ($event->after[$field] ?? null)) {
                $summary[] = $label.': '.($event->before[$field] ?? '—').' ← '.($event->after[$field] ?? '—');
            }
        }
        if (($event->before['phone'] ?? null) !== ($event->after['phone'] ?? null)) {
            $summary[] = 'تم تحديث رقم الاتصال';
        }

        return $summary ?: ['لم تتغير قيمة تنظيمية ظاهرة'];
    }

    /** @param list<string> $before @param list<string> $after @return list<string> */
    private function listChanges(string $label, array $before, array $after): array
    {
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        return array_values(array_merge(
            array_map(fn (string $value) => "إضافة {$label}: {$value}", $added),
            array_map(fn (string $value) => "إزالة {$label}: {$value}", $removed),
        )) ?: ['لا يوجد فرق فعلي'];
    }
}
