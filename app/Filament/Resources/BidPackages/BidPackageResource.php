<?php

namespace App\Filament\Resources\BidPackages;

use App\Actions\CrewBids\ImportBidPackage;
use App\Actions\CrewBids\ImportPortalBidPackage;
use App\Filament\Resources\BidPackages\Pages\ListBidPackages;
use App\Filament\Resources\BidPackages\Pages\ViewBidPackage;
use App\Filament\Resources\BidPackages\Schemas\BidPackageInfolist;
use App\Filament\Resources\BidPackages\Tables\BidPackagesTable;
use App\Models\BidPackage;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class BidPackageResource extends Resource
{
    protected static ?string $model = BidPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $recordTitleAttribute = 'source_revision';

    public static function infolist(Schema $schema): Schema
    {
        return BidPackageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BidPackagesTable::configure($table);
    }

    public static function importAction(bool $replace = false): Action
    {
        return Action::make($replace ? 'replaceSources' : 'import')
            ->label($replace ? 'Replace draft sources' : 'Import package')
            ->authorize(fn (?BidPackage $record): bool => $replace ? Gate::allows('update', $record) : Gate::allows('create', BidPackage::class))
            ->modalDescription('Upload up to three files: one Lines PDF, one Trips PDF, and one Overview XLSX, up to 12 MB each. Sources must match. Partial coverage is allowed and shown in the preview. Uploaded files are deleted after parsing. Replacing sources requires the complete desired file set.')
            ->modalSubmitActionLabel('Prepare preview')
            ->schema(fn (Schema $schema): Schema => $schema->model(BidPackage::class)->components([
                FileUpload::make('files')
                    ->label('Package files')
                    ->multiple()
                    ->required()
                    ->minFiles(1)
                    ->maxFiles(3)
                    ->maxSize(12288)
                    ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->rules(['extensions:pdf,xlsx'])
                    ->preventFilePathTampering()
                    ->disk('local')
                    ->visibility('private')
                    ->storeFiles(false)
                    ->previewable(false),
            ]))
            ->action(function (array $data, ?BidPackage $record, Page $livewire, Action $action) use ($replace): void {
                $form = $livewire->getSchema((string) $livewire->getMountedActionSchemaName());
                try {
                    $package = app(ImportBidPackage::class)->handle(array_values($data['files']), $replace ? $record?->id : null);
                } catch (ValidationException $exception) {
                    $path = $form->getStatePath();
                    $form->fill(['files' => []]);
                    throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [$path.'.'.$key => $messages])->all());
                } catch (AuthorizationException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    report($exception);
                    $form->fill(['files' => []]);
                    Notification::make()->danger()->title('Import failed')->body('The current package is unchanged. Check the source files and try again.')->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title($package->published_at === null ? 'Draft ready for review' : 'These files are already published')->send();
                $livewire->redirect(static::getUrl('view', ['record' => $package]));
            });
    }

    public static function fetchPortalAction(): Action
    {
        return Action::make('fetchPortal')
            ->label('Fetch from CrewBids')
            ->authorize(fn (): bool => Gate::allows('create', BidPackage::class))
            ->modalDescription('Fetch the public Lines PDF, Trips PDF, and Overview workbook for one fleet and bid month. A complete first release publishes automatically. Changed content becomes a draft for review; missing or invalid files leave current packages unchanged.')
            ->modalSubmitActionLabel('Fetch package')
            ->schema([
                TextInput::make('period')
                    ->label('Bid month')
                    ->default(fn (): string => now('UTC')->addMonth()->format('Y-m'))
                    ->placeholder('YYYY-MM')
                    ->required()
                    ->rules(['regex:/\A\d{4}-(0[1-9]|1[0-2])\z/']),
                Select::make('fleet')
                    ->options(['747' => 'B747', '777' => 'B777'])
                    ->default('777')
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                $owner = Auth::user();
                if (! $owner instanceof User) {
                    throw new AuthorizationException;
                }
                Gate::authorize('create', BidPackage::class);

                try {
                    $status = app(ImportPortalBidPackage::class)->handle($data['period'], $data['fleet'], $owner);
                } catch (AuthorizationException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->danger()->title('Portal import failed')
                        ->body('The portal files could not be validated or downloaded. Existing packages are unchanged.')->send();
                    $action->halt();

                    return;
                }

                $notification = match ($status) {
                    'published' => Notification::make()->success()->title('Package published')->body('The complete first release is available to eligible crew.'),
                    'revision_held' => Notification::make()->warning()->title('Revision held for review')->body('Changed portal files created a draft. Review it before publishing.'),
                    'unchanged' => Notification::make()->success()->title('Package unchanged')->body('These portal files are already imported.'),
                    'missing' => Notification::make()->warning()->title('Package not available yet')->body('One or more required portal files are missing. No package was changed.'),
                };
                $notification->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBidPackages::route('/'),
            'view' => ViewBidPackage::route('/{record}'),
        ];
    }
}
