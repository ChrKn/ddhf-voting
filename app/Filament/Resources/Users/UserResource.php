<?php

namespace App\Filament\Resources\Users;

use App\Actions\SetTemporaryPassword;
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
use Illuminate\Support\HtmlString;
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
                        Select::make('votingMembers')
                            ->label('Vertritt')
                            ->relationship('votingMembers', 'name')
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
                TextColumn::make('votingMembers.name')
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
                static::setPasswordAction(),
            ])
            ->toolbarActions([
                BulkAction::make('invite')
                    ->label('Einladung senden')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->requiresConfirmation()
                    ->modalDescription('Alle markierten Personen erhalten eine E-Mail mit einem Link, um ihr Passwort zu setzen. Die Mails werden direkt verschickt, das kann bei vielen Personen etwas dauern.')
                    ->authorizeIndividualRecords('invite')
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
            ->authorize('invite')
            ->action(function (User $record) {
                static::sendInvitation($record);
            });
    }

    /** Für den Fall, dass die Einladungsmail partout nicht ankommt */
    public static function setPasswordAction(): Action
    {
        return Action::make('setPassword')
            ->label('Passwort festlegen')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Vorläufiges Passwort festlegen')
            ->modalDescription('Es wird ein zufälliges Passwort erzeugt und einmalig angezeigt. Bitte telefonisch oder persönlich weitergeben. Die Person muss es beim ersten Login durch ein eigenes ersetzen. Ein bisheriges Passwort gilt danach nicht mehr.')
            ->modalSubmitActionLabel('Passwort erzeugen')
            ->authorize('setPassword')
            ->action(function (User $record, SetTemporaryPassword $setPassword) {
                /** @var User $by */
                $by = auth()->user();
                $password = $setPassword($record, $by);

                Notification::make()
                    ->title("Vorläufiges Passwort für {$record->name}")
                    ->body(new HtmlString(
                        '<p class="font-mono text-lg font-semibold select-all">'.e($password).'</p>'
                        .'<p>Anmeldung mit '.e($record->email).'. Das Passwort wird nur jetzt angezeigt.</p>'
                    ))
                    ->success()
                    ->persistent()
                    ->send();
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
