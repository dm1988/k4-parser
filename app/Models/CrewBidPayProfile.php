<?php

namespace App\Models;

use Database\Factories\CrewBidPayProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'settings', 'scenarios', 'revision'])]
#[Hidden(['settings', 'scenarios'])]
class CrewBidPayProfile extends Model
{
    /** @use HasFactory<CrewBidPayProfileFactory> */
    use HasFactory;

    /** @return array{settings: 'encrypted:array', scenarios: 'encrypted:array', revision: 'integer'} */
    protected function casts(): array
    {
        return ['settings' => 'encrypted:array', 'scenarios' => 'encrypted:array', 'revision' => 'integer'];
    }

    /** @return array<string, mixed> */
    public function context(int $packageId, string $seat, User $user): array
    {
        abort_if($this->exists && $this->user_id !== $user->id, 403);
        $personal = $user->crew_pay_settings ?? [];
        $settings = $this->settings ?? [];
        $settings['longevity_date'] = $personal['longevity_date'] ?? null;

        return array_replace($this->scenarios[$packageId.':'.$seat] ?? [], [
            'settings' => $settings,
            'check_airman' => array_key_exists('check_airman', $personal) ? $personal['check_airman'] : false,
        ]);
    }
}
