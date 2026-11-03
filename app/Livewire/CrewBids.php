<?php

namespace App\Livewire;

use App\Actions\CrewBids\ImportBidPackage;
use App\Actions\CrewBids\PublishBidPackage;
use App\Actions\CrewBids\SaveBidWorkspace;
use App\DTOs\CrewBidBrowseCriteria;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use App\Services\CrewBids\BidLineMatcher;
use App\Services\CrewBids\BrowseBidLines;
use App\View\Models\CrewBidsPageDataFactory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class CrewBids extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $packageId = null;

    #[Locked]
    public string $seat = 'FO';

    #[Locked]
    public int $revision = 0;

    /** @var list<string> */
    #[Locked]
    public array $selections = [];

    #[Locked]
    public bool $initialized = false;

    #[Locked]
    public bool $dirty = false;

    #[Locked]
    public ?int $draftId = null;

    #[Locked]
    public ?int $pendingPackageId = null;

    #[Locked]
    public ?string $undoId = null;

    public string $contextPackage = '';

    public string $tab = 'flying';

    public string $sort = 'best';

    public string $startCity = '';

    /** @var list<string> */
    public array $goals = [];

    /** @var list<string> */
    public array $daysOff = [];

    public string $rangeStart = '';

    public string $rangeEnd = '';

    public ?int $minimumCredit = null;

    public ?int $minimumDaysOff = null;

    public bool $selectedOnly = false;

    public string $layoverCity = '';

    public bool $extendedLayoversOnly = false;

    public bool $avoidShortLayovers = false;

    public bool $showPreferences = false;

    public bool $showList = false;

    public bool $showReview = false;

    public bool $showImport = false;

    public string $notice = '';

    /** @var list<TemporaryUploadedFile> */
    public array $files = [];

    public function boot(): void
    {
        abort_unless(config('features.crew_bids.enabled', true), 404);
        Gate::authorize('use-crew-bids');
    }

    public function mount(): void
    {
        $this->seat = $this->user()->crew_bid_seat_preference;
        $last = BidWorkspace::query()->where('user_id', Auth::id())->latest('updated_at')->first();
        $package = $last === null ? null : BidPackage::query()->whereNotNull('published_at')->find($last->bid_package_id);
        if ($package?->archived_at !== null && ! $package->workspaces()->where('user_id', Auth::id())->where('seat', $this->seat)->exists()) {
            $package = null;
        }
        $package ??= BidPackage::query()->whereNotNull('published_at')->whereNull('archived_at')->orderByDesc('period')->orderByDesc('id')->first();
        if ($package !== null) {
            $this->loadPackage($package, $this->seat);
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['minimumCredit', 'minimumDaysOff', 'sort', 'tab', 'layoverCity'], true)) {
            $this->validate([
                'minimumCredit' => ['nullable', 'integer', 'between:0,300'],
                'minimumDaysOff' => ['nullable', 'integer', 'between:0,31'],
                'sort' => ['required', Rule::in(['best', 'credit', 'block', 'days_off', 'id'])],
                'tab' => ['required', Rule::in(['flying', 'reserve', 'trips'])],
                'layoverCity' => ['nullable', 'regex:/\A[A-Z]{3}\z/'],
            ]);
        }
        if (in_array(explode('.', $property)[0], ['goals', 'daysOff', 'startCity'], true) && $this->packageId !== null) {
            if (explode('.', $property)[0] === 'daysOff') {
                sort($this->daysOff);
            }
            $this->initialized = true;
            $this->persist();
        }
    }

    public function changeContext(): void
    {
        $this->validate(['contextPackage' => ['required', 'integer']]);
        $target = BidPackage::query()->whereNotNull('published_at')->findOrFail((int) $this->contextPackage);
        $this->assertPackageAvailable($target);
        if ($this->dirty && ! $this->persist()) {
            $this->contextPackage = (string) $this->packageId;

            return;
        }
        $prior = BidWorkspace::query()->where('user_id', Auth::id())->where('seat', $this->seat)
            ->whereIn('bid_package_id', BidPackage::query()->where('period', $target->period)->where('fleet', $target->fleet)->whereNotNull('published_at')->select('id'))
            ->latest('updated_at')->first();
        if ($prior !== null && $prior->bid_package_id !== $target->id && ! BidWorkspace::query()->where('user_id', Auth::id())->where('bid_package_id', $target->id)->where('seat', $this->seat)->exists()) {
            $this->loadPackage(BidPackage::query()->findOrFail($prior->bid_package_id), $this->seat);
            $this->pendingPackageId = $target->id;

            return;
        }
        $this->loadPackage($target, $this->seat);
    }

    public function reviewRevision(int $id): void
    {
        $package = $this->package();
        $target = BidPackage::query()->whereNotNull('published_at')->where('period', $package->period)->where('fleet', $package->fleet)->findOrFail($id);
        $this->assertPackageAvailable($target);
        $this->pendingPackageId = $target->id;
    }

    public function confirmRevision(): void
    {
        if ($this->pendingPackageId === null || ! $this->persist()) {
            return;
        }
        $source = $this->package();
        $target = BidPackage::query()->whereNotNull('published_at')->where('period', $source->period)->where('fleet', $source->fleet)->findOrFail($this->pendingPackageId);
        $this->assertPackageAvailable($target);
        $existing = BidWorkspace::query()->where('user_id', Auth::id())->where('bid_package_id', $target->id)->where('seat', $this->seat)->first();
        if ($existing === null) {
            $lines = $target->payload['lines'];
            $choices = array_values(array_filter($this->selections, fn (string $id): bool => isset($lines[$id]) && $lines[$id][$this->seat === 'CA' ? 'captains' : 'first_officers'] > 0));
            app(SaveBidWorkspace::class)->handle($this->user(), $target, $this->seat, 0, $this->preferences(), $choices);
        }
        $this->loadPackage($target, $this->seat);
        $this->pendingPackageId = null;
        $this->notice = 'Revision opened. Review any changed choices before copying.';
    }

    public function dismissRevision(): void
    {
        $this->pendingPackageId = null;
    }

    public function finishSetup(): void
    {
        $this->initialized = true;
        if ($this->persist()) {
            $this->showPreferences = false;
        }
    }

    public function toggleDate(string $date): void
    {
        $this->assertDate($date);
        $this->daysOff = in_array($date, $this->daysOff, true) ? array_values(array_diff($this->daysOff, [$date])) : [...$this->daysOff, $date];
        sort($this->daysOff);
        $this->initialized = true;
        $this->persist();
    }

    public function addRange(): void
    {
        $this->validate(['rangeStart' => ['required', 'date_format:Y-m-d'], 'rangeEnd' => ['required', 'date_format:Y-m-d', 'after_or_equal:rangeStart']]);
        $this->assertDate($this->rangeStart);
        $this->assertDate($this->rangeEnd);
        for ($date = CarbonImmutable::parse($this->rangeStart, 'UTC'); $date->format('Y-m-d') <= $this->rangeEnd; $date = $date->addDay()) {
            $this->daysOff[] = $date->format('Y-m-d');
        }
        $this->daysOff = array_values(array_unique($this->daysOff));
        sort($this->daysOff);
        $this->initialized = true;
        $this->persist();
    }

    public function resetFilters(): void
    {
        $this->daysOff = [];
        $this->startCity = '';
        $this->clearCreditFilters();
        $this->clearWorkPatternFilters();
        $this->selectedOnly = false;
        $this->clearLayoverFilters();
        $this->persist();
    }

    public function clearCreditFilters(): void
    {
        $this->minimumCredit = null;
        $this->resetValidation('minimumCredit');
    }

    public function clearWorkPatternFilters(): void
    {
        $this->minimumDaysOff = null;
        $this->resetValidation('minimumDaysOff');
    }

    public function clearLayoverFilters(): void
    {
        $this->layoverCity = '';
        $this->extendedLayoversOnly = false;
        $this->avoidShortLayovers = false;
        $this->resetValidation('layoverCity');
    }

    public function clearDates(): void
    {
        $this->daysOff = [];
        $this->persist();
    }

    public function sortGoal(string $goal, int $position): void
    {
        abort_unless(in_array($goal, $this->goals, true) && $position >= 0 && $position < count($this->goals), 422);
        $this->goals = array_values(array_diff($this->goals, [$goal]));
        array_splice($this->goals, $position, 0, [$goal]);
        $this->persist();
    }

    public function add(string $id): void
    {
        $line = $this->eligibleLine($id);
        $view = app(BidLineMatcher::class)->present($line, $this->package()->period, $this->preferences());
        if (! $view['matches']) {
            $this->addError('workspace', 'This line conflicts with your required days off or start city.');

            return;
        }
        if (! in_array($id, $this->selections, true)) {
            $this->selections[] = $id;
            $this->undoId = $id;
            $this->notice = $id.' added at rank '.count($this->selections).'.';
            $this->persist();
        }
    }

    public function addMatching(BrowseBidLines $browseBidLines): void
    {
        $package = $this->packageId === null ? null : $this->package();
        $result = $browseBidLines->browse($package, $this->browseCriteria());
        foreach ($result->lines as $line) {
            if (! in_array($line['id'], $this->selections, true)) {
                $this->selections[] = $line['id'];
            }
        }
        $this->persist();
    }

    public function remove(string $id): void
    {
        $this->selections = array_values(array_diff($this->selections, [$id]));
        $this->persist();
    }

    public function undoAdd(): void
    {
        if ($this->undoId !== null) {
            $this->remove($this->undoId);
            $this->undoId = null;
            $this->notice = 'Addition undone.';
        }
    }

    public function move(string $id, int $position): void
    {
        abort_unless(in_array($id, $this->selections, true) && $position >= 0 && $position < count($this->selections), 422);
        $this->selections = array_values(array_diff($this->selections, [$id]));
        array_splice($this->selections, $position, 0, [$id]);
        $this->persist();
    }

    public function clearList(): void
    {
        $this->selections = [];
        $this->persist();
    }

    public function retrySave(): void
    {
        $this->persist();
    }

    public function reloadSaved(): void
    {
        $this->loadPackage($this->package(), $this->seat);
        $this->notice = 'Saved choices reloaded.';
    }

    public function previewImport(): void
    {
        Gate::authorize('manage-crew-bids');
        $this->draftId = null;
        try {
            $draft = app(ImportBidPackage::class)->handle($this->files);
            if ($draft->published_at !== null) {
                $this->notice = 'These files are already published.';
                $this->draftId = null;
            } else {
                abort_unless($draft->imported_by === Auth::id(), 403);
                $this->draftId = $draft->id;
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('files', 'Import failed. The current package is unchanged. Check the source files and try again.');
        } finally {
            $this->files = [];
        }
    }

    public function publishImport(): void
    {
        Gate::authorize('manage-crew-bids');
        abort_if($this->draftId === null, 422);
        $package = app(PublishBidPackage::class)->handle($this->draftId, (int) Auth::id());
        $this->draftId = null;
        $this->showImport = false;
        $this->notice = 'Package published. Choose it from the package selector.';
        if ($this->packageId === null) {
            $this->loadPackage($package, $this->seat);
        }
    }

    private function persist(): bool
    {
        if ($this->packageId === null) {
            return false;
        }
        $this->dirty = true;
        $this->resetErrorBag();
        try {
            $workspace = app(SaveBidWorkspace::class)->handle($this->user(), $this->package(), $this->seat, $this->revision, $this->preferences(), $this->selections);
            $this->revision = $workspace->revision;
            $this->dirty = false;

            return true;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('workspace', 'Save failed. Your edits are retained. Retry when connected.');
        }

        return false;
    }

    private function loadPackage(BidPackage $package, string $seat): void
    {
        $workspace = BidWorkspace::query()->where('user_id', Auth::id())->where('bid_package_id', $package->id)->where('seat', $seat)->first();
        $preferences = $workspace->preferences ?? [];
        $this->packageId = $package->id;
        $this->contextPackage = (string) $package->id;
        $this->seat = $seat;
        $this->revision = $workspace->revision ?? 0;
        $this->selections = $workspace->selections ?? [];
        $this->goals = $preferences['goals'] ?? [];
        $this->daysOff = $preferences['days_off'] ?? [];
        $this->startCity = $preferences['start_city'] ?? '';
        $this->initialized = $preferences['initialized'] ?? false;
        $this->showPreferences = ! $this->initialized;
        $this->dirty = false;
        $this->undoId = null;
        $this->notice = '';
        $this->showReview = false;
        $this->pendingPackageId = null;
        $this->rangeStart = $package->period.'-01';
        $this->rangeEnd = $package->period.'-01';
        $this->minimumCredit = null;
        $this->minimumDaysOff = null;
        $this->selectedOnly = false;
        $this->clearLayoverFilters();
        $this->resetErrorBag();
    }

    private function package(): BidPackage
    {
        return BidPackage::query()->whereNotNull('published_at')->findOrFail($this->packageId);
    }

    private function assertPackageAvailable(BidPackage $package): void
    {
        abort_if($package->archived_at !== null && ! $package->workspaces()->where('user_id', Auth::id())->where('seat', $this->seat)->exists(), 404);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** @return array{goals: list<string>, days_off: list<string>, start_city: string, initialized: bool} */
    private function preferences(): array
    {
        return ['goals' => $this->goals, 'days_off' => $this->daysOff, 'start_city' => $this->startCity, 'initialized' => $this->initialized];
    }

    /** @return array<string, mixed> */
    private function eligibleLine(string $id): array
    {
        $line = $this->package()->payload['lines'][$id] ?? null;
        abort_unless($line !== null && $line[$this->seat === 'CA' ? 'captains' : 'first_officers'] > 0, 404);

        return $line;
    }

    private function assertDate(string $date): void
    {
        $period = $this->package()->period;
        if (! preg_match('/^'.preg_quote($period, '/').'-\d{2}$/', $date) || ! CarbonImmutable::hasFormat($date, 'Y-m-d')) {
            throw ValidationException::withMessages(['daysOff' => 'Choose a valid UTC date in this package month.']);
        }
    }

    private function browseCriteria(): CrewBidBrowseCriteria
    {
        return new CrewBidBrowseCriteria(
            seat: $this->seat,
            preferences: $this->preferences(),
            selections: $this->selections,
            tab: $this->tab,
            sort: $this->sort,
            minimumCredit: $this->minimumCredit,
            minimumDaysOff: $this->minimumDaysOff,
            selectedOnly: $this->selectedOnly,
            layoverCity: $this->layoverCity,
            extendedLayoversOnly: $this->extendedLayoversOnly,
            avoidShortLayovers: $this->avoidShortLayovers,
        );
    }

    public function render(CrewBidsPageDataFactory $pageDataFactory): View
    {
        $data = $pageDataFactory->make(
            package: $this->packageId === null ? null : $this->package(),
            user: $this->user(),
            criteria: $this->browseCriteria(),
            draftId: $this->draftId,
            pendingPackageId: $this->pendingPackageId,
        );

        return view('livewire.crew-bids', $data->toViewData())->layout('layouts.app');
    }
}
