/* =============================================================================
   About — js/pages/public/about.js
   Renders the About page dynamic sections (mission/vision/motto, core values,
   history timeline, leadership, programs, facilities) from /api/website/content
   and /api/website/settings through window.PublicSite. about.php stays a thin
   HTML shell; this controller fills #about-* containers.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = (v) => PS.escapeHtml(v);

  const about = {
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        const [content, settings] = await Promise.all([
          PS.get('content', {}, { tier: 'reference' }),
          PS.get('settings', {}, { tier: 'reference' }),
        ]);
        const blockMap = PS.keyToMap(content && content.blocks, 'content_key', 'content_value');
        const statMap = PS.keyToMap(settings, 'setting_key', 'setting_value');
        const sections = (content && content.sections) || {};
        this.renderIntro(blockMap, statMap);
        this.renderValues(sections.values);
        this.renderHistory(sections.history);
        this.renderLeadership(sections.leadership);
        this.renderPrograms(sections.programs);
        this.renderFacilities(sections.facilities);
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[about] render failed:', err);
      }
    },

    renderIntro(blocks, m) {
      const set = (id, val) => { const node = document.getElementById(id); if (node) node.textContent = val; };
      set('about-mission', blocks.mission || 'To provide a nurturing, inclusive, and academically rigorous environment that develops confident, virtuous, and globally-competitive learners through the Kenya Competency-Based Curriculum.');
      set('about-vision', blocks.vision || 'To be the most preferred school of excellence in the East African region, producing well-rounded, morally upright, and intellectually superior graduates.');
      set('about-motto', m.school_motto || 'In God We Soar');
      set('about-founded', m.school_founded_year || '2008');
    },

    renderValues(data) {
      const el = document.getElementById('about-values');
      if (!el) return;
      const list = PS.items(data);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((v) =>
        '<div class="col-6">' +
        '<div class="d-flex align-items-start gap-3 p-3 bg-white border rounded-3">' +
        '<i class="bi ' + S(v.icon || 'bi-star-fill') + ' fs-4 flex-shrink-0" style="color:' + S(v.color || '#198754') + '"></i>' +
        '<div>' +
        '<div class="fw-semibold small">' + S(v.name) + '</div>' +
        '<div class="text-muted" style="font-size:.75rem">' + S(v.description) + '</div>' +
        '</div></div></div>'
      ).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderHistory(data) {
      const el = document.getElementById('about-history');
      if (!el) return;
      const list = PS.items(data);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((h) =>
        '<div class="mb-5 position-relative reveal">' +
        '<div class="position-absolute" style="width:14px;height:14px;background:var(--green);border-radius:50%;left:-40px;top:5px;border:3px solid #fff;box-shadow:0 0 0 3px var(--green)"></div>' +
        '<span class="badge bg-success mb-2">' + S(h.year) + '</span>' +
        '<h5 class="fw-bold text-dark mb-1">' + S(h.event_title) + '</h5>' +
        '<p class="text-muted mb-0">' + S(h.description) + '</p>' +
        '</div>'
      ).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderLeadership(data) {
      const el = document.getElementById('about-leadership');
      if (!el) return;
      const levels = Array.isArray(data) ? data : [];
      const section = el.closest('section');
      if (!levels.length) {
        el.innerHTML = '';
        if (section) section.hidden = true;
        return;
      }
      if (section) section.hidden = false;

      const fallbackColors = ['#198754', '#0d6efd', '#6f42c1', '#fd7e14', '#dc3545'];

      function renderCard(member, colorIdx) {
        const nameParts = String(member.name || '').split(' ').filter(Boolean);
        const initials = (nameParts.length > 1
          ? nameParts.slice(0, 2).map(function (p) { return p.charAt(0); }).join('')
          : (nameParts.length ? nameParts[0].slice(0, 2) : 'KA')).toUpperCase();
        const color = fallbackColors[colorIdx % fallbackColors.length];
        const avatar = member.avatar_url
          ? '<img src="' + S(member.avatar_url) + '" alt="' + S(member.name) + '" class="leadership-avatar-img" loading="lazy">'
          : '<span class="leadership-avatar-initials" style="background:linear-gradient(135deg,' + color + ',var(--green-dark))">' + S(initials) + '</span>';
        return '<div class="leadership-card h-100 reveal" style="--lead-accent:' + color + '">' +
          '<div class="leadership-avatar">' + avatar + '</div>' +
          '<h5 class="leadership-name">' + S(member.name) + '</h5>' +
          (member.position_name ? '<div class="leadership-role">' + S(member.position_name) + '</div>' : '') +
          (member.bio ? '<p class="leadership-bio">' + S(member.bio) + '</p>' : '') +
          '</div>';
      }

      function renderCarousel(members, carouselId, colorBase) {
        var perSlide = 4;
        var slides = [];
        for (var i = 0; i < members.length; i += perSlide) {
          slides.push(members.slice(i, i + perSlide));
        }
        if (!slides.length) return '<p class="text-muted fst-italic">No members assigned yet.</p>';

        var items = slides.map(function (group, idx) {
          var cols = group.map(function (m, gIdx) {
            return '<div class="col-lg-3 col-md-6 col-6">' + renderCard(m, colorBase + gIdx) + '</div>';
          }).join('');
          return '<div class="carousel-item' + (idx === 0 ? ' active' : '') + '">' +
            '<div class="row g-3 justify-content-center">' + cols + '</div></div>';
        }).join('');

        var indicators = slides.map(function (_, idx) {
          return '<button type="button" data-bs-target="#' + carouselId + '" data-bs-slide-to="' + idx + '"' +
            (idx === 0 ? ' class="active" aria-current="true"' : '') +
            ' aria-label="Slide ' + (idx + 1) + '"></button>';
        }).join('');

        return '<div id="' + carouselId + '" class="carousel slide" data-bs-ride="carousel" data-bs-interval="6000">' +
          '<div class="carousel-indicators mb-4">' + indicators + '</div>' +
          '<div class="carousel-inner">' + items + '</div>' +
          '<button class="carousel-control-prev" type="button" data-bs-target="#' + carouselId + '" data-bs-slide="prev">' +
          '<span class="carousel-control-prev-icon" aria-hidden="true"></span>' +
          '<span class="visually-hidden">Previous</span></button>' +
          '<button class="carousel-control-next" type="button" data-bs-target="#' + carouselId + '" data-bs-slide="next">' +
          '<span class="carousel-control-next-icon" aria-hidden="true"></span>' +
          '<span class="visually-hidden">Next</span></button>' +
          '</div>';
      }

      var colorIdx = 0;
      el.innerHTML = levels.map(function (level) {
        var members = Array.isArray(level.members) ? level.members : [];
        var isTeaching = String(level.level_name || '').toLowerCase() === 'teaching staff';
        var levelName = S(level.level_name || 'Level');
        var baseColor = colorIdx;

        var body;
        if (!members.length) {
          body = '<p class="text-muted fst-italic">No members assigned yet.</p>';
        } else if (isTeaching) {
          body = renderCarousel(members, 'leadership-carousel-' + level.level_id, baseColor);
        } else {
          body = '<div class="row g-4 justify-content-center">' +
            members.map(function (m) {
              return '<div class="col-12 col-sm-6 col-lg-4">' + renderCard(m, baseColor) + '</div>';
            }).join('') +
            '</div>';
        }

        colorIdx += members.length || 1;
        return '<div class="mb-5 reveal">' +
          '<h4 class="fw-bold text-dark mb-4 text-center">' + levelName + '</h4>' +
          body +
          '</div>';
      }).join('');

      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderPrograms(data) {
      const el = document.getElementById('about-programs');
      if (!el) return;
      const list = PS.items(data);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((p) => {
        const color = p.color || '#198754';
        return '<div class="col-lg-4 col-md-6 reveal">' +
          '<div class="program-card h-100">' +
          '<div class="program-icon" style="background:' + S(color) + '22">' +
          '<i class="bi ' + S(p.icon || 'bi-journal-bookmark-fill') + ' fs-2" style="color:' + S(color) + '"></i></div>' +
          '<h5>' + S(p.name) + '</h5>' +
          (p.level_range ? '<div class="tag mb-2" style="background:' + S(color) + '22;color:' + S(color) + '">' + S(p.level_range) + '</div>' : '') +
          '<p>' + S(p.description) + '</p>' +
          '</div></div>';
      }).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderFacilities(data) {
      const el = document.getElementById('about-facilities');
      if (!el) return;
      const list = PS.items(data);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((f) =>
        '<div class="col-lg-4 col-md-6 reveal">' +
        '<div class="d-flex align-items-start gap-3 p-4 bg-white border rounded-3 h-100">' +
        '<div class="bg-success bg-opacity-10 rounded-2 p-2 flex-shrink-0">' +
        '<i class="bi ' + S(f.icon || 'bi-building') + ' text-success fs-4"></i></div>' +
        '<div>' +
        '<div class="fw-bold small mb-1">' + S(f.name) + '</div>' +
        '<div class="text-muted" style="font-size:.82rem">' + S(f.description) + '</div>' +
        '</div></div></div>'
      ).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => about.init());
  } else {
    about.init();
  }
})();
