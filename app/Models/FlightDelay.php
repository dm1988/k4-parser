<?php

namespace App\Models;

use Database\Factories\FlightDelayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'flight_plan_key', 'flight_context', 'revision', 'logbook_received_at', 'flight_following_brief_at', 'fueling_completed_at', 'loading_completed_at', 'load_master_briefing_at', 'new_payload_received_at', 'new_recall_flight_plan_at', 'new_tlr_at', 'other_at', 'delay_started_at', 'delay_ended_at', 'other_label', 'other_details', 'notes', 'delay_cause', 'delay_timeline', 'actions_taken', 'additional_facts'])]
#[Hidden(['flight_context', 'other_label', 'other_details', 'notes', 'delay_cause', 'delay_timeline', 'actions_taken', 'additional_facts'])]
class FlightDelay extends Model
{
    /** @use HasFactory<FlightDelayFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{
     *     flight_context: 'encrypted:array',
     *     revision: 'integer',
     *     logbook_received_at: 'immutable_datetime',
     *     flight_following_brief_at: 'immutable_datetime',
     *     fueling_completed_at: 'immutable_datetime',
     *     loading_completed_at: 'immutable_datetime',
     *     load_master_briefing_at: 'immutable_datetime',
     *     new_payload_received_at: 'immutable_datetime',
     *     new_recall_flight_plan_at: 'immutable_datetime',
     *     new_tlr_at: 'immutable_datetime',
     *     other_at: 'immutable_datetime',
     *     delay_started_at: 'immutable_datetime',
     *     delay_ended_at: 'immutable_datetime',
     *     other_label: 'encrypted',
     *     other_details: 'encrypted',
     *     notes: 'encrypted',
     *     delay_cause: 'encrypted',
     *     delay_timeline: 'encrypted',
     *     actions_taken: 'encrypted',
     *     additional_facts: 'encrypted'
     * }
     */
    protected function casts(): array
    {
        return [
            'flight_context' => 'encrypted:array',
            'revision' => 'integer',
            'logbook_received_at' => 'immutable_datetime',
            'flight_following_brief_at' => 'immutable_datetime',
            'fueling_completed_at' => 'immutable_datetime',
            'loading_completed_at' => 'immutable_datetime',
            'load_master_briefing_at' => 'immutable_datetime',
            'new_payload_received_at' => 'immutable_datetime',
            'new_recall_flight_plan_at' => 'immutable_datetime',
            'new_tlr_at' => 'immutable_datetime',
            'other_at' => 'immutable_datetime',
            'delay_started_at' => 'immutable_datetime',
            'delay_ended_at' => 'immutable_datetime',
            'other_label' => 'encrypted',
            'other_details' => 'encrypted',
            'notes' => 'encrypted',
            'delay_cause' => 'encrypted',
            'delay_timeline' => 'encrypted',
            'actions_taken' => 'encrypted',
            'additional_facts' => 'encrypted',
        ];
    }
}
