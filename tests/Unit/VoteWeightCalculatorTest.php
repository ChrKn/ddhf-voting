<?php

use App\Services\VoteWeightCalculator;

it('gibt pro angefangene 50 Einzelmitglieder eine Stimme, wie in der Satzung', function (int $members, int $votes) {
    expect((new VoteWeightCalculator(50))->votesFor($members))->toBe($votes);
})->with([
    [0, 0],
    [1, 1],
    [49, 1],
    [50, 1],
    [51, 2],
    [99, 2],
    [100, 2],
    [101, 3],
    [150, 3],
    [151, 4],
    [1000, 20],
    [1001, 21],
]);

it('behandelt negative Mitgliederzahlen wie 0', function () {
    expect((new VoteWeightCalculator(50))->votesFor(-5))->toBe(0);
});

it('lässt den Teiler konfigurieren', function () {
    expect((new VoteWeightCalculator(25))->votesFor(60))->toBe(3);
});

it('lehnt einen Teiler kleiner 1 ab', function () {
    new VoteWeightCalculator(0);
})->throws(InvalidArgumentException::class);
