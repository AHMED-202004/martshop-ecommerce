<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\StaffTaskStatus;
use App\Models\ContactMessage;
use App\Models\DeliveryDispute;
use App\Models\Merchant;
use App\Models\MerchantOrder;
use App\Models\MerchantPayoutMethod;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Models\ProductOffer;
use App\Models\RefundDestination;
use App\Models\RefundRequest;
use App\Models\StaffTask;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Schema;

class AdminDashboardService
{
    private const SECTIONS = [
        'merchants.view' => ['merchants', 'مراجعة التجار', 'admin.merchants.index'],
        'products.moderate' => ['catalog', 'مراجعة المنتجات والعروض', 'admin.catalog.index'],
        'contact-messages.view' => ['contact_messages', 'رسائل العملاء', 'admin.contact-messages.index'],
        'payments.verify' => ['payments', 'مراجعة الدفعات', 'admin.payments.index'],
        'deliveries.manage' => ['deliveries', 'إدارة التوصيل', 'admin.deliveries.index'],
        'refunds.review' => ['refunds', 'قرارات الاسترداد', 'admin.refunds.index'],
        'refund-destinations.review' => ['destinations', 'التحقق من وسائل الاسترداد', 'admin.refund-destinations.index'],
        'refunds.pay' => ['transfers', 'تحويلات الاسترداد', 'admin.refund-transfers.index'],
        'settlements.manage' => ['settlements', 'التسويات والنزاعات', 'admin.settlements.index'],
        'withdrawals.approve' => ['withdrawals', 'مراجعة السحوبات', 'admin.withdrawals.index'],
        'withdrawals.settings' => ['withdrawal_policy', 'سياسة السحب', 'admin.withdrawals.index'],
        'payment-methods.manage' => ['payment_methods', 'وسائل الدفع', 'admin.payment-methods.index'],
        'commissions.manage' => ['commissions', 'العمولات', 'admin.commissions.index'],
        'ledger.view' => ['ledger', 'السجل المحاسبي', 'admin.ledger.index'],
        'audit-logs.view' => ['audit', 'سجل التدقيق', 'admin.audit-logs.index'],
        'roles.manage' => ['staff', 'إدارة الموظفين', 'admin.staff.index'],
        'settings.manage' => ['settings', 'إعدادات الموقع', 'admin.settings.edit'],
    ];

    public function canAccess(User $user): bool
    {
        return Schema::hasTable('permissions') && $this->permissionQuery($user)->exists();
    }

    public function navigation(User $user): array
    {
        $permissions = Schema::hasTable('permissions') ? $this->permissionQuery($user)->pluck('slug')->all() : [];
        $links = [];
        foreach (self::SECTIONS as $permission => [$key, $title, $route]) {
            if (in_array($permission, $permissions, true) && ! isset($links[$route])) {
                $links[$route] = ['title' => $title, 'route' => $route];
            }
        }

        // Navigation needs only permissions, never the dashboard's work-queue counts.
        return array_values($links);
    }

    public function data(User $user): array
    {
        $permissions = Schema::hasTable('permissions') ? $this->permissionQuery($user)->pluck('slug')->all() : [];
        abort_if($permissions === [], 403);
        $sections = [];
        foreach (self::SECTIONS as $permission => [$key, $title, $route]) {
            if (! in_array($permission, $permissions, true)) {
                continue;
            }
            // Never query a restricted work queue, not merely hide it in the template.
            $sections[] = ['key' => $key, 'title' => $title, 'route' => $route, 'metrics' => $this->metrics($key)];
        }
        $settings = app(MarketplaceSettings::class);
        $switches = [];
        if (array_intersect($permissions, ['withdrawals.approve', 'withdrawals.settings'])) {
            $switches[] = ['label' => 'طلبات السحب الجديدة', 'enabled' => $settings->boolean('withdrawals.enabled')];
        }
        if (in_array('settlements.manage', $permissions, true)) {
            $switches[] = ['label' => 'التحرير التلقائي للتسويات', 'enabled' => $settings->boolean('settlement.auto_release_enabled')];
        }

        return ['sections' => $sections, 'switches' => $switches, 'checkedAt' => now()];
    }

    private function permissionQuery(User $user)
    {
        return Permission::query()->whereIn('slug', array_keys(self::SECTIONS))
            ->where(fn ($query) => $query
                ->whereHas('roles.users', fn ($users) => $users->where('users.id', $user->id))
                ->orWhereHas('directUsers', fn ($users) => $users->where('users.id', $user->id)));
    }

    private function metrics(string $key): array
    {
        return match ($key) {
            'merchants' => ['تجار بانتظار المراجعة' => Merchant::where('verification_status', 'pending_review')->count()],
            'catalog' => [
                'منتجات تجار بانتظار المراجعة' => Product::whereNotNull('created_by_merchant_id')->where('status', 'pending_review')->count(),
                'عروض تجار بانتظار المراجعة' => ProductOffer::whereNotNull('merchant_id')->where('status', 'pending_review')->count(),
                'طلبات تعديل بانتظار المراجعة' => ProductChangeRequest::where('status', 'pending_review')->count(),
            ],
            'contact_messages' => ['رسائل العملاء' => ContactMessage::query()->count()],
            'payments' => ['دفعات بانتظار القرار' => Payment::where('status', 'pending')->count()],
            'deliveries' => ['طلبات تجار مدفوعة ومؤكدة دون تعيين توصيل' => MerchantOrder::whereNotNull('merchant_id')
                ->where('status', 'confirmed')->whereHas('order', fn ($query) => $query->where('status', 'confirmed')->where('payment_status', 'paid'))
                ->whereDoesntHave('delivery')->count()],
            'refunds' => ['طلبات استرداد بانتظار القرار' => RefundRequest::where('status', 'requested')->count()],
            'destinations' => ['وسائل استرداد حالية بانتظار التحقق' => RefundDestination::whereNotNull('active_key')->where('status', 'pending')
                ->whereHas('refund', fn ($query) => $query->whereIn('status', ['requested', 'approved']))->count()],
            'transfers' => [
                'استردادات معتمدة بانتظار التجهيز' => RefundRequest::where('status', 'approved')->count(),
                'استردادات قيد التحويل اليدوي' => RefundRequest::where('status', 'processing')->count(),
            ],
            'settlements' => ['نزاعات مفتوحة على طلبات مسلّمة' => DeliveryDispute::where('status', 'open')
                ->whereHas('delivery', fn ($query) => $query->where('status', 'delivered'))->count()],
            'withdrawals' => [
                'سحوبات بانتظار القرار' => WithdrawalRequest::where('status', 'requested')->count(),
                'سحوبات معتمدة لم تُسجّل كمدفوعة' => WithdrawalRequest::where('status', 'approved')->count(),
                'وسائل سحب بانتظار التحقق' => MerchantPayoutMethod::where('status', 'pending')->count(),
            ],
            'staff' => [
                'حسابات موظفين نشطة' => User::query()
                    ->where('account_status', AccountStatus::Active->value)
                    ->where(fn ($staff) => $staff
                        ->whereHas('roles', fn ($roles) => $roles
                            ->whereIn('slug', ['admin', 'delivery-worker'])
                            ->orWhereHas('permissions'))
                        ->orWhereHas('directPermissions'))
                    ->count(),
                'مهام موظفين مفتوحة' => StaffTask::query()
                    ->whereNotIn('status', [StaffTaskStatus::Completed->value, StaffTaskStatus::Cancelled->value])
                    ->count(),
                'مهام موظفين متأخرة' => StaffTask::query()
                    ->whereNotIn('status', [StaffTaskStatus::Completed->value, StaffTaskStatus::Cancelled->value])
                    ->where('due_at', '<', now())
                    ->count(),
            ],
            default => [],
        };
    }
}
