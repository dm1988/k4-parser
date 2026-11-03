<?php

namespace Tests\Feature;

use App\Models\CrewBidPayProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrewPayProfileMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('legacyFlags')]
    public function test_migration_preserves_private_values_and_requires_review_for_conflicts(array $flags, ?bool $expected): void
    {
        $user = User::factory()->create();
        $untouched = User::factory()->create();
        $migration = require database_path('migrations/2026_11_03_082019_move_crew_pay_settings_to_users_table.php');
        $migration->down();
        $scenarios = [];
        foreach ($flags as $key => $flag) {
            $scenarios[$key] = ['qualified' => true, 'check_airman' => $flag, 'lines' => ['F1' => ['reviewed' => true]]];
        }
        $profile = CrewBidPayProfile::factory()->create(['user_id' => $user->id,
            'settings' => ['longevity_date' => '2020-02-29', 'show_compact' => true], 'scenarios' => $scenarios, 'revision' => 3]);

        $migration->up();

        $personal = $user->fresh()->crew_pay_settings;
        $this->assertSame('2020-02-29', $personal['longevity_date']);
        $this->assertSame($expected, $personal['check_airman']);
        $this->assertNull($untouched->fresh()->crew_pay_settings);
        $raw = DB::table('users')->where('id', $user->id)->value('crew_pay_settings');
        $this->assertStringNotContainsString('2020-02-29', $raw);
        $this->assertSame($personal, json_decode(Crypt::decryptString($raw), true));
        $profile->refresh();
        $this->assertSame(['show_compact' => true], $profile->settings);
        $this->assertSame(4, $profile->revision);
        foreach ($profile->scenarios as $scenario) {
            $this->assertArrayNotHasKey('check_airman', $scenario);
            $this->assertSame(['F1' => ['reviewed' => true]], $scenario['lines']);
        }
        if ($expected === null) {
            $this->assertSame($flags, $personal['previous_check_airman_choices']);
        }

        $migration->down();

        $profile->refresh();
        $this->assertSame('2020-02-29', $profile->settings['longevity_date']);
        foreach ($flags as $key => $flag) {
            $this->assertSame($flag, $profile->scenarios[$key]['check_airman']);
        }
        $migration->up();
    }

    /** @return array<string, array{array<string, bool>, ?bool}> */
    public static function legacyFlags(): array
    {
        return [
            'enabled consistently' => [['1:CA' => true, '2:CA' => true], true],
            'disabled consistently' => [['1:CA' => false, '2:FO' => false], false],
            'no package scenario' => [[], false],
            'conflicting scenarios' => [['1:CA' => true, '2:FO' => false], null],
        ];
    }
}
