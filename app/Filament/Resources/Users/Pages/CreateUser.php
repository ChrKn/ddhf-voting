<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Role;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Neue Personen bekommen ein zufälliges Passwort und setzen ihr eigenes
     * über den Einladungslink. Wer keine Rollen vergeben darf, legt Vertreter:innen an.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = Str::password(32);

        if (! UserResource::canChangeRole(null)) {
            $data['role'] = Role::Member;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        UserResource::sendInvitation($user);
    }
}
