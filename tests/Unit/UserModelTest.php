<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class UserModelTest extends \Tests\TestCase
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
}
