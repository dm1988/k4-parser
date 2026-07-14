<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    #[Test]
    public function it_has_the_expected_fillable_attributes_for_user_fields(): void
    {
        $user = new User;

        $this->assertSame([
            'name',
            'email',
            'email_verified_at',
            'password',
            'remember_token',
            'role',
            'is_active',
            'last_admin_login_at',
            'stripe_id',
            'pm_type',
            'pm_last_four',
            'trial_ends_at',
        ], $user->getFillable());
    }

    #[Test]
    public function it_resolves_feature_access_from_config_and_role(): void
    {
        Config::set('features.flight_release.enabled', true);
        Config::set('features.flight_release.for_all_users', false);
        Config::set('features.schedule_parser.enabled', true);
        Config::set('features.schedule_parser.for_all_users', true);
        Config::set('features.schedule_parser.duty_export_for_all_users', false);

        $admin = new User([
            'role' => 'admin',
        ]);
        $user = new User([
            'role' => 'user',
        ]);

        $this->assertTrue($admin->canUseFlightRelease());
        $this->assertFalse($user->canUseFlightRelease());
        $this->assertTrue($admin->canUseScheduleParser());
        $this->assertTrue($user->canUseScheduleParser());
        $this->assertTrue($admin->canExportScheduleParserDuty());
        $this->assertFalse($user->canExportScheduleParserDuty());
    }
}
