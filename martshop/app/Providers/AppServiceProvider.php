<?php

namespace App\Providers;

use App\Models\Delivery;
use App\Models\LedgerEntry;
use App\Models\Merchant;
use App\Models\MerchantDocument;
use App\Models\MerchantOrder;
use App\Models\MerchantPayoutMethod;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Models\ProductOffer;
use App\Models\RefundDestination;
use App\Models\RefundTransfer;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Policies\DeliveryPolicy;
use App\Policies\LedgerEntryPolicy;
use App\Policies\MerchantDocumentPolicy;
use App\Policies\MerchantOrderPolicy;
use App\Policies\MerchantPayoutMethodPolicy;
use App\Policies\MerchantPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductChangeRequestPolicy;
use App\Policies\ProductOfferPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RefundDestinationPolicy;
use App\Policies\RefundTransferPolicy;
use App\Policies\WithdrawalRequestPolicy;
use App\Services\Cart;
use App\Services\MarketplaceSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-id:'.$this->hashedInput($request, 'login')),
        ]);
        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perMinute(5)->by('registration-ip:'.$request->ip()),
            Limit::perHour(10)->by('registration-ip-hour:'.$request->ip()),
        ]);
        RateLimiter::for('password-recovery', fn (Request $request) => [
            Limit::perMinute(5)->by('recovery-ip:'.$request->ip()),
            Limit::perMinute(3)->by('recovery-email:'.$this->hashedInput($request, 'email')),
        ]);
        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        View::composer('layouts.app', function ($view) {
            $settings = app(MarketplaceSettings::class);
            $view->with('siteSettings', [
                'name' => $settings->string('site.name'),
                'contact_phone' => $settings->string('site.contact_phone'),
                'whatsapp' => $settings->string('site.whatsapp'),
                'email' => $settings->string('site.email'),
                'support_hours' => $settings->string('site.support_hours'),
                'emergency_notice' => $settings->string('site.emergency_notice'),
                'chat_enabled' => $settings->boolean('site.chat_enabled'),
                'merchant_registration_enabled' => $settings->boolean('site.merchant_registration_enabled'),
                'orders_enabled' => $settings->boolean('site.orders_enabled'),
            ]);
        });
        Gate::policy(Merchant::class, MerchantPolicy::class);
        Gate::policy(MerchantDocument::class, MerchantDocumentPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductOffer::class, ProductOfferPolicy::class);
        Gate::policy(ProductChangeRequest::class, ProductChangeRequestPolicy::class);
        Gate::policy(MerchantOrder::class, MerchantOrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(LedgerEntry::class, LedgerEntryPolicy::class);
        Gate::policy(MerchantPayoutMethod::class, MerchantPayoutMethodPolicy::class);
        Gate::policy(WithdrawalRequest::class, WithdrawalRequestPolicy::class);
        Gate::policy(Delivery::class, DeliveryPolicy::class);
        Gate::policy(RefundDestination::class, RefundDestinationPolicy::class);
        Gate::policy(RefundTransfer::class, RefundTransferPolicy::class);

        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });

        View::composer('*', function ($view) {
            $count = Cart::count();
            $view->with('cartCount', $count);
            $view->with('cart_count', $count);
            $view->with('cart_total', Cart::total());
        });

    }

    private function hashedInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return hash('sha256', strtolower(trim(is_string($value) ? $value : '')));
    }
}
