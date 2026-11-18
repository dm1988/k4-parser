<?php

namespace App\Filament\Resources\BidPackages\Pages;

use App\Actions\CrewBids\ArchiveBidPackage;
use App\Actions\CrewBids\DeleteBidPackage;
use App\Actions\CrewBids\PublishBidPackage;
use App\Filament\Resources\BidPackages\BidPackageResource;
use App\Models\BidPackage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewBidPackage extends ViewRecord
{
    protected static string $resource = BidPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BidPackageResource::importAction(true),
            Action::make('publish')
                ->label('Publish shared package')
                ->authorize('publish')
                ->requiresConfirmation()
                ->modalDescription('Publish this preview for eligible crew. Partial coverage remains labelled. Corrections after publication require a new revision. Saved bid lists will not be migrated automatically.')
                ->action(function (BidPackage $record): void {
                    app(PublishBidPackage::class)->handle($record->id, (int) Auth::id());
                    Notification::make()->success()->title('Package published')->send();
                    $this->redirect(BidPackageResource::getUrl('view', ['record' => $record]));
                }),
            Action::make('archive')
                ->authorize('archive')
                ->requiresConfirmation()
                ->modalDescription('Hide this package from new selections. Existing saved workspaces and bid rankings remain available to their owners.')
                ->action(fn (BidPackage $record) => $this->setArchived($record, true)),
            Action::make('restore')
                ->label('Restore package')
                ->authorize('restore')
                ->requiresConfirmation()
                ->modalDescription('Make this package available for selection again.')
                ->action(fn (BidPackage $record) => $this->setArchived($record, false)),
            DeleteAction::make()
                ->modalDescription('Permanently delete this unused draft. Published packages and drafts with saved workspaces cannot be deleted.')
                ->using(fn (BidPackage $record): bool => app(DeleteBidPackage::class)->handle($record->id)),
        ];
    }

    private function setArchived(BidPackage $record, bool $archive): void
    {
        app(ArchiveBidPackage::class)->handle($record->id, $archive);
        Notification::make()->success()->title($archive ? 'Package archived' : 'Package restored')->send();
        $this->redirect(BidPackageResource::getUrl('view', ['record' => $record]));
    }
}
