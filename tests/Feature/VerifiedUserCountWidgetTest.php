<?php

namespace Tests\Feature;

use App\Filament\Widgets\VerifiedUserCount;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifiedUserCountWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_stat_counts_only_users_with_verified_email_addresses(): void
    {
        User::factory()->count(2)->create();
        User::factory()->unverified()->count(3)->create();

        $stat = $this->stat();

        $this->assertSame('Verified Users', $stat->getLabel());
        $this->assertSame(2, $stat->getValue());
        $this->assertSame('Users with verified email addresses', $stat->getDescription());
    }

    public function test_stat_displays_zero_when_no_users_are_verified(): void
    {
        User::factory()->unverified()->count(2)->create();

        $this->assertSame(0, $this->stat()->getValue());
    }

    public function test_admins_can_see_the_verified_user_count_widget_on_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertSee('wire:name="App\\Filament\\Widgets\\VerifiedUserCount"', escape: false);
    }

    private function stat(): Stat
    {
        $widget = new class extends VerifiedUserCount
        {
            /**
             * @return array<Stat>
             */
            public function stats(): array
            {
                return $this->getStats();
            }
        };

        return $widget->stats()[0];
    }
}
