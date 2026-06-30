<?php

namespace App\Console\Commands;

use App\Mappers\AeroDataBoxFlightMapper;
use App\Models\Aircraft;
use App\Models\FlightEvent;
use App\Services\AeroDataBoxClient;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Throwable;

class SyncAeroDataBoxFlights extends Command
{
    protected $signature = 'aerodatabox:sync-flights {--tail= : Sync only one registration}';

    protected $description = 'Sync flight events from AeroDataBox';

    public function handle(AeroDataBoxClient $client, AeroDataBoxFlightMapper $mapper): int
    {
        $aircraft = Aircraft::query()
            ->active()
            ->when($this->option('tail'), fn ($query, $tail) => $query->where('tail_number', strtoupper(trim($tail))))
            ->orderBy('tail_number')
            ->get();

        if ($aircraft->isEmpty()) {
            $this->warn('No matching active aircraft found.');

            return self::SUCCESS;
        }

        $dateFrom = now('UTC')->subDays(2);
        $dateTo = now('UTC')->addDays(2);
        $synced = 0;
        $skipped = 0;
        $failed = 0;
        $writeFailures = 0;

        foreach ($aircraft as $plane) {
            $this->info("Syncing {$plane->tail_number}");

            try {
                $payload = $client->flightsByRegistration($plane->tail_number, $dateFrom, $dateTo);

                foreach ($payload as $rawFlight) {
                    $flight = $mapper->map($rawFlight, $plane->tail_number);

                    if ($flight === null) {
                        $skipped++;

                        continue;
                    }

                    try {
                        FlightEvent::query()->updateOrCreate(
                            [
                                'source' => $flight->getSource(),
                                'external_id' => $flight->getExternalId(),
                            ],
                            $flight->toFlightEventAttributes($plane->getKey()),
                        );
                        $synced++;
                    } catch (QueryException $exception) {
                        report($exception);
                        $this->error(sprintf(
                            'Database write failed for %s %s (%s): %s',
                            $plane->tail_number,
                            $flight->getFlightNumber(),
                            $flight->getExternalId(),
                            $exception->getMessage(),
                        ));
                        $writeFailures++;
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Failed {$plane->tail_number}: {$exception->getMessage()}");
                $failed++;
            }

        }

        $this->newLine();
        $this->info("Synced {$synced}; skipped {$skipped}; database failures {$writeFailures}; aircraft failures {$failed}.");

        return $failed === 0 && $writeFailures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
