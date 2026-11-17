<?php

namespace App\Models;

use Database\Factories\CrewBidCredentialVaultFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Hidden(['envelope', 'user_id'])]
class CrewBidCredentialVault extends Model
{
    /** @use HasFactory<CrewBidCredentialVaultFactory> */
    use HasFactory;

    use HasUlids;

    /** @return array{envelope: 'array'} */
    protected function casts(): array
    {
        return ['envelope' => 'array'];
    }
}
