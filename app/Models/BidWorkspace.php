<?php

namespace App\Models;

use Database\Factories\BidWorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'bid_package_id', 'seat', 'preferences', 'selections', 'revision'])]
#[Hidden(['preferences', 'selections'])]
class BidWorkspace extends Model
{
    /** @use HasFactory<BidWorkspaceFactory> */
    use HasFactory;

    /** @return array{preferences: 'encrypted:array', selections: 'encrypted:array', revision: 'integer'} */
    protected function casts(): array
    {
        return ['preferences' => 'encrypted:array', 'selections' => 'encrypted:array', 'revision' => 'integer'];
    }
}
