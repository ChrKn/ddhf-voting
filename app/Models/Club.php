<?php

namespace App\Models;

use App\Services\VoteWeightCalculator;
use Database\Factories\ClubFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Ein Mitglied des DDHF (in der Regel ein Verein). In der Oberfläche heißt
 * es „Mitglied“, im Code und in der Datenbank bleibt es „Club“.
 *
 * @property-read int $votes aktuelle Stimmen laut Mitgliederzahl
 * @property-read AssemblyClub|null $attendance Pivot, wenn über Assembly::clubs() geladen
 */
#[Fillable(['name', 'member_count', 'active'])]
class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    protected $attributes = [
        'member_count' => 0,
        'active' => true,
    ];

    /**
     * Zweite Verteidigungslinie neben Policy und Formular: Ist jemand angemeldet,
     * darf die Mitgliederzahl nur ändern, wer die Berechtigung dazu hat.
     * (Konsole, Seeder und Jobs ohne Login sind nicht betroffen; das Anlegen
     * regelt die create-Policy.)
     */
    protected static function booted(): void
    {
        static::updating(function (Club $club): void {
            $user = Auth::user();

            if ($user !== null && $club->isDirty('member_count')
                && Gate::forUser($user)->denies('updateMemberCount', $club)) {
                throw new AuthorizationException('Nur der Systemadmin darf die Mitgliederzahl ändern.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'member_count' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return BelongsToMany<Assembly, $this, AssemblyClub, 'attendance'>
     */
    public function assemblies(): BelongsToMany
    {
        return $this->belongsToMany(Assembly::class)
            ->using(AssemblyClub::class)
            ->as('attendance')
            ->withPivot(['votes', 'checked_in_at']);
    }

    /**
     * @return Attribute<int, never>
     */
    protected function votes(): Attribute
    {
        return Attribute::get(
            fn (): int => app(VoteWeightCalculator::class)->votesFor((int) $this->member_count)
        );
    }
}
