<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hält fest, DASS ein Verein abgestimmt hat, bei geheimen Abstimmungen aber nicht WIE.
 *
 * @property int $votes
 * @property array<int|string, int>|null $public_choices position_id => option_id
 */
class PollParticipation extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'votes' => 'integer',
            'public_choices' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Poll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * @return BelongsTo<VotingMember, $this>
     */
    public function votingMember(): BelongsTo
    {
        return $this->belongsTo(VotingMember::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
