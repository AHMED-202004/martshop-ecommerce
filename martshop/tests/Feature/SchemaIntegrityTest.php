<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_queue_queries_have_composite_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex(
            'stock_reservations',
            ['status', 'expires_at', 'merchant_order_id'],
        ));
        $this->assertTrue(Schema::hasIndex(
            'deliveries',
            ['status', 'settled_at', 'settlement_due_at', 'id'],
        ));
    }

    public function test_managed_department_relationships_have_lookup_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex('staff_departments', ['manager_id']));
        $this->assertTrue(Schema::hasIndex('users', ['staff_department_id']));
    }

    public function test_payment_provider_reference_is_idempotent_per_provider(): void
    {
        DB::table('payments')->insert([
            'order_no' => 'ORDER-1', 'amount' => 100, 'currency' => 'ILS',
            'provider' => 'manual', 'provider_ref' => 'REF-1', 'status' => 'pending',
        ]);

        $this->expectException(QueryException::class);
        DB::table('payments')->insert([
            'order_no' => 'ORDER-2', 'amount' => 100, 'currency' => 'ILS',
            'provider' => 'manual', 'provider_ref' => 'REF-1', 'status' => 'pending',
        ]);
    }

    public function test_order_requires_an_existing_user(): void
    {
        $this->expectException(QueryException::class);
        DB::table('orders')->insert([
            'user_id' => 999999, 'total' => 10,
            'status' => 'pending', 'payment_method' => 'cod',
        ]);
    }

    public function test_login_phone_is_unique_at_database_level(): void
    {
        $attributes = [
            'name' => 'First user', 'email' => 'first@example.test', 'phone' => '0599000111',
            'password' => 'not-used-in-this-test', 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('users')->insert($attributes);

        $this->expectException(QueryException::class);
        DB::table('users')->insert(array_replace($attributes, ['email' => 'second@example.test']));
    }

    public function test_phone_unique_migration_refuses_ambiguous_existing_accounts(): void
    {
        $migration = require database_path('migrations/2026_09_10_000024_add_unique_login_phone_to_users.php');
        $migration->down();
        DB::table('users')->insert([
            ['name' => 'First user', 'email' => 'first@example.test', 'phone' => '0599000222',
                'password' => 'unused', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Second user', 'email' => 'second@example.test', 'phone' => '0599000222',
                'password' => 'unused', 'created_at' => now(), 'updated_at' => now()],
        ]);

        try {
            $migration->up();
            $this->fail('The migration must reject duplicate login phones.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('duplicate user phone', $exception->getMessage());
        } finally {
            DB::table('users')->delete();
            $migration->up();
        }
    }

    public function test_phone_unique_migration_is_safe_when_the_index_already_exists(): void
    {
        $migration = require database_path('migrations/2026_09_10_000024_add_unique_login_phone_to_users.php');

        $migration->up();

        $this->assertTrue(Schema::hasIndex('users', ['phone'], 'unique'));
    }
}
