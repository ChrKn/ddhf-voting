<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wer hat abgestimmt (nicht: wie). Der Unique-Index verhindert doppelte Abgabe.
        Schema::create('poll_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('votes');
            // nur bei öffentlichen Abstimmungen befüllt: [position_id => option_id]
            $table->json('public_choices')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['poll_id', 'club_id']);
        });

        // Wie wurde abgestimmt (nicht: wer). Es werden keine Einzelstimmen gespeichert,
        // sondern nur Summen je Option. So lässt sich auch über Einfügereihenfolge
        // (z.B. SQLite-rowid) nichts einem Verein zuordnen.
        Schema::create('poll_tallies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_option_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('votes')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_tallies');
        Schema::dropIfExists('poll_participations');
    }
};
