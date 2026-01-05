/**
 * Admission Notifications Widget
 *
 * A reusable component that displays pending admission tasks based on user role.
 * Can be included in any dashboard controller.
 *
 * Usage:
 *   // In any dashboard controller init():
 *   await AdmissionNotificationsWidget.load('admissionNotificationsContainer');
 *
 * HTML Required:
 *   <div id="admissionNotificationsContainer"></div>
 */

const AdmissionNotificationsWidget = {
  /**
   * Load and render admission notifications
   * @param {string} containerId - ID of the container element
   * @param {object} options - Configuration options
   */
  async load(containerId, options = {}) {
    const container = document.getElementById(containerId);
    if (!container) {
      console.warn(
        "[AdmissionNotificationsWidget] Container not found:",
        containerId
      );
      return;
    }

    try {
      container.innerHTML = this.renderLoading();

      const response = await API.admission.getNotifications();

      if (response.success && response.data) {
        container.innerHTML = this.render(response.data, options);
      } else {
        container.innerHTML = this.renderEmpty();
      }
    } catch (error) {
      console.error(
        "[AdmissionNotificationsWidget] Error loading notifications:",
        error
      );
      container.innerHTML = this.renderError();
    }
  },

  /**
   * Render the notifications widget
   */
  render(data, options = {}) {
    const { pending_tasks, total_count } = data;
    const { compact = false, title = "Admission Tasks" } = options;

    if (!pending_tasks || pending_tasks.length === 0) {
      return this.renderEmpty();
    }

    const tasksHtml = pending_tasks
      .map(
        (task) => `
            <a href="${
              task.link
            }" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${
          compact ? "py-2" : ""
        }">
                <div>
                    <i class="bi ${task.icon} text-${task.color} me-2"></i>
                    ${task.label}
                </div>
                <span class="badge bg-${task.color} rounded-pill">${
          task.count
        }</span>
            </a>
        `
      )
      .join("");

    return `
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-person-plus me-2 text-primary"></i>
                        ${title}
                    </h6>
                    ${
                      total_count > 0
                        ? `<span class="badge bg-danger">${total_count} pending</span>`
                        : ""
                    }
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        ${tasksHtml}
                    </div>
                </div>
                <div class="card-footer bg-transparent text-center">
                    <a href="/pages/manage_admissions.php" class="text-primary text-decoration-none small">
                        View All Admissions <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        `;
  },

  /**
   * Render loading state
   */
  renderLoading() {
    return `
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="small text-muted mb-0 mt-2">Loading admission tasks...</p>
                </div>
            </div>
        `;
  },

  /**
   * Render empty state
   */
  renderEmpty() {
    return `
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0">
                    <h6 class="mb-0">
                        <i class="bi bi-person-plus me-2 text-primary"></i>
                        Admission Tasks
                    </h6>
                </div>
                <div class="card-body text-center py-4">
                    <i class="bi bi-check-circle display-6 text-success"></i>
                    <p class="small text-muted mb-0 mt-2">No pending admission tasks</p>
                </div>
            </div>
        `;
  },

  /**
   * Render error state
   */
  renderError() {
    return `
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0">
                    <h6 class="mb-0">
                        <i class="bi bi-person-plus me-2 text-primary"></i>
                        Admission Tasks
                    </h6>
                </div>
                <div class="card-body text-center py-4">
                    <i class="bi bi-exclamation-triangle display-6 text-warning"></i>
                    <p class="small text-muted mb-0 mt-2">Could not load admission tasks</p>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="AdmissionNotificationsWidget.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Retry
                    </button>
                </div>
            </div>
        `;
  },

  /**
   * Reload the widget (call load again with last container)
   */
  _lastContainerId: null,
  _lastOptions: {},

  reload() {
    if (this._lastContainerId) {
      this.load(this._lastContainerId, this._lastOptions);
    }
  },

  /**
   * Get summary badge HTML for navbar/header notifications
   * @returns {Promise<string>} HTML for a notification badge
   */
  async getBadgeHtml() {
    try {
      const response = await API.admission.getNotifications();

      if (response.success && response.data && response.data.total_count > 0) {
        return `<span class="badge bg-danger rounded-pill">${response.data.total_count}</span>`;
      }
      return "";
    } catch (error) {
      console.error(
        "[AdmissionNotificationsWidget] Error fetching badge:",
        error
      );
      return "";
    }
  },

  /**
   * Update navbar notification badge
   * @param {string} badgeElementId - ID of the badge element to update
   */
  async updateNavbarBadge(badgeElementId = "admissionNotificationBadge") {
    const badge = document.getElementById(badgeElementId);
    if (!badge) return;

    try {
      const response = await API.admission.getNotifications();

      if (response.success && response.data && response.data.total_count > 0) {
        badge.textContent = response.data.total_count;
        badge.style.display = "inline";
      } else {
        badge.style.display = "none";
      }
    } catch (error) {
      console.error(
        "[AdmissionNotificationsWidget] Error updating badge:",
        error
      );
    }
  },
};

// Export for global use
window.AdmissionNotificationsWidget = AdmissionNotificationsWidget;
