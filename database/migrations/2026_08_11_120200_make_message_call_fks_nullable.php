<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve message and call history when a user account is hard-deleted.
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropForeign(['caller_user_id']);
            $table->unsignedBigInteger('caller_user_id')->nullable()->change();
            $table->foreign('caller_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('call_log_participants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
            $table->unsignedBigInteger('creator_id')->nullable()->change();
            $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropForeign(['caller_user_id']);
            $table->unsignedBigInteger('caller_user_id')->nullable(false)->change();
            $table->foreign('caller_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('call_log_participants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
            $table->unsignedBigInteger('creator_id')->nullable(false)->change();
            $table->foreign('creator_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
