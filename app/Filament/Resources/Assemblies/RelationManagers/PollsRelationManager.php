<?php

namespace App\Filament\Resources\Assemblies\RelationManagers;

use App\Filament\Resources\Polls\PollResource;
use App\Models\Assembly;
use App\Models\Poll;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PollsRelationManager extends RelationManager
{
    protected static string $relationship = 'polls';

    protected static ?string $title = 'Abstimmungen';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')->label('Titel'),
                TextColumn::make('type')->label('Art')->badge()->color('gray'),
                TextColumn::make('mode')->label('Modus'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Neue Abstimmung')
                    ->url(function () {
                        /** @var Assembly $assembly */
                        $assembly = $this->getOwnerRecord();

                        return PollResource::getUrl('create', ['assembly' => $assembly->getKey()]);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (Poll $record) => PollResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
