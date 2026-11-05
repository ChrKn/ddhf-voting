<?php

namespace App\Models;

use App\Enums\PollMode;
use App\Enums\PollStatus;
use App\Enums\PollType;
use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property PollType $type
 * @property PollMode $mode
 * @property PollStatus $status
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 */
#[Fillable(['assembly_id', 'title', 'description', 'type', 'mode', 'sort'])]
class Poll extends Model
{
    /** @use HasFactory<PollFactory> */
    use HasFactory;

    protected $attributes = [
        'mode' => 'secret',
        'status' => 'draft',
        'sort' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PollType::class,
            'mode' => PollMode::class,
            'status' => PollStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Assembly, $this>
     */
    public function assembly(): BelongsTo
    {
        return $this->belongsTo(Assembly::class);
    }

    /**
     * @return HasMany<PollPosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(PollPosition::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<PollParticipation, $this>
     */
    public function participations(): HasMany
    {
        return $this->hasMany(PollParticipation::class);
    }

    /**
     * @return HasMany<PollTally, $this>
     */
    public function tallies(): HasMany
    {
        return $this->hasMany(PollTally::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PollStatus::Open;
    }

    public function isPublic(): bool
    {
        return $this->mode === PollMode::Public;
    }

    public function hasVoted(Club $club): bool
    {
        return $this->participations()->where('club_id', $club->getKey())->exists();
    }
}
