<?php

namespace App\Filament\Resources\VotingMembers;

use App\Enums\VotingMemberType;
use App\Filament\Resources\VotingMembers\Pages\CreateVotingMember;
use App\Filament\Resources\VotingMembers\Pages\EditVotingMember;
use App\Filament\Resources\VotingMembers\Pages\ImportVotingMembers;
use App\Filament\Resources\VotingMembers\Pages\ListVotingMembers;
use App\Models\VotingMember;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class VotingMemberResource extends Resource
{
    protected static ?string $model = VotingMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Stimmberechtigte';

    protected static ?string $pluralModelLabel = 'Stimmberechtigte';

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
                            ->helperText(fn (Get $get) => self::isBoard($get) ? 'Der Posten, z. B. „Präsident:in“ – nicht die Person.' : null)
                            ->columnSpanFull(),
                        Select::make('type')
                            ->label('Art')
                            ->options(VotingMemberType::class)
                            ->default(VotingMemberType::Member)
                            ->required()
                            ->selectablePlaceholder(false)
                            ->live()
                            ->disabled(fn (?VotingMember $record) => ! static::canEditVotes($record))
                            ->dehydrated(fn (?VotingMember $record) => static::canEditVotes($record)),
                        TextInput::make('member_count')
                            ->label('Mitgliederzahl')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required()
                            ->default(0)
                            ->live(debounce: 300)
                            ->visible(fn (Get $get) => ! self::isBoard($get))
                            ->disabled(fn (?VotingMember $record) => ! static::canEditVotes($record))
                            ->dehydrated(fn (?VotingMember $record) => static::canEditVotes($record))
                            ->helperText(fn (Get $get) => 'Ergibt '.app(VoteWeightCalculator::class)->votesFor((int) $get('member_count')).' Stimme(n).'),
                        TextInput::make('votes')
                            ->label('Stimmen')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->default(1)
                            ->visible(fn (Get $get) => self::isBoard($get))
                            ->disabled(fn (?VotingMember $record) => ! static::canEditVotes($record))
                            ->dehydrated(fn (?VotingMember $record) => static::canEditVotes($record))
                            ->helperText('Feste Stimmenzahl des Postens.'),
                        Toggle::make('active')
                            ->label('Aktiv')
                            ->helperText('Inaktive Stimmberechtigte können nicht eingecheckt werden.')
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
                TextColumn::make('type')
                    ->label('Art')
                    ->badge()
                    ->sortable(),
                TextColumn::make('member_count')
                    ->label('Mitglieder')
                    ->numeric()
                    ->placeholder('–')
                    ->sortable(),
                TextColumn::make('votes')
                    ->label('Stimmen')
                    ->state(fn (VotingMember $record) => $record->votes)
                    ->badge(),
                TextColumn::make('users_count')
                    ->label('Personen')
                    ->counts('users'),
                IconColumn::make('active')
                    ->label('Aktiv')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Art')->options(VotingMemberType::class),
                TernaryFilter::make('active')->label('Aktiv'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /** Ob das Formular gerade einen Präsidiumsposten zeigt (der Wert kommt als Enum oder String). */
    private static function isBoard(Get $get): bool
    {
        $type = $get('type');

        return ($type instanceof VotingMemberType ? $type : VotingMemberType::tryFrom((string) $type)) === VotingMemberType::BoardMember;
    }

    /**
     * Beim Anlegen gehören die Stimmen dazu, das darf jede:r, die anlegen darf. Bei
     * einem bestehenden Eintrag nur, wer Stimmen ändern darf.
     */
    public static function canEditVotes(?VotingMember $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $record === null
            ? $user->can('create', VotingMember::class)
            : $user->can('updateVotes', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVotingMembers::route('/'),
            'create' => CreateVotingMember::route('/create'),
            'import' => ImportVotingMembers::route('/import'),
            'edit' => EditVotingMember::route('/{record}/edit'),
        ];
    }
}
