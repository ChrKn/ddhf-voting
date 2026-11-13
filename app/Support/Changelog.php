<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * CHANGELOG.md, gelesen als die eine Stelle, an der die laufende Version steht.
 *
 * Die Version ist die oberste veröffentlichte Überschrift - "## 1.0.0 - 2026-11-08" - und nie
 * "Unreleased". Diese Überschrift umzubenennen gehört ohnehin zu jedem Release, die Nummer im Footer
 * ist also die Nummer in der Datei, die das Release beschreibt, ohne dass sonst etwas nachgezogen
 * werden muss. Git wird nicht gefragt: Ein Server, der den Code nur als Dateien bekommen hat, hat
 * kein Repository.
 *
 * Nur auf Englisch: Die Datei richtet sich an die, die das System pflegen, und eine Übersetzung wäre
 * ein zweites Dokument, das aktuell gehalten werden müsste.
 */
final class Changelog
{
    /** "## 1.0.0 - 2026-11-08", toleriert auch "[1.0.0]" und ein "v" davor. */
    private const RELEASE = '/^## \[?v?(\d+\.\d+\.\d+)\]?/m';

    private static ?string $file = null;

    public static function path(): string
    {
        return self::$file ?? base_path('CHANGELOG.md');
    }

    /** Für Tests: eine andere Datei lesen, oder null für zurück zur echten. */
    public static function usePath(?string $path): void
    {
        self::$file = $path;
    }

    /**
     * Die Version dieses Codes, oder null, wenn die Datei fehlt oder noch kein Release nennt. Dann
     * wird keine Version angezeigt statt einer erfundenen.
     */
    public static function version(): ?string
    {
        $markdown = self::markdown();

        return $markdown !== null && preg_match(self::RELEASE, $markdown, $match) === 1 ? $match[1] : null;
    }

    /**
     * Die Datei als HTML, ohne ihre eigene oberste Überschrift - die setzt die Seite. Jede
     * Release-Überschrift bekommt die Version als id, damit /changelog#1.0.0 dort landet.
     */
    public static function html(): ?string
    {
        $markdown = self::markdown();

        if ($markdown === null) {
            return null;
        }

        $markdown = preg_replace('/\A\s*# [^\n]*\n/', '', $markdown);

        // Die Datei ist unsere, aber es gibt keinen Grund, warum sie Markup durchreichen können sollte.
        $html = Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);

        return preg_replace_callback(
            '/<h2>v?(\d+\.\d+\.\d+)/',
            fn (array $match) => '<h2 id="'.$match[1].'">'.substr($match[0], 4),
            $html,
        );
    }

    private static function markdown(): ?string
    {
        $path = self::path();

        return is_file($path) ? (string) file_get_contents($path) : null;
    }
}
