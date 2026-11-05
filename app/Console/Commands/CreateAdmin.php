<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('voting:create-admin {--name=} {--email=} {--password=}')]
#[Description('Legt einen Systemadmin an (ersetzt die alte Ersteinrichtung)')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: text('Name', required: true),
            'email' => $this->option('email') ?: text('E-Mail-Adresse', required: true),
            'password' => $this->option('password') ?: password('Passwort (mind. 10 Zeichen)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(10)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([...$data, 'role' => Role::SystemAdmin]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->components->info("Systemadmin {$user->email} wurde angelegt.");

        return self::SUCCESS;
    }
}
