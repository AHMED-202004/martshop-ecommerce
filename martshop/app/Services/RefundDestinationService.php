<?php

namespace App\Services;

use App\Enums\RefundDestinationStatus;
use App\Enums\RefundStatus;
use App\Models\{Delivery, RefundDestination, RefundRequest, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RefundDestinationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function submit(RefundRequest $refund, User $actor, array $data, int $version): RefundDestination
    {
        return DB::transaction(function () use ($refund, $actor, $data, $version) {
            [$delivery, $locked] = $this->lockRefund($refund);
            abort_unless($delivery->order->user_id === $actor->id, 403);
            $this->ensureOpen($locked, $delivery);
            $snapshot = $this->normalizedSnapshot($data);
            $active = $locked->activeDestination()->lockForUpdate()->first();
            if ($active && $active->recipient_snapshot === $snapshot) {
                return $active;
            }
            if ($active) {
                $this->invalid('ألغِ وسيلة الاستلام الحالية أولًا؛ لا يمكن استبدال بياناتها مباشرة.');
            }
            if ($locked->lock_version !== $version) {
                $this->invalid('تم تحديث طلب الاسترداد؛ حدّث الصفحة قبل تسجيل الوسيلة.');
            }
            $destination = $locked->destinations()->create([
                'active_key' => 'refund:'.$locked->id,
                'recipient_snapshot' => $snapshot, 'last_four' => substr($snapshot['account_identifier'], -4),
                'status' => RefundDestinationStatus::Pending, 'submitted_by' => $actor->id,
            ]);
            $locked->increment('lock_version');
            $this->audit->record('refund_destination.submitted', $destination, null, [
                'refund_request_id' => $locked->id, 'status' => 'pending',
            ]);

            return $destination;
        }, 3);
    }

    public function review(RefundDestination $destination, User $actor, RefundDestinationStatus $decision,
        string $notes, int $version, bool $ownershipConfirmed): RefundDestination
    {
        abort_unless($actor->hasPermission('refund-destinations.review'), 403);
        if (! in_array($decision, [RefundDestinationStatus::Verified, RefundDestinationStatus::Rejected], true)) {
            $this->invalid('قرار التحقق غير صالح.');
        }
        if ($decision === RefundDestinationStatus::Verified && ! $ownershipConfirmed) {
            $this->invalid('يجب تأكيد التحقق المستقل من ملكية العميل لوسيلة الاستلام.');
        }

        return DB::transaction(function () use ($destination, $actor, $decision, $notes, $version) {
            [$delivery, $refund] = $this->lockRefund($destination->refund);
            abort_if($delivery->order->user_id === $actor->id, 403);
            $this->ensureOpen($refund, $delivery);
            $locked = RefundDestination::query()->whereKey($destination->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $decision) {
                return $locked;
            }
            if ($locked->status !== RefundDestinationStatus::Pending || $locked->active_key !== 'refund:'.$refund->id
                || $locked->lock_version !== $version) {
                $this->invalid('تغيّرت حالة الوسيلة أو تمت مراجعتها؛ حدّث الصفحة.');
            }
            $locked->update([
                'status' => $decision, 'active_key' => $decision === RefundDestinationStatus::Verified ? $locked->active_key : null,
                'reviewed_by' => $actor->id, 'review_notes' => $notes, 'reviewed_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $refund->increment('lock_version');
            // Sensitive notes remain encrypted on the destination, not in the general audit log.
            $this->audit->record('refund_destination.reviewed', $locked, ['status' => 'pending'], [
                'status' => $decision->value, 'ownership_confirmed' => $decision === RefundDestinationStatus::Verified,
            ], 'Manual recipient review; details retained in the protected destination record.');

            return $locked;
        }, 3);
    }

    public function revoke(RefundDestination $destination, User $actor, int $version): RefundDestination
    {
        return DB::transaction(function () use ($destination, $actor, $version) {
            [$delivery, $refund] = $this->lockRefund($destination->refund);
            abort_unless($delivery->order->user_id === $actor->id, 403);
            $this->ensureOpen($refund, $delivery);
            $locked = RefundDestination::query()->whereKey($destination->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === RefundDestinationStatus::Revoked) {
                return $locked;
            }
            if (! in_array($locked->status, [RefundDestinationStatus::Pending, RefundDestinationStatus::Verified], true)
                || $locked->lock_version !== $version || $locked->active_key !== 'refund:'.$refund->id) {
                $this->invalid('تغيّرت حالة الوسيلة؛ حدّث الصفحة.');
            }
            $before = $locked->status->value;
            $locked->update(['status' => RefundDestinationStatus::Revoked, 'active_key' => null,
                'revoked_by' => $actor->id, 'revoked_at' => now(), 'lock_version' => $locked->lock_version + 1]);
            $refund->increment('lock_version');
            $this->audit->record('refund_destination.revoked', $locked, ['status' => $before], ['status' => 'revoked']);

            return $locked;
        }, 3);
    }

    private function lockRefund(RefundRequest $refund): array
    {
        $delivery = Delivery::query()->whereKey($refund->dispute->delivery_id)->lockForUpdate()->firstOrFail();

        return [$delivery, RefundRequest::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail()];
    }

    private function ensureOpen(RefundRequest $refund, Delivery $delivery): void
    {
        if (! in_array($refund->status, [RefundStatus::Requested, RefundStatus::Approved], true)
            || $delivery->settled_at || $delivery->dispute->status !== 'open') {
            $this->invalid('لا يمكن تغيير وجهة الاستلام لطلب استرداد مغلق أو تمت تسويته.');
        }
    }

    private function normalizedSnapshot(array $data): array
    {
        foreach (['provider_name', 'account_name', 'account_identifier'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = trim($data[$key]);
            }
        }
        $validated = Validator::make($data, self::recipientRules())->validate();
        $identifier = strtoupper(preg_replace('/[ -]+/', '', trim($validated['account_identifier'])));
        if (strlen($identifier) < 5 || strlen($identifier) > 100) {
            $this->invalid('رقم الحساب أو المحفظة غير صالح.');
        }

        return ['type' => $validated['type'], 'provider_name' => trim($validated['provider_name']),
            'account_name' => trim($validated['account_name']), 'account_identifier' => $identifier];
    }

    public static function recipientRules(): array
    {
        return [
            'type' => ['required', 'in:bank_account,mobile_wallet'],
            'provider_name' => ['required', 'string', 'min:2', 'max:100'],
            'account_name' => ['required', 'string', 'min:2', 'max:150'],
            'account_identifier' => ['required', 'string', 'min:5', 'max:120', 'regex:/\A\+?[A-Za-z0-9 -]+\z/'],
        ];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['destination' => $message]);
    }
}
