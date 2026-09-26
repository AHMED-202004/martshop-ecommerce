<?php

namespace Tests\Feature;

use App\Enums\MerchantDocumentType;
use App\Enums\MerchantVerificationStatus;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\User;
use App\Services\MerchantDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class MarketplaceIdentityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_hierarchy_and_merchant_identity_encryption(): void
    {
        $governorate = Location::create([
            'name' => 'Gaza', 'slug' => 'gaza', 'type' => 'governorate',
        ]);
        $city = Location::create([
            'parent_id' => $governorate->id,
            'name' => 'Gaza City', 'slug' => 'gaza-city', 'type' => 'city',
        ]);

        $merchant = Merchant::create([
            'user_id' => User::factory()->create()->id,
            'location_id' => $city->id,
            'legal_name' => 'Merchant Legal Name',
            'identity_number' => 'ID-123 456',
            'phone' => '0599000000',
            'date_of_birth' => '1990-01-01',
            'address' => 'Private address',
            'business_type' => 'retail',
            'verification_status' => MerchantVerificationStatus::Incomplete,
        ]);

        $rawIdentity = DB::table('merchants')->where('id', $merchant->id)->value('identity_number');
        $this->assertNotSame('ID-123 456', $rawIdentity);
        $this->assertSame('ID-123 456', $merchant->fresh()->identity_number);
        $this->assertSame($governorate->id, $city->parent->id);
        $this->assertSame(MerchantVerificationStatus::Incomplete, $merchant->verification_status);
    }

    public function test_merchant_document_is_written_only_to_private_storage_and_audited(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $merchant = Merchant::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Merchant', 'identity_number' => '998877',
            'phone' => '0599000001', 'date_of_birth' => '1990-01-01',
            'address' => 'Private address', 'business_type' => 'retail',
        ]);

        $document = app(MerchantDocumentStorage::class)->store(
            $merchant,
            MerchantDocumentType::IdentityFront,
            UploadedFile::fake()->create('identity.jpg', 100, 'image/jpeg'),
            $merchant->user,
        );

        Storage::disk('local')->assertExists($document->getRawOriginal('path'));
        Storage::disk('public')->assertMissing($document->getRawOriginal('path'));
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $document->sha256);
        $this->assertSame(
            hash('sha256', Storage::disk('local')->get($document->getRawOriginal('path'))),
            $document->sha256,
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'merchant.document_uploaded',
            'subject_id' => $document->id,
        ]);
    }

    public function test_legacy_document_integrity_backfill_is_dry_run_by_default_and_audited_on_apply(): void
    {
        Storage::fake('local');
        $merchant = Merchant::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Legacy Merchant', 'identity_number' => 'LEGACY-998877',
            'phone' => '0599000002', 'date_of_birth' => '1990-01-01',
            'address' => 'Private address', 'business_type' => 'retail',
        ]);
        $document = app(MerchantDocumentStorage::class)->store(
            $merchant,
            MerchantDocumentType::IdentityFront,
            UploadedFile::fake()->create('identity.jpg', 10, 'image/jpeg'),
            $merchant->user,
        );
        DB::table('merchant_documents')->where('id', $document->id)->update(['sha256' => null]);

        $this->artisan('merchant-documents:backfill-integrity')
            ->expectsOutput('Mode: dry-run; eligible: 1; updated: 0; invalid: 0.')
            ->assertSuccessful();
        $this->assertNull($document->fresh()->sha256);

        $this->artisan('merchant-documents:backfill-integrity', ['--apply' => true])
            ->expectsOutput('Mode: apply; eligible: 1; updated: 1; invalid: 0.')
            ->assertSuccessful();
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $document->fresh()->sha256);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'merchant.document_integrity_backfilled',
            'subject_id' => $document->id,
        ]);
    }

    public function test_audit_log_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $log = AuditLog::create(['action' => 'security.test', 'created_at' => now()]);

        try {
            $log->update(['action' => 'tampered']);
            $this->fail('Updating an audit log should fail.');
        } catch (LogicException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'security.test']);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
