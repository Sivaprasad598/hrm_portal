<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lead Team → Team Chat: the lead's internal group chat (owner + team), the chat with the lead (the lead + owner + team),
 * or a one-to-one chat between two people on the team.
 */
class LeadChatConversation extends Model
{

    public const GROUP = 'group';
    public const DIRECT = 'direct';
    public const LEAD = 'lead';

    /** Key used by the chat page (?with=) for each shared chat. */
    public const KEYS = [self::GROUP => 'group', self::LEAD => 'lead'];

    protected $guarded = ['id'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function messages()
    {
        return $this->hasMany(LeadChatMessage::class, 'conversation_id');
    }

    /** The lead's group chat, made the first time it is opened. */
    public static function groupFor(Lead $lead): self
    {
        return self::firstOrCreate(
            ['lead_id' => $lead->id, 'type' => self::GROUP, 'user_one' => 0, 'user_two' => 0],
            ['company_id' => $lead->company_id]
        );
    }

    /** The chat with the lead (the lead, the lead owner and the team), separate from the internal group. */
    public static function leadChatFor(Lead $lead): self
    {
        return self::firstOrCreate(
            ['lead_id' => $lead->id, 'type' => self::LEAD, 'user_one' => 0, 'user_two' => 0],
            ['company_id' => $lead->company_id]
        );
    }

    /** The one-to-one chat between two people on the lead's team. */
    public static function directFor(Lead $lead, int $a, int $b): self
    {
        return self::firstOrCreate(
            ['lead_id' => $lead->id, 'type' => self::DIRECT, 'user_one' => min($a, $b), 'user_two' => max($a, $b)],
            ['company_id' => $lead->company_id]
        );
    }

    public function isGroup(): bool
    {
        return $this->type === self::GROUP;
    }

    /** The whole team is in it: the internal group or the chat with the lead (not a one-to-one chat). */
    public function isShared(): bool
    {
        return $this->type !== self::DIRECT;
    }

    /** ?with= key of this chat for a given viewer: group, lead, or the other person's user id. */
    public function keyFor(?int $userId): string
    {
        return self::KEYS[$this->type] ?? (string) $this->otherUserId((int) $userId);
    }

    /** For a one-to-one chat: the other person. */
    public function otherUserId(int $userId): ?int
    {
        return $this->isShared() ? null : ($this->user_one === $userId ? $this->user_two : $this->user_one);
    }

}
