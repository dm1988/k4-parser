<?php

namespace App\Livewire;

use App\Actions\CrewBids\ImportBidPackage;
use App\Actions\CrewBids\ImportPilotSeniority;
use App\Actions\CrewBids\PublishBidPackage;
use App\Actions\CrewBids\SaveBidWorkspace;
use App\Actions\CrewBids\SaveCrewBidPayProfile;
use App\DTOs\CrewBidBrowseCriteria;
use App\Enums\CrewBidSort;
use App\Livewire\Forms\BidAwardsForm;
use App\Models\BidAwardReport;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\CrewBidPayProfile;
use App\Models\CrewBidSnapshot;
use App\Models\User;
use App\Services\CrewBids\AwardedBidSchedule;
use App\Services\CrewBids\BidLineMatcher;
use App\Services\CrewBids\BrowseBidLines;
use App\Services\CrewBids\CalculateCrewBidPay;
use App\Services\CrewBids\CrewPayPeriod;
use App\Services\CrewBids\PredictCrewBidLines;
use App\Services\CrewBids\QueuedSubmittedBidRefresh;
use App\Services\CrewBids\ResolveSubmittedBidOrder;
use App\Services\CrewBids\StoreSubmittedBidSnapshot;
use App\View\Models\CrewBidPayEditorViewModel;
use App\View\Models\CrewBidsPageDataFactory;
use App\View\Models\SubmittedBidComparisonViewModel;
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
use RuntimeException;
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

    #[Locked]
    public ?string $lastSavedAt = null;

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

    #[Locked]
    public ?int $awardReportId = null;

    public BidAwardsForm $awardForm;

    public string $tab = 'flying';

    public string $sort = CrewBidSort::BestMatch->value;

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

    #[Locked]
    public bool $editingList = false;

    /** @var list<string> */
    public array $checkedSelections = [];

    public bool $showReview = false;

    public bool $showComparison = false;

    public bool $showImport = false;

    public bool $showPayEditor = false;

    #[Locked]
    public ?string $payLineId = null;

    #[Locked]
    public int $payRevision = 0;

    /** @var list<string> */
    #[Locked]
    public array $payDetails = [];

    /** @var array<string, mixed> */
    public array $paySettings = [];

    /** @var array<string, mixed> */
    public array $payContext = [];

    /** @var array<string, mixed> */
    public array $payInput = [];

    public string $notice = '';

    public string $seniorityUrl = '';

    /** @var list<TemporaryUploadedFile> */
    public array $files = [];

    public function boot(): void
    {
        abort_unless(config('features.crew_bids.enabled', true), 404);
        Gate::authorize('use-crew-bids');
    }

    public function mount(): void
    {
        $imported = session()->pull('crew_bid_import_context');
        if (is_array($imported) && in_array($imported['seat'] ?? null, ['CA', 'FO'], true)) {
            $package = BidPackage::query()->whereNotNull('published_at')->whereNull('archived_at')->find($imported['package_id'] ?? null);
            if ($package !== null) {
                $this->loadPackage($package, $imported['seat']);
                $this->showPreferences = false;
                $this->showList = true;
                $this->notice = 'Submitted bids synced from K4 in their original order.';

                return;
            }
        }
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

    public function openComparison(): void
    {
        app(StoreSubmittedBidSnapshot::class)->authorize($this->user(), $this->package(), $this->seat);
        $this->showComparison = true;
    }

    public function refreshComparison(): void
    {
        app(StoreSubmittedBidSnapshot::class)->authorize($this->user(), $this->package(), $this->seat);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['minimumCredit', 'minimumDaysOff', 'sort', 'tab', 'layoverCity'], true)) {
            $this->validate([
                'minimumCredit' => ['nullable', 'integer', 'between:0,300'],
                'minimumDaysOff' => ['nullable', 'integer', 'between:0,31'],
                'sort' => ['required', Rule::enum(CrewBidSort::class)],
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
        if (preg_match('/\Aawards:([1-9]\d*)\z/', $this->contextPackage, $match)) {
            $report = BidAwardReport::availableTo($this->user())->findOrFail((int) $match[1]);
            if ($this->dirty && ! $this->persist()) {
                $this->contextPackage = $this->awardReportId === null ? (string) $this->packageId : 'awards:'.$this->awardReportId;

                return;
            }
            $this->awardReportId = $report->id;
            $this->showList = false;
            $this->showPreferences = false;
            $this->showComparison = false;
            $this->showPayEditor = false;

            return;
        }
        $this->validate(['contextPackage' => ['required', 'integer']]);
        $target = BidPackage::query()->whereNotNull('published_at')->findOrFail((int) $this->contextPackage);
        $this->assertPackageAvailable($target);
        if ($this->dirty && ! $this->persist()) {
            $this->contextPackage = $this->awardReportId === null ? (string) $this->packageId : 'awards:'.$this->awardReportId;

            return;
        }
        $this->awardReportId = null;
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
            $this->sort = CrewBidSort::BestMatch->value;
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
        $profile = CrewBidPayProfile::query()->where('user_id', $this->user()->id)->first() ?? new CrewBidPayProfile;
        $result = $browseBidLines->browse($package, $this->browseCriteria(), $package === null ? [] : $profile->context($package->id, $this->seat, $this->user()));
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

    public function toggleListEditMode(): void
    {
        $this->editingList = ! $this->editingList;
        $this->checkedSelections = [];
        $this->resetValidation(['checkedSelections', 'checkedSelections.*']);
    }

    public function moveCheckedToTop(): void
    {
        $this->moveChecked('top');
    }

    public function moveCheckedToBottom(): void
    {
        $this->moveChecked('bottom');
    }

    public function removeChecked(): void
    {
        $checked = $this->checkedListItems();
        if ($checked === []) {
            return;
        }
        $this->selections = array_values(array_diff($this->selections, $checked));
        if ($this->persist()) {
            $this->notice = count($checked).' choices removed from your bid list.';
        }
    }

    private function moveChecked(string $destination): void
    {
        $checked = $this->checkedListItems();
        if ($checked === []) {
            return;
        }
        $remaining = array_values(array_diff($this->selections, $checked));
        $selections = $destination === 'top' ? [...$checked, ...$remaining] : [...$remaining, ...$checked];
        if ($this->selections === $selections) {
            return;
        }
        $this->selections = $selections;
        if ($this->persist()) {
            $this->notice = count($checked).' choices moved to the '.$destination.'.';
        }
    }

    /** @return list<string> */
    private function checkedListItems(): array
    {
        abort_unless($this->editingList, 422);
        $this->validate([
            'checkedSelections' => ['array', 'list', 'max:1000'],
            'checkedSelections.*' => ['string', 'distinct', Rule::in($this->selections)],
        ], [
            'checkedSelections.*.in' => 'Choose only lines in your current bid list.',
        ]);

        return array_values(array_intersect($this->selections, $this->checkedSelections));
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

    public function openPayEditor(?string $lineId = null): void
    {
        $package = $this->package();
        $this->assertPackageAvailable($package);
        if ($lineId !== null) {
            $this->eligibleLine($lineId);
        }
        $profile = CrewBidPayProfile::query()->where('user_id', $this->user()->id)->first() ?? new CrewBidPayProfile;
        $context = $profile->context($package->id, $this->seat, $this->user());
        $this->payRevision = $profile->revision ?? 0;
        $this->payLineId = $lineId;
        $this->paySettings = ['show_compact' => true];
        $this->payContext = array_intersect_key($context, array_flip(['qualified', 'full_period', 'line_guarantee', 'unsupported', 'custom_assumptions']))
            + ['qualified' => true, 'full_period' => true, 'line_guarantee' => true, 'unsupported' => false, 'custom_assumptions' => false];
        $this->payInput = $lineId === null ? [] : ($context['lines'][$lineId] ?? ['mode' => 'source', 'reviewed' => true, 'category' => CalculateCrewBidPay::category($lineId) ?? '', 'rows' => []]);
        $this->showPreferences = false;
        $this->showList = false;
        $this->showPayEditor = true;
        $this->resetErrorBag();
    }

    public function loadPayDetails(string $lineId): void
    {
        $this->assertPackageAvailable($this->package());
        $this->eligibleLine($lineId);
        if (! in_array($lineId, $this->payDetails, true)) {
            $this->payDetails[] = $lineId;
        }
    }

    public function addPayRow(): void
    {
        abort_unless($this->payLineId !== null && is_array($this->payInput['rows'] ?? null) && count($this->payInput['rows']) < 200, 422);
        $this->eligibleLine($this->payLineId);
        $this->payInput['rows'][] = ['assignment' => '', 'type' => 'credit', 'date' => (new CrewPayPeriod($this->package()->period))->start->format('Y-m-d'), 'duration' => '0:00', 'actual' => ''];
        $this->payInput['reviewed'] = false;
    }

    public function removePayRow(int $index): void
    {
        abort_unless(is_array($this->payInput['rows'] ?? null) && isset($this->payInput['rows'][$index]), 422);
        array_splice($this->payInput['rows'], $index, 1);
        $this->payInput['reviewed'] = false;
    }

    public function savePayInputs(SaveCrewBidPayProfile $save): void
    {
        $this->resetErrorBag();
        try {
            $save->handle($this->user(), $this->package(), $this->seat, $this->payRevision, $this->paySettings, $this->payContext, $this->payLineId, $this->payInput);
            $this->closePayEditor();
            $this->notice = 'Private pay inputs saved. Estimates recalculated from the saved scenario.';
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('pay', 'Pay inputs could not be saved. Your edits are retained; retry saving.');
        }
    }

    public function forgetPayInputs(SaveCrewBidPayProfile $save): void
    {
        $save->forget($this->user(), $this->payRevision);
        $this->closePayEditor();
        $this->notice = 'Private pay comparison inputs removed. Profile pay settings retained.';
    }

    public function closePayEditor(): void
    {
        $this->showPayEditor = false;
        $this->payLineId = null;
        $this->paySettings = [];
        $this->payContext = [];
        $this->payInput = [];
    }

    public function importSeniority(ImportPilotSeniority $importer): void
    {
        Gate::authorize('manage-crew-bids');
        $this->validate(['seniorityUrl' => ['required', 'string', 'url:https', 'max:2048']]);
        try {
            $list = $importer->handle($this->seniorityUrl, $this->user());
            $this->notice = 'Seniority list updated: '.$list->effective_date->format('Y-m-d').' · '.count($list->pilots).' pilots.';
            $this->seniorityUrl = '';
        } catch (RuntimeException $exception) {
            $this->addError('seniorityUrl', $exception->getMessage());
        } catch (Throwable) {
            $this->addError('seniorityUrl', 'Seniority import failed. The previous list is unchanged.');
        }
    }

    public function identifyAwardEmployee(): void
    {
        abort_if($this->awardReportId === null, 404);
        BidAwardReport::availableTo($this->user())->findOrFail($this->awardReportId);
        $this->awardForm->validate(['employeeNumber' => ['required', 'string', 'regex:/\A\d{1,20}\z/']]);
        session()->put('bid_awards_employee.'.$this->user()->id, $this->awardForm->employeeNumber);
        $this->awardForm->employeeNumber = '';
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
        $this->checkedSelections = array_values(array_filter($this->checkedSelections,
            fn (mixed $id): bool => in_array($id, $this->selections, true)));
        if ($this->packageId === null) {
            return false;
        }
        $this->dirty = true;
        $this->resetErrorBag();
        try {
            $workspace = app(SaveBidWorkspace::class)->handle($this->user(), $this->package(), $this->seat, $this->revision, $this->preferences(), $this->selections);
            $this->revision = $workspace->revision;
            $this->lastSavedAt = $workspace->updated_at?->toIso8601String();
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
        $this->closePayEditor();
        $this->payDetails = [];
        $workspace = BidWorkspace::query()->where('user_id', Auth::id())->where('bid_package_id', $package->id)->where('seat', $seat)->first();
        $preferences = $workspace->preferences ?? [];
        $this->packageId = $package->id;
        $this->contextPackage = (string) $package->id;
        $this->seat = $seat;
        $this->revision = $workspace->revision ?? 0;
        $this->lastSavedAt = $this->revision > 0 ? $workspace->updated_at?->toIso8601String() : null;
        $this->selections = $workspace->selections ?? [];
        $this->editingList = false;
        $this->checkedSelections = [];
        $this->goals = $preferences['goals'] ?? [];
        $this->daysOff = $preferences['days_off'] ?? [];
        $this->startCity = $preferences['start_city'] ?? '';
        $this->initialized = $preferences['initialized'] ?? false;
        $this->showPreferences = ! $this->initialized;
        $this->dirty = false;
        $this->undoId = null;
        $this->notice = '';
        $this->showReview = false;
        $this->showComparison = false;
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
        $submittedOrder = app(ResolveSubmittedBidOrder::class)->resolve($this->user(), $this->packageId === null ? null : $this->package(), $this->seat);
        if ($this->sort === CrewBidSort::Submitted->value && ! $submittedOrder->available()) {
            $this->sort = CrewBidSort::BestMatch->value;
            $this->notice = $submittedOrder->unavailableReason.'. Switched to Best match.';
        }

        return new CrewBidBrowseCriteria(
            seat: $this->seat,
            preferences: $this->preferences(),
            selections: $this->selections,
            tab: $this->tab,
            sort: CrewBidSort::tryFrom($this->sort) ?? CrewBidSort::BestMatch,
            minimumCredit: $this->minimumCredit,
            minimumDaysOff: $this->minimumDaysOff,
            selectedOnly: $this->selectedOnly,
            layoverCity: $this->layoverCity,
            extendedLayoversOnly: $this->extendedLayoversOnly,
            avoidShortLayovers: $this->avoidShortLayovers,
            submittedOrder: $submittedOrder,
        );
    }

    public function render(CrewBidsPageDataFactory $pageDataFactory, PredictCrewBidLines $predictions): View
    {
        $awardReports = BidAwardReport::availableTo($this->user())->orderByDesc('period')->orderByDesc('id')
            ->get(['id', 'period', 'fleet', 'bid_package_id', 'created_at']);
        if ($this->awardReportId !== null) {
            $report = BidAwardReport::availableTo($this->user())->findOrFail($this->awardReportId);

            return view('livewire.crew-bid-awards', [
                'report' => $report->only(['id', 'period', 'fleet', 'created_at']),
                'awardReports' => $awardReports,
                'packages' => BidPackage::query()->whereNotNull('published_at')->where(fn ($query) => $query->whereNull('archived_at')
                    ->orWhereHas('workspaces', fn ($query) => $query->where('user_id', $this->user()->id)))
                    ->orderByDesc('period')->orderByDesc('id')->get(['id', 'period', 'fleet', 'source_revision', 'archived_at']),
                'awards' => app(AwardedBidSchedule::class)->forUser($report, $this->user(), true),
            ])->layout('layouts.app');
        }
        $data = $pageDataFactory->make(
            package: $this->packageId === null ? null : $this->package(),
            user: $this->user(),
            criteria: $this->browseCriteria(),
            draftId: $this->draftId,
            pendingPackageId: $this->pendingPackageId,
            payDetails: $this->payDetails,
        );

        return view('livewire.crew-bids', $data->toViewData() + [
            'awardReports' => $awardReports,
            'prediction' => $predictions->forUser($data->package, $data->package === null ? null : CrewBidSnapshot::forPackage($data->package, $this->seat), $this->seat, $this->user(), $this->selections),
            'comparison' => $this->showComparison && $data->package !== null ? SubmittedBidComparisonViewModel::make($this->user(), $data->package, $this->seat, $this->selections, $data->submittedOrder, app(StoreSubmittedBidSnapshot::class)) : null,
            'personalRefresh' => $this->showComparison && $data->package !== null ? app(QueuedSubmittedBidRefresh::class)->current($this->user(), $data->package, $this->seat) : null,
            'payEditor' => CrewBidPayEditorViewModel::fromInput($this->showPayEditor, $this->payLineId, $this->payInput, $data->package?->period, $this->seat, $this->user()->crew_pay_settings ?? []),
        ])->layout('layouts.app');
    }
}
