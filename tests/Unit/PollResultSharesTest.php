<?php

use App\Services\PollResult;

it('rundet die Anteile kaufmännisch auf zwei Nachkommastellen', function (array $votes, array $shares) {
    expect(PollResult::shares($votes))->toBe($shares);
})->with([
    'glatt' => [[3, 1], [75.0, 25.0]],
    'aufrunden ab ,005' => [[1, 7], [12.5, 87.5]],
    'zwei Drittel' => [[2, 1], [66.67, 33.33]],
    'ein Siebtel' => [[1, 6], [14.29, 85.71]],
    'nur eine Option' => [[5], [100.0]],
    'Option ohne Stimmen' => [[4, 0], [100.0, 0.0]],
]);

it('gleicht Rundungsdifferenzen aus, damit die Summe stets 100 ergibt', function (array $votes, array $shares) {
    $result = PollResult::shares($votes);

    expect($result)->toBe($shares)
        ->and(round(array_sum($result), 2))->toBe(100.0);
})->with([
    'drei gleich' => [[1, 1, 1], [33.34, 33.33, 33.33]],
    'kaufmännisch passt schon' => [[1, 2, 2, 2], [14.29, 28.57, 28.57, 28.57]],
    'Gleichstand beim Rest: mehr Stimmen zuerst' => [[1, 1, 2], [25.0, 25.0, 50.0]],
    'drei gleich hinter einer größeren' => [[3, 1, 1, 1], [50.0, 16.67, 16.67, 16.66]],
    'sechs gleich' => [[1, 1, 1, 1, 1, 1], [16.67, 16.67, 16.67, 16.67, 16.66, 16.66]],
]);

it('ergibt für beliebige Verteilungen exakt 100', function () {
    mt_srand(42);
    for ($i = 0; $i < 500; $i++) {
        $votes = array_map(fn () => mt_rand(0, 300), range(1, mt_rand(2, 8)));
        if (array_sum($votes) === 0) {
            continue;
        }

        $hundredths = array_map(fn (float $s) => (int) round($s * 100), PollResult::shares($votes));
        expect(array_sum($hundredths))->toBe(10000);
    }
});

it('liefert ohne Stimmen nur Nullen und behält die Schlüssel', function () {
    expect(PollResult::shares([7 => 0, 9 => 0]))->toBe([7 => 0.0, 9 => 0.0])
        ->and(PollResult::shares([]))->toBe([]);
});
