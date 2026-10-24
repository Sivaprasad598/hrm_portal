<?php

namespace App\Models;

use App\Scopes\ActiveScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A Team Chat message. Its recipients (with read status) are in lead_chat_message_reads, its files in
 * lead_chat_attachments.
 */
class LeadChatMessage extends Model
{

    protected $guarded = ['id'];

    public function conversation()
    {
        return $this->belongsTo(LeadChatConversation::class, 'conversation_id');
    }

    /** from_lead messages were written by the lead (no portal user). */
    protected $casts = ['from_lead' => 'boolean', 'edited_at' => 'datetime', 'deleted_at' => 'datetime'];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /** The earlier message this one replies to (same chat). */
    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScope(ActiveScope::class);
    }

    public function attachments()
    {
        return $this->hasMany(LeadChatAttachment::class, 'message_id');
    }

    /** One row per recipient: read_at, emailed_at. */
    public function reads()
    {
        return $this->hasMany(LeadChatMessageRead::class, 'message_id');
    }

}
