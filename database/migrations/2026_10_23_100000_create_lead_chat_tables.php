<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Lead Team → Team Chat: conversations of a lead's team (one group chat per lead, and one-to-one chats between
     * two people on the team), their messages, who each message is for (read status, unread email), and attachments.
     */
    public function up(): void
    {
        Schema::create('lead_chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->unsignedInteger('lead_id');
            $table->string('type', 10); // group | direct
            // direct: the two people, lower user id first, so a pair has one conversation per lead.
            // group: 0 and 0 (not NULL, so the unique key also allows only one group chat per lead)
            $table->unsignedInteger('user_one')->default(0);
            $table->unsignedInteger('user_two')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'type', 'user_one', 'user_two'], 'lead_chat_conv_unique');
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });

        Schema::create('lead_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedInteger('lead_id')->index();
            $table->unsignedInteger('user_id'); // sender
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->foreign('conversation_id')->references('id')->on('lead_chat_conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // One row per recipient of a message: read status, and the "still unread after 15 minutes" email
        Schema::create('lead_chat_message_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedInteger('user_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('emailed_at')->nullable();

            $table->unique(['message_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
            $table->foreign('message_id')->references('id')->on('lead_chat_messages')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('lead_chat_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('filename', 255); // name shown and downloaded as
            $table->string('path', 255);     // on the lead_chat disk (storage/app/lead-chat), outside the web root
            $table->string('mime', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('lead_chat_messages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_chat_attachments');
        Schema::dropIfExists('lead_chat_message_reads');
        Schema::dropIfExists('lead_chat_messages');
        Schema::dropIfExists('lead_chat_conversations');
    }

};
