<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property Role $role
 * @property bool $must_change_password
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'member',
        'must_change_password' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<VotingMember, $this>
     */
    public function votingMembers(): BelongsToMany
    {
        return $this->belongsToMany(VotingMember::class);
    }

    public function isSystemAdmin(): bool
    {
        return $this->role === Role::SystemAdmin;
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager;
    }

    /** Systemadmin oder Verwalter:in */
    public function canManage(): bool
    {
        return $this->isSystemAdmin() || $this->isManager();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canManage();
    }

    public function represents(VotingMember $votingMember): bool
    {
        return $this->votingMembers()->whereKey($votingMember->getKey())->exists();
    }
}
