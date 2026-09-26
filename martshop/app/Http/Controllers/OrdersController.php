<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function history(Request $request)
    {
        $orders = $request->user()
            ->hasMany(\App\Models\Order::class, 'user_id')
            ->select(['id', 'user_id', 'total', 'currency', 'status', 'payment_status', 'created_at'])
            ->withCount('merchantOrders')
            ->with([
                'items:id,order_id,product_name,price,qty,image',
                'deliveries:id,order_id,merchant_order_id,status,confirmation_pin,settlement_due_at,settled_at',
                'deliveries.dispute:id,delivery_id,status,close_reason',
                'deliveries.rating:id,delivery_id,rating,created_at',
                'deliveries.dispute.refundRequest:id,delivery_dispute_id,reference,status,amount,currency,review_reason',
                'deliveries.dispute.refundRequest.transfer:id,refund_request_id,paid_at',
                'latestPayment' => fn ($query) => $query->select([
                    'payments.id', 'payments.order_id', 'payments.status',
                ]),
                'latestPayment.proof:id,payment_id',
            ])
            ->latest('id')
            ->paginate(15);

        return view('orders.history', compact('orders'));
    }
}
