<?php
/* Website workspace — partial page served inside the home.php app shell.
   Sections are shown by js/pages/manage_website.js from the signed-in
   user's website_* permissions. No SQL here. */
?>

<style>
.kw-web {
  --web-ink: #1B2620;
  --web-muted: #5E6B64;
  --web-line: #E3E9E4;
  --web-paper: #F7F9F6;
  --web-card: #FFFFFF;
  --web-green: #155C3B;
  --web-leaf: #2E8B57;
  --web-leaf-tint: #E7F2EC;
  --web-maize: #E0A526;
  --web-maize-tint: #FBF3DE;
  --web-danger: #B4362B;
  color: var(--web-ink);
  background: var(--web-paper);
  border-radius: 18px;
  padding: 14px;
}
/* The hidden attribute always wins over component display rules. */
.kw-web [hidden], .kw-web-modal [hidden] { display: none !important; }
.kw-web h1 { font-size: 1.5rem; font-weight: 700; letter-spacing: -.01em; margin: 0; }
.kw-web h2 { font-size: 1.125rem; font-weight: 650; margin: 0; }
.kw-web h3 { font-size: .95rem; font-weight: 650; margin: 0; }
.kw-web .web-sub { color: var(--web-muted); font-size: .875rem; margin: 4px 0 0; max-width: 62ch; }
.kw-web .web-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.kw-web .web-head-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.kw-web .btn-web { background: var(--web-green); color: #fff; border: 0; border-radius: 10px; font-weight: 600; font-size: .875rem; padding: 8px 14px; }
.kw-web .btn-web:hover, .kw-web .btn-web:focus-visible { background: #0F4A2F; color: #fff; }
.kw-web .btn-web-quiet { background: var(--web-card); color: var(--web-ink); border: 1px solid var(--web-line); border-radius: 10px; font-weight: 600; font-size: .875rem; padding: 8px 14px; }
.kw-web .btn-web-quiet:hover { border-color: var(--web-leaf); color: var(--web-green); }
.kw-web .btn-icon { border: 1px solid var(--web-line); background: var(--web-card); border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; color: var(--web-muted); }
.kw-web .btn-icon:hover { color: var(--web-green); border-color: var(--web-leaf); }
.kw-web .btn-icon.danger:hover { color: var(--web-danger); border-color: var(--web-danger); }
.kw-web :focus-visible { outline: 3px solid rgba(46,139,87,.45); outline-offset: 2px; }

/* Desk: internal sidebar | content. Two columns with independent scrolling. */
.kw-web .web-desk {
  display: flex;
  gap: 14px;
  height: calc(100vh - 130px); /* viewport minus header + padding */
  min-height: 600px;
}
.kw-web .web-rail { flex: 0 0 220px; min-width: 0; }
.kw-web .web-canvas { flex: 1 1 auto; min-width: 0; }

/* Each column is a flex column with independent vertical scrolling */
.kw-web .web-desk > * {
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  overflow-x: hidden;
  max-height: 100%;
  min-height: 0;
}

/* Rail: scrolls as a single column */
.kw-web .web-rail {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* Canvas: scrolls independently */
.kw-web .web-canvas {
  position: relative;
}

/* Scrollbar styling for webkit */
.kw-web .web-desk > *::-webkit-scrollbar {
  width: 6px;
}
.kw-web .web-desk > *::-webkit-scrollbar-track {
  background: transparent;
}
.kw-web .web-desk > *::-webkit-scrollbar-thumb {
  background: var(--web-line);
  border-radius: 3px;
}
.kw-web .web-desk > *::-webkit-scrollbar-thumb:hover {
  background: var(--web-muted);
}

/* Locked workspace (body.kw-web-workspace, set by manage_website.js):
   the app shell stops scrolling as a page; the rail, canvas and "Up next"
   columns each scroll inside their own fixed-height grid area. */
body.kw-web-workspace .app-main-column { height: 100vh; height: 100dvh; overflow: hidden; }
body.kw-web-workspace .app-content { display: flex; flex-direction: column; min-height: 0; overflow-y: auto; }
body.kw-web-workspace .app-content-inner { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
body.kw-web-workspace .kw-web-page { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
body.kw-web-workspace .kw-web { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
body.kw-web-workspace .web-desk { flex: 1 1 auto; min-height: 0; height: auto; gap: 14px; }
/* Tighter page chrome: the shared shell padding is generous for this dense workspace. */
body.kw-web-workspace .app-content-inner { padding: 10px 14px 14px !important; }
body.kw-web-workspace .kw-web-page { padding: 0 !important; }
body.kw-web-workspace .web-head { margin-bottom: 10px; }

/* Mobile - sidebar becomes a horizontal strip above the content */
@media (max-width: 991.98px) {
  .kw-web .web-desk {
    flex-direction: column;
    height: auto;
    min-height: 0;
  }
  .kw-web .web-rail {
    flex: 0 0 auto;
    width: auto;
    position: static;
    flex-direction: row;
    flex-wrap: nowrap;
    overflow-x: auto;
    gap: 6px;
    padding-bottom: 6px;
    scrollbar-width: thin;
    max-height: none;
  }
  .kw-web .web-canvas { flex: 1 1 auto; }
  .kw-web { padding: 14px; }
  .kw-web .web-rail .web-rail-group { display: contents; }
  .kw-web .web-rail .web-rail-group-label { display: none; }
  .kw-web .web-rail .web-rail-group button { width: auto; flex: 0 0 auto; border: 1px solid var(--web-line); background: var(--web-card); }
  .kw-web .web-rail .web-rail-group button[aria-current="page"] { background: var(--web-green); border-color: var(--web-green); }
}
.kw-web .web-rail-group { display: flex; flex-direction: column; gap: 2px; }
.kw-web .web-rail-group-label { font-size: .75rem; font-weight: 600; color: var(--web-muted); padding: 0 10px 4px; }
.kw-web .web-rail button { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; border: 0; background: transparent; color: var(--web-ink); border-radius: 10px; padding: 8px 10px; font-size: .9rem; font-weight: 500; white-space: nowrap; }
.kw-web .web-rail button i { color: var(--web-muted); font-size: 1rem; }
.kw-web .web-rail button:hover { background: var(--web-leaf-tint); }
.kw-web .web-rail button[aria-current="page"] { background: var(--web-green); color: #fff; }
.kw-web .web-rail button[aria-current="page"] i { color: #fff; }
.kw-web .web-rail .web-count { margin-left: auto; font-size: .75rem; background: var(--web-maize); color: #3B2A05; border-radius: 999px; padding: 0 7px; font-weight: 700; }

.kw-web .web-panel { background: var(--web-card); border: 1px solid var(--web-line); border-radius: 16px; padding: 18px; min-height: 420px; }
.kw-web .web-panel-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.kw-web .web-tools { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
.kw-web .web-tools .form-control, .kw-web .web-tools .form-select { font-size: .875rem; border-radius: 9px; border-color: var(--web-line); }
.kw-web .web-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.kw-web .web-chip { border: 1px solid var(--web-line); background: var(--web-card); border-radius: 999px; padding: 4px 12px; font-size: .8125rem; color: var(--web-muted); }
.kw-web .web-chip[aria-pressed="true"] { background: var(--web-leaf-tint); border-color: var(--web-leaf); color: var(--web-green); font-weight: 600; }

/* Post list: media-led rows, not identical cards */
.kw-web .web-list { display: flex; flex-direction: column; }
.kw-web .web-row { display: grid; grid-template-columns: 88px minmax(0, 1fr) auto; gap: 14px; align-items: center; padding: 12px 4px; border-top: 1px solid var(--web-line); }
.kw-web .web-row:first-child { border-top: 0; }
.kw-web .web-row-media { width: 88px; height: 60px; border-radius: 10px; object-fit: cover; background: var(--web-leaf-tint); display: block; }
.kw-web .web-row-media.ph { display: flex; align-items: center; justify-content: center; color: var(--web-leaf); font-size: 1.3rem; }
.kw-web .web-row-title { font-weight: 600; font-size: .95rem; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kw-web .web-row-meta { font-size: .8125rem; color: var(--web-muted); margin-top: 2px; }
.kw-web .web-row-actions { display: flex; gap: 6px; }
.kw-web .web-state { font-size: .75rem; font-weight: 600; border-radius: 999px; padding: 1px 9px; margin-right: 6px; display: inline-block; }
.kw-web .web-state.live { background: var(--web-leaf-tint); color: var(--web-green); }
.kw-web .web-state.draft { background: #EEF0EF; color: var(--web-muted); }
.kw-web .web-state.off { background: #FBE9E7; color: var(--web-danger); }
@media (max-width: 575.98px) { .kw-web .web-row { grid-template-columns: 64px minmax(0,1fr); } .kw-web .web-row-media { width: 64px; height: 48px; } .kw-web .web-row-actions { grid-column: 1 / -1; justify-content: flex-end; } }

.kw-web .web-empty { text-align: center; padding: 46px 16px; color: var(--web-muted); }
.kw-web .web-empty i { font-size: 2rem; color: var(--web-leaf); display: block; margin-bottom: 8px; }
.kw-web .web-empty p { margin: 0 auto 14px; max-width: 46ch; }

/* Gallery wall */
.kw-web .web-wall { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; }
.kw-web .web-tile { position: relative; border-radius: 12px; overflow: hidden; background: var(--web-leaf-tint); border: 1px solid var(--web-line); }
.kw-web .web-tile img, .kw-web .web-tile video { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; }
.kw-web .web-tile figcaption { font-size: .8125rem; padding: 7px 9px; display: flex; justify-content: space-between; gap: 6px; align-items: center; }
.kw-web .web-tile .web-tile-kind { position: absolute; top: 8px; left: 8px; background: rgba(27,38,32,.75); color: #fff; border-radius: 6px; font-size: .7rem; padding: 1px 6px; }
.kw-web .web-tile.is-hidden img, .kw-web .web-tile.is-hidden video { opacity: .45; }

/* Forms */
.kw-web-modal .form-label, .kw-web .form-label { font-size: .8125rem; font-weight: 600; color: #2D3A33; margin-bottom: 4px; }
.kw-web-modal .form-control, .kw-web-modal .form-select { border-radius: 9px; border-color: #D5DED8; font-size: .9rem; }
.kw-web-modal .form-control:focus, .kw-web-modal .form-select:focus, .kw-web .form-control:focus, .kw-web .form-select:focus { border-color: #2E8B57; box-shadow: 0 0 0 3px rgba(46,139,87,.15); }
.kw-web-modal .form-hint { font-size: .78rem; color: #5E6B64; margin-top: 3px; }
.kw-web-modal .modal-content { border: 0; border-radius: 18px; }
.kw-web-modal .modal-header { border-bottom: 1px solid #E3E9E4; }
.kw-web-modal .modal-footer { border-top: 1px solid #E3E9E4; }
.kw-web-modal .btn-web { background: #155C3B; color: #fff; border: 0; border-radius: 10px; font-weight: 600; padding: 8px 16px; }
.kw-web-modal .btn-web:hover { background: #0F4A2F; color: #fff; }
.kw-web-modal .btn-web-quiet { background: #fff; color: #1B2620; border: 1px solid #E3E9E4; border-radius: 10px; font-weight: 600; padding: 8px 16px; }
.kw-web-modal .web-seg { display: inline-flex; border: 1px solid #D5DED8; border-radius: 10px; overflow: hidden; }
.kw-web-modal .web-seg button { border: 0; background: #fff; padding: 6px 14px; font-size: .85rem; font-weight: 600; color: #5E6B64; }
.kw-web-modal .web-seg button[aria-pressed="true"] { background: #155C3B; color: #fff; }

/* Drop zone */
.web-drop { border: 2px dashed #B9CBBF; border-radius: 14px; background: #F5FAF6; padding: 18px; text-align: center; color: #3F4D45; cursor: pointer; transition: border-color .15s, background .15s; }
.web-drop:hover, .web-drop.is-over { border-color: #2E8B57; background: #E7F2EC; }
.web-drop i { font-size: 1.6rem; color: #2E8B57; display: block; margin-bottom: 4px; }
.web-drop small { color: #5E6B64; display: block; margin-top: 2px; }
.web-drop-current { position: relative; margin-top: 10px; }
.web-drop-current img, .web-drop-current video { width: 100%; max-height: 220px; object-fit: cover; border-radius: 12px; display: block; }
.web-drop-current .btn-icon { position: absolute; top: 8px; right: 8px; background: #fff; }
.web-queue { list-style: none; padding: 0; margin: 12px 0 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.web-queue li { border: 1px solid #E3E9E4; border-radius: 12px; overflow: hidden; background: #fff; font-size: .8rem; }
.web-queue img, .web-queue video { width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block; background: #E7F2EC; }
.web-queue .web-q-body { padding: 6px 8px; }
.web-queue input { font-size: .8rem; padding: 3px 6px; }
.web-queue .web-q-state { font-size: .75rem; color: #5E6B64; }
.web-queue .web-q-state.err { color: #B4362B; }
.web-queue .web-q-state.ok { color: #155C3B; font-weight: 600; }

/* Live preview of a news card, as the public site renders it */
.web-live { position: sticky; top: 0; }
.web-live-label { font-size: .8125rem; font-weight: 600; color: #5E6B64; margin-bottom: 8px; }
.web-live-card { border-radius: 16px; overflow: hidden; background: #fff; box-shadow: 0 6px 24px rgba(21,92,59,.12); border: 1px solid #E3E9E4; }
.web-live-card img { width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; background: #E7F2EC; }
.web-live-card .web-live-body { padding: 14px 16px 16px; }
.web-live-card .web-live-cat { display: inline-block; font-size: .72rem; font-weight: 700; color: #155C3B; background: #E7F2EC; border-radius: 999px; padding: 1px 9px; }
.web-live-card h4 { font-size: 1.05rem; font-weight: 700; margin: 8px 0 6px; line-height: 1.3; }
.web-live-card p { font-size: .875rem; color: #5E6B64; margin: 0; }
.web-live-card .web-live-date { font-size: .75rem; color: #7C8982; margin-top: 10px; }

/* Settings groups */
.kw-web .web-set-group { border: 1px solid var(--web-line); border-radius: 14px; padding: 14px 16px; margin-bottom: 14px; }
.kw-web .web-set-group header { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
.kw-web .web-set-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px 16px; }
.kw-web .web-set-field small { color: var(--web-muted); font-size: .75rem; }
.kw-web .web-set-field.is-dirty .form-control { border-color: var(--web-maize); background: #FFFDF5; }

/* Downloads config */
.kw-web .web-split { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 18px; }
@media (max-width: 1199.98px) { .kw-web .web-split { grid-template-columns: 1fr; } }
.kw-web .web-box { border: 1px solid var(--web-line); border-radius: 14px; padding: 14px 16px; }
.kw-web .web-box + .web-box { margin-top: 14px; }
.kw-web .web-checks { display: flex; flex-wrap: wrap; gap: 6px 14px; }
.kw-web .web-checks .form-check { font-size: .875rem; }
.kw-web .web-dl-row { display: grid; grid-template-columns: 34px minmax(0,1fr) auto; gap: 10px; align-items: center; padding: 9px 2px; border-top: 1px solid var(--web-line); }
.kw-web .web-dl-row:first-child { border-top: 0; }
.kw-web .web-dl-row i.web-dl-ico { font-size: 1.3rem; }
.kw-web .web-dl-row.is-hidden .web-row-title { color: var(--web-muted); text-decoration: line-through; }

/* Kept sections (page text, about the school, leadership) */
.kw-web .ws-stat-card { background: var(--web-card); border: 1px solid var(--web-line); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; }
.kw-web .ws-stat-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.kw-web .ws-table td, .kw-web .ws-table th { vertical-align: middle; font-size: .85rem; }
.kw-web .ws-tag-chip { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: .75rem; font-weight: 600; border: 1px solid; }
.kw-web .ws-form-group label { font-size: .8125rem; font-weight: 600; color: #2D3A33; margin-bottom: 3px; display: block; }
.kw-web .ws-settings-row { display: grid; grid-template-columns: minmax(140px, 1fr) 2fr; gap: 12px; align-items: start; padding: 10px 0; border-bottom: 1px solid var(--web-line); }
.kw-web .ws-settings-key { font-size: .8125rem; font-weight: 600; word-break: break-word; }
@media (max-width: 575.98px) { .kw-web .ws-settings-row { grid-template-columns: 1fr; } }

/* Preview frame */
.web-preview-bar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.web-preview-stage { background: #E9EEEA; flex: 1; display: flex; justify-content: center; overflow: auto; padding: 16px; }
.web-preview-stage iframe { border: 0; background: #fff; width: 100%; height: calc(100vh - 110px); min-height: 480px; border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,.12); transition: width .2s; }
.web-preview-stage.is-phone iframe { width: 390px; }
@media (prefers-reduced-motion: reduce) { .web-preview-stage iframe, .web-drop { transition: none; } }
</style>

<div class="container-fluid px-3 px-lg-4 py-3 kw-web-page">
<div class="kw-web" id="kwWeb">

  <header class="web-head">
    <div class="web-head-actions ms-auto">
      <button type="button" class="btn btn-web-quiet" data-action="preview" data-preview-page="">
        <i class="bi bi-eye me-1"></i>Preview site
      </button>
      <a class="btn btn-web-quiet" href="<?= htmlspecialchars($appBase, ENT_QUOTES) ?>/" target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right me-1"></i>Open in new tab
      </a>
    </div>
  </header>

  <div class="web-desk">

    <!-- Rail: grouped by the job, not by the database table -->
    <nav class="web-rail" aria-label="Website sections" id="webRail">
      <div class="web-rail-group">
        <div class="web-rail-group-label">Publish</div>
        <button type="button" data-tab="news" data-perm="website_news_manage"><i class="bi bi-megaphone"></i>News &amp; announcements</button>
        <button type="button" data-tab="events" data-perm="website_events_manage"><i class="bi bi-calendar-event"></i>Events</button>
        <button type="button" data-tab="gallery" data-perm="website_gallery_manage"><i class="bi bi-images"></i>Photos &amp; videos</button>
        <button type="button" data-tab="downloads" data-perm="website_downloads_manage"><i class="bi bi-file-earmark-arrow-down"></i>Downloads</button>
      </div>
      <div class="web-rail-group">
        <div class="web-rail-group-label">Careers</div>
        <button type="button" data-tab="jobs" data-perm="website_jobs_manage"><i class="bi bi-briefcase"></i>Vacancies</button>
        <button type="button" data-tab="applications" data-perm="website_applications_view"><i class="bi bi-person-lines-fill"></i>Applications</button>
      </div>
      <div class="web-rail-group">
        <div class="web-rail-group-label">Inbox</div>
        <button type="button" data-tab="inquiries" data-perm="website_inquiries_view"><i class="bi bi-envelope"></i>Enquiries <span class="web-count d-none" id="webInboxCount"></span></button>
      </div>
      <div class="web-rail-group">
        <div class="web-rail-group-label">Site</div>
        <button type="button" data-tab="content" data-perm="website_content_manage"><i class="bi bi-text-paragraph"></i>Page text</button>
        <button type="button" data-tab="static" data-perm="website_content_manage"><i class="bi bi-building"></i>About the school</button>
        <button type="button" data-tab="settings" data-perm="website_settings_manage"><i class="bi bi-telephone"></i>School details</button>
      </div>
    </nav>

    <main class="web-canvas">

      <!-- NEWS & ANNOUNCEMENTS -->
      <section class="web-panel" id="tab-news" data-panel hidden>
        <div class="web-panel-head">
          <div>
            <h2>News &amp; announcements</h2>
            <p class="web-sub">Announcements reach families quickly; news articles tell the longer story with photos.</p>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-web-quiet" data-action="compose-news" data-kind="announcement"><i class="bi bi-megaphone me-1"></i>New announcement</button>
            <button type="button" class="btn btn-web" data-action="compose-news" data-kind="news"><i class="bi bi-plus-lg me-1"></i>New article</button>
          </div>
        </div>
        <div class="web-tools">
          <div class="web-chips" role="group" aria-label="Filter by state" id="newsStateChips">
            <button type="button" class="web-chip" data-news-status="" aria-pressed="true">All</button>
            <button type="button" class="web-chip" data-news-status="published" aria-pressed="false">On the website</button>
            <button type="button" class="web-chip" data-news-status="draft" aria-pressed="false">Drafts</button>
            <button type="button" class="web-chip" data-news-status="archived" aria-pressed="false">Archived</button>
          </div>
          <select id="newsCatFilter" class="form-select form-select-sm" style="max-width:190px" aria-label="Category"><option value="">All categories</option></select>
          <input type="search" id="newsSearch" class="form-control form-control-sm" style="max-width:240px" placeholder="Search titles" aria-label="Search titles">
        </div>
        <div class="web-list" id="newsList"></div>
      </section>

      <!-- EVENTS -->
      <section class="web-panel" id="tab-events" data-panel hidden>
        <div class="web-panel-head">
          <div>
            <h2>Events</h2>
            <p class="web-sub">Events from the school calendar appear here automatically. Add the ones families should know about, such as sports day or a prize-giving.</p>
          </div>
          <button type="button" class="btn btn-web" data-action="compose-event"><i class="bi bi-plus-lg me-1"></i>New event</button>
        </div>
        <div class="web-tools">
          <div class="web-chips" role="group" aria-label="Which events" id="eventScopeChips">
            <button type="button" class="web-chip" data-event-scope="1" aria-pressed="true">Coming up</button>
            <button type="button" class="web-chip" data-event-scope="0" aria-pressed="false">All events</button>
          </div>
        </div>
        <div class="web-list" id="eventsList"></div>
      </section>

      <!-- GALLERY -->
      <section class="web-panel" id="tab-gallery" data-panel hidden>
        <div class="web-panel-head">
          <div>
            <h2>Photos &amp; videos</h2>
            <p class="web-sub">Upload straight from this computer or phone. Several files at once is fine.</p>
          </div>
          <button type="button" class="btn btn-web" data-action="compose-gallery"><i class="bi bi-cloud-arrow-up me-1"></i>Upload photos or videos</button>
        </div>
        <div class="web-tools">
          <div class="web-chips" role="group" aria-label="Which items" id="galleryScopeChips">
            <button type="button" class="web-chip" data-gallery-scope="0" aria-pressed="true">Everything</button>
            <button type="button" class="web-chip" data-gallery-scope="1" aria-pressed="false">On the website</button>
          </div>
        </div>
        <div class="web-wall" id="galleryWall"></div>
      </section>

      <!-- DOWNLOADS -->
      <section class="web-panel" id="tab-downloads" data-panel hidden>
        <div class="web-panel-head">
          <div>
            <h2>Downloads</h2>
            <p class="web-sub">Choose exactly what families can download: documents you upload, and the fee structures and calendar the system produces.</p>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-web-quiet" data-action="preview" data-preview-page="downloads"><i class="bi bi-eye me-1"></i>Preview page</button>
            <button type="button" class="btn btn-web" data-action="compose-download"><i class="bi bi-cloud-arrow-up me-1"></i>Upload a document</button>
          </div>
        </div>
        <div class="web-split">
          <div>
            <div class="web-box">
              <h3 class="mb-1">Documents you uploaded</h3>
              <p class="web-sub mb-2">Hide a file to take it off the page without deleting it. Use the arrows to change the order within its group.</p>
              <div id="downloadsList"></div>
            </div>
          </div>
          <div>
            <form class="web-box" id="dlConfigForm" novalidate>
              <h3 class="mb-1">Documents the system produces</h3>
              <p class="web-sub mb-3">Built from the fee structures and the school calendar, always up to date.</p>

              <label class="form-label">Academic years families can choose</label>
              <div class="web-checks mb-1" id="dlYears"></div>
              <div class="form-hint text-muted small mb-3">Leave all unticked to offer only the current year.</div>

              <label class="form-label" for="dlDefaultYear">Year shown first</label>
              <select class="form-select form-select-sm mb-3" id="dlDefaultYear" style="max-width:260px"></select>

              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="dlCalendar" role="switch">
                <label class="form-check-label" for="dlCalendar">Academic year calendar</label>
              </div>

              <fieldset class="mb-2">
                <legend class="form-label mb-1">Fee structures</legend>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="dlFeeWhole" role="switch">
                  <label class="form-check-label" for="dlFeeWhole">Whole school</label>
                </div>
                <div class="web-checks mt-1 mb-1" id="dlSections"></div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="dlFeeClass" role="switch">
                  <label class="form-check-label" for="dlFeeClass">One for each class</label>
                </div>
              </fieldset>

              <label class="form-label mt-2">Show fee structures for</label>
              <div class="web-checks mb-3" id="dlTypes"></div>

              <label class="form-label" for="dlIntro">Note at the top of the downloads page</label>
              <textarea class="form-control form-control-sm mb-3" id="dlIntro" rows="2" maxlength="400" placeholder="For example: Fee structures for next year are published in December."></textarea>

              <div class="d-flex gap-2 align-items-center">
                <button type="submit" class="btn btn-web" id="dlConfigSave">Save downloads page</button>
                <span class="small text-muted" id="dlConfigState"></span>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- VACANCIES -->
      <section class="web-panel" id="tab-jobs" data-panel hidden>
        <div class="web-panel-head">
          <div>
            <h2>Vacancies</h2>
            <p class="web-sub">Open vacancies appear on the Careers page until you close them or the deadline passes.</p>
          </div>
          <button type="button" class="btn btn-web" data-action="compose-job"><i class="bi bi-plus-lg me-1"></i>Post a vacancy</button>
        </div>
        <div class="web-list" id="jobsList"></div>
      </section>

      <!-- APPLICATIONS (kept) -->
      <section class="web-panel" id="tab-applications" data-panel hidden>
        <div class="web-panel-head"><div><h2>Applications</h2><p class="web-sub">Admission and job applications sent through the website.</p></div></div>
        <ul class="nav nav-tabs mb-3" id="appsSubTab">
          <li class="nav-item"><button class="nav-link active" data-apps-tab="admission">Admissions</button></li>
          <li class="nav-item"><button class="nav-link" data-apps-tab="jobs">Jobs</button></li>
        </ul>
        <div id="admissionAppsPanel">
          <div class="d-flex gap-2 mb-3">
            <select id="appStatusFilter" class="form-select form-select-sm" style="width:200px" aria-label="Application status">
              <option value="">All statuses</option>
              <option value="submitted">Submitted</option>
              <option value="documents_pending">Documents pending</option>
              <option value="documents_verified">Documents verified</option>
              <option value="placement_offered">Placement offered</option>
              <option value="fees_pending">Fees pending</option>
              <option value="placement_test_required">Placement test required</option>
              <option value="waitlisted">Waitlisted</option>
              <option value="enrolled">Enrolled</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="table-responsive"><table class="table table-hover align-middle small mb-0">
            <thead><tr><th>Ref</th><th>Child</th><th>Grade</th><th>Parent</th><th>Phone</th><th>Boarding</th><th>Status</th><th>Date</th><th class="text-end">Change status</th></tr></thead>
            <tbody id="appsTableBody"></tbody>
          </table></div>
        </div>
        <div id="jobAppsPanel" style="display:none">
          <div class="table-responsive"><table class="table table-hover align-middle small mb-0">
            <thead><tr><th>Name</th><th>Position</th><th>Email</th><th>Phone</th><th>TSC no.</th><th>Status</th><th>Date</th></tr></thead>
            <tbody id="jobAppsTableBody"></tbody>
          </table></div>
        </div>
      </section>

      <!-- ENQUIRIES (kept) -->
      <section class="web-panel" id="tab-inquiries" data-panel hidden>
        <div class="web-panel-head"><div><h2>Enquiries</h2><p class="web-sub">Messages families sent through the contact form. Mark them read once handled.</p></div></div>
        <div class="table-responsive"><table class="table table-hover align-middle small mb-0">
          <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Subject</th><th>Message</th><th>Status</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
          <tbody id="inquiriesTableBody"></tbody>
        </table></div>
      </section>

      <!-- PAGE TEXT (kept) -->
      <section class="web-panel" id="tab-content" data-panel hidden>
        <div class="web-panel-head"><div><h2>Page text</h2><p class="web-sub">Wording used across the public pages. Changes save when you leave a box.</p></div></div>
        <div class="row g-4">
          <div class="col-xl-7"><div id="contentBlocksList"></div></div>
          <div class="col-xl-5">
            <div class="web-box">
              <h3 class="mb-2">News categories</h3>
              <div id="categoriesList"></div>
              <button class="btn btn-web-quiet btn-sm mt-2 w-100" data-kw-onclick="wsAddCategory()"><i class="bi bi-plus me-1"></i>Add a category</button>
            </div>
          </div>
        </div>
      </section>

      <!-- ABOUT THE SCHOOL (kept) -->
      <section class="web-panel" id="tab-static" data-panel hidden>
        <div class="web-panel-head"><div><h2>About the school</h2><p class="web-sub">Values, history, programmes, facilities, departments, careers benefits, testimonials and leadership.</p></div></div>
        <div class="row g-4" id="staticTablesWrap">
          <div id="staticCard-values" class="col-xxl-6"></div>
          <div id="staticCard-history" class="col-xxl-6"></div>
          <div id="staticCard-programs" class="col-xxl-6"></div>
          <div id="staticCard-facilities" class="col-xxl-6"></div>
          <div id="staticCard-departments" class="col-xxl-6"></div>
          <div id="staticCard-benefits" class="col-xxl-6"></div>
          <div id="staticCard-testimonials" class="col-xxl-6"></div>
        </div>
        <div class="web-box mt-3">
          <h3 class="mb-2">Leadership team</h3>
          <div id="leadershipPanel"></div>
        </div>
      </section>

      <!-- SCHOOL DETAILS -->
      <section class="web-panel" id="tab-settings" data-panel hidden>
        <div class="web-panel-head">
          <div>
            <h2>School details</h2>
            <p class="web-sub">Contact details, office hours, social media and the numbers on the homepage. Edit a group, then save it.</p>
          </div>
          <input type="search" id="settingsSearch" class="form-control form-control-sm" style="max-width:240px" placeholder="Find a detail" aria-label="Find a detail">
        </div>
        <div id="settingsGroups"></div>
      </section>

    </main>

  </div>
</div>
</div>

<!-- ═══ COMPOSERS ═══════════════════════════════════════════════════════════ -->

<!-- News / announcement composer -->
<div class="modal fade kw-web-modal" id="wsNewsModal" tabindex="-1" aria-labelledby="wsNewsModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title fw-bold" id="wsNewsModalTitle">New article</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="newsEditId">
      <div class="row g-4">
        <div class="col-lg-7">
          <div class="web-seg mb-3" role="group" aria-label="Post type" id="newsKindSeg">
            <button type="button" data-news-kind="news" aria-pressed="true">News article</button>
            <button type="button" data-news-kind="announcement" aria-pressed="false">Announcement</button>
          </div>
          <div class="mb-3">
            <label class="form-label" for="newsTitle">Headline</label>
            <input type="text" class="form-control" id="newsTitle" maxlength="255" placeholder="Grade 6 learners visit the Kericho tea farms">
          </div>
          <div class="mb-3">
            <label class="form-label" for="newsExcerpt">Summary</label>
            <textarea class="form-control" id="newsExcerpt" rows="2" maxlength="600" placeholder="One or two sentences families will read first."></textarea>
            <div class="form-hint"><span id="newsExcerptCount">0</span>/600</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="newsContent">Full story</label>
            <textarea class="form-control" id="newsContent" rows="8" placeholder="Write the details. Keep learners' full names out unless parents have agreed."></textarea>
          </div>
          <div class="mb-3">
            <span class="form-label d-block">Cover photo</span>
            <div class="web-drop" data-drop="news" tabindex="0" role="button" aria-label="Choose a cover photo from this device">
              <i class="bi bi-image"></i>
              <strong>Drop a photo here or choose one from this device</strong>
              <small id="newsDropHint">JPG, PNG, WebP or GIF</small>
            </div>
            <input type="file" id="newsFile" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
            <div class="web-drop-current" id="newsImgCurrent" hidden>
              <img id="newsImgPreview" alt="Cover photo">
              <button type="button" class="btn-icon danger" data-action="clear-news-image" aria-label="Remove the cover photo"><i class="bi bi-x-lg"></i></button>
            </div>
            <input type="hidden" id="newsImageUrl">
            <details class="mt-2"><summary class="small text-muted">Use a photo link instead</summary>
              <input type="url" class="form-control form-control-sm mt-2" id="newsImageLink" placeholder="https://…">
            </details>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="newsCategory">Category</label>
              <select class="form-select" id="newsCategory"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="newsAuthor">Written by</label>
              <input type="text" class="form-control" id="newsAuthor" placeholder="School office">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="newsStatus">When saved</label>
              <select class="form-select" id="newsStatus">
                <option value="published">Publish on the website</option>
                <option value="draft">Keep as a draft</option>
                <option value="archived">Archive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="web-live">
            <div class="web-live-label">How it will look on the website</div>
            <article class="web-live-card" aria-live="polite">
              <img id="newsLiveImg" alt="" src="https://placehold.co/800x450/E7F2EC/155C3B?text=Cover+photo">
              <div class="web-live-body">
                <span class="web-live-cat" id="newsLiveCat">Announcement</span>
                <h4 id="newsLiveTitle">Your headline</h4>
                <p id="newsLiveExcerpt">The summary appears here.</p>
                <div class="web-live-date" id="newsLiveDate"></div>
              </div>
            </article>
            <button type="button" class="btn btn-web-quiet w-100 mt-3" id="newsAiDraftBtn" data-action="ai-draft-news"><i class="bi bi-stars me-1"></i>Ask the assistant to draft this</button>
            <div id="newsAiDraftHost"></div>
            <p class="form-hint mt-2">The assistant writes a draft for you to check. Nothing is published until you save.</p>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-web-quiet" data-bs-dismiss="modal">Cancel</button>
      <button type="button" class="btn btn-web" id="newsSubmitBtn" data-action="save-news">Publish</button>
    </div>
  </div></div>
</div>

<!-- Gallery uploader -->
<div class="modal fade kw-web-modal" id="wsGalleryModal" tabindex="-1" aria-labelledby="wsGalleryModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title fw-bold" id="wsGalleryModalTitle">Upload photos or videos</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
      <div class="web-drop" data-drop="gallery" tabindex="0" role="button" aria-label="Choose photos or videos from this device">
        <i class="bi bi-cloud-arrow-up"></i>
        <strong>Drop photos and videos here or choose them from this device</strong>
        <small id="galleryDropHint">Photos: JPG, PNG, WebP, GIF. Videos: MP4, WebM, MOV.</small>
      </div>
      <input type="file" id="galleryFiles" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" multiple hidden>
      <ul class="web-queue" id="galleryQueue"></ul>
      <div class="row g-3 mt-1">
        <div class="col-md-6">
          <label class="form-label" for="galleryCategory">Album</label>
          <select class="form-select" id="galleryCategory">
            <option>General</option><option>Events</option><option>Sports</option><option>Academics</option>
            <option>Worship</option><option>Farm &amp; vocational</option><option>Campus</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="galleryCaptionAll">Caption for all (optional)</label>
          <div class="input-group input-group-sm">
            <input type="text" class="form-control" id="galleryCaptionAll" maxlength="200" placeholder="Prize-giving day 2026">
            <button class="btn btn-outline-secondary" type="button" id="galleryCaptionAiBtn" title="Suggest a caption"><i class="bi bi-stars"></i></button>
          </div>
          <div id="galleryCaptionAiHost"></div>
        </div>
      </div>
      <details class="mt-3"><summary class="small text-muted">Add a YouTube or video link instead</summary>
        <div class="row g-2 mt-1">
          <div class="col-md-8"><input type="url" class="form-control form-control-sm" id="galleryVideoLink" placeholder="https://www.youtube.com/watch?v=…"></div>
          <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="galleryVideoCaption" maxlength="200" placeholder="Caption"></div>
        </div>
      </details>
    </div>
    <div class="modal-footer">
      <span class="small text-muted me-auto" id="galleryUploadState"></span>
      <button type="button" class="btn btn-web-quiet" data-bs-dismiss="modal">Close</button>
      <button type="button" class="btn btn-web" id="galleryUploadBtn" data-action="save-gallery">Add to the website</button>
    </div>
  </div></div>
</div>

<!-- Gallery item edit -->
<div class="modal fade kw-web-modal" id="wsGalleryEditModal" tabindex="-1" aria-labelledby="wsGalleryEditTitle" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title fw-bold" id="wsGalleryEditTitle">Edit photo</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" id="galleryEditId">
      <div class="mb-3"><label class="form-label" for="galleryEditCaption">Caption</label><input type="text" class="form-control" id="galleryEditCaption" maxlength="200"></div>
      <div class="mb-3"><label class="form-label" for="galleryEditCategory">Album</label><input type="text" class="form-control" id="galleryEditCategory" maxlength="50"></div>
      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="galleryEditActive"><label class="form-check-label" for="galleryEditActive">Show on the website</label></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-web-quiet" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-web" data-action="save-gallery-edit">Save</button></div>
  </div></div>
</div>

<!-- Event composer -->
<div class="modal fade kw-web-modal" id="wsEventModal" tabindex="-1" aria-labelledby="wsEventModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title fw-bold" id="wsEventModalTitle">New event</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" id="eventEditId">
      <div class="row g-3">
        <div class="col-12"><label class="form-label" for="eventTitle">Event name</label><input type="text" class="form-control" id="eventTitle" placeholder="Inter-house athletics"></div>
        <div class="col-md-4"><label class="form-label" for="eventDate">Date</label><input type="date" class="form-control" id="eventDate"></div>
        <div class="col-md-4"><label class="form-label" for="eventTime">Starts at</label><input type="time" class="form-control" id="eventTime"></div>
        <div class="col-md-4"><label class="form-label" for="eventEndDate">Ends on</label><input type="date" class="form-control" id="eventEndDate"></div>
        <div class="col-md-6"><label class="form-label" for="eventCategory">Kind of event</label>
          <select class="form-select" id="eventCategory">
            <option>Academic</option><option>Sports</option><option>Ceremony</option><option>Meeting</option><option>Community</option><option>Cultural</option><option>Worship</option>
          </select></div>
        <div class="col-md-6"><label class="form-label" for="eventStatus">Status</label>
          <select class="form-select" id="eventStatus"><option value="upcoming">Coming up</option><option value="ongoing">Happening now</option><option value="past">Finished</option><option value="cancelled">Cancelled</option></select></div>
        <div class="col-12"><label class="form-label" for="eventLocation">Where</label><input type="text" class="form-control" id="eventLocation" placeholder="School field"></div>
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-label mb-0" for="eventDescription">What families should know</label>
            <button type="button" class="btn btn-link btn-sm p-0" id="eventAiDraftBtn"><i class="bi bi-stars me-1"></i>Draft with AI</button>
          </div>
          <textarea class="form-control" id="eventDescription" rows="4"></textarea>
          <div id="eventAiDraftHost"></div>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-web-quiet" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-web" data-action="save-event">Save event</button></div>
  </div></div>
</div>

<!-- Download upload / edit -->
<div class="modal fade kw-web-modal" id="wsDownloadModal" tabindex="-1" aria-labelledby="wsDownloadModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title fw-bold" id="wsDownloadModalTitle">Upload a document</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" id="dlEditId">
      <div class="web-drop" data-drop="download" tabindex="0" role="button" aria-label="Choose a document from this device">
        <i class="bi bi-file-earmark-arrow-up"></i>
        <strong id="dlDropLabel">Drop a document here or choose one from this device</strong>
        <small>PDF, Word, Excel or PowerPoint, up to 25 MB</small>
      </div>
      <input type="file" id="dlFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" hidden>
      <div class="row g-3 mt-1">
        <div class="col-md-8"><label class="form-label" for="dlTitle">Title families will see</label><input type="text" class="form-control" id="dlTitle" maxlength="200" placeholder="Term 1 book list"></div>
        <div class="col-md-4"><label class="form-label" for="dlCategory">Group</label><input type="text" class="form-control" id="dlCategory" list="dlCategoryOptions" maxlength="50" value="General"><datalist id="dlCategoryOptions"></datalist></div>
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-label mb-0" for="dlDesc">Short description</label>
            <button type="button" class="btn btn-link btn-sm p-0" id="dlDescAiBtn"><i class="bi bi-stars me-1"></i>Suggest</button>
          </div>
          <input type="text" class="form-control" id="dlDesc" maxlength="400">
          <div id="dlDescAiHost"></div>
        </div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="dlActive" checked><label class="form-check-label" for="dlActive">Show on the downloads page</label></div></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-web-quiet" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-web" id="dlSaveBtn" data-action="save-download">Save document</button></div>
  </div></div>
</div>

<!-- Vacancy composer -->
<div class="modal fade kw-web-modal" id="wsJobModal" tabindex="-1" aria-labelledby="wsJobModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title fw-bold" id="wsJobModalTitle">Post a vacancy</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" id="jobEditId">
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label" for="jobTitle">Job title</label><input type="text" class="form-control" id="jobTitle" placeholder="Class teacher, Grade 5"></div>
        <div class="col-md-4"><label class="form-label" for="jobDepartment">Department</label><select class="form-select" id="jobDepartment"></select></div>
        <div class="col-md-4"><label class="form-label" for="jobType">Terms</label><select class="form-select" id="jobType"><option>Full-Time</option><option>Part-Time</option><option>Contract</option></select></div>
        <div class="col-md-4"><label class="form-label" for="jobDeadline">Apply by</label><input type="date" class="form-control" id="jobDeadline"></div>
        <div class="col-md-4"><label class="form-label" for="jobStatus">Status</label><select class="form-select" id="jobStatus"><option value="open">Open for applications</option><option value="closed">Closed</option><option value="filled">Filled</option></select></div>
        <div class="col-12"><label class="form-label" for="jobLocation">Location</label><input type="text" class="form-control" id="jobLocation" value="Londiani, Kenya"></div>
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-label mb-0" for="jobDescription">About the role</label>
            <button type="button" class="btn btn-link btn-sm p-0" id="jobAiDraftBtn"><i class="bi bi-stars me-1"></i>Draft all three with AI</button>
          </div>
          <textarea class="form-control" id="jobDescription" rows="4"></textarea>
          <div id="jobAiDraftHost"></div>
        </div>
        <div class="col-md-6"><label class="form-label" for="jobRequirements">Requirements</label><textarea class="form-control" id="jobRequirements" rows="5" placeholder="One per line&#10;P1 or B.Ed (Primary)&#10;TSC registration"></textarea></div>
        <div class="col-md-6"><label class="form-label" for="jobResponsibilities">Responsibilities</label><textarea class="form-control" id="jobResponsibilities" rows="5" placeholder="One per line&#10;Teach CBC lessons&#10;Keep class registers"></textarea></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-web-quiet" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-web" data-action="save-job">Post vacancy</button></div>
  </div></div>
</div>

<!-- Site preview, inside the workspace -->
<div class="modal fade kw-web-modal" id="wsPreviewModal" tabindex="-1" aria-labelledby="wsPreviewTitle" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen"><div class="modal-content" style="border-radius:0">
    <div class="modal-header">
      <div class="web-preview-bar flex-grow-1">
        <h5 class="modal-title fw-bold me-2" id="wsPreviewTitle">Preview</h5>
        <select class="form-select form-select-sm" id="previewPage" style="max-width:200px" aria-label="Page to preview">
          <option value="">Home</option><option value="news">News</option><option value="events">Events</option>
          <option value="gallery">Gallery</option><option value="downloads">Downloads</option><option value="careers">Careers</option>
          <option value="admissions">Admissions</option><option value="about">About</option><option value="contact">Contact</option>
        </select>
        <div class="web-seg" role="group" aria-label="Screen size" id="previewDevice">
          <button type="button" data-device="desktop" aria-pressed="true"><i class="bi bi-display me-1"></i>Computer</button>
          <button type="button" data-device="phone" aria-pressed="false"><i class="bi bi-phone me-1"></i>Phone</button>
        </div>
        <button type="button" class="btn-icon" data-action="preview-reload" title="Reload" aria-label="Reload the preview"><i class="bi bi-arrow-clockwise"></i></button>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close the preview"></button>
    </div>
    <div class="modal-body p-0 d-flex">
      <div class="web-preview-stage" id="previewStage"><iframe id="previewFrame" title="Public website preview" referrerpolicy="same-origin"></iframe></div>
    </div>
  </div></div>
</div>

<!-- Enquiry reply -->
<div class="modal fade kw-web-modal" id="wsReplyModal" tabindex="-1" aria-labelledby="wsReplyTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title fw-bold" id="wsReplyTitle">Reply to enquiry</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="replyEditId">
      <div class="web-box mb-3">
        <div class="small text-muted" id="replyContext"></div>
      </div>
      <label class="form-label" for="replyText">Your reply</label>
      <textarea class="form-control" id="replyText" rows="8" placeholder="Write your reply to the family…"></textarea>
      <div class="d-flex justify-content-between align-items-center mt-2">
        <button type="button" class="btn btn-link btn-sm p-0" id="replyAiDraftBtn"><i class="bi bi-stars me-1"></i>Draft a reply with AI</button>
        <span class="small text-muted">Queued for email delivery when you send.</span>
      </div>
      <div id="replyAiDraftHost"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-web-quiet" data-bs-dismiss="modal">Cancel</button>
      <button type="button" class="btn btn-web" id="replySendBtn" data-action="send-reply">Send reply</button>
    </div>
  </div></div>
</div>

<!-- Image viewer -->
<div class="modal fade" id="wsImgViewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content bg-transparent border-0">
    <div class="modal-body p-0 text-center">
      <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
      <img id="wsImgViewSrc" class="img-fluid rounded-4 shadow-lg" style="max-height:80vh" alt="">
    </div>
  </div></div>
</div>

<!-- Leadership member (kept) -->
<div class="modal fade kw-web-modal" id="wsLeadershipModal" tabindex="-1" aria-labelledby="wsLeadershipModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title fw-bold" id="wsLeadershipModalTitle">Add member</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="leadEditId">
      <input type="hidden" id="leadCategoryId">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label" for="leadPosition">Position</label>
          <select id="leadPosition" class="form-select"><option value="">Loading…</option></select>
        </div>
        <div class="col-12">
          <span class="form-label d-block">Person</span>
          <div class="d-flex gap-3 mb-2">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="leadPersonMode" id="leadPersonExisting" value="existing" checked data-kw-onchange="wsLeadershipPersonMode('existing')">
              <label class="form-check-label" for="leadPersonExisting">Someone already in the system</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="leadPersonMode" id="leadPersonNew" value="new" data-kw-onchange="wsLeadershipPersonMode('new')">
              <label class="form-check-label" for="leadPersonNew">Someone new</label>
            </div>
          </div>
          <div id="leadPersonSearchWrap">
            <input type="text" id="leadPersonSearch" class="form-control form-control-sm" placeholder="Search by name" autocomplete="off" data-kw-oninput="wsLeadershipPersonSearch(this.value)">
            <input type="hidden" id="leadPersonId">
            <div id="leadPersonSearchResults" class="list-group mt-1" style="max-height:180px;overflow-y:auto;display:none"></div>
            <div id="leadPersonSelected" class="d-none mt-1"><span class="badge bg-success fs-6" id="leadPersonSelectedName"></span></div>
          </div>
          <div id="leadPersonNewWrap" class="d-none">
            <div class="row g-2">
              <div class="col-md-6"><label class="form-label" for="leadFirstName">First name</label><input type="text" id="leadFirstName" class="form-control form-control-sm"></div>
              <div class="col-md-6"><label class="form-label" for="leadLastName">Last name</label><input type="text" id="leadLastName" class="form-control form-control-sm"></div>
            </div>
          </div>
        </div>
        <div class="col-12"><label class="form-label" for="leadBio">Short biography</label><textarea id="leadBio" rows="3" class="form-control"></textarea></div>
        <div class="col-md-8"><label class="form-label" for="leadPhotoUrl">Photo link</label><input type="url" id="leadPhotoUrl" class="form-control form-control-sm" placeholder="https://…"></div>
        <div class="col-md-4"><label class="form-label" for="leadDisplayOrder">Order</label><input type="number" id="leadDisplayOrder" class="form-control form-control-sm" value="0"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-web-quiet" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-web" id="leadSubmitBtn" data-kw-onclick="wsSaveLeadership()">Save</button>
    </div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/manage_website.js'); ?>
