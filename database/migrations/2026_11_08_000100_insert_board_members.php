<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Die Präsidiumsposten nach § 19 Abs. 1 der Satzung, je mit einer Stimme.
 *
 * Bewusst über DB::table statt über das Model: Die Migration soll so laufen, wie sie
 * geschrieben ist, auch wenn sich das Model später ändert. Vorhandene Einträge gleichen
 * Namens bleiben unangetastet, damit ein nachträglich angelegter Posten nicht doppelt
 * entsteht oder überschrieben wird.
 */
return new class extends Migration
{
    private const POSTS = [
        'Präsident:in',
        'Vizepräsident:in Wirtschaft und Finanzen',
        'Vizepräsident:in Sport und Sportentwicklung',
        'Vizepräsident:in Bildung',
        'Gleichstellungsbeauftragte:r',
        'Jugendbeauftragte:r',
        'Beauftragte:r für internationale Kommunikation',
        'Aktivenvertreter:in',
    ];

    public function up(): void
    {
        $now = now();
        $existing = DB::table('voting_members')->whereIn('name', self::POSTS)->pluck('name')->all();

        $rows = collect(self::POSTS)
            ->reject(fn (string $name) => in_array($name, $existing, true))
            ->map(fn (string $name) => [
                'name' => $name,
                'type' => 'board_member',
                'member_count' => null,
                'votes' => 1,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::table('voting_members')->insert($rows);
    }

    /**
     * Entfernt nur Posten ohne Versammlungshistorie. Wer schon eingecheckt war oder
     * abgestimmt hat, bleibt fürs Protokoll erhalten (die Fremdschlüssel verbieten es ohnehin).
     */
    public function down(): void
    {
        DB::table('voting_members')
            ->where('type', 'board_member')
            ->whereIn('name', self::POSTS)
            ->whereNotExists(fn ($q) => $q->from('assembly_voting_member')->whereColumn('assembly_voting_member.voting_member_id', 'voting_members.id'))
            ->whereNotExists(fn ($q) => $q->from('poll_participations')->whereColumn('poll_participations.voting_member_id', 'voting_members.id'))
            ->delete();
    }
};
