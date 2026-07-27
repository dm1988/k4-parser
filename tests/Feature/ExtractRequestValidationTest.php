<?php

namespace Tests\Feature;

use App\Livewire\ScheduleExtractor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ExtractRequestValidationTest extends TestCase
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
            ->call('extractRoster')
            ->assertHasErrors(['eventTypes.0' => 'in']);
    }

    public function test_livewire_requires_text_or_a_supported_upload(): void
    {
        Livewire::test(ScheduleExtractor::class)
            ->call('extractRoster')
            ->assertHasErrors([
                'files' => 'required_without',
                'text' => 'required_without',
            ]);

        Livewire::test(ScheduleExtractor::class)
            ->set('files', [UploadedFile::fake()->create('roster.csv', 10, 'text/csv')])
            ->call('extractRoster')
            ->assertHasErrors(['files.0' => 'mimes']);
    }

    public function test_livewire_limits_image_uploads_and_rejects_mixed_sources(): void
    {
        $images = array_map(
            static fn (int $number): UploadedFile => UploadedFile::fake()->image("roster-{$number}.png", 300, 200),
            range(1, 6),
        );

        Livewire::test(ScheduleExtractor::class)
            ->set('files', $images)
            ->call('extractRoster')
            ->assertHasErrors(['files' => 'max']);

        Livewire::test(ScheduleExtractor::class)
            ->set('files', [
                UploadedFile::fake()->image('roster.png', 300, 200),
                UploadedFile::fake()->create('roster.pdf', 120, 'application/pdf'),
            ])
            ->call('extractRoster')
            ->assertHasErrors(['files']);

        Livewire::test(ScheduleExtractor::class)
            ->set('files', [UploadedFile::fake()->image('roster.png', 300, 200)])
            ->set('text', 'Roster text')
            ->call('extractRoster')
            ->assertHasErrors(['files' => 'prohibits', 'text' => 'prohibits']);
    }
}
