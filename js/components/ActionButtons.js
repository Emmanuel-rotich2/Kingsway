/**
 * ActionButtons Component - Permission-aware action button helpers
 * Renders buttons/actions based on user permissions and conditions
 */

class ActionButtons {
    /**
     * Create permission-aware button with checks
     * @param {Object} config - Button configuration
     * @returns {string} HTML button or empty string if permission denied
     */
    static createButton(config) {
        const {
            id,
            label,
            icon,
            variant = 'primary',
            size = 'md',
            permission,
            visible = true,
            disabled = false,
            onclick,
            title,
            className = ''
        } = config;

        // Check permission
        if (permission && !AuthContext.hasPermission(permission)) {
            return '';
        }

        // Check visibility condition
        if (typeof visible === 'function') {
            if (!visible()) return '';
        } else if (!visible) {
            return '';
        }

        const sizeClass = size === 'sm' ? 'btn-sm' : size === 'lg' ? 'btn-lg' : '';
        const onclickAttr = onclick ? `onclick="${onclick}"` : '';

        return `
            <button 
                type="button" 
                class="btn btn-${variant} ${sizeClass} ${className}" 
                id="${id}"
                ${disabled ? 'disabled' : ''}
                ${title ? `title="${title}"` : ''}
                data-bs-toggle="tooltip"
                ${onclickAttr}
            >
                ${icon ? `<i class="bi ${icon} me-2"></i>` : ''}
                ${label}
            </button>
        `;
    }

    /**
     * Create a group of action buttons
     * @param {Array} buttons - Array of button configs
     * @param {string} variant - 'group', 'vertical', 'toolbar'
     * @returns {string} HTML
     */
    static createButtonGroup(buttons, variant = 'group') {
        const validButtons = buttons
            .map(btn => ActionButtons.createButton(btn))
            .filter(html => html !== '');

        if (validButtons.length === 0) return '';

        if (variant === 'group') {
            return `
                <div class="btn-group" role="group">
                    ${validButtons.join('')}
                </div>
            `;
        } else if (variant === 'vertical') {
            return `
                <div class="btn-group-vertical w-100" role="group">
                    ${validButtons.join('')}
                </div>
            `;
        } else {
            return `<div class="btn-toolbar">${validButtons.join('')}</div>`;
        }
    }

    /**
     * Create dropdown menu with actions
     * @param {Array} actions - Array of action configs
     * @returns {string} HTML
     */
    static createDropdownMenu(actions, label = 'Actions', variant = 'secondary') {
        const validActions = actions.filter(action => {
            if (action.permission && !AuthContext.hasPermission(action.permission)) {
                return false;
            }
            if (action.visible && !action.visible()) {
                return false;
            }
            return true;
        });

        if (validActions.length === 0) return '';

        const dropdownId = 'dropdown-' + Math.random().toString(36).substr(2, 9);

        return `
            <div class="dropdown">
                <button class="btn btn-${variant} dropdown-toggle" type="button" id="${dropdownId}" data-bs-toggle="dropdown">
                    ${label}
                </button>
                <ul class="dropdown-menu" aria-labelledby="${dropdownId}">
                    ${validActions.map((action, idx) => {
                        if (action.divider) {
                            return '<li><hr class="dropdown-divider"></li>';
                        }
                        return `
                            <li>
                                <a class="dropdown-item" href="#" data-action="${action.id}" onclick="event.preventDefault(); ${action.onclick || 'return false;'}">
                                    ${action.icon ? `<i class="bi ${action.icon} me-2"></i>` : ''}
                                    ${action.label}
                                </a>
                            </li>
                        `;
                    }).join('')}
                </ul>
            </div>
        `;
    }

    /**
     * Create badge with status
     * @param {string} status - Status value
     * @param {Object} statusMap - Mapping of status to badge class
     * @returns {string} HTML badge
     */
    static createStatusBadge(status, statusMap = {}) {
        const badgeClass = statusMap[status] || 'secondary';
        return `<span class="badge bg-${badgeClass}">${status}</span>`;
    }

    /**
     * Create icon button (small, square)
     * @param {Object} config - Button configuration
     * @returns {string} HTML
     */
    static createIconButton(config) {
        const {
            id,
            icon,
            variant = 'info',
            permission,
            visible = true,
            title,
            onclick
        } = config;

        if (permission && !AuthContext.hasPermission(permission)) {
            return '';
        }

        if (typeof visible === 'function') {
            if (!visible()) return '';
        } else if (!visible) {
            return '';
        }

        const onclickAttr = onclick ? `onclick="${onclick}"` : '';

        return `
            <button 
                type="button" 
                class="btn btn-${variant} btn-sm"
                id="${id}"
                title="${title || ''}"
                data-bs-toggle="tooltip"
                ${onclickAttr}
            >
                <i class="bi ${icon}"></i>
            </button>
        `;
    }

    /**
     * Create bulk action selector with checkbox
     * @returns {string} HTML
     */
    static createBulkActionCheckbox(rowId) {
        return `
            <input type="checkbox" class="form-check-input bulk-action-checkbox" value="${rowId}">
        `;
    }

    /**
     * Create action badge for workflow status
     * @param {string} status - Current status
     * @param {Array} allowedActions - Array of allowed next actions
     * @returns {string} HTML
     */
    static createWorkflowActions(status, allowedActions = []) {
        const validActions = allowedActions.filter(action => {
            if (action.permission && !AuthContext.hasPermission(action.permission)) {
                return false;
            }
            if (action.requiresStatus && !action.requiresStatus.includes(status)) {
                return false;
            }
            return true;
        });

        if (validActions.length === 0) return '';

        return `
            <div class="action-badges">
                ${validActions.map(action => `
                    <button class="btn btn-outline-${action.variant || 'primary'} btn-sm" 
                            data-action="${action.id}"
                            data-workflow-action="true">
                        ${action.icon ? `<i class="bi ${action.icon}"></i>` : ''}
                        ${action.label}
                    </button>
                `).join('')}
            </div>
        `;
    }

    /**
     * Create confirmation dialog for dangerous actions
     * @param {string} message - Confirmation message
     * @param {string} action - Action to perform on confirm
     * @param {string} buttonLabel - Button label
     * @returns {Promise<boolean>}
     */
    static async confirm(message, buttonLabel = 'Confirm', isDangerous = false) {
        return new Promise((resolve) => {
            const modalId = 'confirm-modal-' + Math.random().toString(36).substr(2, 9);
            const html = `
                <div class="modal fade" id="${modalId}" tabindex="-1" data-bs-backdrop="static">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Confirm Action</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${message}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-${isDangerous ? 'danger' : 'primary'}" id="${modalId}-confirm">
                                    ${buttonLabel}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', html);
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            
            document.getElementById(`${modalId}-confirm`).addEventListener('click', () => {
                modal.hide();
                resolve(true);
            });

            modal.show();
            
            // Cleanup on hide
            document.getElementById(modalId).addEventListener('hidden.bs.modal', () => {
                document.getElementById(modalId).remove();
                resolve(false);
            });
        });
    }

    /**
     * Create inline editing controls
     * @param {string} fieldName - Field name
     * @param {string} currentValue - Current value
     * @returns {string} HTML
     */
    static createInlineEdit(fieldName, currentValue) {
        const fieldId = 'inline-edit-' + fieldName;
        return `
            <span class="inline-edit-wrapper">
                <span class="inline-edit-display">${currentValue}</span>
                <input type="text" class="form-control form-control-sm d-none" id="${fieldId}" value="${currentValue}">
                <button class="btn btn-link btn-sm inline-edit-btn" data-field="${fieldName}">
                    <i class="bi bi-pencil"></i>
                </button>
            </span>
        `;
    }

    /**
     * Create permission indicator badge
     * @param {string|Array} requiredPermissions - Required permission(s)
     * @returns {string} HTML
     */
    static createPermissionIndicator(requiredPermissions) {
        if (!requiredPermissions) return '';

        const perms = Array.isArray(requiredPermissions) ? requiredPermissions : [requiredPermissions];
        const hasPermission = perms.some(p => AuthContext.hasPermission(p));

        if (hasPermission) {
            return '<span class="badge bg-success me-2" title="You have access to this">✓</span>';
        } else {
            return '<span class="badge bg-danger me-2" title="You don\'t have access to this">✗</span>';
        }
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ActionButtons;
}
