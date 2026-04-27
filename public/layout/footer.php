<!-- ═══ FOOTER ════════════════════════════════════════════════════════════════ -->
<footer class="site-footer">
  <div class="container">
    <div class="row g-5">

      <!-- Brand col -->
      <div class="col-lg-4 col-md-6">
        <div class="footer-brand d-flex align-items-center gap-2 mb-3">
          <img src="<?= $appBase ?>/images/kings logo.png" alt="Kingsway Logo" onerror="this.style.display='none'">
          <div>
            <div class="footer-brand-name">Kingsway Prep School</div>
            <div class="footer-tagline">In God We Soar</div>
          </div>
        </div>
        <p class="footer-desc">
          Nurturing excellence, character and leadership in every child.
          CBC-aligned curriculum, modern facilities, and a caring community —
          right in the heart of Londiani, Kenya.
        </p>
        <div class="social-links">
          <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
          <a href="#" aria-label="Twitter/X"><i class="bi bi-twitter-x"></i></a>
          <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
          <a href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
          <a href="#" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="col-lg-2 col-md-6 col-6">
        <h6 class="footer-title">Quick Links</h6>
        <ul class="footer-links">
          <li><a href="<?= $appBase ?>/index.php"><i class="bi bi-chevron-right"></i>Home</a></li>
          <li><a href="<?= $appBase ?>/about.php"><i class="bi bi-chevron-right"></i>About Us</a></li>
          <li><a href="<?= $appBase ?>/admissions.php"><i class="bi bi-chevron-right"></i>Admissions</a></li>
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
      <p>&copy; <?= date('Y') ?> Kingsway Preparatory School. All rights reserved.</p>
      <p>
        <a href="<?= $appBase ?>/index.php">Privacy Policy</a> &nbsp;·&nbsp;
        <a href="<?= $appBase ?>/index.php">Terms of Use</a> &nbsp;·&nbsp;
        <a href="<?= $appBase ?>/home.php">Staff Portal</a>
      </p>
    </div>
  </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  window.APP_BASE = <?= json_encode($appBase) ?>;
</script>
<script src="<?= $appBase ?>/js/api.js?v=<?= filemtime(__DIR__.'/../../js/api.js') ?>"></script>
<script src="<?= $appBase ?>/public/js/public.js?v=<?= time() ?>"></script>
</body>
</html>
