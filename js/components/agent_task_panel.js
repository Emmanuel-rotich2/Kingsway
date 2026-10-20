/**
 * Reusable Agent Task Panel Component
 * 
 * Uses Node agent gateway task API:
 * - POST /api/agents/tasks to create task
 * - GET /api/agents/tasks/:id/stream for SSE subscription (component-level)
 * - Falls back to polling (PHP dashboard endpoints) when the gateway origin
 *   is unavailable or SSE drops
 * 
 * All guardrails, tools, sessions, charts, drafts, RBAC, output guards are handled by
 * the existing PHP/Python backend - Node is just a gateway/proxy.
 * 
 * Usage:
 *   const panel = new AgentTaskPanel({
 *     container: '#my-panel',
 *     tool: 'drafts.create' | 'reports.aggregate' | 'charts.generate',
 *     question: 'Draft a lesson plan on photosynthesis',
 *     route: 'academics',
 *     module: 'lesson_plans',
 *     targetComponent: '#lesson-plan-editor',
 *     sessionId: 'optional-session-id',
 *     onComplete: (result) => { /* update editor *\/ },
 *     onError: (error) => { /* show notification *\/ },
 *     onProgress: (progress) => { /* update UI *\/ },
 *   });
 *   panel.start();
 */

(function() {
  'use strict';

  class AgentTaskPanel {
    constructor(options = {}) {
      this.container = typeof options.container === 'string' 
        ? document.querySelector(options.container) 
        : options.container;
      if (!this.container) {
        throw new Error('AgentTaskPanel: container element not found');
      }

      this.tool = options.tool || 'drafts.create';
      this.question = options.question || '';
      this.route = options.route || 'dashboard';
      this.module = options.module || 'dashboard';
      this.sessionId = options.sessionId || null;
      this.targetComponent = options.targetComponent || null;
      this.componentId = options.componentId || 'agent-task-' + Math.random().toString(36).slice(2);
      this.onComplete = options.onComplete || (() => {});
      this.onError = options.onError || (() => {});
      this.onProgress = options.onProgress || (() => {});

      this.taskId = null;
      this.eventSource = null;
      this.pollInterval = null;
      this.isDestroyed = false;
    }

    async start() {
      if (this.isDestroyed) return;
      
      try {
        this.showLoading('Creating agent task...');
        
        // 1. Create task via Node gateway
        const task = await this.createTask();
        this.taskId = task.task_id;
        
        // 2. Subscribe to SSE for THIS task (component-level subscription)
        await this.subscribeToTaskStream();
        
      } catch (error) {
        this.showError(error.message || 'Failed to start agent task');
        this.onError(error);
      }
    }

    async createTask() {
      const response = await window.API.dashboard.agentTaskCreate({
        tool: this.tool,
        question: this.question,
        route: this.route,
        module: this.module,
        session_id: this.sessionId,
      });
      const task = response?.data ?? response;
      
      if (!task?.task_id) {
        throw new Error('Failed to create task: ' + (task?.message || response?.message || 'Unknown error'));
      }
      
      return task;
    }

    async subscribeToTaskStream() {
      if (this.isDestroyed) return;
      
      // Get auth token
      const token = window.AuthContext?.getToken?.() || '';
      if (!token) {
        throw new Error('Authentication required for task stream');
      }

      const gatewayOrigin = String(window.KINGSWAY_REALTIME_ORIGIN || '').replace(/\/+$/, '');
      if (!gatewayOrigin) {
        this.startPollingFallback();
        return;
      }
      
      this.showLoading('Waiting for agent response...');
      
      const streamUrl = `${gatewayOrigin}/api/agents/tasks/${encodeURIComponent(this.taskId)}/stream?token=${encodeURIComponent(token)}`;
      this.eventSource = new EventSource(streamUrl);
      
      this.eventSource.addEventListener('delta', (e) => this.handleDelta(JSON.parse(e.data)));
      this.eventSource.addEventListener('status', (e) => this.handleStatus(JSON.parse(e.data)));
      this.eventSource.addEventListener('final', (e) => this.handleFinal(JSON.parse(e.data)));
      this.eventSource.addEventListener('error', (e) => this.handleError(JSON.parse(e.data)));
      
      this.eventSource.onerror = () => {
        // SSE failed, fall back to polling
        this.logger('SSE connection lost, falling back to polling');
        this.startPollingFallback();
      };
    }

    startPollingFallback() {
      if (this.isDestroyed) return;
      
      // Close SSE if open
      if (this.eventSource) {
        this.eventSource.close();
        this.eventSource = null;
      }
      
      this.showLoading('Polling for task status...');
      
      this.pollInterval = setInterval(async () => {
        if (this.isDestroyed) {
          clearInterval(this.pollInterval);
          return;
        }
        
        try {
          const response = await window.API.dashboard.agentTaskGet(this.taskId);
          const task = response?.data ?? response;
          if (!task) return;
          
          if (task.status === 'completed') {
            clearInterval(this.pollInterval);
            this.handleFinal({ result: task.result });
          } else if (task.status === 'failed') {
            clearInterval(this.pollInterval);
            this.handleError({ message: task.error || 'Task failed' });
          } else if (task.status === 'cancelled') {
            clearInterval(this.pollInterval);
            this.handleError({ message: 'Task was cancelled' });
          } else {
            // Still processing
            this.updateStatus(task.message || task.status);
            if (task.progress) {
              this.updateProgress(task.progress);
            }
          }
        } catch (e) {
          this.logger('Polling error:', e);
        }
      }, 2000); // Poll every 2 seconds
    }

    handleDelta(data) {
      if (this.isDestroyed) return;
      if (data?.text) {
        this.appendOutput(data.text);
        this.onProgress({ type: 'delta', text: data.text });
      }
    }

    handleStatus(data) {
      if (this.isDestroyed) return;
      if (data?.text) {
        this.updateStatus(data.text);
        this.onProgress({ type: 'status', text: data.text });
      }
    }

    handleFinal(data) {
      if (this.isDestroyed) return;
      this.cleanup();
      
      if (data?.result) {
        this.showSuccess(data.result);
        this.onComplete(data.result);
      } else if (data?.answer) {
        this.showSuccess(data.answer);
        this.onComplete(data.answer);
      }
    }

    handleError(data) {
      if (this.isDestroyed) return;
      this.cleanup();
      const message = data?.message || data?.error || 'Agent task failed';
      this.showError(message);
      this.onError(new Error(message));
    }

    cleanup() {
      if (this.eventSource) {
        this.eventSource.close();
        this.eventSource = null;
      }
      if (this.pollInterval) {
        clearInterval(this.pollInterval);
        this.pollInterval = null;
      }
    }

    destroy() {
      this.isDestroyed = true;
      this.cleanup();
      this.container.innerHTML = '';
    }

    // UI Methods
    showLoading(message) {
      this.container.innerHTML = `
        <div class="agent-task-panel loading">
          <div class="d-flex align-items-center gap-2 p-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <span class="text-muted">${this.escapeHtml(message)}</span>
          </div>
          <div class="progress mt-2" style="height: 4px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 10%"></div>
          </div>
        </div>
      `;
    }

    updateStatus(message) {
      const statusEl = this.container.querySelector('.agent-status');
      if (statusEl) {
        statusEl.textContent = message;
      } else {
        this.container.innerHTML = `
          <div class="agent-task-panel">
            <div class="agent-status small text-muted mb-2">${this.escapeHtml(message)}</div>
            <div class="progress" style="height: 4px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 50%"></div>
            </div>
            <div class="agent-output mt-2 small" style="max-height: 300px; overflow-y: auto;"></div>
          </div>
        `;
      }
    }

    updateProgress(percent) {
      const progressBar = this.container.querySelector('.progress-bar');
      if (progressBar) {
        progressBar.style.width = percent + '%';
      }
    }

    appendOutput(text) {
      let outputEl = this.container.querySelector('.agent-output');
      if (!outputEl) {
        this.container.innerHTML = `
          <div class="agent-task-panel">
            <div class="agent-status small text-muted mb-2">Receiving response...</div>
            <div class="progress" style="height: 4px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 80%"></div>
            </div>
            <div class="agent-output mt-2 small" style="max-height: 300px; overflow-y: auto;"></div>
          </div>
        `;
        outputEl = this.container.querySelector('.agent-output');
      }
      outputEl.textContent += text;
      outputEl.scrollTop = outputEl.scrollHeight;
    }

    showSuccess(result) {
      const body = result?.answer?.body || result?.body || result?.draft || JSON.stringify(result);
      
      // If targetComponent specified, inject the result there
      if (this.targetComponent) {
        const target = document.querySelector(this.targetComponent);
        if (target) {
          target.value = body;
          target.dispatchEvent(new Event('input', { bubbles: true }));
        }
      }
      
      this.container.innerHTML = `
        <div class="agent-task-panel success">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-check-circle-fill text-success"></i>
            <strong>Draft Ready</strong>
          </div>
          <div class="agent-output small p-2 bg-light rounded" style="max-height: 300px; overflow-y: auto; white-space: pre-wrap;">${this.escapeHtml(body)}</div>
          <div class="d-flex gap-2 mt-3">
            <button class="btn btn-sm btn-primary" data-action="use-draft">Use This Draft</button>
            <button class="btn btn-sm btn-outline-secondary" data-action="retry">Retry</button>
          </div>
        </div>
      `;
      this.container.querySelector('[data-action="use-draft"]')?.addEventListener('click', () => {
        this.onComplete({ body, raw: result });
      });
      this.container.querySelector('[data-action="retry"]')?.addEventListener('click', () => this.start());
    }

    showError(message) {
      this.container.innerHTML = `
        <div class="agent-task-panel error">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
            <strong class="text-danger">Error</strong>
          </div>
          <div class="small text-danger">${this.escapeHtml(message)}</div>
          <div class="d-flex gap-2 mt-3">
            <button class="btn btn-sm btn-primary" data-action="retry">Retry</button>
            <button class="btn btn-sm btn-outline-secondary" data-action="cancel">Cancel</button>
          </div>
        </div>
      `;
      this.container.querySelector('[data-action="retry"]')?.addEventListener('click', () => this.start());
      this.container.querySelector('[data-action="cancel"]')?.addEventListener('click', () => this.cleanup());
    }

    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = String(text ?? '');
      return div.innerHTML;
    }

    logger(...args) {
      if (window.console && console.debug) {
        console.debug('[AgentTaskPanel]', ...args);
      }
    }
  }

  // Export for module systems and global
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = AgentTaskPanel;
  } else {
    window.AgentTaskPanel = AgentTaskPanel;
  }
})();