<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffDepartmentRequest;
use App\Http\Requests\Admin\UpdateStaffDepartmentRequest;
use App\Models\StaffDepartment;
use App\Models\User;
use App\Services\StaffDepartmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class StaffDepartmentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);

        $departments = StaffDepartment::query()
            ->with('manager:id,name,employee_number')
            ->withCount('staff')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
        $managers = User::query()
            ->select(['id', 'name', 'employee_number'])
            ->where('account_status', AccountStatus::Active->value)
            ->where(fn (Builder $staff) => $staff
                ->whereHas('roles', fn (Builder $roles) => $roles
                    ->whereIn('slug', ['admin', 'delivery-worker'])
                    ->orWhereHas('permissions'))
                ->orWhereHas('directPermissions'))
            ->orderBy('name')
            ->get();

        return view('admin.staff.departments.index', compact('departments', 'managers'));
    }

    public function store(StoreStaffDepartmentRequest $request, StaffDepartmentService $service)
    {
        $service->create(
            $request->user(),
            $request->validated('name'),
            $request->validated('slug'),
            $request->integer('manager_id') ?: null,
            $request->validated('reason'),
        );

        return back()->with('success', 'تم إنشاء القسم وتسجيل الإجراء.');
    }

    public function update(
        UpdateStaffDepartmentRequest $request,
        string $department,
        StaffDepartmentService $service,
    ) {
        $service->update(
            $request->user(),
            (int) $department,
            $request->validated('name'),
            $request->validated('slug'),
            $request->integer('manager_id') ?: null,
            $request->boolean('is_active'),
            $request->validated('reason'),
        );

        return back()->with('success', 'تم تحديث القسم وتسجيل الإجراء.');
    }
}
