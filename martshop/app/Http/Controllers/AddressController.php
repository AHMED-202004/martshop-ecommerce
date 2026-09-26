<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAddressRequest;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        return view('address.edit', compact('user'));
    }

    public function update(UpdateAddressRequest $request, AuditLogger $audit)
    {
        $data = $request->safe()->except('current_password');
        $currentPassword = $request->validated('current_password');
        $user = $request->user();

        DB::transaction(function () use ($audit, $currentPassword, $data, $user) {
            $lockedUser = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($currentPassword, $lockedUser->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'كلمة المرور الحالية لم تعد صالحة. أعد المحاولة.',
                ]);
            }
            $lockedUser->forceFill($data + [
                'name' => $data['first_name'].' '.$data['last_name'],
            ])->save();
            $audit->record(
                'account.delivery_details_updated',
                $lockedUser,
                reason: 'Delivery address and contact details updated by the account owner.',
            );
        });

        return redirect()->route('my-account')->with('success', 'تم تحديث العنوان وبيانات التواصل.');
    }
}
