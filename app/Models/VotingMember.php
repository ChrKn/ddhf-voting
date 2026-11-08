<?php

namespace App\Models;

use App\Enums\VotingMemberType;
use App\Services\VoteWeightCalculator;
use Database\Factories\VotingMemberFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Stimmberechtigte der Versammlung, in der Oberfläche „Stimmberechtigte“:
 *
 * - Mitglieder des DDHF (in der Regel Vereine): Stimmen aus der Mitgliederzahl,
 *   siehe VoteWeightCalculator; die Spalte votes bleibt leer.
 * - Präsidiumsposten: eine feste Stimmenzahl in votes, die am Posten hängt, nicht
 *   an der Person, die ihn gerade innehat; member_count bleibt leer.
 *
 * @property VotingMemberType $type
 * @property int|null $member_count
 * @property int $votes Stimmen nach Typ: berechnet bzw. fest
 * @property-read AssemblyVotingMember|null $attendance Pivot, wenn über Assembly::votingMembers() geladen
 */
#[Fillable(['name', 'type', 'member_count', 'votes', 'active'])]
class VotingMember extends Model
{
    /** @use HasFactory<VotingMemberFactory> */
    use HasFactory;

    protected $attributes = [
        'type' => 'member',
        'member_count' => 0,
        'active' => true,
    ];

    /**
     * Je Typ gilt genau eine der beiden Zahlen; die andere wird geleert, damit nichts
     * Veraltetes stehen bleibt, wenn ein Eintrag den Typ wechselt.
     *
     * Dazu die zweite Verteidigungslinie neben Policy und Formular: Ist jemand angemeldet,
     * darf Mitgliederzahl, feste Stimmen und Typ nur ändern, wer die Berechtigung dazu hat.
     * (Konsole, Seeder und Jobs ohne Login sind nicht betroffen; das Anlegen
     * regelt die create-Policy.)
     */
    protected static function booted(): void
    {
        static::saving(function (VotingMember $votingMember): void {
            if ($votingMember->type === VotingMemberType::BoardMember) {
                if ((int) ($votingMember->getAttributes()['votes'] ?? 0) < 1) {
                    throw new InvalidArgumentException('Ein Präsidiumsposten braucht mindestens eine Stimme.');
                }

                $votingMember->member_count = null;
            } else {
                $votingMember->member_count ??= 0;
                // Die Spalte, nicht der Accessor: der liest für Mitglieder die berechneten Stimmen
                $votingMember->setAttribute('votes', null);
            }
        });

        static::updating(function (VotingMember $votingMember): void {
            $user = Auth::user();

            if ($user !== null && $votingMember->isDirty(['type', 'member_count', 'votes'])
                && Gate::forUser($user)->denies('updateVotes', $votingMember)) {
                throw new AuthorizationException('Nur der Systemadmin darf die Stimmen ändern.');
            }
        });
    }

    public function isBoardMember(): bool
    {
        return $this->type === VotingMemberType::BoardMember;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeMembers(Builder $query): void
    {
        $query->where('type', VotingMemberType::Member);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeBoardMembers(Builder $query): void
    {
        $query->where('type', VotingMemberType::BoardMember);
    }

    /** Vergleichsform eines Namens: klein geschrieben, Leerraum vereinheitlicht. */
    public static function normalizeName(string $name): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($name)) ?? '');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VotingMemberType::class,
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
     * @return BelongsToMany<Assembly, $this, AssemblyVotingMember, 'attendance'>
     */
    public function assemblies(): BelongsToMany
    {
        return $this->belongsToMany(Assembly::class)
            ->using(AssemblyVotingMember::class)
            ->as('attendance')
            ->withPivot(['votes', 'checked_in_at']);
    }

    /**
     * Liest die Spalte votes nur für Präsidiumsposten; Mitglieder rechnen aus der
     * Mitgliederzahl. Gesetzt wird die Spalte ganz normal (kein Setter).
     *
     * @return Attribute<int, never>
     */
    protected function votes(): Attribute
    {
        return Attribute::get(
            fn (mixed $value): int => $this->isBoardMember()
                ? (int) $value
                : app(VoteWeightCalculator::class)->votesFor((int) $this->member_count)
        );
    }
}
