<?php

namespace Tests\Feature;

use App\Livewire\ScheduleExtractor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ParserRequestValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->make());
    }

    public function test_removed_roster_post_route_is_not_available(): void
    {
        $this->post('/parse/roster')->assertNotFound();
    }

    public function test_livewire_rejects_invalid_event_type_filters(): void
    {
        Livewire::test(ScheduleExtractor::class)
            ->set('text', 'Roster text')
            ->set('eventTypes', ['not-a-real-type'])
            ->call('parseRoster')
            ->assertHasErrors(['eventTypes.0' => 'in']);
    }

    public function test_livewire_requires_text_or_a_supported_upload(): void
    {
        Livewire::test(ScheduleExtractor::class)
            ->call('parseRoster')
            ->assertHasErrors([
                'file' => 'required_without',
                'text' => 'required_without',
            ]);

        Livewire::test(ScheduleExtractor::class)
            ->set('file', UploadedFile::fake()->create('roster.csv', 10, 'text/csv'))
            ->call('parseRoster')
            ->assertHasErrors(['file' => 'mimes']);
    }
}
