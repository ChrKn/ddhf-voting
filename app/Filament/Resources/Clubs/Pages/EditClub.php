<?php

namespace App\Filament\Resources\Clubs\Pages;

use App\Filament\Resources\Clubs\ClubResource;
use App\Models\Club;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClub extends EditRecord
{
    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                // Vereine mit Versammlungshistorie bleiben fürs Protokoll erhalten
                ->hidden(fn (Club $record) => $record->assemblies()->exists()),
        ];
    }
}
