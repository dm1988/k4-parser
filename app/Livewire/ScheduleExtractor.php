<?php

namespace App\Livewire;

use App\Actions\HandleExtractExecution;
use App\DTOs\ExtractedResultData;
use App\Enums\ScheduleEventType;
use App\Exceptions\ExtractSourceResolutionException;
use App\Models\User;
use App\Services\Infrastructure\EngineResultCache;
use App\Services\Schedule\JcaScheduleProcessor;
use App\Validation\ExtractValidationRules;
use App\View\Models\Extract\ExtractPageViewModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LogicException;

class ScheduleExtractor extends Component
{
    use WithFileUploads;

    private const VIEW_UPLOAD = 'upload';

    private const VIEW_RESULTS = 'results';

    #[Locked]
    public string $view = self::VIEW_UPLOAD;

    // /** @var list<TemporaryUploadedFile> */
    // public array $files = [];
    public ?TemporaryUploadedFile $file = null;

    public string $text = '';

    /** @var list<string> */
    public array $eventTypes = [];

    #[Locked]
    public ?string $parseKey = null;

    protected HandleExtractExecution $handleExtractExecution;

    protected JcaScheduleProcessor $jcaScheduleProcessor;

    protected EngineResultCache $engineResultCache;

    public function boot(
        HandleExtractExecution $handleExtractExecution,
        JcaScheduleProcessor $jcaScheduleProcessor,
        EngineResultCache $engineResultCache,
    ): void {
        $this->handleExtractExecution = $handleExtractExecution;
        $this->jcaScheduleProcessor = $jcaScheduleProcessor;
        $this->engineResultCache = $engineResultCache;
    }

    public function mount(): void
    {
        $result = $this->engineResultCache->latest();
        $viewModel = ExtractPageViewModel::fromResult($result);

        $this->eventTypes = $viewModel->selectedTypes;
        $this->parseKey = $result?->parseKey;
        $this->view = $result === null ? self::VIEW_UPLOAD : self::VIEW_RESULTS;
    }

    public function extractRoster(): void
    {
        $user = $this->authorizedUser();

        $validated = $this->validate($this->rules(), $this->messages());
        $file = $this->resolveValidatedFile($validated);
        $text = is_string($validated['text'] ?? null) && filled($validated['text'])
            ? $validated['text']
            : null;
        $eventTypes = array_values(array_filter(
            is_array($validated['eventTypes'] ?? null) ? $validated['eventTypes'] : [],
            static fn (mixed $eventType): bool => is_string($eventType),
        ));
        $this->eventTypes = $eventTypes;
        $sourceType = $this->resolveSourceType($file);

        try {
            $payload = $this->handleExtractExecution->handle(
                userId: $user->id,
                sourceType: $sourceType,
                parserType: $sourceType === 'image' ? 'screenshot' : 'unknown',
                file: $file,
                operation: fn (): array => $this->jcaScheduleProcessor->extractRoster(
                    $file,
                    $text,
                    $eventTypes,
                ),
            );
        } catch (ExtractSourceResolutionException $exception) {
            $this->addExtractErrors($exception);

            $this->view = self::VIEW_UPLOAD;

            return;
        }

        $result = $payload['result'];

        if (($result->parsed['calendar_events'] ?? []) === []) {
            $this->addError('file', 'No calendar events were found in that schedule. Try another file or adjust the event filters.');
            $this->view = self::VIEW_UPLOAD;

            return;
        }

        $this->parseKey = $result->parseKey;
        $this->reset('file');
        $this->view = self::VIEW_RESULTS;
        $this->resetValidation();
    }

    public function updatedFile(): void
    {
        $this->resetValidation('file');
        // $this->resetValidation('files.*');
    }

    public function updatedText(): void
    {
        $this->resetValidation('text');
    }

    public function extractAnotherRoster(): void
    {
        $this->authorizedUser();

        $this->view = self::VIEW_UPLOAD;
        $this->resetRosterForm();
    }

    public function render(): View
    {
        return view('livewire.schedule-extractor', [
            'available' => auth()->user()?->canUseScheduleExtractor() ?? false,
            'filterOptions' => ScheduleEventType::filterable(),
            'viewModel' => $this->view === self::VIEW_RESULTS
                ? ExtractPageViewModel::fromResult($this->currentResult())
                : null,
        ]);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return ExtractValidationRules::rosterRules(eventTypesField: 'eventTypes');
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return ExtractValidationRules::rosterMessages(eventTypesField: 'eventTypes');
    }

    private function currentResult(): ?ExtractedResultData
    {
        if ($this->parseKey !== null) {
            $result = $this->engineResultCache->get($this->parseKey);

            if ($result !== null) {
                return $result;
            }
        }

        return $this->engineResultCache->latest();
    }

    private function resetRosterForm(): void
    {
        $this->reset(['file', 'text']);
        $this->resetValidation();
    }

    /** @param array<string, mixed> $validated */
    private function resolveValidatedFile(array $validated): ?UploadedFile
    {
        $file = $validated['file'] ?? null;

        return $file instanceof UploadedFile ? $file : null;
    }
    // /** @return list<UploadedFile> */
    // private function resolveValidatedFiles(array $validated): array
    // {
    //     $files = $validated['files'] ?? [];

    //     if (! is_array($files)) {
    //         return [];
    //     }

    //     return array_values(array_filter(
    //         $files,
    //         fn ($file) => $file instanceof UploadedFile
    //     ));
    // }

    private function resolveSourceType(?UploadedFile $file): string
    {
        if ($file === null) {
            return 'pasted_text';
        }
        // Determine if all are images vs PDF
        // $mimes = array_map(fn (UploadedFile $f) => $f->getMimeType(), $files);

        return match ($file->getMimeType()) {
            'application/pdf' => 'pdf',
            'image/jpeg',
            'image/png',
            'image/webp' => 'image',
            default => throw new LogicException('Validated upload has an unsupported MIME type.'),
        };
    }

    private function addExtractErrors(ExtractSourceResolutionException $exception): void
    {
        foreach ($exception->errors() as $key => $messages) {
            $livewireKey = $this->livewireErrorKey($key);

            foreach ((array) $messages as $message) {
                $this->addError($livewireKey, (string) $message);
            }
        }
    }

    private function livewireErrorKey(string $key): string
    {
        if ($key === 'event_types') {
            return 'eventTypes';
        }

        if (str_starts_with($key, 'event_types.')) {
            return 'eventTypes.'.substr($key, strlen('event_types.'));
        }

        return $key;
    }

    private function authorizedUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        Gate::authorize('use-schedule-extractor');

        return $user;
    }
}
