<?php

use App\Services\VoteWeightCalculator;

it('berechnet Stimmen wie die Excel-Formel ABRUNDEN(Mitglieder / 50 + 1)', function (int $members, int $votes) {
    expect((new VoteWeightCalculator(50, 1))->votesFor($members))->toBe($votes);
})->with([
    [0, 1],
    [1, 1],
    [49, 1],
    [50, 2],
    [99, 2],
    [100, 3],
    [149, 3],
    [150, 4],
    [1000, 21],
]);

it('behandelt negative Mitgliederzahlen wie 0', function () {
    expect((new VoteWeightCalculator(50, 1))->votesFor(-5))->toBe(1);
});

it('lässt Teiler und Sockel konfigurieren', function () {
    expect((new VoteWeightCalculator(25, 0))->votesFor(60))->toBe(2);
});

it('lehnt einen Teiler kleiner 1 ab', function () {
    new VoteWeightCalculator(0, 1);
})->throws(InvalidArgumentException::class);
