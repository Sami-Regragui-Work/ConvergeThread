<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chatable_type');
            $table->unsignedBigInteger('chatable_id');
            $table->timestamps();

            $table->unique(['user_id', 'chatable_type', 'chatable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_mutes');
    }
};
