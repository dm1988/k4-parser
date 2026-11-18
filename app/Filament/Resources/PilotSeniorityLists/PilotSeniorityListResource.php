<?php

namespace App\Filament\Resources\PilotSeniorityLists;

use App\Actions\CrewBids\ImportPilotSeniority;
use App\Filament\Resources\PilotSeniorityLists\Pages\ListPilotSeniorityLists;
use App\Filament\Resources\PilotSeniorityLists\Pages\ViewPilotSeniorityList;
use App\Filament\Resources\PilotSeniorityLists\Schemas\PilotSeniorityListInfolist;
use App\Filament\Resources\PilotSeniorityLists\Tables\PilotSeniorityListsTable;
use App\Models\PilotSeniorityList;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
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
use Throwable;
use UnitEnum;

class PilotSeniorityListResource extends Resource
{
    protected static ?string $model = PilotSeniorityList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'K4 Bids';

    protected static ?string $recordTitleAttribute = 'effective_date';

    public static function importAction(): Action
    {
        return Action::make('importSeniority')->label('Import seniority PDF')
            ->authorize(fn (): bool => Gate::allows('manage-crew-bids'))
            ->modalDescription('Manually replace the shared pilot roster using a direct Kalitta PDF link. Employee links and bid lists are preserved. Older lists and failed imports leave the current list unchanged.')
            ->schema([
                TextInput::make('url')->label('Seniority PDF link')->url()->required()->maxLength(2048)
                    ->helperText('HTTPS link from crewbids.kalittaair.com/hyperlinks/. Up to 8 MB; no redirects.'),
            ])
            ->action(function (array $data, ImportPilotSeniority $importer, Action $action, Page $livewire): void {
                Gate::authorize('manage-crew-bids');
                $user = Auth::user();
                if (! $user instanceof User) {
                    throw new AuthorizationException;
                }
                try {
                    $list = $importer->handle($data['url'], $user);
                } catch (AuthorizationException $exception) {
                    throw $exception;
                } catch (Throwable) {
                    Notification::make()->danger()->title('Seniority import failed')
                        ->body('The previous list is unchanged. Check the PDF link, date and supported layout.')->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title('Seniority list imported')
                    ->body($list->effective_date->format('Y-m-d').' · '.count($list->pilots).' pilots.')->send();
                $livewire->redirect(static::getUrl('view', ['record' => $list]));
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return PilotSeniorityListInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PilotSeniorityListsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPilotSeniorityLists::route('/'),
            'view' => ViewPilotSeniorityList::route('/{record}'),
        ];
    }
}
