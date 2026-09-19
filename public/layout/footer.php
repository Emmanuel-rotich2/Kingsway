<!-- ═══ FOOTER ════════════════════════════════════════════════════════════════ -->
<footer class="site-footer">
  <div class="container">
    <div class="row g-5">

      <!-- Brand col -->
      <div class="col-lg-4 col-md-6">
        <div class="footer-brand d-flex align-items-center gap-2 mb-3">
          <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" class="school-logo" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
          <div>
            <div class="footer-brand-name">Kingsway Preparatory School</div>
            <div class="footer-tagline">In God We Soar</div>
          </div>
        </div>
        <p class="footer-desc">
          Nurturing excellence, character and leadership in every child.
          CBC-aligned curriculum, modern facilities, and a caring community —
          right in the heart of Londiani, Kenya.
        </p>
        <div class="mt-3">
          <a href="<?= $appBase ?>/admissions.php#apply" class="btn-kw-primary">
            <i class="bi bi-person-plus me-2"></i>Apply for Admission
          </a>
        </div>
        <div class="social-links mt-4">
          <a href="https://www.facebook.com/profile.php/?id=100066942647437" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
          <a href="https://www.instagram.com/p/DE7jLBkIEjS/?utm_source=ig_web_copy_link&amp;igsi=NTc4MTIwNjQ2YQ==" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="col-lg-2 col-md-6 col-6">
        <h6 class="footer-title">Quick Links</h6>
        <ul class="footer-links">
          <li><a href="<?= $appBase ?>/index.php"><i class="bi bi-chevron-right"></i>Home</a></li>
          <li><a href="<?= $appBase ?>/about.php"><i class="bi bi-chevron-right"></i>About Us</a></li>
          <li><a href="<?= $appBase ?>/admissions.php"><i class="bi bi-chevron-right"></i>Admissions</a></li>
          <li><a href="<?= $appBase ?>/admissions.php#apply"><i class="bi bi-chevron-right"></i>Apply for Admission</a></li>
          <li><a href="<?= $appBase ?>/news.php"><i class="bi bi-chevron-right"></i>News &amp; Updates</a></li>
          <li><a href="<?= $appBase ?>/events.php"><i class="bi bi-chevron-right"></i>Events</a></li>
          <li><a href="<?= $appBase ?>/careers.php"><i class="bi bi-chevron-right"></i>Careers</a></li>
          <li><a href="<?= $appBase ?>/downloads.php"><i class="bi bi-chevron-right"></i>Downloads</a></li>
          <li><a href="<?= $appBase ?>/contact.php"><i class="bi bi-chevron-right"></i>Contact Us</a></li>
        </ul>
      </div>

      <!-- Programs -->
      <div class="col-lg-2 col-md-6 col-6">
        <h6 class="footer-title">Programs</h6>
        <ul class="footer-links">
          <li><a href="<?= $appBase ?>/about.php#programs"><i class="bi bi-chevron-right"></i>Pre-Primary (ECD)</a></li>
          <li><a href="<?= $appBase ?>/about.php#programs"><i class="bi bi-chevron-right"></i>Lower Primary</a></li>
          <li><a href="<?= $appBase ?>/about.php#programs"><i class="bi bi-chevron-right"></i>Upper Primary</a></li>
          <li><a href="<?= $appBase ?>/about.php#programs"><i class="bi bi-chevron-right"></i>Junior Secondary</a></li>
          <li><a href="<?= $appBase ?>/about.php#programs"><i class="bi bi-chevron-right"></i>STEM &amp; ICT</a></li>
          <li><a href="<?= $appBase ?>/about.php#programs"><i class="bi bi-chevron-right"></i>Sports &amp; Arts</a></li>
          <li><a href="<?= $appBase ?>/about.php#programs"><i class="bi bi-chevron-right"></i>Boarding</a></li>
        </ul>
      </div>

      <!-- Contact -->
      <div class="col-lg-4 col-md-6">
        <h6 class="footer-title">Contact Us</h6>
        <div class="footer-contact-item">
          <i class="bi bi-geo-alt-fill"></i>
          <span>P.O BOX 203-20203, Londiani, Kericho County, Kenya</span>
        </div>
        <div class="footer-contact-item">
          <i class="bi bi-telephone-fill"></i>
          <span>+254 720 113 030 / +254 720 113 031</span>
        </div>
        <div class="footer-contact-item">
          <i class="bi bi-envelope-fill"></i>
          <span>info@kingswaypreparatoryschool.sc.ke</span>
        </div>
        <div class="footer-contact-item">
          <i class="bi bi-clock-fill"></i>
          <span>Mon – Fri: 7:30 AM – 5:00 PM</span>
        </div>
        <div class="mt-3">
          <a href="<?= $appBase ?>/contact.php" class="btn-kw-outline" style="padding:8px 20px;font-size:.82rem;">
            <i class="bi bi-envelope me-2"></i>Send a Message
          </a>
        </div>
      </div>

    </div>
  </div>

  <div class="footer-bottom mt-5">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
      <p>&copy; <?= date('Y') ?> Kingsway Preparatory School. All rights reserved. Maintained by <a href="https://www.angisoft.co.ke" target="_blank" rel="noopener">AngiSoft Technologies</a>.</p>
      <p>
        <a href="<?= $appBase ?>/index.php">Privacy Policy</a> &nbsp;·&nbsp;
        <a href="<?= $appBase ?>/index.php">Terms of Use</a> &nbsp;·&nbsp;
        <a href="<?= $appBase ?>/home.php">Staff Portal</a>
      </p>
    </div>
  </div>
</footer>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
  window.APP_BASE = <?= json_encode($appBase) ?>;
</script>
<?php asset_script($appBase, 'js/api.js'); ?>
<!-- Public-facing cache layer: reuses the SAME js/core storage stack the admin
     SPA uses (IndexedDB via KingswayDB + DataStore, plus the service worker for
     offline/bfcache). Order matters: kingsway_db.js defines window.KingswayDB,
     data_store.js depends on it, then service_worker_manager.js. -->
<?php asset_script($appBase, 'js/storage/kingsway_db.js'); ?>
<?php asset_script($appBase, 'js/core/data_store.js'); ?>
<?php asset_script($appBase, 'js/core/service_worker_manager.js'); ?>
<!-- Public site REST client: reads /api/website/* through DataStore with the
     guest-scoped cache (memory + IndexedDB + stale-while-revalidate). Loaded
     after data_store.js so window.PublicSite is ready for the page controllers. -->
<?php asset_script($appBase, 'js/core/public_site.js'); ?>
<script>
  // Open the IndexedDB store, then register the service worker. Both are guarded
  // so a missing global degrades gracefully instead of throwing on every page.
  (function () {
    const initSW = () => { if (window.ServiceWorkerManager && window.ServiceWorkerManager.initialize) window.ServiceWorkerManager.initialize().catch(() => {}); };
    if (window.KingswayDB && typeof window.KingswayDB.initialize === 'function') {
      window.KingswayDB.initialize().then(initSW).catch(initSW);
    } else {
      initSW();
    }
  })();
</script>
<?php asset_script($appBase, 'js/public.js'); ?>
<?php asset_script($appBase, 'js/pages/public/ai_faq.js'); ?>
<?php if (!empty($pageScript)): ?>
<!-- Page controller: pages are thin HTML shells; js/pages/public/<name>.js renders
     the dynamic sections through window.PublicSite. Falls back to SSR-only output
     (already printed above) when JS is unavailable. -->
<?php asset_script($appBase, 'js/pages/public/' . $pageScript . '.js'); ?>
<?php endif; ?>
</body>
</html>
