<?php

namespace Tests\Feature;

use App\Actions\ShouldPromptForCoffee;
use App\Models\ExtractRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoffeePromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_eligibility_uses_only_the_users_successful_non_empty_extractions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $eligibility = app(ShouldPromptForCoffee::class);

        $this->createExtractRequests($user, 7);
        $this->assertFalse($eligibility->handle($user));

        $this->createExtractRequests($user, 1);
        $this->assertTrue($eligibility->handle($user));

        $this->createExtractRequests($user, 1, status: 'failed');
        $this->createExtractRequests($user, 1, detectedEventCount: 0);
        $this->createExtractRequests($otherUser, 2);
        $this->assertTrue($eligibility->handle($user));

        $this->createExtractRequests($user, 1);
        $this->assertFalse($eligibility->handle($user));

        $this->createExtractRequests($user, 1);
        $this->assertTrue($eligibility->handle($user));

        $user->update(['has_bought_coffee' => true]);
        $this->assertFalse($eligibility->handle($user));
    }

    public function test_user_coffee_status_defaults_casts_factory_state_and_extract_request_relationship(): void
    {
        $user = User::factory()->create();
        $purchaser = User::factory()->boughtCoffee()->create();
        $this->createExtractRequests($user, 1);

        $this->assertFalse($user->has_bought_coffee);
        $this->assertIsBool($user->has_bought_coffee);
        $this->assertTrue($purchaser->has_bought_coffee);
        $this->assertCount(1, $user->extractRequests);
        $this->assertSame($user->getKey(), $user->extractRequests->first()?->user_id);
    }

    public function test_shared_coffee_modal_is_accessible_uses_the_configured_safe_link_and_is_not_open_on_reload(): void
    {
        Config::set('services.buy_me_a_coffee.url', 'https://example.com/support-crew-compass');

        $response = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('buy-me-a-coffee', escape: false)
            ->assertSee('role="dialog"', escape: false)
            ->assertSee('aria-modal="true"', escape: false)
            ->assertSee('aria-labelledby="buy-me-a-coffee-title"', escape: false)
            ->assertSee('aria-describedby="buy-me-a-coffee-description"', escape: false)
            ->assertSee('transform overflow-hidden rounded-lg bg-white shadow-xl transition-all', escape: false)
            ->assertSee('href="https://example.com/support-crew-compass"', escape: false)
            ->assertSee('target="_blank"', escape: false)
            ->assertSee('rel="noopener noreferrer"', escape: false)
            ->assertSee('style="display: none;"', escape: false);
    }

    private function createExtractRequests(
        User $user,
        int $count,
        string $status = 'success',
        int $detectedEventCount = 1,
    ): void {
        for ($requestNumber = 0; $requestNumber < $count; $requestNumber++) {
            ExtractRequest::create([
                'user_id' => $user->getKey(),
                'request_uuid' => (string) Str::uuid(),
                'source_type' => 'pasted_text',
                'parser_type' => 'published_roster',
                'status' => $status,
                'extraction_duration_ms' => 1,
                'detected_event_count' => $detectedEventCount,
                'detected_flight_count' => $detectedEventCount,
                'detected_hotel_count' => 0,
            ]);
        }
    }
}
