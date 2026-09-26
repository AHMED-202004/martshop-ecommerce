<?php

namespace Tests\Feature;

use App\Enums\ProductOfferStatus;
use App\Enums\ProductStatus;
use App\Models\CatalogReviewDecision;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductModeratorPerformanceService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModeratorPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_catalog_review_metrics_use_persisted_reviewers_and_timestamps(): void
    {
        $manager = $this->staff('roles.manage', 'catalog-performance-manager');
        $moderator = $this->staff('products.moderate', 'catalog-performance-reviewer');
        $product = Product::create([
            'name' => 'Reviewed product', 'slug' => 'reviewed-product', 'price' => 10,
            'status' => ProductStatus::Active, 'submitted_at' => now()->subMinutes(90),
            'reviewed_at' => now()->subMinutes(60), 'reviewed_by' => $moderator->id,
        ]);
        ProductOffer::create([
            'product_id' => $product->id, 'price' => 10, 'currency' => 'ILS', 'stock' => 2,
            'status' => ProductOfferStatus::ChangesRequested, 'submitted_at' => now()->subMinutes(70),
            'reviewed_at' => now()->subMinutes(40), 'reviewed_by' => $moderator->id,
        ]);
        CatalogReviewDecision::create([
            'subject_type' => $product->getMorphClass(), 'subject_id' => $product->id,
            'reviewer_id' => $moderator->id, 'from_status' => 'pending_review', 'to_status' => 'active',
            'submitted_at' => now()->subMinutes(90), 'decided_at' => now()->subMinutes(60), 'is_reversal' => false,
        ]);
        CatalogReviewDecision::create([
            'subject_type' => ProductOffer::class, 'subject_id' => 1,
            'reviewer_id' => $moderator->id, 'from_status' => 'pending_review', 'to_status' => 'changes_requested',
            'submitted_at' => now()->subMinutes(70), 'decided_at' => now()->subMinutes(40), 'is_reversal' => false,
        ]);
        CatalogReviewDecision::create([
            'subject_type' => $product->getMorphClass(), 'subject_id' => $product->id,
            'reviewer_id' => $moderator->id, 'from_status' => 'hidden', 'to_status' => 'active',
            'decided_at' => now()->subMinutes(20), 'is_reversal' => true,
        ]);
        Product::create([
            'name' => 'Pending product', 'slug' => 'pending-product', 'price' => 12,
            'status' => ProductStatus::PendingReview, 'submitted_at' => now(),
        ]);

        $summary = app(ProductModeratorPerformanceService::class)->summary($manager, $moderator, 'week');
        $this->assertSame(3, $summary['reviewed']);
        $this->assertSame(2, $summary['approved']);
        $this->assertSame(0, $summary['rejected']);
        $this->assertSame(1, $summary['changes_requested']);
        $this->assertSame(30.0, $summary['average_review_minutes']);
        $this->assertSame(1, $summary['reversed_decisions']);
        $this->assertSame(1, $summary['pending_workload']);

        $this->actingAs($manager)->get(route('admin.staff.show', ['staff' => $moderator]))
            ->assertOk()->assertSee('أداء مراجعة الكتالوج')->assertSee('السرعة مؤشر تشغيلي فقط');
    }

    private function staff(string $permission, string $roleSlug): User
    {
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);
        $role->permissions()->attach(Permission::where('slug', $permission)->sole());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
