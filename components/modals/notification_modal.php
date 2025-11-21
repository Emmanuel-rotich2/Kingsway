<!-- Unified Notification Modal (Bootstrap 5) -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-0 border-0">
      <div class="modal-body d-flex align-items-center gap-3 py-4 px-4">
        <span class="notification-icon flex-shrink-0 fs-2">
          <i class="bi bi-info-circle"></i>
        </span>
        <div class="notification-message fs-5 flex-grow-1"></div>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
        </div>
    </div>
</div>

<style>
  /* Notification Modal Styles */
  #notificationModal .modal-content.notification-success { background: #e6f9ed; border-left: 6px solid #28a745; }
  #notificationModal .modal-content.notification-error { background: #fdeaea; border-left: 6px solid #dc3545; }
  #notificationModal .modal-content.notification-warning { background: #fffbe6; border-left: 6px solid #ffc107; }
  #notificationModal .modal-content.notification-info { background: #eaf4fd; border-left: 6px solid #0d6efd; }
  #notificationModal .notification-icon .bi { vertical-align: middle; }
</style> 