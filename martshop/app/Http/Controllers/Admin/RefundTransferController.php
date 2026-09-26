<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundDestinationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\{CancelRefundTransferRequest, ListRefundTransfersRequest, PrepareRefundTransferRequest, RecordRefundTransferRequest};
use App\Models\{RefundRequest, RefundTransfer};
use App\Services\{AuditLogger, RefundIntegrityCheck, RefundTransferQueue, RefundTransferService};
use Illuminate\Http\Request;

class RefundTransferController extends Controller
{
    public function index(ListRefundTransfersRequest $request, RefundTransferQueue $queue)
    {
        return view('refund-transfers.index', $queue->data($request->validated()));
    }

    public function check(Request $request, RefundRequest $refundRequest, RefundIntegrityCheck $check)
    {
        return view('refund-transfers.check', ['refund' => $refundRequest,
            'report' => $check->inspect($refundRequest, $request->user())]);
    }

    public function show(Request $request, RefundRequest $refundRequest, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('refunds.pay'), 403);
        $refundRequest->load(['activeDestination', 'transfer']);
        $destination = $refundRequest->activeDestination;
        $recipient = $refundRequest->transfer?->recipient_snapshot
            ?? ($destination?->status === RefundDestinationStatus::Verified ? $destination->recipient_snapshot : null);
        $audit->record('refund.transfer_viewed', $refundRequest);

        return view('refund-transfers.show', ['refund' => $refundRequest, 'recipient' => $recipient,
            'destination' => $destination, 'transfer' => $refundRequest->transfer,
            'attempts' => $refundRequest->transfers()->latest('id')->paginate(20),
            'isOwn' => $refundRequest->dispute->delivery->order->user_id === $request->user()->id]);
    }

    public function prepare(PrepareRefundTransferRequest $request, RefundRequest $refundRequest, RefundTransferService $service)
    {
        $data = $request->validated();
        $service->prepare($refundRequest, $request->user(), (int) $data['lock_version'],
            (int) $data['destination_id'], (int) $data['destination_version']);

        return redirect()->route('admin.refund-transfers.show', $refundRequest)
            ->with('success', 'ثُبّت المبلغ والمستلم للتحويل اليدوي. لم يُسجّل أي صرف بعد.');
    }

    public function cancel(CancelRefundTransferRequest $request, RefundTransfer $refundTransfer, RefundTransferService $service)
    {
        $service->cancelPreparation($refundTransfer, $request->user(), $request->validated());

        return redirect()->route('admin.refund-transfers.show', $refundTransfer->refund_request_id)
            ->with('success', 'محاولة التجهيز ملغاة ومحفوظة في السجل. لم تُلغَ أي حوالة بنكية من الموقع.');
    }

    public function paid(RecordRefundTransferRequest $request, RefundRequest $refundRequest, RefundTransferService $service)
    {
        $service->recordPaid($refundRequest, $request->user(), $request->validated(), $request->file('proof'));

        return redirect()->route('admin.refund-transfers.show', $refundRequest)
            ->with('success', 'سُجّلت الحوالة وإثباتها والاسترداد المحاسبي. لا تُكرر الحوالة خارج الموقع.');
    }
}
