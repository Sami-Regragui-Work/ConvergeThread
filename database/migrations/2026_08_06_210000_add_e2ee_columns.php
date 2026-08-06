<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('e2ee_public_key')->nullable()->after('banned_by_id');
        });

        Schema::create('chat_key_shares', function (Blueprint $table) {
            $table->id();
            $table->morphs('chatable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('wrapped_key');
            $table->text('ephemeral_public_key');
            $table->timestamps();

            $table->unique(['chatable_type', 'chatable_id', 'user_id'], 'chat_key_shares_unique');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('content');
        });

        Schema::table('message_attachments', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('original_name');
            $table->string('encryption_iv', 64)->nullable()->after('is_encrypted');
            $table->string('mime_type', 127)->nullable()->after('encryption_iv');
        });
    }

    public function down(): void
    {
        Schema::table('message_attachments', function (Blueprint $table) {
            $table->dropColumn(['is_encrypted', 'encryption_iv', 'mime_type']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('is_encrypted');
        });

        Schema::dropIfExists('chat_key_shares');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('e2ee_public_key');
        });
    }
};
