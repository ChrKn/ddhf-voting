<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Anwesenheit eines Vereins bei einer Versammlung.
 *
 * @property int $votes Stimmen-Snapshot beim Check-in
 * @property int|null $member_count Mitgliederzahl, aus der die Stimmen berechnet wurden
 *                                  (leer bei Präsidiumsposten und alten Check-ins)
 * @property Carbon|null $checked_in_at
 */
class AssemblyVotingMember extends Pivot
{
    protected $table = 'assembly_voting_member';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'votes' => 'integer',
            'member_count' => 'integer',
            'checked_in_at' => 'datetime',
        ];
    }
}
