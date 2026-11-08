<?php

namespace App\Filament\Resources\VotingMembers\Pages;

use App\Filament\Resources\VotingMembers\VotingMemberResource;
use App\Models\VotingMember;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVotingMember extends EditRecord
{
    protected static string $resource = VotingMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                // Vereine mit Versammlungshistorie bleiben fürs Protokoll erhalten
                ->hidden(fn (VotingMember $record) => $record->assemblies()->exists()),
        ];
    }
}
