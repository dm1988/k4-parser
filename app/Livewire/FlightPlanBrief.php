<?php

namespace App\Livewire;

use App\Actions\FlightPlan\BuildFlightPlanPageData;
use App\Actions\FlightPlan\HandleFlightPlanExtraction;
use App\Actions\ShouldPromptForCoffee;
use App\Enums\FlightPlanTask;
use App\Exceptions\FlightRouteNotFoundException;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultCache;
use App\Validation\FlightPlanValidationRules;
use App\View\Models\FlightReleasePageViewModel;
use App\View\Models\FlightReleasePageViewModelFactory;
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

    #[Locked]
    public string $activeTask = FlightPlanTask::Overview->value;

    protected HandleFlightPlanExtraction $handleFlightPlanExtraction;

    protected ShouldPromptForCoffee $shouldPromptForCoffee;

    protected FlightPlanResultCache $flightPlanResultCache;

    protected BuildFlightPlanPageData $buildFlightPlanPageData;

    protected FlightReleasePageViewModelFactory $flightReleasePageViewModelFactory;

    public function boot(
        HandleFlightPlanExtraction $handleFlightPlanExtraction,
        ShouldPromptForCoffee $shouldPromptForCoffee,
        FlightPlanResultCache $flightPlanResultCache,
        BuildFlightPlanPageData $buildFlightPlanPageData,
        FlightReleasePageViewModelFactory $flightReleasePageViewModelFactory,
    ): void {
        $this->handleFlightPlanExtraction = $handleFlightPlanExtraction;
        $this->shouldPromptForCoffee = $shouldPromptForCoffee;
        $this->flightPlanResultCache = $flightPlanResultCache;
        $this->buildFlightPlanPageData = $buildFlightPlanPageData;
        $this->flightReleasePageViewModelFactory = $flightReleasePageViewModelFactory;
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
            $this->activeTask = FlightPlanTask::Overview->value;
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
        $this->dispatch('scroll-to-release-summary');

        if ($this->shouldPromptForCoffee->handle($user)) {
            $this->dispatch('open-modal', name: 'buy-me-a-coffee');
        }
    }

    public function updatedFlightRelease(): void
    {
        $this->resetValidation('flightRelease');

        if ($this->flightRelease !== null) {
            $this->extractFlightPlan();
        }
    }

    public function extractAnotherFlightPlan(): void
    {
        $this->resetToUpload($this->authorizedUser());
    }

    public function selectTask(string $task): void
    {
        $this->authorizedUser();

        $selectedTask = FlightPlanTask::tryFrom($task);

        if (
            $selectedTask === null
            || $this->flightPlanKey === null
            || ! $this->currentViewModel()->isTaskVisible($selectedTask)
        ) {
            return;
        }

        $this->activeTask = $selectedTask->value;
    }

    public function render(): View
    {
        $viewModel = $this->currentViewModel();

        return view('livewire.flight-plan-brief', [
            'activeTaskCase' => FlightPlanTask::from($this->activeTask),
            'model' => $viewModel,
            'isResultsView' => $viewModel->hasFlightPlan(),
            'tasks' => $viewModel->tasks(),
        ]);
    }

    private function currentViewModel(): FlightReleasePageViewModel
    {
        return $this->flightReleasePageViewModelFactory->make(
            $this->buildFlightPlanPageData->handle($this->currentFlightPlan()),
        );
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

        $this->reset(['flightRelease', 'flightPlanKey', 'activeTask']);
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
