<?php

namespace App\Filament\Resources\BidAwardReports;

use App\Actions\CrewBids\ImportBidAwards;
use App\Filament\Resources\BidAwardReports\Pages\ListBidAwardReports;
use App\Filament\Resources\BidAwardReports\Tables\BidAwardReportsTable;
use App\Models\BidAwardReport;
use App\Models\BidPackage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;
use UnitEnum;

class BidAwardReportResource extends Resource
{
    protected static ?string $model = BidAwardReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'K4 Bids';

    protected static ?string $navigationLabel = 'Awards';

    protected static ?string $modelLabel = 'award report';

    public static function table(Table $table): Table
    {
        return BidAwardReportsTable::configure($table);
    }

    public static function importAction(): Action
    {
        return Action::make('importAwards')->label('Upload awards PDF')
            ->authorize(fn (): bool => Gate::allows('manage-crew-bids'))
            ->modalDescription('Choose the published schedule revision matching the awards and upload the original PDF (12 MB maximum). A corrected report creates a separate selectable revision. Direct K4 retrieval is pending validation when the portal is available.')
            ->schema([
                Select::make('package_id')->label('Schedule package revision')->required()->searchable()
                    ->options(fn (): array => BidPackage::query()->whereNotNull('published_at')->whereNull('archived_at')->orderByDesc('period')->orderByDesc('id')->get()
                        ->mapWithKeys(fn (BidPackage $package): array => [$package->id => $package->period.' · B'.$package->fleet.' · '.$package->source_revision.' (#'.$package->id.')'])->all()),
                FileUpload::make('awardFile')->label('Awards PDF')->required()->maxSize(12288)
                    ->acceptedFileTypes(['application/pdf'])->rules(['extensions:pdf'])->preventFilePathTampering()
                    ->disk('local')->visibility('private')->storeFiles(false)->previewable(false),
            ])
            ->action(function (array $data, ImportBidAwards $importer, Action $action, Page $livewire): void {
                Gate::authorize('manage-crew-bids');
                $form = $livewire->getSchema((string) $livewire->getMountedActionSchemaName());
                $file = $data['awardFile'];
                try {
                    $report = $importer->handle($file, BidPackage::query()->findOrFail((int) $data['package_id']));
                } catch (ValidationException $exception) {
                    $path = $form->getStatePath();
                    $form->fill(['package_id' => $data['package_id'], 'awardFile' => null]);
                    throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [$path.'.'.$key => $messages])->all());
                } catch (AuthorizationException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    report($exception);
                    $form->fill(['package_id' => $data['package_id'], 'awardFile' => null]);
                    Notification::make()->danger()->title('Awards import failed')->body('Existing reports are unchanged. Check the PDF and schedule revision, then retry.')->send();
                    $action->halt();

                    return;
                } finally {
                    if ($file instanceof TemporaryUploadedFile) {
                        $file->delete();
                    }
                }
                Notification::make()->success()->title('Awards imported')->body($report->period.' · B'.$report->fleet.' · Report #'.$report->id.' is available in the K4 Bids package selector.')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBidAwardReports::route('/'),
        ];
    }
}
