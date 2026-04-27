/**
 * Enhanced RoleBasedUI - Module & Workflow Aware Permission Guards
 *
 * Addition to existing RoleBasedUI.js that provides:
 * - Module-scoped permission checks
 * - Workflow stage guards
 * - Component-level visibility
 * - Permission with context
 *
 * @since 2026-03-29
 */

const EnhancedRoleBasedUI = (() => {
  /**
   * Check if user has permission in specific module context
   */
  const hasModulePermission = (module, action, component = null) => {
    if (!AuthContext || !AuthContext.permissions) {
      return false;
    }

    const permissions = AuthContext.permissions || [];

    // Build permission codes to check (in order of specificity)
    const permissionCodes = [];

    if (component) {
      // Most specific: module_entity_action
      permissionCodes.push(`${module}_${component}_${action}`);
      // Also module_action_component variant
      permissionCodes.push(`${module}_${action}_${component}`);
    }

    // Module and action
    permissionCodes.push(`${module}_${action}`);

    // Module only (for catch-all permissions)
    permissionCodes.push(`${module}_manage`);

    // Check all variants (user or dot notation)
    for (const code of permissionCodes) {
      const underscoreVersion = code.replace(/\./g, '_');
      const dotVersion = code.replace(/_/g, '.');

      if (permissions.includes(underscoreVersion) || permissions.includes(dotVersion)) {
        return true;
      }
    }

    return false;
  };

  /**
   * Check if in specific workflow stage and has permission
   */
  const hasWorkflowPermission = (workflowCode, stageCode, action) => {
    if (!AuthContext || !AuthContext.metadata) {
      return false;
    }

    const metadata = AuthContext.metadata || {};
    const currentWorkflow = metadata.current_workflow;
    const currentStage = metadata.current_stage;

    // Verify we're in the expected workflow/stage
    if (currentWorkflow !== workflowCode || currentStage !== stageCode) {
      return false;
    }

    // Check for workflow_stage_action permission
    const permissionCode = `${workflowCode}_${stageCode}_${action}`;
    const permissions = AuthContext.permissions || [];

    return permissions.includes(permissionCode) ||
           permissions.includes(permissionCode.replace(/_/g, '.'));
  };

  /**
   * Guard a component (tab, section, button) with permission check
   */
  const guardComponent = (componentId, module, action, component = null) => {
    const el = document.getElementById(componentId);
    if (!el) return;

    if (hasModulePermission(module, action, component)) {
      el.style.display = '';
      el.removeAttribute('data-permission-blocked');
    } else {
      el.style.display = 'none';
      el.setAttribute('data-permission-blocked', 'true');
    }
  };

  /**
   * Guard an action (button, form submission) with context
   */
  const guardAction = (actionId, permission, workflow = null, stage = null) => {
    const el = document.getElementById(actionId);
    if (!el) return;

    let allowed = false;

    if (workflow && stage) {
      // Workflow context
      const [workflowCode, stageCode, action] = permission.split(':');
      allowed = hasWorkflowPermission(workflowCode, stageCode, action);
    } else {
      // Simple permission check
      const permissions = AuthContext.permissions || [];
      allowed = permissions.includes(permission) ||
                permissions.includes(permission.replace(/_/g, '.'));
    }

    if (allowed) {
      el.removeAttribute('disabled');
      el.removeAttribute('data-permission-denied');
      el.style.opacity = '1';
      el.style.cursor = 'pointer';
    } else {
      el.setAttribute('disabled', 'true');
      el.setAttribute('data-permission-denied', 'true');
      el.style.opacity = '0.5';
      el.style.cursor = 'not-allowed';
    }
  };

  /**
   * Get effective actions for a role in a module
   */
  const getEffectiveActionsInModule = (module) => {
    if (!AuthContext || !AuthContext.permissions) {
      return [];
    }

    const regex = new RegExp(`^${module}_(.+)(?:_view|_create|_edit|_delete|_approve|_export)?$`);
    const permissions = AuthContext.permissions || [];

    const actions = new Set();
    permissions.forEach(perm => {
      const match = perm.match(regex);
      if (match) {
        // Extract action type from permission code
        if (perm.includes('_view')) actions.add('view');
        if (perm.includes('_create')) actions.add('create');
        if (perm.includes('_edit')) actions.add('edit');
        if (perm.includes('_delete')) actions.add('delete');
        if (perm.includes('_approve')) actions.add('approve');
        if (perm.includes('_export')) actions.add('export');
      }
    });

    return Array.from(actions);
  };

  /**
   * Apply guards to all elements with data attributes
   */
  const applyModuleGuards = (container = document) => {
    // Guard components with data-module-permission
    container.querySelectorAll('[data-module-permission]').forEach(el => {
      const module = el.getAttribute('data-module');
      const action = el.getAttribute('data-action');
      const component = el.getAttribute('data-component');

      if (hasModulePermission(module, action, component)) {
        el.style.display = '';
      } else {
        el.style.display = 'none';
      }
    });

    // Guard actions with data-guard-action
    container.querySelectorAll('[data-guard-action]').forEach(el => {
      const permission = el.getAttribute('data-guard-action');
      const workflow = el.getAttribute('data-workflow');
      const stage = el.getAttribute('data-stage');

      guardAction(el.id, permission, workflow, stage);
    });

    // Guard workflow stages
    container.querySelectorAll('[data-workflow-stage]').forEach(el => {
      const workflow = el.getAttribute('data-workflow');
      const stage = el.getAttribute('data-workflow-stage');
      const actions = el.getAttribute('data-stage-actions')?.split(',') || [];

      let stageAccessible = false;
      actions.forEach(action => {
        if (hasWorkflowPermission(workflow, stage, action)) {
          stageAccessible = true;
        }
      });

      if (stageAccessible) {
        el.style.display = '';
      } else {
        el.style.display = 'none';
      }
    });
  };

  return {
    hasModulePermission,
    hasWorkflowPermission,
    guardComponent,
    guardAction,
    getEffectiveActionsInModule,
    applyModuleGuards
  };
})();

// Auto-apply on page load
document.addEventListener('DOMContentLoaded', () => {
  EnhancedRoleBasedUI.applyModuleGuards();
});

// Re-apply when content changes dynamically
const _innerHTMLDesc = Object.getOwnPropertyDescriptor(Element.prototype, 'innerHTML') ||
                       Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'innerHTML');
if (_innerHTMLDesc && _innerHTMLDesc.set) {
  const _originalSetter = _innerHTMLDesc.set;
  Object.defineProperty(Element.prototype, 'innerHTML', {
    set(html) {
      _originalSetter.call(this, html);
      if (this && this.nodeType === Node.ELEMENT_NODE) {
        EnhancedRoleBasedUI.applyModuleGuards(this);
      }
    },
    get: _innerHTMLDesc.get,
    configurable: true,
  });
}
