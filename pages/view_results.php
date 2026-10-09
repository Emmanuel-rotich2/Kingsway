<?php
/** Results Studio — the school's governed results workspace.
 *
 * Four intended surfaces over one shared scope:
 *   Formative  — learner × learning-area aggregate + editable sub-strand/assessment grid.
 *   Summative  — wide learner × exam-period × learning-area matrix (totals, average, rank).
 *   Analytics  — pooled formative + summative comparisons, best-of and charts.
 *   Portfolio  — learner e-portfolio cards.
 *
 * A page template only: every value is fetched through window.API by
 * js/pages/view_results.js. SQL never lives here.
 */
$pageTitle = 'Results Studio';
$activeNav = 'academics';
?>
<div class="vr-studio" id="vrStudio">

  <!-- Hero / scope band -->
  <header class="vr-hero">
    <div class="vr-hero-main">
      <span class="vr-eyebrow">Academics &middot; Results</span>
      <h1 class="vr-hero-title">Results Studio</h1>
      <p class="vr-hero-sub">Formative evidence, summative examinations, analytical comparisons and learner portfolios — one governed workspace.</p>
    </div>
    <div class="vr-hero-context" id="vrHeroContext" aria-live="polite"></div>
  </header>

  <!-- Control deck -->
  <section class="vr-deck" id="vrDeck" aria-label="Result filters">
    <div class="vr-deck-grid" id="vrDeckGrid">
      <div class="vr-field">
        <label for="vrYearSelect">Academic year</label>
        <select id="vrYearSelect" class="vr-select"></select>
      </div>
      <div class="vr-field">
        <label for="vrTermSelect">Term</label>
        <select id="vrTermSelect" class="vr-select"></select>
      </div>
      <div class="vr-field">
        <label for="vrClassSelect">Class</label>
        <select id="vrClassSelect" class="vr-select"></select>
      </div>
      <div class="vr-field">
        <label for="vrStreamSelect">Stream</label>
        <select id="vrStreamSelect" class="vr-select"><option value="">All streams</option></select>
      </div>
      <div class="vr-field vr-field-area" data-tab="formative">
        <label for="vrAreaSelect">Learning area</label>
        <select id="vrAreaSelect" class="vr-select"><option value="">All areas</option></select>
      </div>
      <div class="vr-field vr-field-area" data-tab="formative">
        <label for="vrStrandSelect">Strand</label>
        <select id="vrStrandSelect" class="vr-select"><option value="">All strands</option></select>
      </div>
      <div class="vr-field vr-field-area" data-tab="formative">
        <label for="vrSubStrandSelect">Sub-strand</label>
        <select id="vrSubStrandSelect" class="vr-select"><option value="">All sub-strands</option></select>
      </div>
      <div class="vr-field" data-tab="summative">
        <label for="vrCategorySelect">Exam category</label>
        <select id="vrCategorySelect" class="vr-select">
          <option value="">All categories</option>
        </select>
      </div>
      <div class="vr-field" data-tab="summative">
        <label for="vrExamPeriodSelect">Exam period</label>
        <select id="vrExamPeriodSelect" class="vr-select"><option value="">All periods</option></select>
      </div>
      <div class="vr-field vr-field-grow">
        <label for="vrSearchInput">Search</label>
        <input id="vrSearchInput" type="search" class="vr-input" placeholder="Learner, admission no., area or exam" autocomplete="off">
      </div>
    </div>
    <div class="vr-deck-actions">
      <div class="vr-scale" id="vrScaleToggle" role="group" aria-label="Grading scale"></div>
      <button type="button" class="vr-btn vr-btn-ghost" id="vrRefreshBtn" title="Refresh">
        <i class="bi bi-arrow-clockwise"></i><span>Refresh</span>
      </button>
      <button type="button" class="vr-btn vr-btn-ghost" id="vrExportBtn" title="Export current table to CSV">
        <i class="bi bi-filetype-csv"></i><span>CSV</span>
      </button>
      <button type="button" class="vr-btn vr-btn-ghost" id="vrPrintBtn" title="Print / save as PDF">
        <i class="bi bi-printer"></i><span>Print</span>
      </button>
    </div>
    <div class="vr-deck-foot">
      <div id="vrFilterChips" class="vr-chips"></div>
      <small class="vr-freshness" id="vrFreshness"></small>
    </div>
  </section>

  <!-- Tabs -->
  <nav class="vr-tabs" id="vrTabs" role="tablist" aria-label="Result views">
    <button class="vr-tab is-active" data-tab="formative" role="tab" type="button">
      <i class="bi bi-pencil-square"></i><span>Formative</span>
    </button>
    <button class="vr-tab" data-tab="summative" role="tab" type="button">
      <i class="bi bi-journal-check"></i><span>Summative</span>
    </button>
    <button class="vr-tab" data-tab="analytics" role="tab" type="button">
      <i class="bi bi-graph-up-arrow"></i><span>Analytics</span>
    </button>
    <button class="vr-tab" data-tab="portfolio" role="tab" type="button">
      <i class="bi bi-collection"></i><span>Portfolio</span>
    </button>
  </nav>

  <!-- KPI ribbon -->
  <section class="vr-kpis" id="vrKpis" aria-label="Summary"></section>

  <!-- Active surface -->
  <main class="vr-body" id="vrMain">
    <div id="vrView"></div>
  </main>

  <!-- Learner drill-down -->
  <aside class="vr-drawer" id="vrDrawer" aria-hidden="true" aria-label="Learner detail">
    <div class="vr-drawer-head">
      <div>
        <h2 class="vr-drawer-name" id="vrDrawerName"></h2>
        <small class="vr-drawer-sub" id="vrDrawerSub"></small>
      </div>
      <button class="vr-icon-btn" id="vrDrawerClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="vr-drawer-body" id="vrDrawerBody"></div>
  </aside>
  <div class="vr-scrim" id="vrDrawerBackdrop"></div>
</div>

<style>
/* ── Results Studio ─────────────────────────────────────────────────────
   Self-contained surface identity scoped under .vr-studio so it cannot
   leak into the rest of the authenticated shell. */
.vr-studio{
  --vr-forest:#0b3d2e; --vr-forest-2:#0f5132; --vr-moss:#178a50;
  --vr-gold:#c9a227; --vr-gold-soft:#f3bd18;
  --vr-ink:#17251f; --vr-muted:#6f7d76; --vr-line:#e4e7e2;
  --vr-surface:#ffffff; --vr-surface-2:#fbfaf6; --vr-cream:#f8f6f0;
  --vr-red:#b4232b; --vr-blue:#1f5f8b; --vr-amber:#9a6a00;
  --vr-radius:14px; --vr-radius-sm:9px;
  --vr-shadow:0 1px 2px rgba(16,32,26,.04),0 10px 28px -16px rgba(16,32,26,.28);
  --vr-serif:"Iowan Old Style","Palatino Linotype","Book Antiqua",Palatino,Georgia,"Times New Roman",serif;
  --vr-sans:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  color:var(--vr-ink); font-family:var(--vr-sans);
  display:flex; flex-direction:column; gap:14px; padding:2px 2px 28px;
}

/* Hero */
.vr-hero{
  position:relative; overflow:hidden; border-radius:var(--vr-radius);
  display:flex; align-items:flex-end; justify-content:space-between; gap:24px; flex-wrap:wrap;
  padding:22px 24px 20px;
  background:
    radial-gradient(120% 150% at 100% 0%, rgba(243,189,24,.20) 0%, transparent 55%),
    radial-gradient(90% 140% at 0% 100%, rgba(23,138,80,.28) 0%, transparent 60%),
    linear-gradient(135deg,var(--vr-forest) 0%, var(--vr-forest-2) 62%, #0a4634 100%);
  color:#f2f6f1; box-shadow:var(--vr-shadow);
}
.vr-hero::before{content:""; position:absolute; inset:0 0 auto 0; height:3px;
  background:linear-gradient(90deg,var(--vr-gold) 0%,var(--vr-gold-soft) 42%,rgba(243,189,24,0) 100%);}
.vr-hero-main{max-width:62ch;}
.vr-eyebrow{display:inline-block; font-size:.68rem; font-weight:700; letter-spacing:.14em;
  text-transform:uppercase; color:var(--vr-gold-soft); margin-bottom:6px;}
.vr-hero-title{font-family:var(--vr-serif); font-size:clamp(1.6rem,3.2vw,2.15rem);
  font-weight:600; line-height:1.05; margin:0 0 6px; letter-spacing:-.01em;}
.vr-hero-sub{margin:0; font-size:.86rem; line-height:1.45; color:rgba(242,246,241,.78); max-width:58ch;}
.vr-hero-context{display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;}
.vr-ctx-pill{display:flex; flex-direction:column; gap:1px; min-width:104px;
  padding:8px 12px; border-radius:10px; background:rgba(255,255,255,.10);
  border:1px solid rgba(255,255,255,.16); backdrop-filter:blur(2px);}
.vr-ctx-pill b{font-size:.9rem; font-weight:650; line-height:1.15;}
.vr-ctx-pill span{font-size:.62rem; letter-spacing:.08em; text-transform:uppercase; color:rgba(242,246,241,.66);}

/* Control deck */
.vr-deck{position:sticky; top:0; z-index:20; background:var(--vr-surface);
  border:1px solid var(--vr-line); border-radius:var(--vr-radius); box-shadow:var(--vr-shadow);
  padding:12px 14px;}
.vr-deck-grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px;}
.vr-field{display:flex; flex-direction:column; gap:4px; min-width:0;}
.vr-field.vr-field-grow{grid-column:span 2; min-width:180px;}
.vr-field label{font-size:.66rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase;
  color:var(--vr-muted);}
.vr-select,.vr-input{width:100%; font-size:.82rem; color:var(--vr-ink);
  background:var(--vr-surface-2); border:1px solid var(--vr-line); border-radius:var(--vr-radius-sm);
  padding:7px 9px; transition:border-color .15s, box-shadow .15s, background .15s;}
.vr-select{appearance:none; background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236f7d76' stroke-width='2.5'><path d='M6 9l6 6 6-6'/></svg>");
  background-repeat:no-repeat; background-position:right 9px center; padding-right:26px;}
.vr-select:focus,.vr-input:focus{outline:none; border-color:var(--vr-moss);
  box-shadow:0 0 0 3px rgba(23,138,80,.14); background:#fff;}
.vr-deck-actions{display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:12px;}
.vr-scale{display:inline-flex; border:1px solid var(--vr-line); border-radius:var(--vr-radius-sm);
  overflow:hidden; background:var(--vr-surface-2);}
.vr-scale button{border:none; background:none; padding:6px 11px; font-size:.72rem; font-weight:600;
  color:var(--vr-muted); cursor:pointer; border-right:1px solid var(--vr-line);}
.vr-scale button:last-child{border-right:none;}
.vr-scale button.is-active{background:var(--vr-forest); color:#fff;}
.vr-btn{display:inline-flex; align-items:center; gap:6px; font-size:.75rem; font-weight:600;
  border:1px solid var(--vr-line); background:var(--vr-surface); color:var(--vr-ink);
  border-radius:var(--vr-radius-sm); padding:6px 11px; cursor:pointer; transition:all .15s;}
.vr-btn:hover{border-color:var(--vr-moss); color:var(--vr-forest);}
.vr-btn:disabled{opacity:.45; cursor:not-allowed;}
.vr-btn i{font-size:.85rem;}
.vr-deck-actions .vr-scale{margin-right:auto;}
.vr-deck-foot{display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:10px;
  padding-top:10px; border-top:1px dashed var(--vr-line);}
.vr-chips{display:flex; gap:6px; flex-wrap:wrap; flex:1;}
.vr-chip{display:inline-flex; align-items:center; gap:5px; font-size:.7rem; font-weight:600;
  padding:3px 9px; border-radius:999px; background:var(--vr-cream); color:var(--vr-forest-2);
  border:1px solid #e7e2cf;}
.vr-chip button{border:none; background:none; color:inherit; cursor:pointer; padding:0; line-height:1; opacity:.65;}
.vr-chip button:hover{opacity:1;}
.vr-freshness{font-size:.68rem; color:var(--vr-muted);}

/* Tabs */
.vr-tabs{display:flex; gap:4px; border-bottom:2px solid var(--vr-line); overflow-x:auto;}
.vr-tab{position:relative; border:none; background:none; cursor:pointer; white-space:nowrap;
  display:inline-flex; align-items:center; gap:7px; padding:9px 16px 10px; margin-bottom:-2px;
  font-size:.84rem; font-weight:600; color:var(--vr-muted); border-bottom:2px solid transparent;
  transition:color .15s, border-color .15s;}
.vr-tab:hover{color:var(--vr-ink);}
.vr-tab.is-active{color:var(--vr-forest); border-bottom-color:var(--vr-gold);}
.vr-tab i{font-size:.95rem;}

/* KPI ribbon */
.vr-kpis{display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px;}
.vr-kpi{position:relative; background:var(--vr-surface); border:1px solid var(--vr-line);
  border-radius:var(--vr-radius); padding:12px 14px 12px 16px; box-shadow:0 1px 2px rgba(16,32,26,.03);}
.vr-kpi::before{content:""; position:absolute; left:0; top:12px; bottom:12px; width:3px;
  border-radius:3px; background:var(--vr-kpi-accent,var(--vr-moss));}
.vr-kpi-val{font-size:1.5rem; font-weight:700; line-height:1; font-variant-numeric:tabular-nums;
  letter-spacing:-.02em; color:var(--vr-ink);}
.vr-kpi-val small{font-size:.8rem; font-weight:600; color:var(--vr-muted); margin-left:2px;}
.vr-kpi-lab{font-size:.7rem; font-weight:600; color:var(--vr-muted); margin-top:6px;
  text-transform:uppercase; letter-spacing:.05em;}
.vr-kpi-note{font-size:.68rem; color:var(--vr-muted); margin-top:3px;}

/* Body + panels */
.vr-body{min-height:46vh;}
.vr-panel{background:var(--vr-surface); border:1px solid var(--vr-line); border-radius:var(--vr-radius);
  box-shadow:var(--vr-shadow); overflow:hidden;}
.vr-panel + .vr-panel{margin-top:14px;}
.vr-panel-head{display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  padding:12px 14px; border-bottom:1px solid var(--vr-line); background:linear-gradient(180deg,#fff,var(--vr-surface-2));}
.vr-panel-head h3{font-family:var(--vr-serif); font-size:1.02rem; font-weight:600; margin:0; color:var(--vr-forest);}
.vr-panel-head .vr-panel-sub{font-size:.72rem; color:var(--vr-muted);}
.vr-panel-head .vr-panel-tools{margin-left:auto; display:flex; gap:7px; align-items:center; flex-wrap:wrap;}
.vr-panel-body{padding:0;}

/* Matrix */
.vr-scroll{overflow:auto; max-height:70vh; position:relative;}
.vr-matrix{border-collapse:separate; border-spacing:0; width:100%; font-size:.79rem; margin:0;}
.vr-matrix th,.vr-matrix td{padding:6px 9px; white-space:nowrap; border-bottom:1px solid var(--vr-line);}
.vr-matrix thead th{position:sticky; z-index:5; background:var(--vr-surface-2); color:var(--vr-muted);
  font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
  border-bottom:2px solid var(--vr-line);}
.vr-matrix thead tr:first-child th{top:0;}
.vr-matrix thead th.vr-group{top:0; background:var(--vr-forest); color:#eaf3ec; border-bottom-color:var(--vr-forest);}
.vr-matrix thead tr.vr-sub th{top:31px; background:#f1f5f1;}
.vr-matrix tbody td,.vr-matrix tbody th{background:var(--vr-surface);}
.vr-matrix tbody tr:nth-child(even) td,.vr-matrix tbody tr:nth-child(even) th{background:var(--vr-surface-2);}
.vr-matrix tbody tr:hover td,.vr-matrix tbody tr:hover th{background:#eef6f1;}
.vr-matrix .vr-sticky{position:sticky; z-index:4;}
.vr-matrix thead .vr-sticky{z-index:7;}
.vr-sticky-1{left:112px; min-width:200px;}
.vr-matrix .vr-sticky-1{border-right:2px solid var(--vr-line);}
.vr-sticky-2{left:0; min-width:112px; border-right:1px solid var(--vr-line);}
.vr-num{text-align:center; font-variant-numeric:tabular-nums;}
.vr-total{font-weight:700; color:var(--vr-forest);}
.vr-rank{display:inline-flex; align-items:center; justify-content:center; min-width:24px; height:22px;
  padding:0 6px; border-radius:6px; font-weight:700; font-size:.72rem; background:#eef2ee; color:var(--vr-muted);}
.vr-rank.is-top{background:linear-gradient(135deg,var(--vr-gold),var(--vr-gold-soft)); color:#3d2f00;}

/* Grade badges */
.vr-grade{display:inline-flex; align-items:center; justify-content:center; min-width:38px;
  font-size:.7rem; font-weight:700; padding:2px 7px; border-radius:6px; font-variant-numeric:tabular-nums;}
.vr-grade-EE{background:#dcf3e6; color:#0f6b3f;}
.vr-grade-ME{background:#dbeafe; color:#1e40af;}
.vr-grade-AE{background:#fef0cf; color:#8a5a00;}
.vr-grade-BE{background:#fbe1e1; color:#9b1c22;}

/* Editable cells */
.vr-cell-input{width:58px; text-align:center; font-size:.78rem; font-variant-numeric:tabular-nums;
  border:1px solid transparent; border-radius:6px; padding:3px 4px; background:transparent; color:var(--vr-ink);}
.vr-cell-input:hover{border-color:var(--vr-line); background:#fff;}
.vr-cell-input:focus{outline:none; border-color:var(--vr-moss); background:#fff;
  box-shadow:0 0 0 3px rgba(23,138,80,.16);}
.vr-cell-input.is-saving{background:#fff7dc; border-color:var(--vr-gold);}
.vr-cell-input.is-saved{animation:vrflash .8s ease;}
.vr-cell-input.is-error{background:#fdecec; border-color:var(--vr-red);}
@keyframes vrflash{0%{background:#d8f3e3;}100%{background:transparent;}}
.vr-cell-ro{color:var(--vr-muted);}

/* Kebab / row actions */
.vr-kebab{position:relative; display:inline-block;}
.vr-kebab-btn{border:1px solid var(--vr-line); background:var(--vr-surface); border-radius:7px;
  width:28px; height:26px; cursor:pointer; color:var(--vr-muted); line-height:1;}
.vr-kebab-btn:hover{border-color:var(--vr-moss); color:var(--vr-forest);}
.vr-kebab-menu{position:absolute; right:0; top:30px; z-index:30; min-width:190px;
  background:#fff; border:1px solid var(--vr-line); border-radius:10px; box-shadow:var(--vr-shadow);
  padding:5px; display:none;}
.vr-kebab-menu.is-open{display:block;}
.vr-kebab-menu button{display:flex; align-items:center; gap:8px; width:100%; text-align:left;
  border:none; background:none; padding:7px 9px; border-radius:7px; font-size:.78rem; cursor:pointer; color:var(--vr-ink);}
.vr-kebab-menu button:hover{background:var(--vr-cream);}

/* Analytics */
.vr-grid-2{display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:14px;}
.vr-chart{position:relative; height:260px; padding:8px;}
.vr-chart.vr-chart-sm{height:230px;}
.vr-list{list-style:none; margin:0; padding:0;}
.vr-list li{display:flex; align-items:center; gap:10px; padding:8px 14px; border-bottom:1px solid var(--vr-line);}
.vr-list li:last-child{border-bottom:none;}
.vr-list .vr-list-rank{width:22px; height:22px; border-radius:6px; display:inline-flex;
  align-items:center; justify-content:center; font-size:.7rem; font-weight:700; background:#eef2ee; color:var(--vr-muted);}
.vr-list .vr-list-main{flex:1; min-width:0;}
.vr-list .vr-list-main b{display:block; font-size:.82rem; font-weight:600;}
.vr-list .vr-list-main span{font-size:.7rem; color:var(--vr-muted);}
.vr-list .vr-list-val{font-weight:700; font-variant-numeric:tabular-nums; color:var(--vr-forest);}
.vr-meter{height:7px; border-radius:999px; background:#edf0ec; overflow:hidden; margin-top:4px;}
.vr-meter i{display:block; height:100%; border-radius:999px; background:linear-gradient(90deg,var(--vr-moss),var(--vr-gold));}

/* Portfolio cards */
.vr-cards{display:grid; grid-template-columns:repeat(auto-fill,minmax(268px,1fr)); gap:14px;}
.vr-card{position:relative; background:var(--vr-surface); border:1px solid var(--vr-line);
  border-radius:var(--vr-radius); box-shadow:var(--vr-shadow); overflow:hidden;
  display:flex; flex-direction:column; transition:transform .15s, box-shadow .15s;}
.vr-card:hover{transform:translateY(-2px); box-shadow:0 12px 32px -16px rgba(16,32,26,.4);}
.vr-card::before{content:""; height:4px; background:linear-gradient(90deg,var(--vr-forest),var(--vr-moss) 55%,var(--vr-gold));}
.vr-card-head{display:flex; align-items:center; gap:11px; padding:14px 14px 8px;}
.vr-avatar{width:42px; height:42px; border-radius:12px; flex:0 0 42px; display:flex; align-items:center;
  justify-content:center; font-family:var(--vr-serif); font-size:1.05rem; font-weight:600;
  color:#fff; background:linear-gradient(135deg,var(--vr-forest),var(--vr-moss));}
.vr-card-head b{display:block; font-size:.9rem; font-weight:650;}
.vr-card-head span{font-size:.72rem; color:var(--vr-muted);}
.vr-card-body{padding:4px 14px 12px; display:flex; flex-direction:column; gap:10px;}
.vr-badge-row{display:flex; gap:6px; flex-wrap:wrap;}
.vr-tag{font-size:.66rem; font-weight:600; padding:2px 8px; border-radius:999px;
  background:var(--vr-cream); color:var(--vr-forest-2); border:1px solid #e7e2cf;}
.vr-tag.is-ok{background:#dcf3e6; color:#0f6b3f; border-color:#bfe6cf;}
.vr-tag.is-warn{background:#fef0cf; color:#8a5a00; border-color:#f0dca6;}
.vr-tag.is-none{background:#f1f1ee; color:var(--vr-muted); border-color:#e2e2dd;}
.vr-card-stats{display:grid; grid-template-columns:repeat(3,1fr); gap:8px;}
.vr-card-stat{text-align:center; padding:7px 4px; background:var(--vr-surface-2);
  border:1px solid var(--vr-line); border-radius:9px;}
.vr-card-stat b{display:block; font-size:1rem; font-weight:700; color:var(--vr-forest); font-variant-numeric:tabular-nums;}
.vr-card-stat span{font-size:.62rem; color:var(--vr-muted); text-transform:uppercase; letter-spacing:.04em;}
.vr-card-foot{margin-top:auto; padding:10px 14px; border-top:1px solid var(--vr-line);
  display:flex; align-items:center; gap:8px; background:var(--vr-surface-2);}

/* Empty / loading */
.vr-empty{text-align:center; padding:48px 20px; color:var(--vr-muted);}
.vr-empty i{font-size:2.4rem; color:#c7cfc8; display:block; margin-bottom:10px;}
.vr-empty b{display:block; color:var(--vr-ink); font-size:.95rem; margin-bottom:4px;}
.vr-loading{display:flex; flex-direction:column; align-items:center; gap:10px; padding:52px 20px; color:var(--vr-muted);}
.vr-spinner{width:30px; height:30px; border-radius:50%; border:3px solid var(--vr-line);
  border-top-color:var(--vr-moss); animation:vrspin .8s linear infinite;}
@keyframes vrspin{to{transform:rotate(360deg);}}
.vr-alert{margin:0; border-radius:var(--vr-radius);}

/* Drawer */
.vr-drawer{position:fixed; top:0; right:0; height:100vh; width:min(440px,94vw); z-index:1080;
  background:var(--vr-surface); box-shadow:-18px 0 44px -20px rgba(16,32,26,.5);
  transform:translateX(102%); transition:transform .26s ease; display:flex; flex-direction:column;}
.vr-drawer.is-open{transform:translateX(0);}
.vr-drawer-head{display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
  padding:16px 18px; border-bottom:1px solid var(--vr-line);
  background:linear-gradient(135deg,var(--vr-forest),var(--vr-forest-2)); color:#f2f6f1;}
.vr-drawer-name{font-family:var(--vr-serif); font-size:1.1rem; font-weight:600; margin:0;}
.vr-drawer-sub{font-size:.72rem; color:rgba(242,246,241,.72);}
.vr-drawer-body{flex:1; overflow-y:auto; padding:16px 18px;}
.vr-icon-btn{border:none; background:rgba(255,255,255,.12); color:#fff; width:30px; height:30px;
  border-radius:8px; cursor:pointer;}
.vr-icon-btn:hover{background:rgba(255,255,255,.22);}
.vr-scrim{position:fixed; inset:0; background:rgba(10,24,18,.42); z-index:1079; opacity:0;
  pointer-events:none; transition:opacity .2s;}
.vr-scrim.is-open{opacity:1; pointer-events:auto;}

/* Print */
@media print{
  @page{size:A4 landscape; margin:10mm;}
  .vr-deck,.vr-tabs,.vr-hero-context,.vr-deck-actions,.vr-kebab,.vr-panel-tools,.vr-scrim,
  .vr-drawer,.vr-no-print{display:none !important;}
  .vr-studio{padding:0; gap:8px;}
  .vr-hero{box-shadow:none; border-radius:0;}
  .vr-panel{box-shadow:none; border:none;}
  .vr-scroll{max-height:none; overflow:visible;}
  .vr-matrix .vr-sticky,.vr-matrix thead th{position:static;}
  .vr-cell-input{border:none; background:transparent;}
  .vr-kpis{grid-template-columns:repeat(4,1fr);}
}
@media (max-width:820px){
  .vr-sticky-2{display:none;}
  .vr-sticky-1{left:0;}
  .vr-field.vr-field-grow{grid-column:span 1;}
}
</style>

<?php asset_script($appBase, 'js/utils/results_pivot.js'); ?>
<?php asset_script($appBase, 'js/pages/view_results.js'); ?>
