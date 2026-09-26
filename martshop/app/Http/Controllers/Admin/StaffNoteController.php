<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffNoteRequest;
use App\Services\StaffNoteService;

class StaffNoteController extends Controller
{
    public function store(StoreStaffNoteRequest $request, string $staff, StaffNoteService $notes)
    {
        $notes->add($request->user(), (int) $staff, $request->validated('body'));

        return redirect()->route('admin.staff.show', $staff)
            ->with('success', 'تمت إضافة الملاحظة الداخلية وحفظ أثر الإجراء.');
    }
}
