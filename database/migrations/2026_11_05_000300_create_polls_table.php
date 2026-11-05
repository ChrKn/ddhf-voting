<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assembly_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('mode')->default('secret');
            $table->string('status')->default('draft')->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Ein Amt bei einer Wahl; ein Antrag hat genau eine Position
        Schema::create('poll_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort')->default(0);
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_position_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('club_hint')->nullable();
            $table->boolean('is_abstention')->default(false);
            $table->unsignedInteger('sort')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('poll_positions');
        Schema::dropIfExists('polls');
    }
};
