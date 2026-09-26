<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_form_is_not_exposed_until_a_gateway_is_configured(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/payment/card.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/cart/quick-order.blade.php'));
        $this->actingAs(User::factory()->create())->get(route('payment.card'))
            ->assertRedirect(route('cart.index'))->assertSessionHasErrors('payment');
    }

    public function test_card_submission_cannot_report_a_fake_success(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('payment.card.charge'), [
            'card_number' => '4111111111111111', 'exp' => '12/30', 'cvv' => '123',
        ])->assertStatus(503)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
