<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ein Amt bei einer Wahl bzw. die eine Frage eines Antrags */
#[Fillable(['poll_id', 'title', 'sort'])]
class PollPosition extends Model
{
    public $timestamps = false;

    protected $attributes = [
        'sort' => 0,
    ];

    /**
     * @return BelongsTo<Poll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * @return HasMany<PollOption, $this>
     */
    public function options(): HasMany
    {
        // Enthaltung immer zuletzt
        return $this->hasMany(PollOption::class)
            ->orderBy('is_abstention')
            ->orderBy('sort')
            ->orderBy('id');
    }
}
