<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assemblies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('public_token', 64)->unique();
            $table->timestamps();
        });

        // Anwesenheit; "votes" ist der Snapshot der Stimmen beim Check-in
        Schema::create('assembly_voting_member', function (Blueprint $table) {
            $table->foreignId('assembly_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voting_member_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('votes');
            $table->timestamp('checked_in_at')->nullable();
            $table->primary(['assembly_id', 'voting_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_voting_member');
        Schema::dropIfExists('assemblies');
    }
};
