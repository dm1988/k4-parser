<?php

namespace Database\Factories;

use App\Models\CrewBidCredentialVault;
use App\Models\User;
use App\Services\CrewBids\CrewBidVaultCrypto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CrewBidCredentialVault>
 */
class CrewBidCredentialVaultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory()->crewBidsAccess(),
            'account_hint' => '••00',
            'envelope' => fn (array $attributes): array => app(CrewBidVaultCrypto::class)->seal($attributes['id'], (int) $attributes['user_id'], 'password', '900000', 'synthetic-password'),
        ];
    }
}
