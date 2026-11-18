<?php

namespace App\Services\CrewBids;

use App\DTOs\BidAwardsPageData;
use App\DTOs\ExtractedResultData;
use App\Enums\DutyEventType;
use App\Mappers\DutyEventMapper;
use App\Mappers\FlightMapper;
use App\Models\BidAwardReport;
use App\Models\User;
use App\Services\Schedule\AirportEnrichmentService;
use App\View\Models\Extract\FlightCardViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class AwardedBidSchedule
{
    public function __construct(private FlightMapper $flights, private DutyEventMapper $duties, private AirportEnrichmentService $airports) {}

    public function employee(User $user): ?string
    {
        return session('bid_awards_employee.'.$user->id) ?? $user->crew_bid_employee_number;
    }

    public function forUser(BidAwardReport $report, User $user, bool $enrich = false): BidAwardsPageData
    {
        Gate::forUser($user)->authorize('use-crew-bids');
        $package = $report->package;
        abort_unless($package !== null && $package->published_at !== null && ($package->archived_at === null
            || $package->workspaces()->where('user_id', $user->id)->exists()), 404);
        abort_unless($report->period === $package->period && $report->fleet === $package->fleet, 404);
        $employee = $this->employee($user);
        $mine = $employee === null ? null : ($report->awards[$employee] ?? null);
        $data = ['employeeRequired' => $employee === null, 'matched' => $mine !== null, 'crew' => [], 'days' => [], 'flights' => [], 'duties' => [],
            'lineLabel' => null, 'warnings' => [], 'result' => new ExtractedResultData(parsed: ['calendar_events' => []])];
        if ($mine === null) {
            return new BidAwardsPageData(...$data);
        }
        foreach ($report->awards as $award) {
            if ($award['line'] === $mine['line']) {
                $data['crew'][] = ['name' => $award['name'], 'seat' => $award['seat'], 'isUser' => $award['crew_id'] === $employee];
            }
        }
        usort($data['crew'], static fn (array $left, array $right): int => [$left['seat'], $left['name']] <=> [$right['seat'], $right['name']]);
        $payload = $package->payload;
        $matches = array_filter($payload['lines'] ?? [], static fn (array $line): bool => preg_match('/\A[FQR]([1-9]\d*)\z/', $line['id'], $match) === 1 && $match[1] === $mine['line']);
        $data['lineLabel'] = 'Line '.$mine['line'];
        if (count($matches) !== 1) {
            $data['warnings'][] = 'The awarded line cannot be uniquely matched to this package. Calendar and exports are unavailable.';

            return new BidAwardsPageData(...$data);
        }
        $line = reset($matches);
        $data['lineLabel'] = $line['id'];
        $month = CarbonImmutable::parse($report->period.'-01', 'UTC');
        $data['calendarOffset'] = (int) $month->format('w');
        $calendar = $line['calendar'] ?? [];
        $lastDate = $month->endOfMonth()->format('Y-m-d');
        $events = [];
        $seen = [];
        if ($line['type'] === 'reserve') {
            foreach ($calendar as $date => $day) {
                if ($day['status'] === 'occupied' && $this->date($date) && str_starts_with($date, $report->period.'-')) {
                    $start = CarbonImmutable::parse($date, 'UTC');
                    $events[] = $this->event($report, $line['id'], 'reserve:'.$date, 'duty', 'Reserve day · '.$line['id'], $start, $start->addDay(),
                        ['duty_type' => DutyEventType::GeneralDuty->value, 'all_day' => true, 'coverage' => 'Published reserve day; duty hours unavailable']);
                }
            }
        }
        foreach ($line['pairings'] ?? [] as $pairing) {
            $tripId = $pairing['id'];
            if (isset($seen[$tripId])) {
                continue;
            }
            $seen[$tripId] = true;
            $trip = $payload['trips'][$tripId] ?? null;
            if ($trip === null || ! $this->date($trip['start_date'] ?? null) || ! $this->date($trip['end_date'] ?? null)
                || $trip['end_date'] < $trip['start_date'] || $trip['start_date'] > $month->endOfMonth()->format('Y-m-d')) {
                $data['warnings'][] = 'Some linked trip dates are unavailable. Continuation coverage is incomplete.';

                continue;
            }
            $lastDate = max($lastDate, $trip['end_date']);
            for ($date = CarbonImmutable::parse($trip['start_date'], 'UTC'); $date->format('Y-m-d') <= $trip['end_date']; $date = $date->addDay()) {
                $calendar[$date->format('Y-m-d')] = ['status' => 'occupied', 'activity' => 'Trip '.$tripId.' · includes layover/rest'];
            }
            foreach ($trip['legs'] ?? [] as $index => $leg) {
                if (! $this->date($leg['date'] ?? null) || ! $this->clock($leg['departure'] ?? null) || ! $this->clock($leg['arrival'] ?? null)) {
                    $data['warnings'][] = 'Some flights have incomplete times and are excluded from ICS exports.';

                    continue;
                }
                $departure = CarbonImmutable::parse($leg['date'].' '.$leg['departure'], 'UTC');
                $arrival = CarbonImmutable::parse($leg['date'].' '.$leg['arrival'], 'UTC');
                if ($arrival < $departure) {
                    $arrival = $arrival->addDay();
                }
                if ($arrival <= $departure) {
                    $data['warnings'][] = 'Some flights have invalid time ranges and are excluded from ICS exports.';

                    continue;
                }
                $ground = str_starts_with($leg['flight'] ?? '', 'GT ');
                $deadhead = (bool) ($leg['deadhead'] ?? false);
                $metadata = ['origin' => $leg['origin'], 'destination' => $leg['destination'], 'trip_id' => (string) $tripId,
                    'flight_number' => $ground ? null : $leg['flight'], 'block_time' => $leg['block'] ?? null];
                if ($ground || $deadhead) {
                    $metadata['duty_type'] = $deadhead && ! $ground ? DutyEventType::Deadhead->value : DutyEventType::GeneralDuty->value;
                }
                $events[] = $this->event($report, $line['id'], $tripId.':leg:'.$index,
                    $ground || $deadhead ? 'duty' : 'flight', ($ground ? 'Ground transfer' : $leg['flight']).' '.$leg['origin'].'–'.$leg['destination'],
                    $departure, $arrival, $metadata);
            }
            $duties = $trip['duties'] ?? [];
            if ($duties === []) {
                $data['warnings'][] = 'Duty times are incomplete. Reimport the original Trips PDF for complete duty exports.';
            }
            foreach ($duties as $index => $duty) {
                $start = $duty['report_at'] ?? null;
                $end = $duty['release_at'] ?? null;
                if (! is_string($start) || ! is_string($end) || $end <= $start) {
                    $data['warnings'][] = 'Duty times are incomplete. Reimport the original Trips PDF for complete duty exports.';

                    continue;
                }
                $events[] = $this->event($report, $line['id'], $tripId.':duty:'.$index, 'duty', 'Flight duty · Trip '.$tripId,
                    CarbonImmutable::parse($start, 'UTC'), CarbonImmutable::parse($end, 'UTC'), ['duty_type' => DutyEventType::FlightDuty->value, 'trip_id' => (string) $tripId]);
                for ($date = CarbonImmutable::parse($start, 'UTC')->startOfDay(); $date->format('Y-m-d') <= substr($end, 0, 10); $date = $date->addDay()) {
                    $calendar[$date->format('Y-m-d')] = ['status' => 'duty', 'activity' => 'Duty · Trip '.$tripId];
                }
            }
        }
        if (($line['pairings'] ?? []) === [] && $line['type'] !== 'reserve') {
            $data['warnings'][] = 'No supported flight or duty details are linked to this line. Day-level calendar information is shown where available.';
        }
        usort($events, static fn (array $left, array $right): int => [$left['start'], $left['download_id']] <=> [$right['start'], $right['download_id']]);
        if ($enrich) {
            $events = $this->airports->enrich(['calendar_events' => $events])['calendar_events'];
        }
        for ($date = $month; $date->format('Y-m-d') <= $lastDate; $date = $date->addDay()) {
            $key = $date->format('Y-m-d');
            $entry = $calendar[$key] ?? ['status' => 'unknown', 'activity' => 'Unknown'];
            $data['days'][] = ['date' => $key, 'day' => $date->format('j'), 'weekday' => $date->format('D'), 'label' => $date->format('M j'),
                'transition' => $date->format('Y-m') !== $report->period, 'status' => $entry['status'], 'activity' => $entry['activity']];
        }
        foreach ($events as $event) {
            $flight = $this->flights->fromCalendarEvent($event, $event['download_id']);
            if ($flight !== null) {
                $url = route('crew-bids.awards.event', ['report' => $report->id, 'eventId' => $event['download_id']]);
                $data['flights'][] = FlightCardViewModel::fromFlight($flight->withDownloadUrl($url), enableDutyDownload: false);
            } else {
                $duty = $this->duties->fromCalendarEvent($event, $event['download_id']);
                if ($duty !== null) {
                    $data['duties'][] = $duty;
                }
            }
        }
        $data['warnings'] = array_values(array_unique($data['warnings']));
        $data['result'] = new ExtractedResultData(type: 'awards', source: $report->source, parsed: ['calendar_events' => $events,
            'trip' => ['calendar_name' => $report->period.' · B'.$report->fleet.' · '.$line['id'].' Awards',
                'calendar_description' => 'Awarded schedule from K4 Bids · UTC', 'filename_prefix' => 'k4-awards-'.$report->period.'-B'.$report->fleet.'-'.$line['id']]]);

        return new BidAwardsPageData(...$data);
    }

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function event(BidAwardReport $report, string $line, string $identity, string $type, string $title, CarbonImmutable $start, CarbonImmutable $end, array $metadata): array
    {
        $id = hash('sha256', $report->period.':'.$report->fleet.':'.$line.':'.$identity);

        $event = ['type' => $type, 'title' => $title, 'start' => $start->toIso8601String(), 'end' => $end->toIso8601String(),
            'timezone' => 'UTC', 'download_id' => $id, 'metadata' => $metadata + ['event_uid' => $id, 'awarded_line' => $line]];
        if (($metadata['all_day'] ?? false) === true) {
            $event['schedule_label'] = $start->format('Y-m-d').' · All day (UTC)';
            $event['duration_label'] = 'Day-level assignment; duty hours unavailable';
        }

        return $event;
    }

    private function date(mixed $value): bool
    {
        return is_string($value) && CarbonImmutable::hasFormat($value, 'Y-m-d');
    }

    private function clock(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $value) === 1;
    }
}
