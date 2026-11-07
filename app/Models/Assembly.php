<?php

namespace App\Models;

use App\Enums\AssemblyEventType;
use App\Enums\AssemblyStatus;
use Database\Factories\AssemblyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property AssemblyStatus $status
 * @property string $public_token
 * @property Carbon|null $date
 */
#[Fillable(['title', 'date', 'status'])]
class Assembly extends Model
{
    /** @use HasFactory<AssemblyFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
    ];

    protected static function booted(): void
    {
        static::creating(function (Assembly $assembly): void {
            $assembly->public_token ??= Str::random(40);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AssemblyStatus::class,
        ];
    }

    /**
     * @return HasMany<Poll, $this>
     */
    public function polls(): HasMany
    {
        return $this->hasMany(Poll::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Ablaufprotokoll in zeitlicher Reihenfolge.
     *
     * @return HasMany<AssemblyEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AssemblyEvent::class)->orderBy('id');
    }

    /**
     * Schreibt einen Eintrag ins Ablaufprotokoll.
     *
     * @param  array<string, mixed>|null  $details
     */
    public function record(
        AssemblyEventType $type,
        ?Club $club = null,
        ?Poll $poll = null,
        ?int $votes = null,
        ?array $details = null,
        ?User $by = null,
    ): AssemblyEvent {
        return $this->events()->create([
            'type' => $type,
            'club_id' => $club?->getKey(),
            'poll_id' => $poll?->getKey(),
            'subject' => $club->name ?? $poll?->title,
            'votes' => $votes,
            'details' => $details,
            'user_id' => $by?->getKey(),
            'user_name' => $by?->name,
        ]);
    }

    /**
     * Eingecheckte Vereine mit Stimmen-Snapshot.
     *
     * @return BelongsToMany<Club, $this, AssemblyClub, 'attendance'>
     */
    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class)
            ->using(AssemblyClub::class)
            ->as('attendance')
            ->withPivot(['votes', 'checked_in_at']);
    }

    public function isOpen(): bool
    {
        return $this->status === AssemblyStatus::Open;
    }

    public function hasCheckedIn(Club $club): bool
    {
        return $this->clubs()->whereKey($club->getKey())->exists();
    }

    public function totalVotes(): int
    {
        return (int) $this->clubs()->sum('assembly_club.votes');
    }
}
