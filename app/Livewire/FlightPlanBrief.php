<?php

namespace App\Livewire;

use App\Actions\BuildFlightPlanPageData;
use App\Actions\HandleFlightPlanExtraction;
use App\Actions\ShouldPromptForCoffee;
use App\Exceptions\FlightRouteNotFoundException;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultCache;
use App\Validation\FlightPlanValidationRules;
use App\View\Models\FlightReleasePageViewModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LogicException;
use Throwable;

class FlightPlanBrief extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $flightRelease = null;

    #[Locked]
    public ?string $flightPlanKey = null;

    protected HandleFlightPlanExtraction $handleFlightPlanExtraction;

    protected ShouldPromptForCoffee $shouldPromptForCoffee;

    protected FlightPlanResultCache $flightPlanResultCache;

    protected BuildFlightPlanPageData $buildFlightPlanPageData;

    public function boot(
        HandleFlightPlanExtraction $handleFlightPlanExtraction,
        ShouldPromptForCoffee $shouldPromptForCoffee,
        FlightPlanResultCache $flightPlanResultCache,
        BuildFlightPlanPageData $buildFlightPlanPageData,
    ): void {
        $this->handleFlightPlanExtraction = $handleFlightPlanExtraction;
        $this->shouldPromptForCoffee = $shouldPromptForCoffee;
        $this->flightPlanResultCache = $flightPlanResultCache;
        $this->buildFlightPlanPageData = $buildFlightPlanPageData;
    }

    public function extractFlightPlan(): void
    {
        $user = $this->authorizedUser();
        $validated = $this->validate(FlightPlanValidationRules::rules(), FlightPlanValidationRules::messages());
        $uploadedFile = $validated['flightRelease'] ?? null;

        if (! $uploadedFile instanceof UploadedFile) {
            throw new LogicException('Validated flight release upload is unavailable.');
        }

        try {
            $flightPlan = $this->handleFlightPlanExtraction->handle($user, $uploadedFile);
            $this->flightPlanKey = $this->flightPlanResultCache->put($user, $flightPlan);
        } catch (FlightRouteNotFoundException $exception) {
            $this->resetToUpload($user);
            $this->addError('flightRelease', $exception->getMessage());

            return;
        } catch (Throwable $throwable) {
            report($throwable);

            $this->resetToUpload($user);
            $this->addError('flightRelease', 'We could not process that flight release. Please try again.');

            return;
        }

        $this->reset('flightRelease');
        $this->resetValidation();

        if ($this->shouldPromptForCoffee->handle($user)) {
            $this->dispatch('open-modal', name: 'buy-me-a-coffee');
        }
    }

    public function updatedFlightRelease(): void
    {
        $this->resetValidation('flightRelease');
    }

    public function extractAnotherFlightPlan(): void
    {
        $this->resetToUpload($this->authorizedUser());
    }

    public function render(): View
    {
        $pageData = $this->buildFlightPlanPageData->handle($this->currentFlightPlan());
        $viewModel = new FlightReleasePageViewModel($pageData);

        return view('livewire.flight-plan-brief', [
            'model' => $viewModel,
            'isResultsView' => $viewModel->hasFlightPlan(),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function currentFlightPlan(): ?array
    {
        $user = auth()->user();

        if (! $user instanceof User || $this->flightPlanKey === null) {
            return null;
        }

        return $this->flightPlanResultCache->get($user, $this->flightPlanKey);
    }

    private function resetToUpload(User $user): void
    {
        if ($this->flightPlanKey !== null) {
            $this->flightPlanResultCache->forget($user, $this->flightPlanKey);
        }

        $this->reset(['flightRelease', 'flightPlanKey']);
        $this->resetValidation();
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
