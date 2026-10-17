<?php
/**
 * Manage Email (Mailbox Workspace)
 *
 * Gmail-style three-pane mail client for the school's role-scoped mailboxes.
 * The left rail lists every mail account the signed-in staff member can reach,
 * each labelled with its address and department so it is obvious which account
 * belongs to which team. School Administrators additionally manage per-member
 * read/reply/compose access. System Administrators configure mail accounts
 * elsewhere and never see mailbox contents.
 */
?>
<style>
  .kw-mail {
    --kw-accent: #1a73e8;
    --kw-text: #202124;
    --kw-muted: #5f6368;
    --kw-border: #e0e0e0;
    --kw-hover: #f2f6fc;
    --kw-read-bg: #f6f8fc;
    --kw-sidebar-bg: #f6f8fc;
    position: relative;
    display: flex;
    flex-direction: column;
    height: calc(100vh - 130px);
    min-height: 560px;
    background: #fff;
    border: 1px solid var(--kw-border);
    border-radius: 12px;
    overflow: hidden;
    color: var(--kw-text);
  }
  .kw-mail-topbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    border-bottom: 1px solid var(--kw-border);
    background: #fff;
    flex: 0 0 auto;
  }
  .kw-mail-menu-btn,
  .kw-icon-btn {
    border: none;
    background: transparent;
    color: var(--kw-muted);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
  }
  .kw-mail-menu-btn:hover,
  .kw-icon-btn:hover { background: rgba(60,64,67,.08); color: var(--kw-text); }
  .kw-mail-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1.35rem;
    color: var(--kw-muted);
    font-weight: 500;
    white-space: nowrap;
  }
  .kw-mail-brand i { color: #ea4335; }
  .kw-mail-search {
    flex: 1 1 auto;
    max-width: 720px;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #eaf1fb;
    border-radius: 24px;
    padding: 0 16px;
    height: 46px;
  }
  .kw-mail-search i { color: var(--kw-muted); }
  .kw-mail-search input {
    border: none;
    background: transparent;
    outline: none;
    width: 100%;
    font-size: 0.95rem;
    color: var(--kw-text);
  }
  .kw-mail-topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 4px; }
  .kw-account-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 12px 4px 6px;
    border-radius: 20px;
    background: var(--kw-sidebar-bg);
    border: 1px solid var(--kw-border);
    max-width: 280px;
  }
  .kw-account-chip .kw-avatar { width: 28px; height: 28px; min-width: 28px; font-size: .8rem; }
  .kw-account-chip .kw-account-chip-text { line-height: 1.15; overflow: hidden; }
  .kw-account-chip .kw-account-chip-text strong { display: block; font-size: .78rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .kw-account-chip .kw-account-chip-text span { font-size: .72rem; color: var(--kw-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }

  .kw-mail-body { flex: 1 1 auto; display: flex; min-height: 0; position: relative; }
  .kw-mail-sidebar {
    flex: 0 0 268px;
    width: 268px;
    background: var(--kw-sidebar-bg);
    border-right: 1px solid var(--kw-border);
    padding: 12px 8px;
    overflow-y: auto;
  }
  .kw-compose-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #c2e7ff;
    color: #001d35;
    border: none;
    border-radius: 16px;
    padding: 0 24px 0 16px;
    height: 52px;
    font-weight: 500;
    font-size: .95rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
    cursor: pointer;
    margin: 4px 8px 12px;
  }
  .kw-compose-btn:hover { background: #a8dbfb; }
  .kw-compose-btn:disabled { opacity: .5; cursor: not-allowed; }
  .kw-nav { display: flex; flex-direction: column; gap: 1px; margin-bottom: 12px; }
  .kw-nav-item {
    display: flex;
    align-items: center;
    gap: 14px;
    border: none;
    background: transparent;
    color: var(--kw-text);
    padding: 8px 20px 8px 16px;
    border-radius: 0 20px 20px 0;
    font-size: .9rem;
    cursor: pointer;
    text-align: left;
    width: 100%;
  }
  .kw-nav-item:hover { background: rgba(60,64,67,.08); }
  .kw-nav-item.active { background: #d3e3fd; font-weight: 700; }
  .kw-nav-item i { width: 18px; text-align: center; color: var(--kw-muted); }
  .kw-nav-item.active i { color: #1a73e8; }
  .kw-nav-item .kw-count { margin-left: auto; font-size: .78rem; color: var(--kw-muted); }
  .kw-nav-item .kw-count.unread { color: #1a73e8; font-weight: 700; }
  .kw-avatar {
    width: 32px; height: 32px; min-width: 32px;
    border-radius: 50%;
    background: #1a73e8;
    color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: .85rem;
    text-transform: uppercase;
  }
  .kw-account-meta { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
  .kw-account-meta strong { font-size: .86rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
  .kw-account-meta strong .kw-badge-setup { background:#fef7e0; color:#b06000; border-radius:8px; padding:0 6px; font-size:.62rem; font-weight:700; }
  .kw-account-meta span { font-size: .74rem; color: var(--kw-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .kw-account-gear { border: none; background: transparent; color: var(--kw-muted); width: 28px; height: 28px; min-width: 28px; border-radius: 50%; cursor: pointer; }
  .kw-account-gear:hover { background: rgba(60,64,67,.12); color: var(--kw-text); }

  .kw-mail-main { flex: 1 1 auto; display: flex; flex-direction: column; min-width: 0; background: #fff; }
  .kw-mail-listhead {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 16px; border-bottom: 1px solid var(--kw-border);
    flex: 0 0 auto;
  }
  .kw-mail-listhead h1 { font-size: 1.15rem; font-weight: 500; margin: 0; }
  .kw-mail-listhead .kw-breadcrumb { color: var(--kw-muted); font-size: .8rem; margin-left: auto; text-align: right; }
  .kw-toolbar {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 6px 16px; border-bottom: 1px solid var(--kw-border); flex: 0 0 auto;
  }
  .kw-chip {
    border: 1px solid var(--kw-border); background: #fff; color: var(--kw-muted);
    border-radius: 16px; padding: 4px 12px; font-size: .8rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
  }
  .kw-chip:hover { background: #f2f6fc; }
  .kw-chip.active { background: #d3e3fd; border-color: #d3e3fd; color: #001d35; font-weight: 600; }

  .kw-state { text-align: center; color: var(--kw-muted); padding: 48px 16px; }
  .kw-msg-row {
    display: flex; align-items: center; gap: 12px;
    padding: 9px 16px; border-bottom: 1px solid #f1f3f4; cursor: pointer;
    background: var(--kw-read-bg);
  }
  .kw-msg-row:hover { background: #fff; box-shadow: inset 1px 0 0 #dadce0, inset -1px 0 0 #dadce0, 0 1px 2px rgba(0,0,0,.1); }
  .kw-msg-row.unread { background: #fff; font-weight: 700; }
  .kw-msg-row .kw-msg-star { color: #f4b400; width: 16px; text-align: center; }
  .kw-msg-row .kw-msg-star.off { color: #dadce0; }
  .kw-msg-sender { flex: 0 0 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .86rem; }
  .kw-msg-snippet { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .86rem; }
  .kw-msg-snippet .preview { color: #5f6368; font-weight: 400; }
  .kw-msg-date { flex: 0 0 auto; font-size: .78rem; color: #5f6368; padding-left: 12px; }

  .kw-mail-viewer { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; }
  .kw-mail-viewer-head { display: flex; align-items: center; gap: 6px; padding: 8px 12px; border-bottom: 1px solid var(--kw-border); flex-wrap: wrap; }
  .kw-mail-viewer-head .kw-spacer { flex: 1 1 auto; }
  .kw-mail-viewer-scroll { overflow-y: auto; padding: 8px 24px 32px; }
  .kw-mail-viewer-subject { display: flex; align-items: center; gap: 10px; margin: 12px 0 16px; }
  .kw-mail-viewer-subject h2 { font-size: 1.35rem; margin: 0; font-weight: 400; }
  .kw-mail-meta { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 20px; }
  .kw-mail-meta .kw-meta-lines { font-size: .84rem; line-height: 1.5; }
  .kw-mail-meta .from { font-weight: 600; }
  .kw-mail-meta .addr { color: #5f6368; }
  .kw-mail-body-html { font-size: .92rem; line-height: 1.55; word-break: break-word; }
  .kw-mail-body-html img { max-width: 100%; height: auto; }
  .kw-attach-list { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
  .kw-attach { display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--kw-border); border-radius: 8px; padding: 8px 12px; background: #fff; font-size: .82rem; cursor: pointer; }
  .kw-attach:hover { background: #f2f6fc; }
  .kw-pagination { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-top: 1px solid var(--kw-border); flex: 0 0 auto; }

  .kw-access-member { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--kw-border); }
  .kw-access-member .kw-access-name { flex: 1 1 auto; min-width: 0; }
  .kw-access-member .kw-access-name strong { display: block; font-size: .86rem; }
  .kw-access-member .kw-access-name span { font-size: .76rem; color: var(--kw-muted); }

  @media (max-width: 900px) {
    .kw-mail-sidebar { position: absolute; top: 0; left: 0; height: 100%; z-index: 20; box-shadow: 2px 0 12px rgba(0,0,0,.18); transform: translateX(-100%); transition: transform .18s ease; }
    .kw-mail.sidebar-open .kw-mail-sidebar { transform: translateX(0); }
    .kw-mail-brand span { display: none; }
    .kw-account-chip .kw-account-chip-text { display: none; }
    .kw-msg-sender { flex-basis: 120px; }
  }
</style>

<div id="mailboxes-root" class="kw-mail">
  <header class="kw-mail-topbar">
    <button type="button" class="kw-mail-menu-btn" id="mailSidebarToggle" title="Toggle mailbox list"><i class="fas fa-bars"></i></button>
    <div class="kw-mail-brand"><i class="fas fa-envelope"></i><span>Kingsway Mail</span></div>
    <div class="kw-mail-search">
      <i class="fas fa-search"></i>
      <input type="search" id="msgSearch" placeholder="Search mail" autocomplete="off" aria-label="Search mail">
    </div>
    <div class="kw-mail-topbar-actions">
      <button type="button" class="kw-icon-btn" id="mailRefreshBtn" title="Refresh"><i class="fas fa-sync-alt"></i></button>
      <button type="button" class="kw-icon-btn" id="mailExportBtn" title="Export current view to CSV"><i class="fas fa-file-csv"></i></button>
      <button type="button" class="kw-icon-btn" id="mailPrintBtn" title="Print current view"><i class="fas fa-print"></i></button>
      <div class="kw-account-chip" title="Signed in as">
        <span class="kw-avatar" id="mailAccountAvatar">K</span>
        <span class="kw-account-chip-text">
          <strong id="mailAccountName">Kingsway Staff</strong>
          <span id="mailAccountEmail"></span>
        </span>
      </div>
    </div>
  </header>

  <div class="kw-mail-body">
    <aside class="kw-mail-sidebar" id="mailSidebar">
      <button type="button" class="kw-compose-btn" id="mailComposeBtn"><i class="fas fa-pen"></i>Compose</button>

      <nav class="kw-nav" id="folderList" aria-label="Folders"></nav>

      <div class="kw-mail-accounts">
        <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1">
          <small class="text-uppercase text-muted" style="letter-spacing:.08em;font-size:.7rem;">Mail accounts</small>
          <button type="button" class="kw-account-gear" id="mailAccessBtn" title="Manage mailbox access" style="display:none;"><i class="fas fa-user-shield"></i></button>
        </div>
        <div id="mailboxTabs" role="tablist"></div>
      </div>
    </aside>

    <main class="kw-mail-main">
      <div class="kw-mail-listhead">
        <h1 id="mailFolderTitle">Inbox</h1>
        <div class="kw-breadcrumb" id="folderBreadcrumb">
          <span id="folderBreadcrumbLabel"></span>
        </div>
      </div>

      <div class="kw-toolbar" id="listToolbar" style="display:none;">
        <button type="button" class="kw-chip" id="msgUnreadToggle" title="Show unread only"><i class="fas fa-envelope"></i>Unread</button>
        <button type="button" class="kw-chip" id="msgFlaggedToggle" title="Show flagged only"><i class="fas fa-flag"></i>Flagged</button>
        <span id="outboxStatusWrap" style="display:none;">
          <select class="form-select form-select-sm" id="outboxStatus" style="max-width:160px;">
            <option value="">All statuses</option>
            <option value="failed">Failed</option>
            <option value="queued">Queued</option>
            <option value="sending">Sending</option>
            <option value="sent">Sent</option>
          </select>
        </span>
        <small class="ms-auto text-muted" id="listSummary"></small>
      </div>

      <div id="loadingMessages" class="kw-state" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="small text-muted mt-2" id="loadingMessagesText">Loading messages...</div>
      </div>

      <div id="emptyFolderNotice" class="kw-state" style="display:none;">
        <i class="fas fa-envelope-open fa-3x mb-3 d-block opacity-25"></i>
        <span id="emptyFolderText">No messages in this folder.</span>
      </div>

      <div id="messageList" style="overflow-y:auto;flex:1 1 auto;min-height:0;"></div>

      <div id="messageViewer" class="kw-mail-viewer" style="display:none;">
        <div class="kw-mail-viewer-head">
          <button type="button" class="kw-icon-btn" id="mvBackBtn" title="Back to list"><i class="fas fa-arrow-left"></i></button>
          <span class="kw-spacer"></span>
          <button type="button" class="kw-icon-btn" id="mvReplyBtn" title="Reply"><i class="fas fa-reply"></i></button>
          <button type="button" class="kw-icon-btn" id="mvReplyAllBtn" title="Reply all"><i class="fas fa-reply-all"></i></button>
          <button type="button" class="kw-icon-btn" id="mvForwardBtn" title="Forward"><i class="fas fa-share"></i></button>
          <button type="button" class="kw-icon-btn" id="mvReadBtn" title="Toggle read/unread"><i class="fas fa-envelope-open"></i></button>
          <button type="button" class="kw-icon-btn" id="mvFlagBtn" title="Toggle flag"><i class="fas fa-star"></i></button>
          <select class="form-select form-select-sm" id="mvMoveSelect" style="max-width:150px;">
            <option value="">Move to...</option>
          </select>
          <button type="button" class="kw-icon-btn" id="mvMoveBtn" title="Move"><i class="fas fa-folder-open"></i></button>
          <button type="button" class="kw-icon-btn" id="mvDeleteBtn" title="Delete" style="color:#d93025;"><i class="fas fa-trash"></i></button>
        </div>
        <div class="kw-mail-viewer-scroll">
          <div class="kw-mail-viewer-subject"><h2 id="mvSubject"></h2></div>
          <div class="kw-mail-meta">
            <span class="kw-avatar" id="mvAvatar">?</span>
            <div class="kw-meta-lines">
              <div><span class="from" id="mvFrom"></span></div>
              <div class="addr">to <span id="mvTo"></span></div>
              <div class="addr" id="mvCcRow" style="display:none;">cc <span id="mvCc"></span></div>
              <div class="addr" id="mvDate"></div>
            </div>
          </div>
          <div id="mvAttachments" class="kw-attach-list" style="display:none;"></div>
          <div id="mvBody" class="kw-mail-body-html"></div>
        </div>
      </div>

      <div class="kw-pagination" id="msgPagination" style="display:none;">
        <small class="text-muted" id="msgPaginationInfo"></small>
        <div>
          <button class="btn btn-outline-secondary btn-sm" id="msgPrevPage" disabled><i class="fas fa-chevron-left"></i></button>
          <small class="mx-2" id="msgPageLabel">1 / 1</small>
          <button class="btn btn-outline-secondary btn-sm" id="msgNextPage" disabled><i class="fas fa-chevron-right"></i></button>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Compose Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-pen me-1"></i><span id="composeTitle">Compose Email</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="composeForm" autocomplete="off" novalidate>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">From <span class="text-danger">*</span></label>
                            <select class="form-select" id="composeMailbox" required></select>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label fw-semibold mb-0">To <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-link btn-sm p-0" id="composeDirectoryToggle">Hide directory</button>
                            </div>
                            <input type="text" class="form-control" id="composeTo" required
                                   placeholder="name@example.com, comma separated">
                            <div id="composeDirectoryWrap" class="mt-2">
                                <div class="border rounded p-2 bg-body-tertiary">
                                    <small class="text-muted d-block mb-2">Browse or search learners, guardians, staff and vendors &mdash; selecting one adds their email to the To field.</small>
                                    <div id="composeRecipientPicker"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12" id="ccBccWrap" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Cc</label>
                                    <input type="text" class="form-control" id="composeCc" placeholder="cc@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bcc</label>
                                    <input type="text" class="form-control" id="composeBcc" placeholder="bcc@example.com">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-link btn-sm p-0" id="composeCcBccToggle">Show Cc / Bcc</button>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="composeSubject" required>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label fw-semibold mb-0">Message <span class="text-danger">*</span></label>
                                <div class="dropdown">
                                    <button class="btn btn-link btn-sm p-0 dropdown-toggle" type="button" data-bs-toggle="dropdown">Insert variable</button>
                                    <ul class="dropdown-menu" id="messageVariableMenu"></ul>
                                </div>
                            </div>
                            <textarea class="form-control" id="composeBody" rows="10" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Attachments</label>
                            <input type="file" class="form-control" id="composeAttachments" multiple>
                            <div id="composeAttachmentList" class="mt-2"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <span class="text-muted small me-auto" id="composeStatus"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" id="composeSaveDraftBtn"><i class="fas fa-file-alt me-1"></i>Save Draft</button>
                <button type="button" class="btn btn-primary" id="composeSendBtn"><i class="fas fa-paper-plane me-1"></i>Send</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage mailbox access (School Administrator) -->
<div class="modal fade" id="mailboxAccessModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-shield me-1"></i>Manage access — <span id="accessMailboxName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Choose which staff may open this mailbox. <strong>Read</strong> lets them view mail,
                    <strong>Reply</strong> lets them answer existing threads, and <strong>Compose</strong> lets them send new mail.
                </p>
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="accessUserSearch" placeholder="Search staff by name or username..." autocomplete="off">
                </div>
                <div id="accessUserResults" class="list-group mb-3" style="max-height:220px;overflow-y:auto;"></div>
                <h6 class="text-uppercase text-muted small">Members</h6>
                <div id="accessMembersList"></div>
                <div id="accessMembersEmpty" class="text-muted small py-3" style="display:none;">No staff have explicit access yet. School and department rules still apply.</div>
            </div>
            <div class="modal-footer">
                <span class="text-muted small me-auto" id="accessStatus"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="accessSaveBtn"><i class="fas fa-save me-1"></i>Save changes</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_email.js?v=<?php echo asset_version('js/pages/manage_email.js'); ?>"></script>
