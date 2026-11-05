<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['poll_position_id', 'label', 'club_hint', 'is_abstention', 'sort'])]
class PollOption extends Model
{
    public $timestamps = false;

    protected $attributes = [
        'is_abstention' => false,
        'sort' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_abstention' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PollPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(PollPosition::class, 'poll_position_id');
    }
}
