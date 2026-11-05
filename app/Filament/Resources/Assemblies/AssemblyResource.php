<?php

namespace App\Filament\Resources\Assemblies;

use App\Filament\Resources\Assemblies\Pages\ConductAssembly;
use App\Filament\Resources\Assemblies\Pages\CreateAssembly;
use App\Filament\Resources\Assemblies\Pages\EditAssembly;
use App\Filament\Resources\Assemblies\Pages\ListAssemblies;
use App\Filament\Resources\Assemblies\RelationManagers\PollsRelationManager;
use App\Models\Assembly;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AssemblyResource extends Resource
{
    protected static ?string $model = Assembly::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Versammlungen';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Versammlung';

    protected static ?string $pluralModelLabel = 'Versammlungen';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Titel')
                            ->placeholder('Mitgliederversammlung 2027')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('date')
                            ->label('Datum')
                            ->native(false)
                            ->displayFormat('d.m.Y'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable(),
                TextColumn::make('date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('polls_count')
                    ->label('Abstimmungen')
                    ->counts('polls'),
                TextColumn::make('clubs_count')
                    ->label('Eingecheckt')
                    ->counts('clubs'),
            ])
            ->recordUrl(fn (Assembly $record) => static::getUrl('conduct', ['record' => $record]))
            ->recordActions([
                Action::make('conduct')
                    ->label('Leitung')
                    ->icon(Heroicon::OutlinedPresentationChartBar)
                    ->url(fn (Assembly $record) => static::getUrl('conduct', ['record' => $record])),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PollsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssemblies::route('/'),
            'create' => CreateAssembly::route('/create'),
            'edit' => EditAssembly::route('/{record}/edit'),
            'conduct' => ConductAssembly::route('/{record}/leitung'),
        ];
    }
}
