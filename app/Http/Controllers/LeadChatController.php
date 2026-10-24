<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadChatAttachment;
use App\Models\LeadChatConversation;
use App\Models\LeadChatMessage;
use App\Models\LeadChatMessageRead;
use App\Models\User;
use App\Notifications\LeadChatNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lead Team → Team Chat: a group chat with the whole lead team and one-to-one chats between team members,
 * kept per lead. Only the lead owner and the employees assigned to the lead (Assign Team) can read or post;
 * administrators can read and post in the group chat. Messages arrive by polling (every few seconds).
 */
class LeadChatController extends AccountBaseController
{

    private const MAX_FILE_KB = 20480; // 20 MB
    private const FILE_TYPES = 'pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt,png,jpg,jpeg,gif,webp,zip,rar,7z,kml,kmz,shp,shx,dbf,prj,geojson,gpx,dwg,dxf,tif,tiff';

    /**
     * The chats on the left: the group chat, then a one-to-one chat with each other team member, with unread counts.
     */
    public function index($leadId)
    {
        $lead = $this->lead($leadId);
        $me = user()->id;
        $members = $this->memberUsers($lead);

        $group = LeadChatConversation::groupFor($lead);
        $directs = LeadChatConversation::where('lead_id', $lead->id)->where('type', LeadChatConversation::DIRECT)
            ->where(fn ($q) => $q->where('user_one', $me)->orWhere('user_two', $me))->get()
            ->keyBy(fn ($c) => $c->otherUserId($me));

        $unread = LeadChatMessageRead::where('lead_chat_message_reads.user_id', $me)->whereNull('read_at')
            ->join('lead_chat_messages', 'lead_chat_messages.id', '=', 'lead_chat_message_reads.message_id')
            ->where('lead_chat_messages.lead_id', $lead->id)
            ->selectRaw('lead_chat_messages.conversation_id, COUNT(*) AS n')->groupBy('lead_chat_messages.conversation_id')
            ->pluck('n', 'conversation_id');

        $chats = [[
            'key' => 'group',
            'name' => $this->groupName($lead),
            // Like a group chat header: who is in it
            'subtitle' => $members->pluck('name')->implode(', '),
            'members' => $members->count(),
            'unread' => (int) ($unread[$group->id] ?? 0),
            'last_message_at' => $group->last_message_at?->toIso8601String(),
        ]];

        // The chat with the lead (lead + owner + team), separate from the internal group above
        $leadChat = LeadChatConversation::leadChatFor($lead);
        $chats[] = [
            'key' => 'lead',
            'name' => $this->leadChatName($lead),
            'subtitle' => 'Lead · ' . $lead->client_name . ($lead->company_name && $lead->company_name !== $lead->client_name ? ' (' . $lead->company_name . ')' : '') . ' + team',
            'members' => $members->count() + 1,
            'unread' => (int) ($unread[$leadChat->id] ?? 0),
            'last_message_at' => $leadChat->last_message_at?->toIso8601String(),
        ];

        // One-to-one chats with each team member: for people on the team, and for administrators
        $isMember = $members->contains('id', $me);

        foreach (($isMember || $this->isAdmin()) ? $members->where('id', '!=', $me) : [] as $member) {
            $conversation = $directs[$member->id] ?? null;
            $chats[] = [
                'key' => (string) $member->id,
                'name' => $member->name,
                'subtitle' => $member->chat_role . ($member->designation_name ? ' · ' . $member->designation_name : ''),
                'image' => $member->image_url,
                'unread' => $conversation ? (int) ($unread[$conversation->id] ?? 0) : 0,
                'last_message_at' => $conversation?->last_message_at?->toIso8601String(),
            ];
        }

        // Private chats an administrator (not on the team) started with me: so I can see and reply to them
        $others = $directs->keys()->diff($members->pluck('id'))->reject(fn ($id) => $id === $me);

        foreach (User::withoutGlobalScope(\App\Scopes\ActiveScope::class)->whereIn('id', $others)->get() as $other) {
            $conversation = $directs[$other->id];
            $chats[] = [
                'key' => (string) $other->id,
                'name' => $other->name,
                'subtitle' => 'Administrator',
                'image' => $other->image_url,
                'unread' => (int) ($unread[$conversation->id] ?? 0),
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'outside_team' => true,
            ];
        }

        return response()->json([
            'chats' => $chats,
            'unread_total' => (int) $unread->sum(),
            'is_member' => $isMember,
        ]);
    }

    /**
     * Messages of one chat (?with=group|<user id>), oldest first; ?after=<id> returns only newer ones (polling).
     * Opening a chat marks its messages to me as read.
     */
    public function messages(Request $request, $leadId)
    {
        $lead = $this->lead($leadId);
        $conversation = $this->conversation($lead, (string) $request->get('with', 'group'));
        $me = user()->id;

        $query = $conversation->messages()->with(['sender:id,name,image', 'attachments', 'reads', 'replyTo.sender:id,name', 'replyTo.attachments'])->orderBy('id');

        if ($request->filled('after')) {
            $query->where('id', '>', (int) $request->get('after'));
        } else {
            // First load: the latest 100
            $query->where('id', '>=', (int) $conversation->messages()->orderByDesc('id')->skip(99)->value('id'));
        }

        $messages = $query->get();

        // Edited or deleted since my last poll (?since= is the "now" this endpoint returned last time)
        $changed = collect();

        if ($request->filled('since') && $request->filled('after')) {
            $changed = $conversation->messages()->with(['sender:id,name,image', 'attachments', 'reads', 'replyTo.sender:id,name', 'replyTo.attachments'])
                ->where('id', '<=', (int) $request->get('after'))
                ->where('updated_at', '>=', \Carbon\Carbon::parse($request->get('since'))->subSeconds(2))
                ->where(fn ($q) => $q->whereNotNull('edited_at')->orWhereNotNull('deleted_at'))
                ->get();
        }

        LeadChatMessageRead::where('user_id', $me)->whereNull('read_at')
            ->whereIn('message_id', $conversation->messages()->select('id'))
            ->update(['read_at' => now()]);

        // Read receipts of my recent messages change while the chat is open: send them again on every poll
        $receipts = $conversation->messages()->where('user_id', $me)->orderByDesc('id')->limit(30)
            ->withCount(['reads', 'reads as read_count' => fn ($q) => $q->whereNotNull('read_at')])->get()
            ->mapWithKeys(fn ($m) => [$m->id => ['read' => $m->read_count, 'of' => $m->reads_count]]);

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->present($m))->values(),
            'changed' => $changed->map(fn ($m) => $this->present($m))->values(),
            'receipts' => $receipts,
            'now' => now()->toIso8601String(),
        ]);
    }

    /**
     * Group info (Members / Media), like a group chat's info page: everyone on the team with their role, designation
     * and contact details, and the files shared in this chat. For a one-to-one chat: that person and the files shared.
     */
    public function info(Request $request, $leadId)
    {
        $lead = $this->lead($leadId);
        $with = (string) $request->get('with', 'group');
        $conversation = $this->conversation($lead, $with);
        $me = user()->id;

        $people = $this->memberUsers($lead)
            ->when(!$conversation->isShared(), fn ($c) => $c->whereIn('id', [(int) $with, $me]))
            ->sortBy(fn ($u) => $u->id === $me ? 0 : ($u->chat_role === 'Lead Owner' ? 1 : 2))
            ->map(fn ($u) => [
                'id' => $u->id,
                'me' => $u->id === $me,
                'name' => $u->name,
                'image' => $u->image_url,
                'role' => $u->chat_role,
                'designation' => $u->designation_name,
                'department' => $u->employeeDetail?->department?->team_name,
                'email' => $u->email,
                'phone' => $u->mobile ? trim(($u->country_phonecode ? '+' . $u->country_phonecode . ' ' : '') . $u->mobile) : null,
                'active' => $u->status === 'active',
            ])->values();

        // The chat with the lead: the lead is in it too (they post once leads have a login)
        if ($conversation->type === LeadChatConversation::LEAD) {
            $people->splice(1, 0, [[
                'id' => 'lead',
                'me' => false,
                'name' => $lead->client_name,
                'image' => null,
                'role' => 'Lead',
                'designation' => $lead->company_name ? 'Lead · ' . $lead->company_name : 'Lead contact',
                'department' => null,
                'email' => $lead->client_email,
                'phone' => $lead->mobile,
                'active' => true,
            ]]);
        }

        $media = LeadChatAttachment::whereIn('message_id', $conversation->messages()->select('id'))
            ->with('message.sender:id,name')->orderByDesc('id')->limit(200)->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->filename,
                'category' => $a->category,
                'size' => $a->sizeLabel(),
                'image' => Str::startsWith((string) $a->mime, 'image/'),
                'url' => route('lead-chat.attachment', $a->id),
                'sender' => $a->message->from_lead ? $lead->client_name . ' (Lead)' : ($a->message->sender?->name ?? 'Former employee'),
                'time' => $a->created_at->timezone(company()->timezone ?? config('app.timezone'))->format('d M Y, h:i A'),
            ]);

        return response()->json([
            'name' => match ($with) {
                'group' => $this->groupName($lead),
                'lead' => $this->leadChatName($lead),
                default => $people->firstWhere('me', false)['name'] ?? 'Chat',
            },
            'type' => $conversation->type,
            'lead' => $lead->client_name,
            'project' => $lead->leadProject?->name,
            'created' => $conversation->created_at?->format('d M Y'),
            'members' => $people,
            'media' => $media,
        ]);
    }

    public function send(Request $request, $leadId)
    {
        $lead = $this->lead($leadId);
        $conversation = $this->conversation($lead, (string) $request->get('with', 'group'));

        $request->validate([
            'body' => 'nullable|string|max:5000',
            'reply_to' => 'nullable|integer',
            'files' => 'nullable|array|max:10',
            'files.*' => [
                'file', 'max:' . self::MAX_FILE_KB, 'mimes:' . self::FILE_TYPES,
                // Only files named after an approved category (Deliverables, Survey, Engineering Requirements, ...)
                function ($attribute, $file, $fail) {
                    if ($file instanceof \Illuminate\Http\UploadedFile && !LeadChatAttachment::categoryFor($file->getClientOriginalName())) {
                        $fail('"' . $file->getClientOriginalName() . '" is not allowed. The file name must include one of: '
                            . implode(', ', array_keys(LeadChatAttachment::CATEGORIES)) . ' (e.g. "Deliverables - GIS map.pdf").');
                    }
                },
            ],
        ], [
            'files.*.max' => 'Each file can be up to 20 MB.',
            'files.*.mimes' => 'This file type cannot be sent. Use documents, images, GIS files (KML, SHP, GeoJSON, DWG…) or a zip.',
        ]);

        $body = trim((string) $request->body);

        if ($body === '' && !$request->hasFile('files')) {
            return response()->json(['message' => 'Type a message or attach a file.'], 422);
        }

        // Reply: to a message of this same chat
        $replyTo = $request->filled('reply_to')
            ? $conversation->messages()->whereNull('deleted_at')->find((int) $request->reply_to)
            : null;

        if ($request->filled('reply_to') && !$replyTo) {
            return response()->json(['message' => 'The message you replied to is no longer available.'], 422);
        }

        $me = user()->id;
        $recipients = $conversation->isShared()
            ? $this->memberIds($lead)->reject(fn ($id) => $id === $me)
            : collect([$conversation->otherUserId($me)]);

        $message = DB::transaction(function () use ($request, $conversation, $lead, $body, $me, $recipients, $replyTo) {
            $message = $conversation->messages()->create([
                'lead_id' => $lead->id,
                'user_id' => $me,
                'body' => $body ?: null,
                'reply_to_id' => $replyTo?->id,
            ]);

            foreach ((array) $request->file('files') as $file) {
                $path = $file->store((string) $lead->id, 'lead_chat');
                $message->attachments()->create([
                    'filename' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'category' => LeadChatAttachment::categoryFor($file->getClientOriginalName()),
                    'path' => $path,
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            foreach ($recipients as $userId) {
                $message->reads()->create(['user_id' => $userId]);
            }

            $conversation->forceFill(['last_message_at' => now()])->save();

            return $message;
        });

        // Bell notification for each recipient; a failure must not lose the message
        try {
            foreach (User::withoutGlobalScope(\App\Scopes\ActiveScope::class)->whereIn('id', $recipients)->where('status', 'active')->get() as $user) {
                $user->notify(new LeadChatNotification($lead, 'message', $message));
            }
        } catch (\Throwable $e) {
            Log::warning('Team Chat notification failed for message ' . $message->id . ': ' . $e->getMessage());
        }

        return response()->json(['message' => $this->present($message->load(['sender:id,name,image', 'attachments', 'reads', 'replyTo.sender:id,name', 'replyTo.attachments']))]);
    }

    /**
     * Edit my message's text. Only the sender, and not after it was deleted.
     */
    public function update(Request $request, $leadId, $messageId)
    {
        $message = $this->ownMessage($leadId, $messageId);
        abort_403($message->user_id !== user()->id || $message->from_lead);

        $request->validate(['body' => 'nullable|string|max:5000']);
        $body = trim((string) $request->body);

        if ($body === '' && $message->attachments->isEmpty()) {
            return response()->json(['message' => 'A message cannot be empty. Delete it instead.'], 422);
        }

        if ($body !== (string) $message->body) {
            $message->forceFill(['body' => $body ?: null, 'edited_at' => now()])->save();
        }

        return response()->json(['message' => $this->present($message->fresh(['sender:id,name,image', 'attachments', 'reads', 'replyTo.sender:id,name', 'replyTo.attachments']))]);
    }

    /**
     * Delete a message: my own, or any message for an administrator. It stays in the thread as "This message was
     * deleted"; its text is cleared and its files are removed from the disk.
     */
    public function destroy($leadId, $messageId)
    {
        $message = $this->ownMessage($leadId, $messageId);
        abort_403($message->user_id !== user()->id && !$this->isAdmin());

        DB::transaction(function () use ($message) {
            foreach ($message->attachments as $attachment) {
                Storage::disk('lead_chat')->delete($attachment->path);
                $attachment->delete();
            }

            // Nobody is left with an unread badge or an unread email for it
            $message->reads()->whereNull('read_at')->update(['read_at' => now()]);
            $message->forceFill(['body' => null, 'deleted_at' => now(), 'deleted_by' => user()->id])->save();
        });

        return response()->json(['message' => $this->present($message->fresh(['sender:id,name,image', 'attachments', 'reads', 'replyTo.sender:id,name', 'replyTo.attachments']))]);
    }

    /** A message of this lead's chat that I can see, not already deleted. */
    private function ownMessage($leadId, $messageId): LeadChatMessage
    {
        $lead = $this->lead($leadId);
        $message = LeadChatMessage::with(['conversation', 'attachments'])->where('lead_id', $lead->id)->findOrFail($messageId);
        abort_if($message->deleted_at, 422, 'This message was already deleted.');

        // The chat it is in must be one I can open (a private chat: one of its two people)
        $this->conversation($lead, $message->conversation->keyFor(user()->id));

        return $message;
    }

    public function download($attachmentId)
    {
        $attachment = LeadChatAttachment::with('message.conversation')->findOrFail($attachmentId);
        $lead = $this->lead($attachment->message->lead_id);

        // One-to-one files: only the two people in that chat
        $conversation = $attachment->message->conversation;
        abort_403(!$conversation->isShared() && !in_array(user()->id, [$conversation->user_one, $conversation->user_two]));
        abort_unless(Storage::disk('lead_chat')->exists($attachment->path), 404);

        // ?inline=1: shown in the page's file viewer. Only types a browser displays safely: PDF, images, and text
        // files (sent as plain text, never as HTML/SVG, so nothing in a file can run on this site). Others download.
        $viewAs = self::viewableType($attachment->filename);

        if (request()->boolean('inline') && $viewAs) {
            return response(Storage::disk('lead_chat')->get($attachment->path), 200, [
                'Content-Type' => $viewAs,
                'Content-Disposition' => 'inline; filename="' . str_replace('"', '', $attachment->filename) . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            ]);
        }

        return Storage::disk('lead_chat')->download($attachment->path, $attachment->filename);
    }

    /** The type a file may be shown as in the viewer, by its extension; null = download only. */
    public static function viewableType(string $filename): ?string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'txt', 'csv', 'kml', 'geojson', 'gpx', 'prj' => 'text/plain; charset=utf-8',
            default => null,
        };
    }

    // ---------------------------------------------------------------- access

    /**
     * The lead, if I may use its Team Chat: the lead owner, an assigned team member, or an administrator.
     */
    private function lead($leadId): Lead
    {
        $lead = Lead::findOrFail($leadId);
        abort_403(!$this->memberIds($lead)->contains(user()->id) && !in_array('admin', user_roles()));

        return $lead;
    }

    /**
     * group, or the user id of the other person. One-to-one chats are only between two members of the team.
     */
    private function conversation(Lead $lead, string $with): LeadChatConversation
    {
        if ($with === 'group') {
            return LeadChatConversation::groupFor($lead);
        }

        if ($with === 'lead') {
            return LeadChatConversation::leadChatFor($lead);
        }

        $members = $this->memberIds($lead);
        $other = (int) $with;
        abort_403($other <= 0 || $other === user()->id);

        // A private chat that already exists stays open to both people (e.g. one an administrator started)
        $existing = LeadChatConversation::where('lead_id', $lead->id)->where('type', LeadChatConversation::DIRECT)
            ->where('user_one', min(user()->id, $other))->where('user_two', max(user()->id, $other))->first();

        if ($existing) {
            return $existing;
        }

        // A new one: I am on the team or an administrator, and the other person is on the team
        abort_403((!$members->contains(user()->id) && !$this->isAdmin()) || !$members->contains($other));

        return LeadChatConversation::directFor($lead, user()->id, $other);
    }

    private function isAdmin(): bool
    {
        return in_array('admin', user_roles() ?? []);
    }

    /** The chat with the lead: "Ai Adhyapak (Lead)". */
    private function leadChatName(Lead $lead): string
    {
        return trim(($lead->client_name ?: 'Lead') . ' (Lead)');
    }

    /** The group chat's name: the lead's project + "Team" (e.g. "Jayabheri constructions Team"). */
    private function groupName(Lead $lead): string
    {
        return trim(($lead->leadProject?->name ?: $lead->client_name ?: 'Lead') . ' Team');
    }

    /** Lead owner and assigned team members. */
    private function memberIds(Lead $lead)
    {
        return $lead->teamMembers()->pluck('users.id')->push($lead->lead_owner)->filter()->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function memberUsers(Lead $lead)
    {
        return User::withoutGlobalScope(\App\Scopes\ActiveScope::class)
            ->whereIn('id', $this->memberIds($lead))
            ->with('employeeDetail.designation:id,name', 'employeeDetail.department:id,team_name')
            ->orderBy('name')->get()
            ->each(function ($u) use ($lead) {
                $u->chat_role = $u->id === (int) $lead->lead_owner ? 'Lead Owner' : 'Team Member';
                $u->designation_name = $u->employeeDetail?->designation?->name;
            })
            ->sortBy(fn ($u) => $u->chat_role === 'Lead Owner' ? 0 : 1)->values();
    }

    private function present(LeadChatMessage $m): array
    {
        $readCount = $m->reads->whereNotNull('read_at')->count();
        $mine = !$m->from_lead && $m->user_id === user()->id;
        $deleted = $m->deleted_at !== null;

        return [
            'id' => $m->id,
            'mine' => $mine,
            'edited' => !$deleted && $m->edited_at !== null,
            'deleted' => $deleted,
            'can_edit' => $mine && !$deleted,
            'can_delete' => !$deleted && ($mine || $this->isAdmin()),
            'from_lead' => (bool) $m->from_lead,
            'sender' => $m->from_lead ? ($m->lead?->client_name ?? 'Lead') . ' (Lead)' : ($m->sender?->name ?? 'Former employee'),
            'sender_image' => $m->sender?->image_url,
            'body' => $m->body,
            'time' => $m->created_at->timezone(company()->timezone ?? config('app.timezone'))->format('d M Y, h:i A'),
            'attachments' => $m->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->filename,
                'category' => $a->category,
                'size' => $a->sizeLabel(),
                'image' => Str::startsWith((string) $a->mime, 'image/'),
                'url' => route('lead-chat.attachment', $a->id),
            ])->values(),
            'read' => $readCount,
            'of' => $m->reads->count(),
            'reply' => $m->reply_to_id ? $this->quote($m->replyTo) : null,
        ];
    }

    /** The quoted message above a reply: who wrote it and the start of it. */
    private function quote(?LeadChatMessage $q): array
    {
        if (!$q || $q->deleted_at) {
            return ['id' => $q?->id, 'sender' => $q ? $this->senderName($q) : '', 'text' => 'This message was deleted', 'deleted' => true];
        }

        $text = trim((string) $q->body);

        if ($text === '' && $q->attachments->isNotEmpty()) {
            $text = '📎 ' . $q->attachments->pluck('filename')->implode(', ');
        }

        return [
            'id' => $q->id,
            'sender' => $q->user_id === user()->id && !$q->from_lead ? 'You' : $this->senderName($q),
            'text' => Str::limit($text, 140),
            'deleted' => false,
        ];
    }

    private function senderName(LeadChatMessage $m): string
    {
        return $m->from_lead ? ($m->lead?->client_name ?? 'Lead') . ' (Lead)' : ($m->sender?->name ?? 'Former employee');
    }

}
