<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Anwesenheit eines Vereins bei einer Versammlung.
 *
 * @property int $votes Stimmen-Snapshot beim Check-in
 * @property Carbon|null $checked_in_at
 */
class AssemblyClub extends Pivot
{
    protected $table = 'assembly_club';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'votes' => 'integer',
            'checked_in_at' => 'datetime',
        ];
    }
}
