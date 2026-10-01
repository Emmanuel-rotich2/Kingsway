<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via PageShell.
 * Internal user-to-user messaging (Messages). Two-pane layout:
 *   left:  searchable conversation list with filters + unread counts
 *   right: open thread with message bubbles and a reply box, or compose view
 * All dynamic content is rendered by js/pages/communications.js and escaped
 * before injection (XSS-safe). */
?>
<div class="kw-msg" id="kwMessaging">
    <!-- Header toolbar -->
    <div class="kw-msg-header">
        <div class="kw-msg-header-title">
            <h4 class="kw-msg-title mb-0"><i class="bi bi-chat-left-text me-2"></i>Messages</h4>
            <span class="kw-msg-subtitle">Internal staff messaging</span>
        </div>
        <div class="kw-msg-header-actions">
            <div class="kw-msg-search">
                <i class="bi bi-search kw-msg-search-icon"></i>
                <input
                    type="search"
                    class="form-control kw-msg-search-input"
                    id="msgSearch"
                    placeholder="Search conversations..."
                    autocomplete="off"
                >
            </div>
            <button type="button" class="btn btn-success btn-sm kw-msg-new" id="btnNewMessage">
                <i class="bi bi-pencil-square me-1"></i>New Message
            </button>
        </div>
    </div>

    <div class="kw-msg-body">
        <!-- Left: conversation list -->
        <aside class="kw-msg-list-pane">
            <div class="kw-msg-filters" id="msgFilters" role="tablist" aria-label="Conversation filters">
                <button type="button" class="kw-msg-filter active" data-filter="all">All</button>
                <button type="button" class="kw-msg-filter" data-filter="unread">Unread</button>
                <button type="button" class="kw-msg-filter" data-filter="group">Groups</button>
            </div>
            <div class="kw-msg-list" id="conversationList" aria-live="polite">
                <div class="kw-msg-loading text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="text-muted mt-2 mb-0">Loading conversations...</p>
                </div>
            </div>
        </aside>

        <!-- Right: thread / compose pane -->
        <section class="kw-msg-pane">
            <!-- Empty state -->
            <div class="kw-msg-empty" id="threadEmpty">
                <i class="bi bi-chat-dots kw-msg-empty-icon"></i>
                <h5>Select a conversation</h5>
                <p class="text-muted mb-3">Pick a conversation on the left, or start a new one.</p>
                <button type="button" class="btn btn-outline-success btn-sm" data-start-compose>
                    <i class="bi bi-pencil-square me-1"></i>New Message
                </button>
            </div>

            <!-- Thread view -->
            <div class="kw-msg-thread d-none" id="threadView">
                <div class="kw-msg-thread-header">
                    <div class="kw-msg-thread-meta">
                        <div class="kw-msg-thread-title-wrap">
                            <span class="kw-msg-thread-title" id="threadTitle"></span>
                            <span class="badge rounded-pill kw-badge-group d-none" id="threadGroupBadge">Group</span>
                        </div>
                        <span class="kw-msg-thread-participants" id="threadParticipants"></span>
                    </div>
                </div>
                <div class="kw-msg-thread-scroll" id="threadMessages" aria-live="polite"></div>
                <div class="kw-msg-reply">
                    <textarea
                        class="form-control"
                        id="replyBox"
                        rows="2"
                        placeholder="Write a reply..."
                        maxlength="5000"
                    ></textarea>
                    <button type="button" class="btn btn-success btn-sm" id="btnSendReply">
                        <i class="bi bi-send me-1"></i>Send
                    </button>
                </div>
            </div>

            <!-- Compose view -->
            <div class="kw-msg-compose d-none" id="composeView">
                <div class="kw-msg-thread-header">
                    <div class="kw-msg-thread-meta">
                        <span class="kw-msg-thread-title">New Message</span>
                    </div>
                </div>
                <div class="kw-msg-compose-body">
                    <div class="mb-3">
                        <label class="form-label" for="newRecipientSearch">To</label>
                        <div class="kw-msg-recipient-picker">
                            <div class="kw-msg-selected" id="selectedRecipients"></div>
                            <div class="position-relative">
                                <input
                                    type="text"
                                    class="form-control form-control-sm"
                                    id="newRecipientSearch"
                                    placeholder="Search by name, email or username..."
                                    autocomplete="off"
                                >
                                <div class="kw-msg-recipient-results d-none" id="recipientResults"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newSubject">Subject</label>
                        <input type="text" class="form-control" id="newSubject" maxlength="255"
                               placeholder="Short subject (optional)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newMessage">Message</label>
                        <textarea class="form-control" id="newMessage" rows="7" maxlength="5000"
                                  placeholder="Type your message..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newPriority">Priority</label>
                        <select class="form-select" id="newPriority">
                            <option value="normal" selected>Normal</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="kw-msg-compose-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cancel-compose>Cancel</button>
                    <button type="button" class="btn btn-success btn-sm" id="btnSendMessage">
                        <i class="bi bi-send me-1"></i>Send Message
                    </button>
                </div>
            </div>
        </section>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/communications.js?v=<?php echo asset_version('js/pages/communications.js'); ?>"></script>
