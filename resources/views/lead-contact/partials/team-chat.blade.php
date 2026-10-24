{{--
    Lead Team → Team Chat: the whole-team group chat and one-to-one chats with each team member, kept per lead.
    Data comes from LeadChatController; new messages are fetched every 5 seconds while the chat is open,
    the unread counts every 30 seconds otherwise. ?view=chat&with=group|<user id> opens a chat directly.
    $leadContact, $teamView
--}}
<div id="leadTeamChatPanel" @if ($teamView !== 'chat') style="display: none;" @endif>
    <div class="ltc card bg-white border-0 b-shadow-4">
        {{-- ---------- Chats ---------- --}}
        <aside class="ltc-list">
            <div class="ltc-list-head">
                <b class="f-15">Team Chat</b>
                <div class="text-lightest f-12">{{ $leadContact->client_name }}{{ $leadContact->leadProject ? ' · ' . $leadContact->leadProject->name : '' }}</div>
            </div>
            {{-- The chat with the lead (lead + owner + team), then the internal team group with its Members / Media --}}
            <div id="ltcLeadRow"></div>
            <div id="ltcChats"><div class="p-3 text-lightest f-13">Loading…</div></div>

            {{-- Under the group: its Members (click one for a private chat) and Media, like a group chat's info --}}
            <div id="ltcSide" class="ltc-side">
                <div class="ltc-tabs ltc-side-tabs" role="tablist">
                    <button type="button" class="ltc-tab active" data-side="members" role="tab">Members</button>
                    <button type="button" class="ltc-tab" data-side="media" role="tab">Media</button>
                </div>
                <div data-side-pane="members">
                    <div class="ltc-side-count">
                        <span id="ltcSideCount"></span>
                        <button type="button" class="ltc-icon-btn" id="ltcSideSearchBtn" title="Search members"><i class="fa fa-search"></i></button>
                    </div>
                    <div class="px-3 pb-2" id="ltcSideSearchBox" style="display: none;">
                        <input type="search" class="ltc-search w-100" id="ltcSideSearch" placeholder="Search members" aria-label="Search members">
                    </div>
                    <div id="ltcSideMembers"></div>
                    <div id="ltcOtherChats"></div>
                </div>
                <div data-side-pane="media" class="px-3 py-2" style="display: none;">
                    <div id="ltcSideMedia"></div>
                </div>
            </div>
        </aside>

        {{-- ---------- Conversation ---------- --}}
        <section class="ltc-main">
            {{-- Click the header (or the i button) for the group info: Members and Media --}}
            <div class="ltc-head">
                <button type="button" class="ltc-head-info" id="ltcHeadInfo" title="Group info: members and media">
                    <span class="ltc-avatar group" id="ltcHeadAvatar"><i class="fa fa-users"></i></span>
                    <span class="d-flex flex-column text-left" style="min-width: 0;">
                        <b class="f-15 text-dark" id="ltcTitle">Team</b>
                        <span class="text-lightest f-12 ltc-ellipsis" id="ltcSubtitle"></span>
                    </span>
                </button>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <span class="ltc-private d-none" id="ltcPrivate"><i class="fa fa-lock mr-1"></i>Private</span>
                    <span class="ltc-private ltc-lead-badge d-none" id="ltcLeadBadge" title="The lead is in this chat"><i class="fa fa-eye mr-1"></i>Lead can see this chat</span>
                    <button type="button" class="ltc-icon-btn" id="ltcInfoBtn" title="Group info"><i class="fa fa-info-circle"></i></button>
                </div>
            </div>

            {{-- Chat with the lead: leads cannot sign in yet --}}
            <div class="ltc-lead-note d-none" id="ltcLeadNote">
                <i class="fa fa-info-circle mr-1"></i>
                {{ $leadContact->client_name }} is in this chat but cannot sign in yet; they will see these messages once lead logins are added.
                Use the team chat for internal discussion.
            </div>
            <div class="ltc-messages" id="ltcMessages"></div>

            {{-- ---------- Group info (replaces the messages while open) ---------- --}}
            <div class="ltc-info" id="ltcInfo" style="display: none;">
                <div class="ltc-info-top">
                    <button type="button" class="ltc-icon-btn" id="ltcInfoBack" title="Back to the chat"><i class="fa fa-arrow-left"></i></button>
                    <span class="ltc-avatar group ltc-avatar-lg" id="ltcInfoAvatar"><i class="fa fa-users"></i></span>
                    <div style="min-width: 0;">
                        <div class="f-18 font-weight-bold text-dark" id="ltcInfoName"></div>
                        <div class="text-lightest f-12" id="ltcInfoSub"></div>
                    </div>
                </div>
                <div class="ltc-tabs" role="tablist">
                    <button type="button" class="ltc-tab active" data-tab="members" role="tab">Members</button>
                    <button type="button" class="ltc-tab" data-tab="media" role="tab">Media</button>
                </div>
                <div class="ltc-info-body">
                    <div data-pane="members">
                        <div class="d-flex justify-content-between align-items-center mb-2" style="gap: 10px;">
                            <span class="text-lightest f-13" id="ltcMemberCount"></span>
                            <input type="search" class="ltc-search" id="ltcMemberSearch" placeholder="Search members" aria-label="Search members">
                        </div>
                        <div id="ltcMembers"></div>
                    </div>
                    <div data-pane="media" style="display: none;">
                        <div id="ltcMedia"></div>
                    </div>
                </div>
            </div>

            <form class="ltc-compose" id="ltcForm" enctype="multipart/form-data">
                {{-- Replying to a message: shown above the box until sent or cancelled --}}
                <div class="ltc-reply-bar" id="ltcReplyBar" style="display: none;">
                    <div style="min-width: 0;">
                        <div class="ltc-reply-who"><i class="fa fa-reply mr-1"></i>Replying to <b id="ltcReplyWho"></b></div>
                        <div class="ltc-reply-text" id="ltcReplyText"></div>
                    </div>
                    <button type="button" class="ltc-icon-btn" id="ltcReplyCancel" title="Cancel reply" aria-label="Cancel reply"><i class="fa fa-times"></i></button>
                </div>
                <div class="ltc-files" id="ltcFiles"></div>
                <div class="d-flex align-items-end" style="gap: 8px;">
                    <label class="ltc-attach" title="Attach files named after an approved category: {{ implode(', ', array_keys(\App\Models\LeadChatAttachment::CATEGORIES)) }} (up to 20 MB each)">
                        <i class="fa fa-paperclip"></i>
                        <input type="file" id="ltcFileInput" multiple hidden>
                    </label>
                    <textarea id="ltcBody" rows="1" maxlength="5000" placeholder="Type a message to your lead team…"
                              aria-label="Message"></textarea>
                    <button type="submit" class="btn btn-primary ltc-send" id="ltcSend"><i class="fa fa-paper-plane mr-1"></i>Send</button>
                </div>
                <div class="text-danger f-12 mt-1" id="ltcError"></div>
                <div class="text-lightest f-11 mt-1">
                    <i class="fa fa-paperclip mr-1"></i>File names must include an approved category:
                    @foreach (array_keys(\App\Models\LeadChatAttachment::CATEGORIES) as $category)
                        <span class="ltc-cat">{{ $category }}</span>
                    @endforeach
                </div>
                <div class="text-lightest f-11 mt-1"><span id="ltcFootNote">Enter to send · Shift+Enter for a new line. Only the lead owner and the assigned team can see this chat.</span></div>
            </form>
        </section>
    </div>
</div>

{{-- File viewer: PDFs, images and text files are shown here; other files offer Download --}}
<div class="modal fade" id="ltcViewer" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header align-items-center">
                <h5 class="modal-title text-truncate mb-0" id="ltcViewerName" style="min-width: 0;"></h5>
                <div class="d-flex align-items-center ml-3" style="gap: 8px;">
                    <a href="#" download class="btn btn-sm btn-outline-primary text-nowrap" id="ltcViewerDownload"><i class="fa fa-download mr-1"></i>Download</a>
                    <button type="button" class="close ml-1" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
            </div>
            <div class="modal-body p-0" id="ltcViewerBody"></div>
        </div>
    </div>
</div>

<style>
    /* Fills the screen; the messages scroll inside it (scrollbar on the right) and the message box stays at the bottom */
    .ltc { display: grid; grid-template-columns: 440px minmax(0, 1fr); grid-template-rows: minmax(0, 1fr);
        height: calc(100vh - 290px); min-height: 480px; overflow: hidden; }
    /* Member search in the left section: full width of the column */
    #ltcSideSearchBox .ltc-search { width: 100%; max-width: none; padding: 7px 14px; font-size: 13px; }
    .ltc-list { border-right: 1px solid #eef1f6; overflow-y: auto; min-height: 0; background: #fbfcfe; }
    .ltc-list-head { padding: 16px; border-bottom: 1px solid #eef1f6; }
    .ltc-chat { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px 14px; border: 0; border-left: 3px solid transparent;
        background: transparent; text-align: left; cursor: pointer; }
    .ltc-chat:hover { background: #f1f5fb; }
    .ltc-chat.active { background: #eef2ff; border-left-color: #4f46e5; }
    .ltc-chat .ltc-name { font-size: 13px; font-weight: 600; color: #1e293b; }
    .ltc-chat .ltc-sub { font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ltc-avatar { flex: 0 0 34px; width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700; color: #4338ca; background: #e0e7ff; overflow: hidden; }
    .ltc-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .ltc-avatar.group { background: #dcfce7; color: #166534; }
    .ltc-unread { margin-left: auto; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; background: #4f46e5; color: #fff;
        font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
    .ltc-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; height: 100%; }
    .ltc-head, .ltc-lead-note, .ltc-compose { flex: 0 0 auto; }
    .ltc-head { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-bottom: 1px solid #eef1f6; }
    .ltc-private { font-size: 11px; color: #64748b; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2px 8px; }
    .ltc-messages { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 18px; background: #fff; }
    .ltc-messages::-webkit-scrollbar, .ltc-list::-webkit-scrollbar { width: 8px; }
    .ltc-messages::-webkit-scrollbar-thumb, .ltc-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .ltc-messages::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .ltc-msg { display: flex; gap: 10px; margin-bottom: 14px; max-width: 80%; }
    .ltc-msg.mine { margin-left: auto; flex-direction: row-reverse; }
    .ltc-bubble { padding: 9px 12px; border-radius: 12px; background: #f1f5f9; font-size: 13px; color: #1e293b; min-width: 0; }
    .ltc-msg.mine .ltc-bubble { background: #eef2ff; }
    .ltc-meta { font-size: 11px; color: #64748b; margin-bottom: 3px; }
    .ltc-text { white-space: pre-wrap; overflow-wrap: anywhere; }
    .ltc-time { font-size: 10px; color: #94a3b8; margin-top: 4px; }
    .ltc-file { display: flex; align-items: center; gap: 8px; margin-top: 6px; padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 8px;
        background: #fff; font-size: 12px; color: #1e293b; text-decoration: none; }
    .ltc-file:hover { border-color: #4f46e5; text-decoration: none; }
    .ltc-file-img { display: block; margin-top: 6px; max-width: 240px; max-height: 180px; border-radius: 8px; border: 1px solid #e2e8f0; }
    .ltc-day { text-align: center; font-size: 11px; color: #94a3b8; margin: 6px 0 14px; }
    .ltc-compose { border-top: 1px solid #eef1f6; padding: 12px 16px; }
    .ltc-compose textarea { flex: 1 1 auto; resize: none; max-height: 140px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; font-size: 13px; }
    .ltc-compose textarea:focus { outline: none; border-color: #4f46e5; }
    .ltc-attach { margin: 0; width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #cbd5e1;
        border-radius: 8px; color: #64748b; cursor: pointer; }
    .ltc-attach:hover { color: #4f46e5; border-color: #4f46e5; }
    .ltc-send { height: 38px; white-space: nowrap; }
    .ltc-files { display: flex; flex-wrap: wrap; gap: 6px; }
    .ltc-files:not(:empty) { margin-bottom: 8px; }
    .ltc-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 8px; border-radius: 12px; background: #f1f5f9; font-size: 11px; }
    .ltc-chip button { border: 0; background: none; color: #94a3b8; padding: 0; line-height: 1; cursor: pointer; }
    #ltChatUnread:empty { display: none; }
    /* Chat with the lead */
    .ltc-avatar.leadchat { background: #ffedd5; color: #c2410c; }
    .ltc-lead-badge { color: #c2410c; border-color: #fed7aa; background: #fff7ed; }
    .ltc-lead-note { padding: 8px 18px; font-size: 12px; color: #9a3412; background: #fff7ed; border-bottom: 1px solid #fed7aa; }
    .ltc-tag.lead { background: #ffedd5; color: #c2410c; }
    #ltcLeadRow .ltc-chat { border-bottom: 1px solid #eef1f6; }
    /* Left section: Members / Media under the group */
    .ltc-side-tabs { padding: 0 16px; gap: 24px; background: #fbfcfe; }
    .ltc-side-tabs .ltc-tab { font-size: 13px; padding: 9px 2px; }
    .ltc-side-count { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px 4px 16px; font-size: 12px; color: #64748b; }
    .ltc-side-member { display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px 14px; border: 0; background: none; text-align: left; }
    .ltc-side-member.clickable { cursor: pointer; }
    .ltc-side-member:disabled { opacity: 1; cursor: default; }
    /* Chat button on each member: a private one-to-one chat */
    .ltc-dm-btn { flex: 0 0 32px; width: 32px; height: 32px; margin-left: 8px; border: 0; border-radius: 8px; background: #2563eb; color: #fff;
        display: inline-flex; align-items: center; justify-content: center; font-size: 14px; cursor: pointer; }
    .ltc-dm-btn:hover { background: #1d4ed8; }
    /* Replies */
    .ltc-reply-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 6px 10px; border-left: 3px solid #4f46e5;
        border-radius: 6px; background: #eef2ff; }
    .ltc-reply-who { font-size: 12px; color: #4338ca; }
    .ltc-reply-text { font-size: 12px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ltc-quote { display: flex; flex-direction: column; margin: 2px 0 6px; padding: 5px 9px; border-left: 3px solid #4f46e5; border-radius: 6px;
        background: rgba(79, 70, 229, .07); font-size: 12px; cursor: pointer; max-width: 420px; }
    .ltc-quote b { color: #4338ca; font-size: 11px; }
    .ltc-quote span { color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ltc-quote.deleted { cursor: default; border-left-color: #cbd5e1; }
    .ltc-quote.deleted span { font-style: italic; color: #94a3b8; }
    .ltc-msg.ltc-flash .ltc-bubble { box-shadow: 0 0 0 3px rgba(79, 70, 229, .35); transition: box-shadow .2s; }
    /* Message menu, editing, deleted */
    .ltc-meta { display: flex; align-items: center; gap: 6px; }
    .ltc-menu { position: relative; margin-left: auto; }
    .ltc-menu-btn { border: 0; background: none; color: #94a3b8; width: 22px; height: 22px; border-radius: 50%; cursor: pointer; opacity: 0; }
    .ltc-msg:hover .ltc-menu-btn, .ltc-menu.open .ltc-menu-btn, .ltc-menu-btn:focus { opacity: 1; }
    .ltc-menu-btn:hover { background: rgba(0, 0, 0, .06); color: #475569; }
    .ltc-menu-list { display: none; position: absolute; right: 0; top: 24px; z-index: 5; min-width: 130px; padding: 4px; background: #fff;
        border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(15, 23, 42, .12); }
    .ltc-msg:not(.mine) .ltc-menu-list { right: auto; left: 0; }
    .ltc-menu.open .ltc-menu-list { display: block; }
    .ltc-menu-list button { display: block; width: 100%; padding: 7px 10px; border: 0; border-radius: 6px; background: none; text-align: left; font-size: 12px; cursor: pointer; }
    .ltc-menu-list button:hover { background: #f1f5f9; }
    .ltc-edit-box textarea { width: 100%; min-width: 260px; border: 1px solid #4f46e5; border-radius: 8px; padding: 6px 8px; font-size: 13px; resize: vertical; }
    .ltc-deleted { color: #94a3b8; font-style: italic; }
    .ltc-msg.deleted .ltc-bubble { background: #f8fafc; }
    /* Files: row with a download button, image download overlay, viewer */
    .ltc-file-row { display: flex; align-items: stretch; gap: 6px; margin-top: 6px; }
    .ltc-file-row .ltc-file { flex: 1 1 auto; min-width: 0; margin-top: 0; }
    .ltc-dl { flex: 0 0 34px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0; border-radius: 8px;
        background: #fff; color: #2563eb; text-decoration: none; }
    .ltc-dl:hover { background: #2563eb; color: #fff; border-color: #2563eb; text-decoration: none; }
    .ltc-img-wrap { position: relative; display: inline-block; }
    .ltc-img-wrap .ltc-dl { position: absolute; right: 6px; bottom: 6px; width: 30px; height: 30px; box-shadow: 0 2px 6px rgba(0, 0, 0, .15); }
    .ltc-viewer-frame { display: block; width: 100%; height: 78vh; border: 0; background: #f8fafc; }
    .ltc-viewer-img { display: block; max-width: 100%; max-height: 78vh; margin: 0 auto; }
    /* Approved upload categories */
    .ltc-cat { display: inline-block; margin: 2px 2px 0 0; padding: 1px 7px; border-radius: 10px; background: #eef2ff; color: #4338ca;
        font-size: 10px; font-weight: 600; white-space: nowrap; }
    .ltc-side-member .ltc-avatar ~ span + .ltc-dm-btn { margin-left: auto; }
    .ltc-side-member .ltc-desig { color: #4338ca; font-weight: 500; }
    .ltc-side-member.clickable:hover { background: #f1f5fb; }
    .ltc-side-member.active { background: #eef2ff; box-shadow: inset 3px 0 0 #4f46e5; }
    .ltc-side-member .ltc-avatar { flex-basis: 38px; width: 38px; height: 38px; }
    .ltc-side-member .ltc-name { font-size: 13px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ltc-side-member .ltc-sub { font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ltc-side-member .ltc-tag { margin-left: auto; padding: 2px 8px; font-size: 10px; }
    .ltc-side-member .ltc-unread { margin-left: 6px; }
    .ltc-side-media-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-bottom: 10px; }
    .ltc-side-media-grid img { width: 100%; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; }
    /* Header and group info */
    .ltc-head-info { display: flex; align-items: center; gap: 10px; min-width: 0; border: 0; background: none; padding: 0; cursor: pointer; }
    .ltc-head-info:hover #ltcTitle { color: #4f46e5 !important; }
    .ltc-ellipsis { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 520px; }
    .ltc-icon-btn { width: 32px; height: 32px; border: 0; border-radius: 50%; background: none; color: #64748b; cursor: pointer; }
    .ltc-icon-btn:hover { background: #f1f5f9; color: #4f46e5; }
    .ltc-info { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; background: #fff; }
    .ltc-info-top { display: flex; align-items: center; gap: 12px; padding: 16px 18px; }
    .ltc-avatar-lg { flex-basis: 52px; width: 52px; height: 52px; font-size: 18px; }
    .ltc-tabs { display: flex; gap: 28px; padding: 0 18px; border-bottom: 1px solid #eef1f6; }
    .ltc-tab { border: 0; background: none; padding: 10px 2px; font-size: 14px; font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; cursor: pointer; }
    .ltc-tab.active { color: #1e293b; border-bottom-color: #4f46e5; }
    .ltc-info-body { flex: 1 1 auto; overflow-y: auto; padding: 14px 18px; }
    .ltc-search { width: 220px; max-width: 60%; border: 1px solid #cbd5e1; border-radius: 16px; padding: 5px 12px; font-size: 12px; }
    .ltc-search:focus { outline: none; border-color: #4f46e5; }
    .ltc-member { display: flex; align-items: center; gap: 12px; padding: 10px 6px; border-radius: 8px; }
    .ltc-member.clickable { cursor: pointer; }
    .ltc-member.clickable:hover { background: #f8fafc; }
    .ltc-member .ltc-avatar { flex-basis: 42px; width: 42px; height: 42px; }
    .ltc-member-name { font-size: 14px; font-weight: 600; color: #1e293b; }
    .ltc-member-sub { font-size: 12px; color: #64748b; overflow-wrap: anywhere; }
    .ltc-tag { margin-left: auto; flex-shrink: 0; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    .ltc-tag.owner { background: #dcfce7; color: #166534; }
    .ltc-tag.member { background: #f1f5f9; color: #475569; }
    .ltc-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; margin-bottom: 14px; }
    .ltc-media-grid img { width: 100%; height: 110px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; }
    @media (max-width: 900px) {
        .ltc { grid-template-columns: 1fr; height: auto; }
        .ltc-list { border-right: 0; border-bottom: 1px solid #eef1f6; max-height: 220px; }
        .ltc-messages { height: 420px; }
        .ltc-msg { max-width: 95%; }
    }
</style>

<script>
    $(function () {
        const LIST_URL = "{{ route('lead-chat.index', $leadContact->id) }}";
        const MESSAGES_URL = "{{ route('lead-chat.messages', $leadContact->id) }}";
        const SEND_URL = "{{ route('lead-chat.send', $leadContact->id) }}";
        const esc = v => $('<div>').text(v ?? '').html();
        const initials = n => (n || '?').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
        // Photo, or the initials when there is none or it fails to load
        const avatarHtml = (image, name) => image
            ? `<img src="${esc(image)}" alt="" onerror="this.parentNode.textContent='${esc(initials(name)).replace(/'/g, '')}'">`
            : esc(initials(name));

        let current = new URLSearchParams(location.search).get('with') || 'group';
        let chats = [];
        let lastId = 0;
        let lastDay = null;
        let files = [];
        let loading = false;
        let generation = 0;
        let since = null; // server time of the last load: edits / deletes after it are fetched // bumped when another chat is opened: late replies for the old chat are ignored

        const chatOpen = () => $('#leadTeamChatPanel').is(':visible') && !document.hidden;

        // ---------- Chats on the left, unread counts ----------
        function loadChats() {
            return $.get(LIST_URL).done(r => {
                chats = r.chats;
                $('#ltChatUnread').text(r.unread_total || '');
                $('#ltcLeadRow').html(chats.filter(c => c.key === 'lead').map(chatRow).join(''));
                $('#ltcChats').html(chats.filter(c => c.key === 'group').map(c => `
                    <button type="button" class="ltc-chat ${c.key === current ? 'active' : ''}" data-key="${esc(c.key)}">
                        <span class="ltc-avatar ${c.key === 'group' ? 'group' : ''}">${c.key === 'group' ? '<i class="fa fa-users"></i>'
                            : avatarHtml(c.image, c.name)}</span>
                        <span class="d-flex flex-column" style="min-width: 0;">
                            <span class="ltc-name">${esc(c.name)}</span>
                            <span class="ltc-sub">${esc(c.subtitle)}</span>
                        </span>
                        ${c.unread ? `<span class="ltc-unread">${c.unread}</span>` : ''}
                    </button>`).join(''));
                // A chat that is not allowed any more (e.g. the member left the team): back to the group
                if (!chats.some(c => c.key === current)) {
                    current = 'group';
                    if (chatOpen()) openChat('group');
                }
                setHeader();
                loadSide();
            });
        }

        // A row in the left list (the chat with the lead)
        function chatRow(c) {
            return `<button type="button" class="ltc-chat ${c.key === current ? 'active' : ''}" data-key="${esc(c.key)}">
                <span class="ltc-avatar leadchat"><i class="fa fa-user-tie"></i></span>
                <span class="d-flex flex-column" style="min-width: 0;">
                    <span class="ltc-name">${esc(c.name)}</span>
                    <span class="ltc-sub">${esc(c.subtitle)}</span>
                </span>
                ${c.unread ? `<span class="ltc-unread">${c.unread}</span>` : ''}
            </button>`;
        }

        function setHeader() {
            let chat = chats.find(c => c.key === current) || {};
            $('#ltcTitle').text(chat.name || 'Team');
            $('#ltcSubtitle').text(chat.subtitle || '');
            let shared = current === 'group' || current === 'lead';
            $('#ltcPrivate').toggleClass('d-none', shared);
            $('#ltcLeadBadge, #ltcLeadNote').toggleClass('d-none', current !== 'lead');
            $('#ltcFootNote').text(current === 'lead'
                ? 'Enter to send · Shift+Enter for a new line. The lead, the lead owner and the assigned team are in this chat.'
                : 'Enter to send · Shift+Enter for a new line. Only the lead owner and the assigned team can see this chat.');
            setAvatar($('#ltcHeadAvatar'), chat);
            $('#ltcInfoBtn').attr('title', shared ? 'Group info' : 'Contact info');
            $('#ltcBody').attr('placeholder', current === 'group' ? 'Type a message to your lead team…'
                : (current === 'lead' ? 'Message the lead and the team…' : `Message ${chat.name || ''} privately…`));
        }

        // ---------- Messages ----------
        function dayOf(time) { return time.split(',')[0]; }

        function renderMessage(m) {
            let day = dayOf(m.time);
            let dayLine = day !== lastDay ? `<div class="ltc-day">${esc(day)}</div>` : '';
            lastDay = day;
            return dayLine + messageHtml(m);
        }

        // One message. Deleted: a placeholder. Mine (or an admin's view): the ⋮ menu with Edit / Delete.
        function messageHtml(m) {
            let receipt = m.mine && m.of && !m.deleted ? `<span class="ltc-receipt" data-id="${m.id}"> · ${receiptText(m.read, m.of)}</span>` : '';
            let menu = !m.deleted ? `
                <span class="ltc-menu">
                    <button type="button" class="ltc-menu-btn" title="Message options" aria-label="Message options"><i class="fa fa-ellipsis-v"></i></button>
                    <span class="ltc-menu-list">
                        <button type="button" class="ltc-reply"><i class="fa fa-reply mr-2"></i>Reply</button>
                        ${m.can_edit ? '<button type="button" class="ltc-edit"><i class="fa fa-pen mr-2"></i>Edit</button>' : ''}
                        ${m.can_delete ? '<button type="button" class="ltc-delete text-danger"><i class="fa fa-trash-alt mr-2"></i>Delete</button>' : ''}
                    </span>
                </span>` : '';
            let attachments = m.attachments.map(a => a.image
                ? `<span class="ltc-img-wrap">${openAttr(a, `<img class="ltc-file-img" src="${esc(a.url)}?inline=1" alt="${esc(a.name)}">`)}${dlButton(a)}</span>`
                : fileRow(a, `<span>${esc(a.name)}</span>${catTag(a.category)}<span class="text-lightest ml-auto">${esc(a.size)}</span>`)).join('');

            return `
                <div class="ltc-msg ${m.mine ? 'mine' : ''} ${m.deleted ? 'deleted' : ''}" data-id="${m.id}">
                    <span class="ltc-avatar">${avatarHtml(m.sender_image, m.sender)}</span>
                    <div class="ltc-bubble">
                        <div class="ltc-meta"><b>${esc(m.mine ? 'You' : m.sender)}</b>${menu}</div>
                        ${m.reply && !m.deleted ? quoteHtml(m.reply) : ''}
                        ${m.deleted
                            ? `<div class="ltc-text ltc-deleted"><i class="fa fa-ban mr-1"></i>This message was deleted</div>`
                            : (m.body ? `<div class="ltc-text">${esc(m.body)}</div>` : '') + attachments}
                        <div class="ltc-time">${esc(m.time.split(', ')[1] || m.time)}${m.edited ? ' · edited' : ''}${receipt}</div>
                    </div>
                </div>`;
        }

        // The quoted message above a reply; click it to jump to the original
        function quoteHtml(q) {
            return `<div class="ltc-quote ${q.deleted ? 'deleted' : ''}" data-jump="${q.id || ''}" title="Go to the original message">
                <b>${esc(q.sender)}</b><span>${esc(q.text)}</span></div>`;
        }

        // Redraw messages that were edited or deleted (here or by someone else)
        function applyChanged(list) {
            (list || []).forEach(m => {
                // Replies quoting it show the new text, or "This message was deleted"
                $('#ltcMessages .ltc-quote[data-jump="' + m.id + '"]').replaceWith(quoteHtml({
                    id: m.id, sender: m.mine ? 'You' : m.sender, deleted: m.deleted,
                    text: m.deleted ? 'This message was deleted' : (m.body || (m.attachments.length ? '📎 ' + m.attachments.map(a => a.name).join(', ') : '')),
                }));
                let el = $('#ltcMessages .ltc-msg[data-id="' + m.id + '"]');
                if (el.length && !el.find('.ltc-edit-box').length) el.replaceWith(messageHtml(m));
            });
        }

        // ---------- Files: click to view, ⬇ to download ----------
        // Shown in the viewer (same list as LeadChatController::viewableType); anything else offers Download
        const VIEWABLE = /\.(pdf|png|jpe?g|gif|webp|txt|csv|kml|geojson|gpx|prj)$/i;

        // A clickable file: opens the viewer
        function openAttr(f, inner, title) {
            return `<a href="${esc(f.url)}" class="ltc-open" data-url="${esc(f.url)}" data-name="${esc(f.name)}" data-image="${f.image ? 1 : 0}"
                        title="${esc(title || ('View ' + f.name))}">${inner}</a>`;
        }

        function dlButton(f) {
            return `<a href="${esc(f.url)}" class="ltc-dl" download title="Download ${esc(f.name)}" aria-label="Download ${esc(f.name)}"><i class="fa fa-download"></i></a>`;
        }

        // A document row: the file (opens the viewer) and its download button
        function fileRow(f, inner, title) {
            return `<div class="ltc-file-row">
                <a href="${esc(f.url)}" class="ltc-file ltc-open" data-url="${esc(f.url)}" data-name="${esc(f.name)}" data-image="${f.image ? 1 : 0}"
                   title="${esc(title || ('View ' + f.name))}"><i class="fa fa-file-alt text-lightest"></i>${inner}</a>${dlButton(f)}</div>`;
        }

        $(document).off('click.ltcOpen').on('click.ltcOpen', '#leadTeamChatPanel .ltc-open', function (e) {
            e.preventDefault();
            let url = $(this).data('url'), name = $(this).data('name'), image = $(this).data('image') == 1;
            $('#ltcViewerName').text(name);
            $('#ltcViewerDownload').attr('href', url);
            let body = $('#ltcViewerBody').empty();
            if (image) {
                body.html(`<img src="${esc(url)}?inline=1" alt="${esc(name)}" class="ltc-viewer-img">`);
            } else if (VIEWABLE.test(name)) {
                body.html(`<iframe src="${esc(url)}?inline=1" class="ltc-viewer-frame" title="${esc(name)}"></iframe>`);
            } else {
                let ext = (name.split('.').pop() || 'this').toUpperCase();
                body.html(`<div class="text-center p-5">
                    <i class="fa fa-file-alt fa-3x text-lightest mb-3 d-block"></i>
                    <div class="f-15 text-dark mb-1">${esc(name)}</div>
                    <div class="text-lightest f-13 mb-3">${esc(ext)} files cannot be shown in the browser. Download it to open it.</div>
                    <a href="${esc(url)}" download class="btn btn-primary"><i class="fa fa-download mr-1"></i>Download</a></div>`);
            }
            $('#ltcViewer').modal('show');
        });
        // Stop the PDF / file when the viewer closes
        $('#ltcViewer').on('hidden.bs.modal', () => $('#ltcViewerBody').empty());

        // The approved category a file was sent under
        function catTag(category) {
            return category ? `<span class="ltc-cat">${esc(category)}</span>` : '';
        }

        function receiptText(read, of) {
            if (of === 1) return read ? 'Seen' : 'Sent';
            return read === of ? 'Seen by all' : `Seen by ${read} of ${of}`;
        }

        function appendMessages(list) {
            // A message can arrive twice (opening load, poll and send overlap): show each once, in order
            list = list.filter(m => m.id > lastId);
            if (!list.length) return;
            let box = $('#ltcMessages')[0];
            let atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
            $('#ltcMessages').find('.ltc-empty').remove();
            $('#ltcMessages').append(list.map(renderMessage).join(''));
            lastId = list[list.length - 1].id;
            if (atBottom || list.some(m => m.mine)) box.scrollTop = box.scrollHeight;
        }

        function updateReceipts(receipts) {
            $.each(receipts || {}, (id, r) => $('.ltc-receipt[data-id="' + id + '"]').text(' · ' + receiptText(r.read, r.of)));
        }

        function openChat(key) {
            current = String(key);
            let gen = ++generation;
            if (typeof setReply === 'function') setReply(null);
            lastId = 0;
            lastDay = null;
            history.replaceState(null, '', location.pathname + '?tab=team&view=chat&with=' + encodeURIComponent(current));
            $('.ltc-chat').removeClass('active').filter('[data-key="' + current + '"]').addClass('active');
            $('.ltc-side-member').removeClass('active').filter('[data-user="' + current + '"]').addClass('active');
            renderSideMembers();
            setHeader();
            $('#ltcError').text('');
            $('#ltcInfo').hide();
            $('#ltcMessages, #ltcForm').show();
            $('#ltcMessages').html('<div class="text-lightest f-13 ltc-empty">Loading…</div>');
            $.get(MESSAGES_URL, {with: current}).done(r => {
                if (gen !== generation) return;
                since = r.now || null;
                // The list is rebuilt from this load; a message sent meanwhile comes back with the next poll
                lastId = 0;
                lastDay = null;
                $('#ltcMessages').html(r.messages.length ? '' : `<div class="text-center text-lightest f-13 mt-5 ltc-empty">
                    <i class="fa fa-comments fa-2x mb-2 d-block"></i>No messages yet. ${current === 'group' ? 'Start the conversation with your lead team.' : (current === 'lead' ? 'Start the conversation with the lead.' : 'Say hello.')}</div>`);
                appendMessages(r.messages);
                $('#ltcMessages')[0].scrollTop = $('#ltcMessages')[0].scrollHeight;
                loadChats();
            }).fail(xhr => $('#ltcMessages').html(`<div class="text-danger f-13">${esc(xhr.responseJSON?.message || 'You cannot open this chat.')}</div>`));
        }

        function poll() {
            // Not before the chat's first load has finished (lastId is set from it)
            if (!chatOpen() || loading || $('#ltcMessages .ltc-empty').text() === 'Loading…') return;
            loading = true;
            let gen = generation;
            $.get(MESSAGES_URL, {with: current, after: lastId, since: since})
                .done(r => {
                    if (gen !== generation) return;
                    since = r.now || since;
                    appendMessages(r.messages);
                    applyChanged(r.changed);
                    updateReceipts(r.receipts);
                    if (r.messages.length) loadChats();
                })
                .always(() => loading = false);
        }

        // ---------- Reply to a message ----------
        let replyTo = null;

        function setReply(msg) {
            replyTo = msg;
            $('#ltcReplyBar').toggle(!!msg);
            if (!msg) return;
            $('#ltcReplyWho').text(msg.sender);
            $('#ltcReplyText').text(msg.text);
            $('#ltcBody').trigger('focus');
        }

        $('#ltcMessages').on('click', '.ltc-reply', function () {
            let el = $(this).closest('.ltc-msg');
            $('.ltc-menu.open').removeClass('open');
            let text = el.find('.ltc-text').first().text().trim()
                || el.find('.ltc-file, .ltc-file-img').map((i, f) => $(f).text().trim() || $(f).attr('alt') || 'File').get().join(', ');
            setReply({id: el.data('id'), sender: el.find('.ltc-meta b').first().text().trim(), text: text.length > 140 ? text.slice(0, 140) + '…' : text});
        });
        $('#ltcReplyCancel').on('click', () => setReply(null));

        // Click a quote: scroll to the original and flash it
        $('#ltcMessages').on('click', '.ltc-quote:not(.deleted)', function () {
            let original = $('#ltcMessages .ltc-msg[data-id="' + $(this).data('jump') + '"]');
            if (!original.length) return;
            let box = $('#ltcMessages');
            box.animate({scrollTop: box.scrollTop() + original.position().top - 60}, 250);
            original.addClass('ltc-flash');
            setTimeout(() => original.removeClass('ltc-flash'), 1600);
        });

        // ---------- Edit / delete a message ----------
        const UPDATE_URL = "{{ route('lead-chat.update', [$leadContact->id, ':msg']) }}";

        $('#ltcMessages').on('click', '.ltc-menu-btn', function (e) {
            e.stopPropagation();
            let menu = $(this).closest('.ltc-menu');
            $('.ltc-menu.open').not(menu).removeClass('open');
            menu.toggleClass('open');
        });
        $(document).on('click', () => $('.ltc-menu.open').removeClass('open'));

        $('#ltcMessages').on('click', '.ltc-edit', function () {
            let msg = $(this).closest('.ltc-msg');
            let text = msg.find('.ltc-text').first();
            $('.ltc-menu.open').removeClass('open');
            if (msg.find('.ltc-edit-box').length) return;
            let box = $(`<div class="ltc-edit-box">
                    <textarea maxlength="5000" rows="2" aria-label="Edit message"></textarea>
                    <div class="d-flex justify-content-end mt-1" style="gap: 6px;">
                        <button type="button" class="btn btn-sm btn-outline-secondary ltc-edit-cancel">Cancel</button>
                        <button type="button" class="btn btn-sm btn-primary ltc-edit-save">Save</button>
                    </div>
                    <div class="text-danger f-11 ltc-edit-error"></div>
                </div>`);
            box.find('textarea').val(text.length ? text.text() : '');
            text.hide().after(box);
            box.find('textarea').trigger('focus');
        });

        $('#ltcMessages').on('click', '.ltc-edit-cancel', function () {
            let msg = $(this).closest('.ltc-msg');
            msg.find('.ltc-edit-box').remove();
            msg.find('.ltc-text').show();
        });

        $('#ltcMessages').on('keydown', '.ltc-edit-box textarea', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $(this).closest('.ltc-edit-box').find('.ltc-edit-save').trigger('click'); }
            if (e.key === 'Escape') $(this).closest('.ltc-edit-box').find('.ltc-edit-cancel').trigger('click');
        });

        $('#ltcMessages').on('click', '.ltc-edit-save', function () {
            let msg = $(this).closest('.ltc-msg');
            let btn = $(this).prop('disabled', true);
            $.ajax({url: UPDATE_URL.replace(':msg', msg.data('id')), type: 'POST', headers: {Accept: 'application/json'},
                    data: {_method: 'PUT', _token: '{{ csrf_token() }}', body: msg.find('.ltc-edit-box textarea').val()}})
                .done(r => msg.replaceWith(messageHtml(r.message)))
                .fail(xhr => {
                    btn.prop('disabled', false);
                    msg.find('.ltc-edit-error').text(xhr.responseJSON?.message || 'Could not save the change.');
                });
        });

        $('#ltcMessages').on('click', '.ltc-delete', function () {
            let msg = $(this).closest('.ltc-msg');
            $('.ltc-menu.open').removeClass('open');
            if (!confirm('Delete this message for everyone? Its attached files are deleted too.')) return;
            $.ajax({url: UPDATE_URL.replace(':msg', msg.data('id')), type: 'POST', headers: {Accept: 'application/json'},
                    data: {_method: 'DELETE', _token: '{{ csrf_token() }}'}})
                .done(r => { msg.replaceWith(messageHtml(r.message)); loadChats(); })
                .fail(xhr => alert(xhr.responseJSON?.message || 'Could not delete the message.'));
        });

        // ---------- Sending ----------
        function renderFiles() {
            $('#ltcFiles').html(files.map((f, i) => `<span class="ltc-chip"><i class="fa fa-paperclip"></i>${esc(f.name)}
                <button type="button" data-i="${i}" aria-label="Remove ${esc(f.name)}">&times;</button></span>`).join(''));
        }

        // Approved upload categories (same patterns as the server, LeadChatAttachment::CATEGORIES)
        const CATEGORIES = @json(\App\Models\LeadChatAttachment::CATEGORIES);

        function categoryFor(filename) {
            let name = filename.replace(/\.[^.]*$/, '').replace(/([a-z])([A-Z])/g, '$1 $2').replace(/[^a-z0-9]+/gi, ' ').trim().toLowerCase();
            return Object.keys(CATEGORIES).find(c => new RegExp(CATEGORIES[c]).test(name)) || null;
        }

        $('#ltcFileInput').on('change', function () {
            let picked = [...this.files];
            let rejected = picked.filter(f => !categoryFor(f.name));
            files = files.concat(picked.filter(f => categoryFor(f.name))).slice(0, 10);
            this.value = '';
            renderFiles();
            $('#ltcError').text(rejected.length
                ? `Not attached: ${rejected.map(f => '"' + f.name + '"').join(', ')}. The file name must include one of: `
                    + Object.keys(CATEGORIES).join(', ') + ' (e.g. "Deliverables - GIS map.pdf").'
                : '');
        });
        $('#ltcFiles').on('click', 'button', function () {
            files.splice(+$(this).data('i'), 1);
            renderFiles();
        });

        $('#ltcBody').on('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 140) + 'px';
        }).on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#ltcForm').trigger('submit'); }
        });

        $('#ltcForm').on('submit', function (e) {
            e.preventDefault();
            let body = $('#ltcBody').val().trim();
            if (!body && !files.length) return;

            let data = new FormData();
            data.append('_token', '{{ csrf_token() }}');
            data.append('with', current);
            data.append('body', body);
            if (replyTo) data.append('reply_to', replyTo.id);
            files.forEach(f => data.append('files[]', f));

            let btn = $('#ltcSend').prop('disabled', true);
            let gen = generation;
            $('#ltcError').text('');
            $.ajax({url: SEND_URL, type: 'POST', data: data, processData: false, contentType: false, headers: {Accept: 'application/json'}})
                .done(r => {
                    $('#ltcBody').val('').css('height', '');
                    files = [];
                    renderFiles();
                    setReply(null);
                    // Still in the chat it was sent to (the poll may already have shown it)
                    if (gen === generation) appendMessages([r.message]);
                })
                .fail(xhr => {
                    let errors = xhr.responseJSON?.errors;
                    $('#ltcError').text(errors ? Object.values(errors)[0][0] : (xhr.responseJSON?.message || 'Could not send the message.'));
                })
                .always(() => btn.prop('disabled', false));
        });

        // The group row: opens the group chat; clicking it while open shows / hides its Members and Media
        $('#ltcLeadRow').on('click', '.ltc-chat', function () { openChat('lead'); });
        $('#ltcChats').on('click', '.ltc-chat', function () {
            if (current === 'group' && !$('#ltcInfo').is(':visible')) return $('#ltcSide').slideToggle(150);
            $('#ltcSide').slideDown(150);
            openChat('group');
        });

        // Opened from the Lead Team switch
        $(document).on('lead-team-view', (e, view) => { if (view === 'chat') openChat(current); });

        // ---------- Group info: Members / Media ----------
        const INFO_URL = "{{ route('lead-chat.info', $leadContact->id) }}";
        let infoMembers = [];

        function showInfo() {
            $('#ltcMessages, #ltcForm').hide();
            $('#ltcInfo').show();
            setInfoTab('members');
            $('#ltcMemberSearch').val('');
            $('#ltcMembers').html('<div class="text-lightest f-13">Loading…</div>');
            $('#ltcMedia').html('');
            let chat = chats.find(c => c.key === current) || {};
            $('#ltcInfoName').text(chat.name || '');
            $('#ltcInfoSub').text('');
            setAvatar($('#ltcInfoAvatar'), chat);
            setAvatar($('#ltcInfoAvatar'), chat);
            $.get(INFO_URL, {with: current}).done(r => {
                infoMembers = r.members;
                $('#ltcInfoName').text(r.name);
                $('#ltcInfoSub').text(current === 'group' ? `Internal team · ${r.members.length} members · Lead: ${r.lead}`
                    : (current === 'lead' ? `Chat with the lead · ${r.members.length} members` : 'Private chat'));
                renderMembers();
                renderMedia(r.media);
            }).fail(xhr => $('#ltcMembers').html(`<div class="text-danger f-13">${esc(xhr.responseJSON?.message || 'Could not load the group info.')}</div>`));
        }

        function hideInfo() {
            $('#ltcInfo').hide();
            $('#ltcMessages, #ltcForm').show();
            let box = $('#ltcMessages')[0];
            box.scrollTop = box.scrollHeight;
        }

        function setInfoTab(tab) {
            $('.ltc-tab').removeClass('active').filter('[data-tab="' + tab + '"]').addClass('active');
            $('#ltcInfo [data-pane]').hide().filter('[data-pane="' + tab + '"]').show();
        }

        function setAvatar(el, chat) {
            el.toggleClass('group', chat.key === 'group').toggleClass('leadchat', chat.key === 'lead')
                .html(chat.key === 'group' ? '<i class="fa fa-users"></i>'
                    : (chat.key === 'lead' ? '<i class="fa fa-user-tie"></i>' : avatarHtml(chat.image, chat.name)));
        }

        function renderMembers() {
            let q = $('#ltcMemberSearch').val().trim().toLowerCase();
            let list = infoMembers.filter(m => !q || [m.name, m.designation, m.email, m.phone].join(' ').toLowerCase().includes(q));
            $('#ltcMemberCount').text(`${infoMembers.length} member${infoMembers.length === 1 ? '' : 's'}`);
            $('#ltcMembers').html(list.length ? list.map(m => {
                let canChat = !m.me && m.id !== 'lead' && chats.some(c => c.key === String(m.id));
                let contact = [m.phone, m.email].filter(Boolean).join(' · ');
                return `<div class="ltc-member ${canChat ? 'clickable' : ''}" data-user="${m.id}" ${canChat ? `title="Message ${esc(m.name)} privately"` : ''}>
                    <span class="ltc-avatar">${avatarHtml(m.image, m.name)}</span>
                    <span class="d-flex flex-column" style="min-width: 0;">
                        <span class="ltc-member-name">${m.me ? 'You' : esc(m.name)}${m.active ? '' : ' <span class="badge badge-secondary">Inactive</span>'}</span>
                        <span class="ltc-member-sub">${esc([m.designation, m.department].filter(Boolean).join(' · ') || '—')}</span>
                        ${contact ? `<span class="ltc-member-sub">${esc(contact)}</span>` : ''}
                    </span>
                    <span class="ltc-tag ${m.role === 'Lead Owner' ? 'owner' : (m.role === 'Lead' ? 'lead' : 'member')}">${esc(m.role)}</span>
                </div>`;
            }).join('') : '<div class="text-lightest f-13">No members match.</div>');
        }

        function renderMedia(media) {
            let images = media.filter(f => f.image), docs = media.filter(f => !f.image);
            if (!media.length) {
                return $('#ltcMedia').html('<div class="text-center text-lightest f-13 mt-4"><i class="fa fa-photo-video fa-2x d-block mb-2"></i>No files shared in this chat yet.</div>');
            }
            $('#ltcMedia').html(
                (images.length ? `<div class="f-12 text-lightest mb-2">Images (${images.length})</div><div class="ltc-media-grid">`
                    + images.map(f => openAttr(f, `<img src="${esc(f.url)}?inline=1" alt="${esc(f.name)}">`, f.name + ' · ' + f.sender + ' · ' + f.time)).join('')
                    + '</div>' : '')
                + (docs.length ? `<div class="f-12 text-lightest mb-2">Documents and files (${docs.length})</div>`
                    + docs.map(f => fileRow(f, `<span class="d-flex flex-column" style="min-width: 0;"><span>${esc(f.name)} ${catTag(f.category)}</span><span class="text-lightest f-11">${esc(f.sender + ' · ' + f.time)}</span></span>
                        <span class="text-lightest ml-auto">${esc(f.size)}</span>`)).join('') : ''));
        }

        $('#ltcHeadInfo, #ltcInfoBtn').on('click', showInfo);
        $('#ltcInfoBack').on('click', hideInfo);
        $('.ltc-tab').on('click', function () { setInfoTab($(this).data('tab')); });
        $('#ltcMemberSearch').on('input', renderMembers);
        // A member: message them privately
        $('#ltcMembers').on('click', '.ltc-member.clickable', function () { hideInfo(); openChat($(this).data('user')); });

        // ---------- Left section: Members / Media under the group ----------
        let sideMembers = [];

        function loadSide() {
            return $.get(INFO_URL, {with: 'group'}).done(r => {
                sideMembers = r.members;
                renderSideMembers();
                renderSideMedia(r.media);
            });
        }

        function renderSideMembers() {
            let q = ($('#ltcSideSearch').val() || '').trim().toLowerCase();
            let list = sideMembers.filter(m => !q || [m.name, m.designation, m.phone, m.email].join(' ').toLowerCase().includes(q));
            $('#ltcSideCount').text(`${sideMembers.length} member${sideMembers.length === 1 ? '' : 's'}`);
            $('#ltcSideMembers').html(list.length ? list.map(m => {
                let chat = chats.find(c => c.key === String(m.id));
                let canChat = !m.me && !!chat;
                let contact = m.phone || m.email || '';
                // A div (not a button): it holds the chat button
                return `<div class="ltc-side-member ${canChat ? 'clickable' : ''} ${String(m.id) === current ? 'active' : ''}" data-user="${m.id}">
                    <span class="ltc-avatar">${avatarHtml(m.image, m.name)}</span>
                    <span class="d-flex flex-column" style="min-width: 0;">
                        <span class="ltc-name">${m.me ? 'You' : esc(m.name)}</span>
                        <span class="ltc-sub ltc-desig">${esc(m.designation || 'No designation')}</span>
                        ${contact ? `<span class="ltc-sub">${esc(contact)}</span>` : ''}
                    </span>
                    ${m.role === 'Lead Owner' ? '<span class="ltc-tag owner">Lead Owner</span>' : ''}
                    ${chat && chat.unread ? `<span class="ltc-unread">${chat.unread}</span>` : ''}
                    ${canChat ? `<button type="button" class="ltc-dm-btn" data-user="${m.id}" title="Chat with ${esc(m.name)}" aria-label="Chat with ${esc(m.name)}">
                        <i class="fa fa-comments"></i></button>` : ''}
                </div>`;
            }).join('') : '<div class="px-3 py-2 text-lightest f-12">No members match.</div>');

            // Private chats started with me by someone outside the team (an administrator)
            let others = chats.filter(c => c.outside_team);
            $('#ltcOtherChats').html(others.length ? '<div class="ltc-side-count"><span>Other chats</span></div>' + others.map(c => `
                <div class="ltc-side-member clickable ${c.key === current ? 'active' : ''}" data-user="${esc(c.key)}">
                    <span class="ltc-avatar">${avatarHtml(c.image, c.name)}</span>
                    <span class="d-flex flex-column" style="min-width: 0;">
                        <span class="ltc-name">${esc(c.name)}</span>
                        <span class="ltc-sub">${esc(c.subtitle)}</span>
                    </span>
                    ${c.unread ? `<span class="ltc-unread">${c.unread}</span>` : ''}
                    <button type="button" class="ltc-dm-btn" data-user="${esc(c.key)}" title="Chat with ${esc(c.name)}"><i class="fa fa-comments"></i></button>
                </div>`).join('') : '');
        }

        function renderSideMedia(media) {
            let images = media.filter(f => f.image), docs = media.filter(f => !f.image);
            $('#ltcSideMedia').html(!media.length ? '<div class="text-lightest f-12 py-2">No files shared in the group yet.</div>'
                : (images.length ? `<div class="ltc-side-media-grid">` + images.map(f =>
                    openAttr(f, `<img src="${esc(f.url)}?inline=1" alt="${esc(f.name)}">`, f.name + ' · ' + f.sender)).join('') + '</div>' : '')
                + docs.map(f => fileRow(f, `<span class="text-truncate">${esc(f.name)}</span>${catTag(f.category)}<span class="text-lightest ml-auto">${esc(f.size)}</span>`,
                    f.sender + ' · ' + f.time)).join(''));
        }

        $('[data-side]').on('click', function () {
            let tab = $(this).data('side');
            $('[data-side]').removeClass('active').filter('[data-side="' + tab + '"]').addClass('active');
            $('[data-side-pane]').hide().filter('[data-side-pane="' + tab + '"]').show();
        });
        $('#ltcSideSearchBtn').on('click', function () {
            $('#ltcSideSearchBox').toggle();
            $('#ltcSideSearch').val('').trigger('focus');
            renderSideMembers();
        });
        $('#ltcSideSearch').on('input', renderSideMembers);
        // A person: their private chat
        // The member row or its chat button: a private one-to-one chat
        $('#ltcSideMembers, #ltcOtherChats').on('click', '.ltc-side-member.clickable, .ltc-dm-btn', function (e) {
            e.stopPropagation();
            openChat($(this).data('user'));
        });

        loadChats();
        if (chatOpen()) openChat(current);
        setInterval(poll, 5000);
        setInterval(() => { if (!chatOpen()) loadChats(); }, 30000);
    });
</script>
