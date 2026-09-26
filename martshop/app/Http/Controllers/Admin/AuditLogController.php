<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('audit-logs.view'), 403);
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:255'],
            'actor' => ['nullable', 'string', 'max:255'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->select(['id', 'actor_id', 'action', 'subject_type', 'subject_id', 'reason', 'created_at'])
            ->with('actor:id,name,email')
            ->when($filters['action'] ?? null, fn (Builder $query, $action) => $query->where('action', $action))
            ->when($filters['actor'] ?? null, fn (Builder $query, $actor) => $query->whereHas(
                'actor', fn (Builder $users) => $users
                    ->whereRaw("email LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($actor).'%'])
                    ->orWhereRaw("name LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($actor).'%'])
            ))
            ->when($filters['subject_type'] ?? null, fn (Builder $query, $type) => $query->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->where('subject_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')->paginate(50)->appends($filters);

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'subjectTypes' => AuditLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }

    public function show(Request $request, string $auditLog)
    {
        abort_unless($request->user()->hasPermission('audit-logs.view'), 403);

        $log = AuditLog::query()
            ->select([
                'id', 'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after',
                'reason', 'ip_address', 'user_agent', 'metadata', 'created_at',
            ])
            ->with('actor:id,name,email')
            ->findOrFail($auditLog);

        return view('admin.audit-logs.show', ['log' => $log]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($value));
    }
}
