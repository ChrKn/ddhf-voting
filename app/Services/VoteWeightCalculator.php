<?php

namespace App\Services;

use InvalidArgumentException;

class VoteWeightCalculator
{
    public function __construct(
        private readonly int $divisor,
        private readonly int $base,
    ) {
        if ($divisor < 1) {
            throw new InvalidArgumentException('Der Teiler für die Stimmenberechnung muss mindestens 1 sein.');
        }
    }

    public static function fromConfig(): self
    {
        return new self(
            (int) config('voting.votes_divisor', 50),
            (int) config('voting.votes_base', 1),
        );
    }

    public function votesFor(int $memberCount): int
    {
        return intdiv(max(0, $memberCount), $this->divisor) + $this->base;
    }
}
