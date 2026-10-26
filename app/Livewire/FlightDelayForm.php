<?php

namespace App\Livewire;

use App\Actions\FlightPlan\SaveFlightDelay;
use App\Models\FlightDelay;
use App\Models\User;
use App\Services\FlightPlan\FlightDelayFields;
use App\Services\FlightPlan\FlightDelayReportFormatter;
use App\Services\Infrastructure\FlightPlanResultStore;
use App\Validation\FlightDelayValidationRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;
use Throwable;

class FlightDelayForm extends Component
{
    #[Locked]
    public string $flightPlanKey;

    #[Locked]
    public int $revision = 0;

    /** @var array<string, string> */
    public array $inputs = [];

    /** @var array<string, string> */
    #[Locked]
    public array $savedInputs = [];

    #[Locked]
    public ?string $savedAt = null;

    #[Locked]
    public bool $reportVisible = false;

    protected SaveFlightDelay $saveFlightDelay;

    protected FlightPlanResultStore $resultStore;

    public function boot(SaveFlightDelay $saveFlightDelay, FlightPlanResultStore $resultStore): void
    {
        $this->saveFlightDelay = $saveFlightDelay;
        $this->resultStore = $resultStore;
    }

    public function mount(string $flightPlanKey): void
    {
        $user = $this->authorizedUser();
        abort_unless(Str::isUlid($flightPlanKey) && $this->resultStore->get($user, $flightPlanKey) !== null, 404);
        $this->flightPlanKey = $flightPlanKey;
        $delay = $this->currentReport($user);
        $this->inputs = FlightDelayFields::inputs($delay);
        $this->savedInputs = $this->inputs;
        $this->revision = $delay->revision ?? 0;
        $this->savedAt = $delay?->updated_at?->utc()->format('Y-m-d H:i:s');
    }

    public function updatingInputs(mixed $value, ?string $key): void
    {
        $this->authorizedUser();
        abort_unless(
            $key === null
                ? is_array($value) && array_diff(array_keys($value), array_keys(FlightDelayFields::labels())) === []
                : array_key_exists($key, FlightDelayFields::labels()),
            422,
        );
    }

    public function updatedInputs(mixed $value, ?string $key): void
    {
        $this->reportVisible = false;

        if ($key === null) {
            $this->savePending();

            return;
        }

        $this->persist([$key => $value]);
    }

    public function setNow(string $field): void
    {
        $this->authorizedUser();
        abort_unless(array_key_exists($field, FlightDelayFields::timestamps()), 422);
        $this->inputs[$field] = now('UTC')->format('Y-m-d\TH:i');
        $this->reportVisible = false;
        $this->persist([$field => $this->inputs[$field]]);
    }

    public function savePending(): bool
    {
        $this->authorizedUser();
        $this->reportVisible = false;

        try {
            FlightDelayValidationRules::validate($this->inputs);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return false;
        }

        $this->resetValidation();
        $changes = array_filter(
            $this->inputs,
            fn (mixed $value, string $field): bool => $value !== ($this->savedInputs[$field] ?? ''),
            ARRAY_FILTER_USE_BOTH,
        );

        return $this->persist($changes);
    }

    public function generateReport(): void
    {
        if ($this->savePending()) {
            $this->reportVisible = true;
        }
    }

    public function render(FlightDelayReportFormatter $formatter): View
    {
        $user = $this->authorizedUser();
        $delay = $this->currentReport($user);
        $context = $delay ?? new FlightDelay([
            'flight_context' => FlightDelayFields::flightContext($this->resultStore->get($user, $this->flightPlanKey) ?? []),
        ]);

        return view('livewire.flight-delay-form', [
            'events' => FlightDelayFields::EVENTS,
            'durationFields' => FlightDelayFields::DURATION,
            'textFields' => FlightDelayFields::TEXT,
            'flightDetails' => $formatter->context($context),
            'reportText' => $this->reportVisible && $this->inputs === $this->savedInputs && $this->getErrorBag()->isEmpty()
                && ($delay->revision ?? 0) === $this->revision
                ? $formatter->format($context)
                : null,
        ]);
    }

    /** @param array<string, mixed> $changes */
    private function persist(array $changes): bool
    {
        $user = $this->authorizedUser();
        $this->resetValidation('save');

        try {
            $delay = $this->saveFlightDelay->handle($user, $this->flightPlanKey, $this->revision, $changes);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->resetValidation($field);

                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return false;
        } catch (Throwable $throwable) {
            report(new RuntimeException('Delay report save failed.', previous: $throwable));
            $this->addError('save', 'We could not save your changes. Your input is still here; retry saving before leaving.');

            return false;
        }

        $persisted = FlightDelayFields::inputs($delay);

        foreach (array_keys($changes) as $field) {
            $this->inputs[$field] = $persisted[$field];
            $this->savedInputs[$field] = $persisted[$field];
            $this->resetValidation('inputs.'.$field);
        }

        $this->revision = $delay->revision ?? 0;
        $this->savedAt = $delay?->updated_at?->utc()->format('Y-m-d H:i:s');

        return true;
    }

    private function currentReport(User $user): ?FlightDelay
    {
        return $user->flightDelays()->where('flight_plan_key', $this->flightPlanKey)->first();
    }

    private function authorizedUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->hasVerifiedEmail(), 403);
        abort_unless((bool) config('features.flight_release.enabled', true), 404);
        Gate::authorize('use-flight-release');

        return $user;
    }
}
