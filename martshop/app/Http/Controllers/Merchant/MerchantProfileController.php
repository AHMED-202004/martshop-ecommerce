<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\SaveMerchantProfileRequest;
use App\Http\Requests\Merchant\SubmitMerchantProfileRequest;
use App\Models\Location;
use App\Services\MerchantRegistrationService;
use Illuminate\Http\Request;
use App\Services\MarketplaceSettings;
use Illuminate\Validation\ValidationException;

class MerchantProfileController extends Controller
{
    public function edit(Request $request, MerchantRegistrationService $registration, MarketplaceSettings $settings)
    {
        $merchant = $request->user()->merchant()
            ->select([
                'id', 'user_id', 'location_id', 'legal_name', 'identity_number', 'phone',
                'date_of_birth', 'address', 'business_type', 'verification_status', 'review_notes',
            ])
            ->with(['documents' => fn ($query) => $query
                ->select(['id', 'merchant_id', 'type', 'status'])
                ->latest('id')])
            ->first();
        $locations = Location::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('merchant.profile', [
            'merchant' => $merchant,
            'locations' => $locations,
            'requiredDocumentTypes' => $registration->requiredDocumentTypes(),
            'registrationEnabled' => $settings->boolean('site.merchant_registration_enabled'),
        ]);
    }

    public function update(SaveMerchantProfileRequest $request, MerchantRegistrationService $registration, MarketplaceSettings $settings)
    {
        if (! $request->user()->merchant()->exists() && ! $settings->boolean('site.merchant_registration_enabled')) {
            throw ValidationException::withMessages(['merchant' => 'تسجيل تجار جدد متوقف مؤقتًا.']);
        }
        $registration->saveProfile($request->user(), $request->safe()->except('current_password'));

        return redirect()->route('merchant.profile.edit')->with('success', 'تم حفظ ملف التاجر.');
    }

    public function submit(SubmitMerchantProfileRequest $request, MerchantRegistrationService $registration)
    {
        $merchant = $request->user()->merchant()->first();
        abort_unless($merchant, 404);

        $registration->submit($merchant, $request->user());

        return redirect()->route('merchant.profile.edit')->with('success', 'تم إرسال الطلب للمراجعة.');
    }
}
