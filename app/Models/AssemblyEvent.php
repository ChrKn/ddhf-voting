<?php

namespace App\Models;

use App\Enums\AssemblyEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Eintrag im Ablaufprotokoll einer Versammlung. Wird nie geändert.
 *
 * @property AssemblyEventType $type
 * @property string|null $subject
 * @property int|null $votes
 * @property array<string, mixed>|null $details
 * @property string|null $user_name
 * @property Carbon|null $created_at
 */
#[Fillable(['type', 'voting_member_id', 'poll_id', 'subject', 'votes', 'details', 'user_id', 'user_name'])]
class AssemblyEvent extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (AssemblyEvent $event): void {
            $user = Auth::user();

            if ($event->user_id === null && $user instanceof User) {
                $event->user_id = $user->getKey();
                $event->user_name = $user->name;
            }
        });

        static::updating(fn () => throw new LogicException('Protokolleinträge sind unveränderlich.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssemblyEventType::class,
            'votes' => 'integer',
            'details' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Assembly, $this>
     */
    public function assembly(): BelongsTo
    {
        return $this->belongsTo(Assembly::class);
    }
}
