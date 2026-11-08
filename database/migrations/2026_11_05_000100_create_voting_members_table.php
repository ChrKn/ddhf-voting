<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voting_members', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // member: Stimmen aus member_count; board_member: feste Stimmen in votes
            $table->string('type')->default('member');
            $table->unsignedInteger('member_count')->nullable();
            $table->unsignedInteger('votes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_voting_member', function (Blueprint $table) {
            $table->foreignId('voting_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['voting_member_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_voting_member');
        Schema::dropIfExists('voting_members');
    }
};
