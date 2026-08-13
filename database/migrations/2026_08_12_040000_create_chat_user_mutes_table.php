<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_user_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chatable_type');
            $table->unsignedBigInteger('chatable_id');
            $table->foreignId('muted_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('mute_notifications')->default(false);
            $table->boolean('mute_calls')->default(false);
            $table->boolean('shrink_messages')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'chatable_type', 'chatable_id', 'muted_user_id'], 'chat_user_mutes_user_chatable_muted_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_user_mutes');
    }
};
