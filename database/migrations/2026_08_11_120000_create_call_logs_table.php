<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('chat_type', 10);
            $table->unsignedBigInteger('chatable_id');
            $table->string('chat_label');
            $table->string('call_id')->unique();
            $table->unsignedBigInteger('caller_user_id');
            $table->foreign('caller_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('call_type', 10);
            $table->string('status', 20)->default('ongoing');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('total_duration')->nullable();
            $table->timestamps();

            $table->index(['chat_type', 'chatable_id', 'started_at']);
            $table->index('caller_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
