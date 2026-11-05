<?php

namespace App\Filament\Resources\Polls;

use App\Enums\PollMode;
use App\Enums\PollType;
use App\Filament\Resources\Polls\Pages\CreatePoll;
use App\Filament\Resources\Polls\Pages\EditPoll;
use App\Filament\Resources\Polls\Pages\ListPolls;
use App\Models\Poll;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PollResource extends Resource
{
    protected static ?string $model = Poll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static string|UnitEnum|null $navigationGroup = 'Versammlungen';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Abstimmung';

    protected static ?string $pluralModelLabel = 'Abstimmungen';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('assembly_id')
                            ->label('Versammlung')
                            ->relationship('assembly', 'title')
                            ->default(fn () => request()->integer('assembly') ?: null)
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('title')
                            ->label('Titel / Frage')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->helperText('Optional, z.B. Antragstext. Wird in der Wahlmaske angezeigt.')
                            ->rows(3)
                            ->columnSpanFull(),
                        Radio::make('type')
                            ->label('Art')
                            ->options(PollType::class)
                            ->descriptions([
                                PollType::Motion->value => 'Dafür / Dagegen / Enthaltung',
                                PollType::Election->value => 'Ein oder mehrere Ämter mit Kandidat:innen',
                            ])
                            ->default(PollType::Motion)
                            ->required()
                            ->live(),
                        Radio::make('mode')
                            ->label('Modus')
                            ->options(PollMode::class)
                            ->descriptions([
                                PollMode::Secret->value => 'Es wird nur gespeichert, dass ein Mitglied abgestimmt hat.',
                                PollMode::Public->value => 'Die Wahl jedes Mitglieds ist im Detailergebnis sichtbar.',
                            ])
                            ->default(PollMode::Secret)
                            ->required(),
                    ]),

                Section::make('Ämter und Kandidat:innen')
                    ->description('„Enthaltung“ wird bei jedem Amt automatisch ergänzt.')
                    ->visible(fn (Get $get) => static::isElection($get('type')))
                    ->schema([
                        Repeater::make('positions')
                            ->hiddenLabel()
                            ->relationship('positions')
                            ->orderColumn('sort')
                            ->itemLabel(fn (array $state) => $state['title'] ?? null)
                            ->addActionLabel('Amt hinzufügen')
                            ->collapsible()
                            ->minItems(1)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Amt')
                                    ->placeholder('Präsident:in')
                                    ->required()
                                    ->maxLength(255),
                                Repeater::make('options')
                                    ->label('Kandidat:innen')
                                    ->relationship(
                                        'options',
                                        modifyQueryUsing: fn (Builder $query) => $query->where('is_abstention', false),
                                    )
                                    ->orderColumn('sort')
                                    ->addActionLabel('Kandidat:in hinzufügen')
                                    ->minItems(1)
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('club_hint')
                                            ->label('Mitglied')
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function isElection(mixed $type): bool
    {
        return $type === PollType::Election || $type === PollType::Election->value;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('assembly.title')
                    ->label('Versammlung'),
                TextColumn::make('type')
                    ->label('Art')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('mode')
                    ->label('Modus'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('assembly')->label('Versammlung')->relationship('assembly', 'title'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPolls::route('/'),
            'create' => CreatePoll::route('/create'),
            'edit' => EditPoll::route('/{record}/edit'),
        ];
    }
}
