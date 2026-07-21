<?php

namespace App\Livewire;

use App\Actions\HandleParseExecution;
use App\DTOs\ParserResultData;
use App\Exceptions\ParseSourceResolutionException;
use App\Models\User;
use App\Services\JcaScheduleParsingService;
use App\Services\ParserResultCache;
use App\Validation\ParserValidationRules;
use App\View\Models\Parser\ParserPageViewModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ScheduleExtractor extends Component
{
    use WithFileUploads;

    private const VIEW_UPLOAD = 'upload';

    private const VIEW_RESULTS = 'results';

    public string $view = self::VIEW_UPLOAD;

    public ?TemporaryUploadedFile $file = null;

    public string $text = '';

    /** @var list<string> */
    public array $eventTypes = [];

    #[Locked]
    public ?string $parseKey = null;

    protected HandleParseExecution $handleParseExecution;

    protected JcaScheduleParsingService $jcaScheduleParsingService;

    protected ParserResultCache $parserResultCache;

    public function boot(
        HandleParseExecution $handleParseExecution,
        JcaScheduleParsingService $jcaScheduleParsingService,
        ParserResultCache $parserResultCache,
    ): void {
        $this->handleParseExecution = $handleParseExecution;
        $this->jcaScheduleParsingService = $jcaScheduleParsingService;
        $this->parserResultCache = $parserResultCache;
    }

    public function mount(): void
    {
        $result = $this->parserResultCache->latest();
        $oldInput = session()->getOldInput();
        $viewModel = ParserPageViewModel::fromResult($result, $oldInput);

        $this->text = $viewModel->text;
        $this->eventTypes = $viewModel->selectedTypes;
        $this->parseKey = $result?->parseKey;
        $this->view = $result !== null && ! session()->has('errors')
            ? self::VIEW_RESULTS
            : self::VIEW_UPLOAD;
    }

    public function parseRoster(): void
    {
        $user = $this->authorizedUser();

        $validated = $this->validate($this->rules(), $this->messages());
        $file = ($validated['file'] ?? null) instanceof UploadedFile
            ? $validated['file']
            : null;
        $text = is_string($validated['text'] ?? null) && filled($validated['text'])
            ? $validated['text']
            : null;
        $eventTypes = array_values(array_filter(
            is_array($validated['eventTypes'] ?? null) ? $validated['eventTypes'] : [],
            static fn (mixed $eventType): bool => is_string($eventType),
        ));
        $sourceType = $file === null
            ? 'pasted_text'
            : ($file->getMimeType() === 'application/pdf' ? 'pdf' : 'image');

        try {
            $payload = $this->handleParseExecution->handle(
                userId: $user->id,
                sourceType: $sourceType,
                parserType: $sourceType === 'image' ? 'screenshot' : 'unknown',
                file: $file,
                operation: fn (): array => $this->jcaScheduleParsingService->parseRoster(
                    $file,
                    $text,
                    $eventTypes,
                ),
            );
        } catch (ParseSourceResolutionException $exception) {
            $this->addParseErrors($exception);

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
        $this->file = null;
        $this->view = self::VIEW_RESULTS;
        $this->resetValidation();
    }

    public function extractAnotherRoster(): void
    {
        $this->authorizedUser();

        $this->view = self::VIEW_UPLOAD;
        $this->resetRosterForm();
    }

    public function render(): View
    {
        $result = $this->currentResult();

        return view('livewire.schedule-extractor', [
            'viewModel' => ParserPageViewModel::fromResult($result, [
                'text' => $this->text,
                'event_types' => $this->eventTypes,
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $rules = ParserValidationRules::rosterRules();
        $rules['eventTypes'] = $rules['event_types'];
        $rules['eventTypes.*'] = $rules['event_types.*'];
        unset($rules['event_types'], $rules['event_types.*']);

        return $rules;
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        $messages = ParserValidationRules::rosterMessages();
        $messages['eventTypes.*.in'] = $messages['event_types.*.in'];
        unset($messages['event_types.*.in']);

        return $messages;
    }

    private function currentResult(): ?ParserResultData
    {
        if ($this->parseKey !== null) {
            $result = $this->parserResultCache->get($this->parseKey);

            if ($result !== null) {
                return $result;
            }
        }

        return $this->parserResultCache->latest();
    }

    private function resetRosterForm(): void
    {
        $this->reset(['file', 'text']);
        $this->resetValidation();
    }

    private function addParseErrors(ParseSourceResolutionException $exception): void
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

        Gate::authorize('use-schedule-parser');

        return $user;
    }
}
