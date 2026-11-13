<?php

use App\Support\Changelog;

/*
 * Die Version im Footer und die Seite, auf die sie verlinkt - beides aus CHANGELOG.md gelesen.
 */

afterEach(fn () => Changelog::usePath(null));

function useChangelog(string $markdown): void
{
    $path = tempnam(sys_get_temp_dir(), 'changelog');
    file_put_contents($path, $markdown);
    Changelog::usePath($path);
}

/*
 * Die Datei wird bei jedem Release von Hand bearbeitet, und die Version wird von der ersten
 * Release-Überschrift gelesen. Eine Überschrift, die nicht passt, oder eine in falscher Reihenfolge
 * würde eine falsche Nummer in den Footer bringen, ohne dass etwas fehlschlägt.
 */
it('hat eine wohlgeformte echte Datei, neueste Version zuerst', function () {
    $markdown = file_get_contents(base_path('CHANGELOG.md'));
    preg_match_all('/^## (.+)$/m', $markdown, $headings);

    $versions = [];

    foreach ($headings[1] as $index => $heading) {
        if ($heading === 'Unreleased') {
            expect($index)->toBe(0, '"Unreleased" gehört nach oben');

            continue;
        }

        expect($heading)->toMatch('/^\d+\.\d+\.\d+ - \d{4}-\d{2}-\d{2}$/');
        $versions[] = strtok($heading, ' ');
    }

    expect($versions)->not->toBeEmpty();

    for ($i = 1; $i < count($versions); $i++) {
        expect(version_compare($versions[$i - 1], $versions[$i], '>'))
            ->toBeTrue("{$versions[$i - 1]} sollte vor {$versions[$i]} stehen");
    }

    expect(Changelog::version())->toBe($versions[0]);
});

it('zählt Unreleased nicht als Version', function () {
    useChangelog("# Changelog\n\n## Unreleased\n\n- Something\n\n## 2.3.4 - 2026-01-01\n\n- Else\n");

    expect(Changelog::version())->toBe('2.3.4');
});

it('zeigt die Version im Footer und verlinkt den Changelog', function () {
    useChangelog("# Changelog\n\n## 2.3.4 - 2026-01-01\n\n- Else\n");

    $this->get(route('login'))->assertOk()
        ->assertSee('Version 2.3.4')
        ->assertSee('href="'.route('changelog').'"', false);
});

it('zeigt ohne Release keine Version statt einer geratenen', function () {
    useChangelog("# Changelog\n\n## Unreleased\n\n- Something\n");

    $this->get(route('login'))->assertOk()->assertDontSee('Version ', false);
});

it('zeigt die Datei auf Englisch mit einem Anker pro Release, auch ohne Login', function () {
    useChangelog("# Changelog\n\nIntro.\n\n## 2.3.4 - 2026-01-01\n\n### Added\n- A thing with `code`\n");

    $this->get(route('changelog'))->assertOk()
        ->assertSee('<div class="changelog" lang="en">', false)
        ->assertSee('<h2 id="2.3.4">2.3.4 - 2026-01-01</h2>', false)
        ->assertSee('<code>code</code>', false)
        // Die eigene oberste Überschrift der Datei weicht der der Seite.
        ->assertDontSee('<h1>Changelog</h1>', false);
});

it('reicht Markup aus der Datei nicht durch', function () {
    useChangelog("# Changelog\n\n## 2.3.4 - 2026-01-01\n\n- <script>alert(1)</script>\n");

    $this->get(route('changelog'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
});

it('hat ohne Datei keine Seite', function () {
    Changelog::usePath(base_path('does-not-exist.md'));

    $this->get(route('changelog'))->assertNotFound();
    $this->get(route('login'))->assertOk();
});
