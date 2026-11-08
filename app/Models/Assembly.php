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
        ?VotingMember $votingMember = null,
        ?Poll $poll = null,
        ?int $votes = null,
        ?array $details = null,
        ?int $memberCount = null,
        ?User $by = null,
    ): AssemblyEvent {
        return $this->events()->create([
            'type' => $type,
            'voting_member_id' => $votingMember?->getKey(),
            'poll_id' => $poll?->getKey(),
            'subject' => $votingMember->name ?? $poll?->title,
            'votes' => $votes,
            'member_count' => $memberCount,
            'details' => $details,
            'user_id' => $by?->getKey(),
            'user_name' => $by?->name,
        ]);
    }

    /**
     * Eingecheckte Vereine mit Stimmen-Snapshot.
     *
     * @return BelongsToMany<VotingMember, $this, AssemblyVotingMember, 'attendance'>
     */
    public function votingMembers(): BelongsToMany
    {
        return $this->belongsToMany(VotingMember::class)
            ->using(AssemblyVotingMember::class)
            ->as('attendance')
            ->withPivot(['votes', 'member_count', 'checked_in_at']);
    }

    public function isOpen(): bool
    {
        return $this->status === AssemblyStatus::Open;
    }

    public function hasCheckedIn(VotingMember $votingMember): bool
    {
        return $this->votingMembers()->whereKey($votingMember->getKey())->exists();
    }

    public function totalVotes(): int
    {
        return (int) $this->votingMembers()->sum('assembly_voting_member.votes');
    }
}
