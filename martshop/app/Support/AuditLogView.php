<?php

namespace App\Support;

use Illuminate\Support\Str;

class AuditLogView
{
    private const SENSITIVE_KEY_PARTS = [
        'password', 'token', 'secret', 'pin', 'account_identifier', 'identity_number',
        'document_number', 'path', 'disk',
    ];

    public static function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return collect($values)->mapWithKeys(function ($value, $key) {
            $normalized = Str::snake((string) $key);
            if (collect(self::SENSITIVE_KEY_PARTS)->contains(fn ($part) =>
                preg_match('/(?:^|[_.-])'.preg_quote($part, '/').'(?:[_.-]|$)/', $normalized) === 1)) {
                return [$key => '[محجوب]'];
            }

            return [$key => is_array($value) ? self::sanitize($value) : $value];
        })->all();
    }

    public static function subjectName(?string $type): string
    {
        return $type ? class_basename($type) : 'النظام';
    }
}
