<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ablaufprotokoll einer Versammlung. Es kommen nur Zeilen hinzu; Namen von
     * Mitglied und Abstimmung werden mitgeschrieben, damit spätere Umbenennungen
     * oder Löschungen das Protokoll nicht verändern.
     */
    public function up(): void
    {
        Schema::create('assembly_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assembly_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('voting_member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('poll_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject')->nullable();
            $table->unsignedInteger('votes')->nullable();
            $table->json('details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['assembly_id', 'type', 'voting_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_events');
    }
};
