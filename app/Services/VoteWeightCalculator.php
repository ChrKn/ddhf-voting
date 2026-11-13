<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Satzung: „Jedes Mitglied hat pro angefangene 50 seiner Einzelmitglieder eine Stimme.“
 * Also aufrunden: 1–50 → 1, 51–100 → 2, 0 → 0.
 */
class VoteWeightCalculator
{
    public function __construct(
        private readonly int $divisor,
    ) {
        if ($divisor < 1) {
            throw new InvalidArgumentException('Der Teiler für die Stimmenberechnung muss mindestens 1 sein.');
        }
    }

    public static function fromConfig(): self
    {
        return new self((int) config('voting.votes_divisor', 50));
    }

    public function votesFor(int $memberCount): int
    {
        // Ganzzahliges Aufrunden, ohne Umweg über float
        return intdiv(max(0, $memberCount) + $this->divisor - 1, $this->divisor);
    }
}
