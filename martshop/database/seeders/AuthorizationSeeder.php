<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'customer' => 'Customer',
            'merchant' => 'Merchant',
            'admin' => 'Admin',
            'delivery-worker' => 'Delivery Worker',
        ];

        foreach ($roles as $slug => $name) {
            Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_system' => true]
            );
        }

        $permissions = [
            'merchants.view' => ['View merchants', 'merchants'],
            'merchants.verify' => ['Verify merchants', 'merchants'],
            'merchant-documents.view' => ['View merchant identity documents', 'merchants'],
            'merchant-documents.review' => ['Review merchant identity documents', 'merchants'],
            'products.moderate' => ['Moderate products', 'catalog'],
            'orders.manage' => ['Manage orders', 'orders'],
            'payments.verify' => ['Verify payments', 'finance'],
            'payment-methods.manage' => ['Manage payment methods', 'finance'],
            'commissions.manage' => ['Manage commission rules', 'finance'],
            'ledger.view' => ['View merchant ledger', 'finance'],
            'withdrawals.approve' => ['Approve withdrawals', 'finance'],
            'withdrawals.settings' => ['Manage withdrawal policy', 'finance'],
            'deliveries.manage' => ['Manage deliveries', 'delivery'],
            'deliveries.view-own' => ['View own delivery tasks', 'delivery'],
            'deliveries.accept' => ['Accept assigned delivery tasks', 'delivery'],
            'deliveries.update-status' => ['Update own delivery status', 'delivery'],
            'deliveries.confirm' => ['Confirm delivery with proof and PIN', 'delivery'],
            'audit-logs.view' => ['View audit logs', 'administration'],
            'roles.manage' => ['Manage roles and permissions', 'administration'],
            'staff-tasks.view-own' => ['View own staff tasks', 'staff-tasks'],
            'staff-tasks.update-own' => ['Update own staff tasks', 'staff-tasks'],
            'staff-notes.view' => ['View internal staff notes', 'staff-notes'],
            'staff-notes.manage' => ['Add internal staff notes', 'staff-notes'],
            'staff-departments.view-managed' => ['View managed department staff', 'staff-departments'],
            'settings.manage' => ['Manage marketplace settings', 'administration'],
            'contact-messages.view' => ['View customer contact messages', 'support'],
            'contact-messages.manage' => ['Manage customer support tickets', 'support'],
            'settlements.manage' => ['Manage settlement dispute holds', 'finance'],
            'refunds.review' => ['Review customer refund requests', 'finance'],
            'refund-destinations.review' => ['Verify and view customer refund recipients', 'finance'],
            'refunds.pay' => ['Prepare and record manual refund transfers', 'finance'],
            'refunds.cancel' => ['Cancel confirmed unsent refund preparations', 'finance'],
        ];

        foreach ($permissions as $slug => [$name, $group]) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'group' => $group]
            );
        }

        $admin = Role::where('slug', 'admin')->firstOrFail();
        $admin->permissions()->sync(Permission::query()->pluck('id'));

        $deliveryWorker = Role::where('slug', 'delivery-worker')->firstOrFail();
        $deliveryWorker->permissions()->sync(Permission::query()
            ->whereIn('slug', [
                'deliveries.view-own', 'deliveries.accept', 'deliveries.update-status', 'deliveries.confirm',
                'staff-tasks.view-own', 'staff-tasks.update-own',
            ])
            ->pluck('id'));
    }
}
