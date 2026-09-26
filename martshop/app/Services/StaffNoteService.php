<?php

namespace App\Services;

use App\Models\StaffNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StaffNoteService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function add(User $actor, int $staffId, string $body): StaffNote
    {
        abort_unless($actor->hasPermission('staff-notes.manage'), 403);

        return DB::transaction(function () use ($actor, $staffId, $body) {
            $employee = User::query()
                ->whereKey($staffId)
                ->where(fn (Builder $staff) => $staff
                    ->whereHas('roles', fn (Builder $roles) => $roles
                        ->whereIn('slug', ['admin', 'delivery-worker'])
                        ->orWhereHas('permissions'))
                    ->orWhereHas('directPermissions'))
                ->lockForUpdate()
                ->firstOrFail();
            $note = StaffNote::create([
                'staff_id' => $employee->getKey(),
                'author_id' => $actor->getKey(),
                'body' => trim($body),
            ]);
            $this->audit->record(
                'staff.note_added',
                $employee,
                after: ['note_id' => $note->getKey()],
                reason: 'Internal staff note added.',
            );

            return $note;
        });
    }
}
