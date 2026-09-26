<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\MerchantOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\ConfirmMerchantOrderRequest;
use App\Http\Requests\Merchant\RejectMerchantOrderRequest;
use App\Models\MerchantOrder;
use App\Services\MerchantOrderService;
use Illuminate\Http\Request;

class MerchantOrderController extends Controller
{
    public function index(Request $request)
    {
        $merchant = $request->user()->merchant()
            ->select(['id', 'user_id', 'verification_status'])
            ->first();
        if (! $merchant) {
            return redirect()->route('merchant.profile.edit')
                ->withErrors(['merchant' => 'أنشئ ملف التاجر أولًا.']);
        }

        $orders = $merchant->orders()
            ->select(['id', 'order_id', 'merchant_id', 'total', 'currency', 'status'])
            ->with([
                'order:id,user_id',
                'order.user:id,name,first_name',
                'items:id,merchant_order_id,qty',
            ])
            ->latest('id')
            ->paginate(30);

        return view('merchant.orders.index', compact('merchant', 'orders'));
    }

    public function show(MerchantOrder $merchantOrder)
    {
        $this->authorize('view', $merchantOrder);
        $merchantOrder = MerchantOrder::query()->select([
            'id', 'order_id', 'merchant_id', 'status', 'product_subtotal', 'delivery_fee',
            'commission_amount', 'total', 'currency',
        ])->whereKey($merchantOrder->id)->firstOrFail();
        $merchantOrder->load([
            'order:id,user_id,delivery_address_snapshot',
            'items:id,merchant_order_id,product_name,price,qty,variant_snapshot',
            'delivery:id,merchant_order_id,status',
        ]);

        return view('merchant.orders.show', compact('merchantOrder'));
    }

    public function confirm(
        ConfirmMerchantOrderRequest $request,
        MerchantOrder $merchantOrder,
        MerchantOrderService $orders,
    ) {
        $result = $orders->confirm($merchantOrder, $request->user());
        if ($result->status === MerchantOrderStatus::Expired) {
            return back()->withErrors(['order' => 'انتهت مهلة الحجز، وتمت إعادة الكميات إلى المخزون.']);
        }

        return back()->with('success', 'تم تأكيد السعر والمخزون للطلب.');
    }

    public function reject(
        RejectMerchantOrderRequest $request,
        MerchantOrder $merchantOrder,
        MerchantOrderService $orders,
    ) {
        $orders->reject($merchantOrder, $request->user(), $request->validated('reason'));

        return back()->with('success', 'تم رفض الطلب وإرجاع الكميات إلى المخزون.');
    }
}
