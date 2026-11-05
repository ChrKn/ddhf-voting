<?php

namespace App\Filament\Resources\Clubs;

use App\Filament\Resources\Clubs\Pages\CreateClub;
use App\Filament\Resources\Clubs\Pages\EditClub;
use App\Filament\Resources\Clubs\Pages\ListClubs;
use App\Models\Club;
use App\Services\VoteWeightCalculator;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Mitglied';

    protected static ?string $pluralModelLabel = 'Mitglieder';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                        TextInput::make('member_count')
                            ->label('Mitgliederzahl')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required()
                            ->default(0)
                            ->live(debounce: 300)
                            ->disabled(fn (?Club $record) => ! static::canEditMemberCount($record))
                            ->dehydrated(fn (?Club $record) => static::canEditMemberCount($record))
                            ->helperText(fn (Get $get) => 'Ergibt '.app(VoteWeightCalculator::class)->votesFor((int) $get('member_count')).' Stimme(n).'),
                        Toggle::make('active')
                            ->label('Aktiv')
                            ->helperText('Inaktive Mitglieder können nicht eingecheckt werden.')
                            ->default(true)
                            ->inline(false),
                        Select::make('users')
                            ->label('Personen mit Zugriff')
                            ->relationship('users', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->email})")
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('member_count')
                    ->label('Mitglieder')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('votes')
                    ->label('Stimmen')
                    ->state(fn (Club $record) => $record->votes)
                    ->badge(),
                TextColumn::make('users_count')
                    ->label('Personen')
                    ->counts('users'),
                IconColumn::make('active')
                    ->label('Aktiv')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Aktiv'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function canEditMemberCount(?Club $record): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('updateMemberCount', $record ?? Club::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubs::route('/'),
            'create' => CreateClub::route('/create'),
            'edit' => EditClub::route('/{record}/edit'),
        ];
    }
}
