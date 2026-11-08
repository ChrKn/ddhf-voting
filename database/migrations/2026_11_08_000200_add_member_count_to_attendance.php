<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Mitgliederzahl, aus der die Stimmen beim Check-in berechnet wurden, wird mit
 * eingefroren. So passen Stimmen und Mitgliederzahl in der Wahlmaske immer zusammen,
 * auch wenn die Mitgliederzahl später geändert wird.
 *
 * Leer bei Präsidiumsposten (feste Stimmen) und bei Check-ins von vor dieser Migration.
 * Im Ereignisprotokoll steht sie auch, weil ein erneuter Check-in den Snapshot des
 * ersten übernimmt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assembly_voting_member', function (Blueprint $table) {
            $table->unsignedInteger('member_count')->nullable()->after('votes');
        });

        Schema::table('assembly_events', function (Blueprint $table) {
            $table->unsignedInteger('member_count')->nullable()->after('votes');
        });
    }

    public function down(): void
    {
        Schema::table('assembly_voting_member', function (Blueprint $table) {
            $table->dropColumn('member_count');
        });

        Schema::table('assembly_events', function (Blueprint $table) {
            $table->dropColumn('member_count');
        });
    }
};
