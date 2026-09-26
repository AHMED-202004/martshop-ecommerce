<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DealsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewProductsController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicPolicyController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WomenShoesController;
use App\Http\Controllers\Admin\MerchantDocumentReviewController;
use App\Http\Controllers\Admin\MerchantVerificationController;
use App\Http\Controllers\Admin\CatalogModerationController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PaymentReviewController;
use App\Http\Controllers\Admin\CommissionRuleController;
use App\Http\Controllers\Admin\LedgerController;
use App\Http\Controllers\Admin\PayoutMethodReviewController;
use App\Http\Controllers\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Admin\DeliveryController as AdminDeliveryController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\MarketplaceSettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StaffDepartmentController;
use App\Http\Controllers\Admin\StaffTaskController;
use App\Http\Controllers\Admin\StaffNoteController;
use App\Http\Controllers\StaffTaskSelfServiceController;
use App\Http\Controllers\ManagedStaffDepartmentController;
use App\Http\Controllers\Merchant\MerchantCatalogController;
use App\Http\Controllers\Merchant\MerchantOrderController;
use App\Http\Controllers\Merchant\MerchantLedgerController;
use App\Http\Controllers\Merchant\PayoutMethodController as MerchantPayoutMethodController;
use App\Http\Controllers\Merchant\WithdrawalController as MerchantWithdrawalController;
use App\Http\Controllers\Merchant\ProductChangeRequestController;
use App\Http\Controllers\ProductChangeRequestImageController;
use App\Http\Controllers\ProductSubmissionImageController;
use App\Http\Controllers\Merchant\MerchantDocumentController;
use App\Http\Controllers\Merchant\MerchantProfileController;
use App\Http\Controllers\WithdrawalProofController;
use App\Http\Controllers\Delivery\TaskController as DeliveryTaskController;
use App\Http\Controllers\DeliveryProofController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AccountController::class, 'show'])->name('login');
    Route::post('/login', [AccountController::class, 'login'])->middleware('throttle:login')->name('login.post');
    Route::post('/register', [AccountController::class, 'register'])->middleware('throttle:registration')->name('register.post');
    Route::middleware(\App\Http\Middleware\ProtectPasswordRecoveryResponse::class)->group(function () {
        Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordRecoveryController::class, 'request'])->name('password.request');
        Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordRecoveryController::class, 'send'])
            ->middleware('throttle:password-recovery')->name('password.email');
        Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\PasswordRecoveryController::class, 'form'])
            ->middleware('throttle:20,1')->name('password.reset');
        Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordRecoveryController::class, 'reset'])
            ->middleware('throttle:password-recovery')->name('password.update');
    });
});

Route::post('/logout', [AccountController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/my-account', [AccountController::class, 'profile'])
    ->middleware(['auth', \App\Http\Middleware\PrivateAccountResponse::class])
    ->name('my-account');
Route::put('/my-account/password', [AccountController::class, 'changePassword'])
    ->middleware(['auth', \App\Http\Middleware\PrivateAccountResponse::class, 'throttle:5,1'])
    ->name('account.password.update');

Route::middleware('auth')->group(function () {
    Route::get('/my-tasks', [StaffTaskSelfServiceController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('staff-tasks.index');
    Route::patch('/my-tasks/{task}', [StaffTaskSelfServiceController::class, 'update'])
        ->whereNumber('task')
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->middleware('throttle:30,1')
        ->name('staff-tasks.update');
    Route::get('/my-departments', [ManagedStaffDepartmentController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('staff-departments.managed');
    Route::middleware(\App\Http\Middleware\PrivateAccountResponse::class)->group(function () {
        Route::get('/address', [AddressController::class, 'edit'])->name('address.edit');
        Route::post('/address', [AddressController::class, 'update'])
            ->middleware('throttle:10,1')->name('address.update');
        Route::get('/order-confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('order.confirmation');
        Route::get('/order/confirmation', fn () => redirect()->route('orders.history'))->name('order.confirmation.legacy');
        Route::get('/order-history', [OrdersController::class, 'history'])->name('orders.history');
        Route::get('/orders/{order}/payment', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/orders/{order}/payment', [PaymentController::class, 'store'])
            ->middleware('throttle:5,1')->name('payments.store');
    });
    Route::post('/checkout/confirm', [CheckoutController::class, 'confirm'])
        ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:10,1'])
        ->name('checkout.confirm');
    Route::get('/payment-proofs/{paymentProof}', [PaymentProofController::class, 'show'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('payment-proofs.show');
    Route::get('/pay/card', [PaymentController::class, 'showCard'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('payment.card');
    Route::post('/pay/card', [PaymentController::class, 'charge'])
        ->middleware([\App\Http\Middleware\PrivateFinancialResponse::class, 'throttle:5,1'])
        ->name('payment.card.charge');

    Route::get('/merchant/profile', [MerchantProfileController::class, 'edit'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('merchant.profile.edit');
    Route::put('/merchant/profile', [MerchantProfileController::class, 'update'])
        ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:5,1'])
        ->name('merchant.profile.update');
    Route::post('/merchant/submit', [MerchantProfileController::class, 'submit'])
        ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:5,1'])
        ->name('merchant.submit');
    Route::post('/merchant/documents', [MerchantDocumentController::class, 'store'])
        ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:5,1'])
        ->name('merchant.documents.store');
    Route::get('/merchant/documents/{merchantDocument}', [MerchantDocumentController::class, 'show'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('merchant.documents.show');

    Route::get('/merchant/catalog', [MerchantCatalogController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('merchant.catalog.index');
    Route::get('/merchant/orders', [MerchantOrderController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('merchant.orders.index');
    Route::get('/merchant/ledger', [MerchantLedgerController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('merchant.ledger.index');
    Route::middleware(\App\Http\Middleware\PrivateFinancialResponse::class)->group(function () {
        Route::get('/merchant/withdrawals', [MerchantWithdrawalController::class, 'index'])
            ->name('merchant.withdrawals.index');
        Route::post('/merchant/withdrawals', [MerchantWithdrawalController::class, 'store'])
            ->middleware('throttle:5,1')->name('merchant.withdrawals.store');
        Route::post('/merchant/payout-methods', [MerchantPayoutMethodController::class, 'store'])
            ->middleware('throttle:5,1')->name('merchant.payout-methods.store');
        Route::post('/merchant/payout-methods/{payoutMethod}/disable', [MerchantPayoutMethodController::class, 'disable'])
            ->middleware('throttle:5,1')->name('merchant.payout-methods.disable');
    });
    Route::get('/merchant/orders/{merchantOrder}', [MerchantOrderController::class, 'show'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('merchant.orders.show');
    Route::post('/merchant/orders/{merchantOrder}/confirm', [MerchantOrderController::class, 'confirm'])
        ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:10,1'])
        ->name('merchant.orders.confirm');
    Route::post('/merchant/orders/{merchantOrder}/reject', [MerchantOrderController::class, 'reject'])
        ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:10,1'])
        ->name('merchant.orders.reject');
    Route::get('/merchant/catalog/create', [MerchantCatalogController::class, 'create'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('merchant.catalog.create');
    Route::post('/merchant/catalog', [MerchantCatalogController::class, 'store'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->middleware('throttle:10,1')
        ->name('merchant.catalog.store');
    Route::get('/merchant/catalog/{offer}/edit', [MerchantCatalogController::class, 'edit'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('merchant.catalog.edit');
    Route::patch('/merchant/catalog/{offer}', [MerchantCatalogController::class, 'update'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->middleware('throttle:20,1')
        ->name('merchant.catalog.update');
    Route::post('/merchant/catalog/{offer}/pause', [MerchantCatalogController::class, 'pause'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->middleware('throttle:10,1')
        ->name('merchant.catalog.pause');
    Route::post('/merchant/catalog/{offer}/resume', [MerchantCatalogController::class, 'resume'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->middleware('throttle:10,1')
        ->name('merchant.catalog.resume');
    Route::get('/merchant/catalog/products/{product}/edit', [MerchantCatalogController::class, 'editProduct'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('merchant.catalog.products.edit');
    Route::patch('/merchant/catalog/products/{product}', [MerchantCatalogController::class, 'updateProduct'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->middleware('throttle:10,1')
        ->name('merchant.catalog.products.update');
    Route::get('/merchant/catalog/products/{product}/change-request', [ProductChangeRequestController::class, 'create'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('merchant.catalog.change-requests.create');
    Route::post('/merchant/catalog/products/{product}/change-request', [ProductChangeRequestController::class, 'store'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->middleware('throttle:5,1')
        ->name('merchant.catalog.change-requests.store');
    Route::get('/product-submissions/{product}/image', [ProductSubmissionImageController::class, 'show'])
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
        ->name('product-submissions.image');
    Route::get('/product-change-requests/{changeRequest}/image', [ProductChangeRequestImageController::class, 'show'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('product-change-requests.image');

    Route::prefix('admin/catalog-moderation')->name('admin.catalog.')
        ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)->group(function () {
        Route::get('/', [CatalogModerationController::class, 'index'])->name('index');
        Route::patch('/products/{product}', [CatalogModerationController::class, 'updateProduct'])
            ->middleware('throttle:10,1')->name('products.update');
        Route::patch('/offers/{offer}', [CatalogModerationController::class, 'updateOffer'])
            ->middleware('throttle:10,1')->name('offers.update');
        Route::patch('/change-requests/{changeRequest}', [CatalogModerationController::class, 'updateChangeRequest'])
            ->middleware('throttle:10,1')->name('change-requests.update');
    });

    Route::prefix('admin/payments')->name('admin.payments.')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)->group(function () {
        Route::get('/', [PaymentReviewController::class, 'index'])->name('index');
        Route::get('/{payment}', [PaymentReviewController::class, 'show'])->name('show');
        Route::patch('/{payment}', [PaymentReviewController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('update');
    });

    Route::prefix('admin/payment-methods')->name('admin.payment-methods.')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index'])->name('index');
        Route::post('/', [PaymentMethodController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::put('/{paymentMethod}', [PaymentMethodController::class, 'update'])->middleware('throttle:10,1')->name('update');
    });

    Route::prefix('admin/commissions')->name('admin.commissions.')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)->group(function () {
        Route::get('/', [CommissionRuleController::class, 'index'])->name('index');
        Route::post('/', [CommissionRuleController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::put('/{commissionRule}', [CommissionRuleController::class, 'update'])->middleware('throttle:10,1')->name('update');
    });

    Route::get('/admin/ledger', [LedgerController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.ledger.index');

    Route::prefix('admin/withdrawals')->name('admin.withdrawals.')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)->group(function () {
        Route::get('/', [AdminWithdrawalController::class, 'index'])->name('index');
        Route::put('/settings', [AdminWithdrawalController::class, 'updatePolicy'])
            ->middleware('throttle:10,1')->name('settings.update');
        Route::patch('/payout-methods/{payoutMethod}', [PayoutMethodReviewController::class, 'update'])
            ->middleware('throttle:30,1')->name('payout-methods.update');
        Route::patch('/{withdrawalRequest}/review', [AdminWithdrawalController::class, 'review'])
            ->middleware('throttle:30,1')->name('review');
        Route::post('/{withdrawalRequest}/pay', [AdminWithdrawalController::class, 'pay'])
            ->middleware('throttle:10,1')->name('pay');
    });

    Route::get('/withdrawal-proofs/{withdrawalProof}', [WithdrawalProofController::class, 'show'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('withdrawal-proofs.show');

    Route::prefix('admin/deliveries')->name('admin.deliveries.')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)->group(function () {
        Route::get('/', [AdminDeliveryController::class, 'index'])->name('index');
        Route::post('/workers', [AdminDeliveryController::class, 'promote'])
            ->middleware('throttle:10,1')->name('workers.promote');
        Route::post('/merchant-orders/{merchantOrder}/assign', [AdminDeliveryController::class, 'assign'])
            ->middleware('throttle:30,1')->name('assign');
        Route::post('/{delivery}/unassign', [AdminDeliveryController::class, 'unassign'])
            ->whereNumber('delivery')->middleware('throttle:20,1')->name('unassign');
        Route::patch('/workers/{worker}/availability', [AdminDeliveryController::class, 'workerAvailability'])
            ->whereNumber('worker')->middleware('throttle:20,1')->name('workers.availability');
        Route::patch('/{delivery}/outcome', [AdminDeliveryController::class, 'outcome'])
            ->whereNumber('delivery')->middleware('throttle:20,1')->name('outcome');
        Route::post('/{delivery}/delay', [AdminDeliveryController::class, 'delay'])->whereNumber('delivery')->middleware('throttle:20,1')->name('delay');
        Route::post('/sla-rules', [AdminDeliveryController::class, 'storeSlaRule'])->middleware('throttle:10,1')->name('sla-rules.store');
        Route::put('/sla-rules/{deliverySlaRule}', [AdminDeliveryController::class, 'updateSlaRule'])->middleware('throttle:10,1')->name('sla-rules.update');
    });

    Route::prefix('admin/audit-logs')->name('admin.audit-logs.')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/{auditLog}', [AuditLogController::class, 'show'])->name('show');
    });
    Route::get('/admin/staff', [StaffController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.staff.index');
    Route::get('/admin/staff/departments', [StaffDepartmentController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.staff.departments.index');
    Route::get('/admin/staff/tasks', [StaffTaskController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.staff.tasks.index');
    Route::post('/admin/staff/departments', [StaffDepartmentController::class, 'store'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:5,1')
        ->name('admin.staff.departments.store');
    Route::patch('/admin/staff/departments/{department}', [StaffDepartmentController::class, 'update'])
        ->whereNumber('department')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:5,1')
        ->name('admin.staff.departments.update');
    Route::get('/admin/staff/create', [StaffController::class, 'create'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.staff.create');
    Route::post('/admin/staff', [StaffController::class, 'store'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:5,1')
        ->name('admin.staff.store');
    Route::get('/admin/staff/{staff}', [StaffController::class, 'show'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.staff.show');
    Route::post('/admin/staff/{staff}/sessions/revoke', [StaffController::class, 'revokeSessions'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:5,1')
        ->name('admin.staff.sessions.revoke');
    Route::post('/admin/staff/{staff}/invitation/resend', [StaffController::class, 'resendInvitation'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:3,1')
        ->name('admin.staff.invitation.resend');
    Route::patch('/admin/staff/{staff}/status', [StaffController::class, 'changeStatus'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:5,1')
        ->name('admin.staff.status.update');
    Route::patch('/admin/staff/{staff}/roles', [StaffController::class, 'updateRoles'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:5,1')
        ->name('admin.staff.roles.update');
    Route::patch('/admin/staff/bulk/roles', [StaffController::class, 'bulkUpdateRoles'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:5,1')->name('admin.staff.roles.bulk-update');
    Route::patch('/admin/staff/{staff}/permissions', [StaffController::class, 'updatePermissions'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:5,1')
        ->name('admin.staff.permissions.update');
    Route::patch('/admin/staff/{staff}/profile', [StaffController::class, 'updateProfile'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:5,1')
        ->name('admin.staff.profile.update');
    Route::patch('/admin/staff/{staff}/email', [StaffController::class, 'updateEmail'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:3,1')
        ->name('admin.staff.email.update');
    Route::patch('/admin/staff/{staff}/scope-schedule', [StaffController::class, 'updateScopeSchedule'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:5,1')
        ->name('admin.staff.scope-schedule.update');
    Route::post('/admin/staff/{staff}/terminate', [StaffController::class, 'terminate'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:3,1')
        ->name('admin.staff.terminate');
    Route::post('/admin/staff/{staff}/tasks', [StaffTaskController::class, 'store'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:10,1')
        ->name('admin.staff.tasks.store');
    Route::post('/admin/staff/{staff}/notes', [StaffNoteController::class, 'store'])
        ->whereNumber('staff')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:10,1')
        ->name('admin.staff.notes.store');
    Route::patch('/admin/staff/tasks/{task}', [StaffTaskController::class, 'update'])
        ->whereNumber('task')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:10,1')
        ->name('admin.staff.tasks.update');
    Route::get('/admin/contact-messages', [AdminContactMessageController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.contact-messages.index');
    Route::get('/admin/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])
        ->whereNumber('contactMessage')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.contact-messages.show');
    Route::patch('/admin/contact-messages/{contactMessage}/assignment', [AdminContactMessageController::class, 'assign'])
        ->whereNumber('contactMessage')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:30,1')
        ->name('admin.contact-messages.assign');
    Route::post('/admin/contact-messages/{contactMessage}/replies', [AdminContactMessageController::class, 'reply'])
        ->whereNumber('contactMessage')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:30,1')
        ->name('admin.contact-messages.reply');
    Route::patch('/admin/contact-messages/{contactMessage}/status', [AdminContactMessageController::class, 'transition'])
        ->whereNumber('contactMessage')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:30,1')
        ->name('admin.contact-messages.transition');
    Route::get('/admin/settings', [MarketplaceSettingsController::class, 'edit'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.settings.edit');
    Route::get('/admin/settlements', [\App\Http\Controllers\Admin\SettlementController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.settlements.index');
    Route::post('/admin/settlements/{delivery}/close-dispute', [\App\Http\Controllers\Admin\SettlementController::class, 'close'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:20,1')->name('admin.settlements.close-dispute');
    Route::post('/deliveries/{delivery}/dispute', [\App\Http\Controllers\DeliveryDisputeController::class, 'store'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:10,1')->name('delivery-disputes.store');
    Route::post('/deliveries/{delivery}/rating', [\App\Http\Controllers\DeliveryRatingController::class, 'store'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:10,1')->name('delivery-ratings.store');
    Route::put('/admin/settings', [MarketplaceSettingsController::class, 'update'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:10,1')
        ->name('admin.settings.update');
    Route::post('/deliveries/{delivery}/refund-request', [\App\Http\Controllers\RefundRequestController::class, 'store'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:10,1')->name('refund-requests.store');
    Route::get('/admin/refunds', [\App\Http\Controllers\Admin\RefundController::class, 'index'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('admin.refunds.index');
    Route::middleware(\App\Http\Middleware\PrivateFinancialResponse::class)->group(function () {
        Route::get('/admin', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/admin/refund-transfers', [\App\Http\Controllers\Admin\RefundTransferController::class, 'index'])->name('admin.refund-transfers.index');
        Route::get('/admin/refund-transfers/{refundRequest}', [\App\Http\Controllers\Admin\RefundTransferController::class, 'show'])->name('admin.refund-transfers.show');
        Route::get('/admin/refund-transfers/{refundRequest}/check', [\App\Http\Controllers\Admin\RefundTransferController::class, 'check'])
            ->middleware('throttle:20,1')->name('admin.refund-transfers.check');
        Route::post('/admin/refund-transfers/{refundRequest}/prepare', [\App\Http\Controllers\Admin\RefundTransferController::class, 'prepare'])
            ->middleware('throttle:10,1')->name('admin.refund-transfers.prepare');
        Route::post('/admin/refund-transfers/{refundRequest}/paid', [\App\Http\Controllers\Admin\RefundTransferController::class, 'paid'])
            ->middleware('throttle:10,1')->name('admin.refund-transfers.paid');
        Route::get('/refund-transfers/{refundTransfer}/proof', [\App\Http\Controllers\RefundTransferProofController::class, 'show'])->name('refund-transfers.proof');
        Route::post('/admin/refund-transfer-attempts/{refundTransfer}/cancel', [\App\Http\Controllers\Admin\RefundTransferController::class, 'cancel'])
            ->middleware('throttle:10,1')->name('admin.refund-transfers.cancel');
        Route::get('/refund-requests/{refundRequest}/destination', [\App\Http\Controllers\RefundDestinationController::class, 'index'])->name('refund-destinations.index');
        Route::post('/refund-requests/{refundRequest}/destination', [\App\Http\Controllers\RefundDestinationController::class, 'store'])
            ->middleware('throttle:5,1')->name('refund-destinations.store');
        Route::get('/refund-destinations/{refundDestination}', [\App\Http\Controllers\RefundDestinationController::class, 'show'])->name('refund-destinations.show');
        Route::post('/refund-destinations/{refundDestination}/revoke', [\App\Http\Controllers\RefundDestinationController::class, 'revoke'])
            ->middleware('throttle:10,1')->name('refund-destinations.revoke');
        Route::get('/admin/refund-destinations', [\App\Http\Controllers\Admin\RefundDestinationController::class, 'index'])->name('admin.refund-destinations.index');
        Route::post('/admin/refund-destinations/{refundDestination}/review', [\App\Http\Controllers\Admin\RefundDestinationController::class, 'review'])
            ->middleware('throttle:10,1')->name('admin.refund-destinations.review');
    });
    Route::post('/admin/refunds/{refundRequest}/review', [\App\Http\Controllers\Admin\RefundController::class, 'review'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->middleware('throttle:10,1')->name('admin.refunds.review');

    Route::prefix('delivery/tasks')->name('delivery.tasks.')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)->group(function () {
        Route::get('/', [DeliveryTaskController::class, 'index'])->name('index');
        Route::patch('/availability', [DeliveryTaskController::class, 'availability'])
            ->middleware('throttle:20,1')->name('availability');
        Route::post('/{delivery}/accept', [DeliveryTaskController::class, 'accept'])
            ->middleware('throttle:20,1')->name('accept');
        Route::post('/{delivery}/milestones/{milestone}', [DeliveryTaskController::class, 'milestone'])
            ->where('milestone', 'heading-to-merchant|merchant-arrived|out-for-delivery|customer-arrived')
            ->middleware('throttle:30,1')->name('milestone');
        Route::post('/{delivery}/delay', [DeliveryTaskController::class, 'delay'])->middleware('throttle:20,1')->name('delay');
        Route::post('/{delivery}/picked-up', [DeliveryTaskController::class, 'pickedUp'])
            ->middleware('throttle:20,1')->name('picked-up');
        Route::post('/{delivery}/in-transit', [DeliveryTaskController::class, 'inTransit'])
            ->middleware('throttle:20,1')->name('in-transit');
        Route::post('/{delivery}/delivered', [DeliveryTaskController::class, 'delivered'])
            ->middleware('throttle:10,1')->name('delivered');
    });
    Route::get('/delivery-proofs/{deliveryProof}', [DeliveryProofController::class, 'show'])
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
        ->name('delivery-proofs.show');

    Route::prefix('admin/merchant-verifications')->name('admin.merchants.')
        ->middleware(\App\Http\Middleware\PrivateFinancialResponse::class)->group(function () {
        Route::get('/', [MerchantVerificationController::class, 'index'])->name('index');
        Route::get('/{merchant}', [MerchantVerificationController::class, 'show'])->name('show');
        Route::post('/{merchant}/approve', [MerchantVerificationController::class, 'approve'])->middleware('throttle:10,1')->name('approve');
        Route::post('/{merchant}/request-changes', [MerchantVerificationController::class, 'requestChanges'])->middleware('throttle:10,1')->name('request-changes');
        Route::post('/{merchant}/reject', [MerchantVerificationController::class, 'reject'])->middleware('throttle:10,1')->name('reject');
        Route::post('/{merchant}/suspend', [MerchantVerificationController::class, 'suspend'])->middleware('throttle:10,1')->name('suspend');
        Route::patch('/documents/{merchantDocument}', [MerchantDocumentReviewController::class, 'update'])
            ->middleware('throttle:20,1')->name('documents.review');
    });
});

Route::get('/contact-us', [ContactController::class, 'create'])
    ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
    ->name('contact.create');
Route::post('/contact-us', [ContactController::class, 'store'])
    ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:5,1'])
    ->name('contact.store');

Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/c', [CategoryController::class, 'index'])->name('categories.legacy-index');
Route::get('/c/men', [CategoryController::class, 'gender'])->defaults('gender', 'men')->name('cat.men');
Route::get('/c/women', [CategoryController::class, 'gender'])->defaults('gender', 'women')->name('cat.women');

Route::redirect('/c/beauty/makeup/nailpolish', '/c/beauty/makeup/nails', 301);
Route::redirect('/c/perfume/{tail?}', '/c/perfumes/{tail}', 301)->where('tail', '.*');
Route::get('/c/health/{tail?}', fn (?string $tail = null) => redirect('/c/sporthealth/health'.($tail ? "/{$tail}" : ''), 301))->where('tail', '.*');
Route::get('/c/sport/{tail?}', fn (?string $tail = null) => redirect('/c/sporthealth/sport'.($tail ? "/{$tail}" : ''), 301))->where('tail', '.*');

Route::get('/c/home/{leaf}', function (string $leaf) {
    $groups = [
        'furniture' => ['curtains', 'bath', 'towels', 'tables', 'blankets', 'decor', 'baskets', 'pillows'],
        'accessories' => ['office', 'kitchen', 'cleaning'],
        'garden' => ['lighting', 'garden', 'pets', 'appliances'],
    ];
    foreach ($groups as $group => $leaves) {
        if (in_array($leaf, $leaves, true)) {
            return redirect("/c/home/{$group}/{$leaf}", 301);
        }
    }
    abort(404);
});

Route::get('/c/sporthealth/{leaf}', function (string $leaf) {
    if (in_array($leaf, ['shakers', 'supplements', 'home-gym', 'football', 'other'], true)) {
        return redirect("/c/sporthealth/sport/{$leaf}", 301);
    }
    if (in_array($leaf, ['braces', 'massage', 'personal', 'pillows', 'insoles', 'supplies'], true)) {
        return redirect("/c/sporthealth/health/{$leaf}", 301);
    }
    abort(404);
});

Route::get('/c/bags/{leaf}', function (string $leaf) {
    $groups = [
        'men' => ['wallets-men', 'belts', 'sunglasses'],
        'women' => ['shoulder', 'laptop', 'sport', 'wallets-women', 'accessories-women'],
        'school' => ['school', 'stationery', 'others'],
    ];
    foreach ($groups as $group => $leaves) {
        if (in_array($leaf, $leaves, true)) {
            return redirect("/c/bags/{$group}/{$leaf}", 301);
        }
    }
    abort(404);
});

Route::get('/c/{path}', [CategoryController::class, 'show'])->where('path', '.*')->name('categories.show');

Route::get('/p/{slug}', [ProductController::class, 'show'])
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9_-]{0,199}')
    ->name('product.show');
Route::get('/brands/{brand:slug}', [BrandController::class, 'show'])->name('brands.show');
Route::get('/shoes/women', [WomenShoesController::class, 'index'])->name('shoes.women');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/deals', [DealsController::class, 'index'])->name('deals.index');
Route::get('/new-products', [NewProductsController::class, 'index'])->name('new.index');
Route::get('/policies', [PublicPolicyController::class, 'index'])->name('policies');
Route::view('/faq', 'content.faq')->name('faq');

Route::get('/cart', [CartController::class, 'index'])
    ->middleware(\App\Http\Middleware\PrivateAccountResponse::class)
    ->name('cart.index');
Route::get('/cart/count', [CartController::class, 'count'])
    ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:120,1'])
    ->name('cart.count');
Route::post('/cart/add', [CartController::class, 'add'])
    ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:60,1'])
    ->name('cart.add');
Route::post('/cart', [CartController::class, 'add'])
    ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:60,1'])
    ->name('cart.add.legacy');
Route::patch('/cart/{key}', [CartController::class, 'updateQty'])
    ->where('key', '[^/]{1,200}')
    ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:60,1'])
    ->name('cart.update');
Route::delete('/cart/{key}', [CartController::class, 'remove'])
    ->where('key', '[^/]{1,200}')
    ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:60,1'])
    ->name('cart.remove');
Route::delete('/cart', [CartController::class, 'clear'])
    ->middleware([\App\Http\Middleware\PrivateAccountResponse::class, 'throttle:20,1'])
    ->name('cart.clear');
