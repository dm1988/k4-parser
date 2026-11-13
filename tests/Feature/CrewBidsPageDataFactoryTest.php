<?php

namespace Tests\Feature;

use App\DTOs\CrewBidBrowseCriteria;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use App\View\Models\CrewBidsPageDataFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewBidsPageDataFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_package_produces_empty_browsing_and_calendar_data(): void
    {
        $user = User::factory()->admin()->create();
        $criteria = new CrewBidBrowseCriteria(
            seat: 'FO',
            preferences: ['goals' => [], 'days_off' => [], 'start_city' => '', 'initialized' => false],
        );

        $data = app(CrewBidsPageDataFactory::class)->make(null, $user, $criteria);
        $viewData = $data->toViewData();

        $this->assertNull($data->package);
        $this->assertSame([], $viewData['lines']);
        $this->assertSame([], $viewData['allLines']);
        $this->assertSame([], $viewData['selected']);
        $this->assertSame([], $viewData['cities']);
        $this->assertSame([], $viewData['layoverCities']);
        $this->assertSame([], $viewData['lineCards']);
        $this->assertSame([], $data->dates);
        $this->assertNull($data->monthLabel);
        $this->assertNull($data->packageStartDate);
        $this->assertNull($data->packageEndDate);
        $this->assertNull($data->latest);
        $this->assertNull($data->target);
        $this->assertSame([], $data->revisionChanges);
        $this->assertSame(0, $viewData['total']);
        $this->assertSame(0, $data->activeFilterCount);
        $this->assertSame('No active browsing filters', $data->activeFilterSummary);
        $this->assertNull($data->aiPrompt);
    }

    public function test_filtered_page_retains_manual_ranks_and_unavailable_selected_choices(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F3'] = array_replace($payload['lines']['F1'], ['id' => 'F3', 'credit' => 4200]);
        $package->update(['payload' => $payload]);
        $user = User::factory()->admin()->create();
        $criteria = new CrewBidBrowseCriteria(
            seat: 'FO',
            preferences: ['goals' => [], 'days_off' => [], 'start_city' => '', 'initialized' => true],
            selections: ['missing', 'R2', 'F3'],
            minimumCredit: 70,
        );

        $data = app(CrewBidsPageDataFactory::class)->make($package, $user, $criteria);
        $viewData = $data->toViewData();

        $this->assertSame(['F3'], array_column($viewData['lines'], 'id'));
        $this->assertSame(['F1', 'R2', 'F3'], array_keys($viewData['allLines']));
        $this->assertSame(['missing', 'R2', 'F3'], array_column($viewData['selected'], 'id'));
        $this->assertSame([
            'id' => 'missing', 'credit_label' => 'Not available', 'block_label' => 'Not available',
            'days_off' => null, 'warnings' => ['Unavailable in this revision'],
        ], $viewData['selected'][0]);
        $this->assertSame('F3', $data->lineCards[0]->id);
        $this->assertSame(3, $data->lineCards[0]->rank);
        $this->assertSame(2, $viewData['total']);
        $this->assertSame(['ANC'], $viewData['cities']);
        $this->assertSame("1. missing\n2. R2\n3. F3", $data->copyText);
        $this->assertNull($data->aiPrompt);
        $this->assertSame(1, $data->activeFilterCount);
        $this->assertSame('Min credit 70h', $data->activeFilterSummary);
        $this->assertSame($payload, $package->fresh()->payload);
    }

    public function test_ai_form_prompt_rejects_repeated_portal_numbers(): void
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['Q1'] = array_replace($payload['lines']['R2'], ['id' => 'Q1']);
        $package->update(['payload' => $payload]);
        $user = User::factory()->admin()->create();
        $criteria = new CrewBidBrowseCriteria(
            seat: 'FO',
            preferences: ['goals' => [], 'days_off' => [], 'start_city' => '', 'initialized' => true],
            selections: ['F1', 'Q1'],
        );

        $data = app(CrewBidsPageDataFactory::class)->make($package, $user, $criteria);

        $this->assertNull($data->aiPrompt);
    }

    public function test_package_queries_use_the_supplied_users_seat_and_draft_ownership(): void
    {
        $user = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $this->actingAs($other);
        $package = BidPackage::factory()->create();
        $ownedArchive = BidPackage::factory()->create(['archived_at' => now()]);
        $otherSeatArchive = BidPackage::factory()->create(['archived_at' => now()]);
        $otherUserArchive = BidPackage::factory()->create(['archived_at' => now()]);
        BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $ownedArchive->id, 'seat' => 'FO']);
        BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $otherSeatArchive->id, 'seat' => 'CA']);
        BidWorkspace::factory()->create(['user_id' => $other->id, 'bid_package_id' => $otherUserArchive->id, 'seat' => 'FO']);
        $revision = BidPackage::factory()->create();
        BidPackage::factory()->create(['archived_at' => now()]);
        $foreignDraft = BidPackage::factory()->create(['imported_by' => $other->id, 'published_at' => null]);
        $criteria = new CrewBidBrowseCriteria(
            seat: 'FO',
            preferences: ['goals' => [], 'days_off' => [], 'start_city' => '', 'initialized' => true],
        );

        $data = app(CrewBidsPageDataFactory::class)->make($package, $user, $criteria, $foreignDraft->id, $revision->id);

        $this->assertSame([$revision->id, $ownedArchive->id, $package->id], $data->packages->modelKeys());
        $this->assertSame($revision->id, $data->latest?->id);
        $this->assertSame($revision->id, $data->target?->id);
        $this->assertNull($data->draft);
        $this->assertSame([], $data->revisionChanges);
    }
}
