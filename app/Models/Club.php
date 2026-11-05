<?php

namespace App\Models;

use App\Services\VoteWeightCalculator;
use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read int $votes aktuelle Stimmen laut Mitgliederzahl
 */
#[Fillable(['name', 'member_count', 'active'])]
class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    protected $attributes = [
        'member_count' => 0,
        'active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return BelongsToMany<Assembly, $this, AssemblyClub, 'attendance'>
     */
    public function assemblies(): BelongsToMany
    {
        return $this->belongsToMany(Assembly::class)
            ->using(AssemblyClub::class)
            ->as('attendance')
            ->withPivot(['votes', 'checked_in_at']);
    }

    /**
     * @return Attribute<int, never>
     */
    protected function votes(): Attribute
    {
        return Attribute::get(
            fn (): int => app(VoteWeightCalculator::class)->votesFor((int) $this->member_count)
        );
    }
}
