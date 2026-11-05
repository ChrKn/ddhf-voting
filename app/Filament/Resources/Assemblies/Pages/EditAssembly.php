<?php

namespace App\Filament\Resources\Assemblies\Pages;

use App\Filament\Resources\Assemblies\AssemblyResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAssembly extends EditRecord
{
    protected static string $resource = AssemblyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('conduct')
                ->label('Versammlungsleitung')
                ->icon(Heroicon::OutlinedPresentationChartBar)
                ->url(fn () => AssemblyResource::getUrl('conduct', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }
}
