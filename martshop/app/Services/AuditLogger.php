<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        array $metadata = [],
    ): AuditLog {
        $request = request();
        $ipAddress = $request?->ip();
        $userAgent = $request?->userAgent();

        return AuditLog::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip_address' => is_string($ipAddress) && filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : null,
            'user_agent' => is_string($userAgent) ? Str::limit($userAgent, 1000, '') : null,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
