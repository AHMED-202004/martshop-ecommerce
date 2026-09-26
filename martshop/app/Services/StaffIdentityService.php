<?php

namespace App\Services;

use App\Models\User;

class StaffIdentityService
{
    public function ensureEmployeeNumber(User $employee): string
    {
        if ($employee->employee_number) {
            return $employee->employee_number;
        }

        $number = 'EMP-'.str_pad((string) $employee->getKey(), 6, '0', STR_PAD_LEFT);
        $employee->forceFill(['employee_number' => $number])->save();

        return $number;
    }
}
