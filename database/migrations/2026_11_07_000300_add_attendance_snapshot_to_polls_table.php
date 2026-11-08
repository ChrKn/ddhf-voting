<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Anwesenheit beim Beenden der Abstimmung, damit spätere Checkouts das Protokoll nicht verfälschen. */
    public function up(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->unsignedInteger('voting_members_present')->nullable()->after('closed_at');
            $table->unsignedInteger('votes_present')->nullable()->after('voting_members_present');
        });
    }

    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->dropColumn(['voting_members_present', 'votes_present']);
        });
    }
};
