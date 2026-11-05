<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Password;
use Throwable;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Person';

    protected static ?string $pluralModelLabel = 'Personen';

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
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('E-Mail-Adresse')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('role')
                            ->label('Rolle')
                            ->options(Role::class)
                            ->default(Role::Member)
                            ->required()
                            // Eigene Rolle nicht änderbar, damit sich niemand aussperrt
                            ->disabled(fn (?User $record) => $record?->is(auth()->user()) ?? false),
                        Select::make('clubs')
                            ->label('Vertritt')
                            ->relationship('clubs', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
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
                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rolle')
                    ->badge(),
                TextColumn::make('clubs.name')
                    ->label('Vertritt')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('role')->label('Rolle')->options(Role::class),
                // Wird gesetzt, sobald jemand über den Einladungslink ein Passwort vergibt
                TernaryFilter::make('email_verified_at')
                    ->label('Passwort gesetzt')
                    ->nullable(),
            ])
            ->recordActions([
                EditAction::make(),
                static::inviteAction(),
            ])
            ->toolbarActions([
                BulkAction::make('invite')
                    ->label('Einladung senden')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->requiresConfirmation()
                    ->modalDescription('Alle markierten Personen erhalten eine E-Mail mit einem Link, um ihr Passwort zu setzen. Die Mails werden direkt verschickt, das kann bei vielen Personen etwas dauern.')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => static::sendInvitations($records)),
            ]);
    }

    /**
     * @param  Collection<int, User>  $users
     */
    public static function sendInvitations(Collection $users): void
    {
        $sent = 0;
        $failed = [];

        foreach ($users as $user) {
            try {
                Password::sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT
                    ? $sent++
                    : $failed[] = $user->email;
            } catch (Throwable $e) {
                report($e);
                $failed[] = $user->email;
            }
        }

        $notification = Notification::make()->title("{$sent} Einladung(en) verschickt");
        $failed === []
            ? $notification->success()->send()
            : $notification->warning()->body('Nicht verschickt (evtl. gerade erst angefordert): '.implode(', ', $failed))->send();
    }

    public static function inviteAction(): Action
    {
        return Action::make('invite')
            ->label('Einladung senden')
            ->icon(Heroicon::OutlinedEnvelope)
            ->requiresConfirmation()
            ->modalDescription('Die Person erhält eine E-Mail mit einem Link, um ihr Passwort zu setzen.')
            ->action(function (User $record) {
                static::sendInvitation($record);
            });
    }

    public static function sendInvitation(User $user): void
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        $status === Password::RESET_LINK_SENT
            ? Notification::make()->title('Einladung verschickt')->success()->send()
            : Notification::make()->title(__($status))->danger()->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
