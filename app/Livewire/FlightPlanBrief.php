<?php

namespace App\Livewire;

use App\Actions\FlightPlan\BuildFlightPlanPageData;
use App\Actions\FlightPlan\HandleFlightPlanExtraction;
use App\Actions\ShouldPromptForCoffee;
use App\Enums\FlightPlanTask;
use App\Exceptions\FlightRouteNotFoundException;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
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
use RuntimeException;
use Throwable;

class FlightPlanBrief extends Component
{
    use WithFileUploads;

    private const string EXTRACTION_COMPLETED_SESSION_KEY = 'flight-plan-brief.extraction-completed';

    private const string COFFEE_PROMPT_SESSION_KEY = 'flight-plan-brief.coffee-prompt';

    public ?TemporaryUploadedFile $flightRelease = null;

    #[Locked]
    public ?string $flightPlanKey = null;

    #[Locked]
    public string $activeTask = FlightPlanTask::Overview->value;

    #[Locked]
    public bool $extractionJustCompleted = false;

    #[Locked]
    public bool $usesTaskRoutes = false;

    protected HandleFlightPlanExtraction $handleFlightPlanExtraction;

    protected ShouldPromptForCoffee $shouldPromptForCoffee;

    protected FlightPlanResultStore $flightPlanResultStore;

    protected BuildFlightPlanPageData $buildFlightPlanPageData;

    protected FlightReleasePageViewModelFactory $flightReleasePageViewModelFactory;

    public function boot(
        HandleFlightPlanExtraction $handleFlightPlanExtraction,
        ShouldPromptForCoffee $shouldPromptForCoffee,
        FlightPlanResultStore $flightPlanResultStore,
        BuildFlightPlanPageData $buildFlightPlanPageData,
        FlightReleasePageViewModelFactory $flightReleasePageViewModelFactory,
    ): void {
        $this->handleFlightPlanExtraction = $handleFlightPlanExtraction;
        $this->shouldPromptForCoffee = $shouldPromptForCoffee;
        $this->flightPlanResultStore = $flightPlanResultStore;
        $this->buildFlightPlanPageData = $buildFlightPlanPageData;
        $this->flightReleasePageViewModelFactory = $flightReleasePageViewModelFactory;
    }

    public function mount(?string $task = null, bool $usesTaskRoutes = false): void
    {
        $this->usesTaskRoutes = $usesTaskRoutes || request()->routeIs(
            'flight-release.index',
            'flight-release.task',
        );

        $user = auth()->user();

        if (! $user instanceof User || ! $this->canAccessFlightRelease($user)) {
            return;
        }

        $this->flightPlanKey = $this->flightPlanResultStore->latest($user)?->result_key;

        if ($this->flightPlanKey === null) {
            if ($this->usesTaskRoutes && $task !== null) {
                $this->redirect(route('flight-release.index'), navigate: true);
            }

            return;
        }

        if ($task === null) {
            if ($this->usesTaskRoutes) {
                $this->navigateToTask(FlightPlanTask::Overview);
            }

            return;
        }

        $selectedTask = FlightPlanTask::fromRouteSlug($task);

        abort_unless(
            $selectedTask !== null && $this->currentViewModel()->isTaskVisible($selectedTask),
            404,
        );

        $this->activeTask = $selectedTask->value;
        $this->restorePostExtractionState();
    }

    public function hydrate(): void
    {
        if (! $this->usesTaskRoutes || $this->flightPlanKey === null) {
            return;
        }

        $user = auth()->user();

        if (! $user instanceof User || $this->flightPlanResultStore->get($user, $this->flightPlanKey) !== null) {
            return;
        }

        $this->reset(['flightRelease', 'flightPlanKey', 'activeTask', 'extractionJustCompleted']);
        $this->redirect(route('flight-release.index'), navigate: true);
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
            $this->extractionJustCompleted = false;
            $this->stream(to: 'flight-plan-upload-status', content: 'Upload successful', replace: true);
            $this->streamProgress('Preparing your flight plan…');
            $flightPlan = $this->handleFlightPlanExtraction->handle($user, $uploadedFile, $this->streamProgress(...));
            $this->streamProgress('Saving your brief…');
            $this->flightPlanKey = $this->flightPlanResultStore->save($user, $flightPlan);
            $this->dispatch('offline-fuel-release-changed', ownerId: (string) $user->getKey(), flightPlanKey: $this->flightPlanKey);
            $this->activeTask = FlightPlanTask::Overview->value;
            $this->extractionJustCompleted = true;
        } catch (FlightRouteNotFoundException $exception) {
            $this->resetFailedUpload();
            $this->addError('flightRelease', $exception->getMessage());

            return;
        } catch (Throwable $throwable) {
            report(new RuntimeException('Flight plan extraction failed.', previous: $throwable));

            $this->resetFailedUpload();
            $this->addError('flightRelease', 'We could not process that flight release. Please try again.');

            return;
        }

        $this->reset('flightRelease');
        $this->resetValidation();
        $shouldPromptForCoffee = $this->shouldPromptForCoffee->handle($user);

        if ($this->usesTaskRoutes) {
            session()->flash(self::EXTRACTION_COMPLETED_SESSION_KEY, true);

            if ($shouldPromptForCoffee) {
                session()->flash(self::COFFEE_PROMPT_SESSION_KEY, true);
            }

            $this->navigateToTask(FlightPlanTask::Overview);

            return;
        }

        $this->dispatch('scroll-to-release-summary');

        if ($shouldPromptForCoffee) {
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
        $this->clearResults();
    }

    public function clearResults(): void
    {
        $this->resetToUpload($this->authorizedUser());

        if ($this->usesTaskRoutes) {
            $this->redirect(route('flight-release.index'), navigate: true);
        }
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
        $this->navigateToTask($selectedTask);
    }

    public function render(): View
    {
        $viewModel = $this->currentViewModel();

        return view('livewire.flight-plan-brief', [
            'activeTaskCase' => FlightPlanTask::from($this->activeTask),
            'model' => $viewModel,
            'isResultsView' => $viewModel->hasFlightPlan(),
            'tasks' => $viewModel->tasks(),
            'fuelCalculatorUrl' => $this->flightPlanKey === null
                ? null
                : route('flight-release.fuel-score', ['flightPlanKey' => $this->flightPlanKey]),
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

        if (
            ! $user instanceof User
            || $this->flightPlanKey === null
            || ! $this->canAccessFlightRelease($user)
        ) {
            return null;
        }

        return $this->flightPlanResultStore->get($user, $this->flightPlanKey);
    }

    private function resetToUpload(User $user): void
    {
        if ($this->flightPlanKey !== null) {
            $this->flightPlanResultStore->delete($user, $this->flightPlanKey);
        }

        $this->reset(['flightRelease', 'flightPlanKey', 'activeTask', 'extractionJustCompleted']);
        $this->resetValidation();
        $this->dispatch('offline-fuel-release-changed', ownerId: (string) $user->getKey(), flightPlanKey: null);
    }

    private function resetFailedUpload(): void
    {
        $this->reset(['flightRelease', 'extractionJustCompleted']);
        $this->resetValidation();
    }

    private function navigateToTask(FlightPlanTask $task): void
    {
        if (! $this->usesTaskRoutes) {
            return;
        }

        $this->redirect(route('flight-release.task', [
            'task' => $task->routeSlug(),
        ]), navigate: true);
    }

    private function restorePostExtractionState(): void
    {
        if (! $this->usesTaskRoutes || ! session()->pull(self::EXTRACTION_COMPLETED_SESSION_KEY, false)) {
            return;
        }

        $this->extractionJustCompleted = true;
        $this->dispatch('scroll-to-release-summary');

        if (session()->pull(self::COFFEE_PROMPT_SESSION_KEY, false)) {
            $this->dispatch('open-modal', name: 'buy-me-a-coffee');
        }
    }

    private function streamProgress(string $message): void
    {
        $this->stream(to: 'flight-plan-progress', content: e($message), replace: true);
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

    private function canAccessFlightRelease(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && (bool) config('features.flight_release.enabled', true)
            && Gate::allows('use-flight-release');
    }
}
