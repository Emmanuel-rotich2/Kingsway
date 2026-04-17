<?php
/**
 * Library Management — router partial
 * Routes: admin/librarian → admin_library.php, others → viewer_library.php
 * JS controller: js/pages/manage_library.js
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via app_layout.php */
?>
<div id="library-loading" class="text-center py-5">
  <div class="spinner-border text-primary" role="status"></div>
  <p class="text-muted mt-2">Loading library...</p>
</div>
<div id="library-content" style="display:none;"></div>

<script src="<?= $appBase ?>/js/pages/manage_library.js?v=<?= time() ?>"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    PageShell.loadRoleTemplate({
      containerId:  'library-content',
      loadingId:    'library-loading',
      templateMap: {
        'library.manage': '<?= $appBase ?>/pages/library/admin_library.php',
        'library.issue':  '<?= $appBase ?>/pages/library/admin_library.php',
        'library.create': '<?= $appBase ?>/pages/library/admin_library.php',
        'library.view':   '<?= $appBase ?>/pages/library/viewer_library.php',
      },
      defaultTemplate: '<?= $appBase ?>/pages/library/viewer_library.php',
      onLoad: function () {
        if (typeof libraryController !== 'undefined') libraryController.init();
      }
    });
  });
</script>
