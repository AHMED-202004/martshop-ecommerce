<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\OrderPlacementService;
use Illuminate\Support\Str;
use App\Services\MarketplaceSettings;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
     * تأكيد طريقة الدفع وإنشاء الطلب (أو تحويل لإضافة بطاقة)
     */
    public function confirm(Request $request, OrderPlacementService $orders, MarketplaceSettings $settings)
    {
        $request->validate([
            'payment_method' => ['required', 'in:manual_transfer'],
            'checkout_token' => ['nullable', 'uuid'],
        ]);

        $checkoutToken = (string) ($request->input('checkout_token')
            ?: session('checkout.token')
            ?: Str::uuid());

        if ($existing = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('checkout_token', $checkoutToken)
            ->first()) {
            session()->forget(['cart.items', 'checkout.token']);

            return redirect()->route('order.confirmation', $existing);
        }

        if (! $settings->boolean('site.orders_enabled')) {
            throw ValidationException::withMessages(['cart' => 'إنشاء الطلبات متوقف مؤقتًا.']);
        }

        $items = session('cart.items', []);
        if (empty($items)) {
            return back()->withErrors(['cart' => 'السلة فارغة']);
        }

        $order = $orders->place(
            $request->user(),
            $items,
            $request->string('payment_method')->toString(),
            $checkoutToken,
        );

        session()->forget(['cart.items', 'checkout.token']);

        return redirect()
            ->route('order.confirmation', $order)
            ->with('ok', 'تم إنشاء الطلب وحجز الكميات لحين تأكيد التاجر.');
    }

    /**
     * صفحة التأكيد بعد إنشاء الطلب
     */
    public function confirmation(Order $order)
    {
        abort_unless($order->user_id === request()->user()->id, 403);
        $order = Order::query()
            ->select(['id', 'user_id', 'status'])
            ->whereKey($order->id)
            ->firstOrFail();

        return view('checkout.confirmation', compact('order'));
    }
}
