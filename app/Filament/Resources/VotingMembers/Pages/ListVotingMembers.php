<?php

namespace App\Filament\Resources\VotingMembers\Pages;

use App\Filament\Resources\VotingMembers\VotingMemberResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListVotingMembers extends ListRecords
{
    protected static string $resource = VotingMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Importieren')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->visible(fn () => ImportVotingMembers::canAccess())
                ->url(fn () => VotingMemberResource::getUrl('import')),
            CreateAction::make(),
        ];
    }
}
