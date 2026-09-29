/**
 * Temporary autosave for unfinished application forms.
 * Drafts stay in this browser tab's sessionStorage and expire automatically.
 */
(() => {
  if (window.FormDraftManager) return;

  const PREFIX = 'kw_formdraft:v1:';
  const TTL_MS = 8 * 60 * 60 * 1000;
  const excluded = /password|passphrase|one[-_ ]?time|\botp\b|csrf|access[-_ ]?token|secret|authorization|cvv|cvc|bank[-_ ]?account|account[-_ ]?number|mpesa|mobile[-_ ]?money|card[-_ ]?number|security[-_ ]?code/i;
  const timers = new WeakMap();
  const restored = new WeakSet();
  const pending = new Set();
  let sequence = 0;

  const isExcluded = (control) => {
    const type = String(control.type || '').toLowerCase();
    const autocomplete = String(control.autocomplete || '').toLowerCase();
    return ['password', 'file', 'submit', 'button', 'reset', 'image'].includes(type)
      || /current-password|new-password|one-time-code|cc-number|cc-csc/.test(autocomplete)
      || excluded.test(`${control.name || ''} ${control.id || ''}`);
  };

  const userKey = () => {
    const user = window.AuthContext?.getUser?.() || {};
    return String(user.id || user.user_id || user.username || 'session');
  };

  const formKey = (form) => {
    if (!form.dataset.formDraftKey) {
      const route = String(window.REQUESTED_ROUTE || location.pathname).replace(/[^a-z0-9_-]/gi, '_');
      const identity = form.id || form.name || form.getAttribute('action') || `form_${++sequence}`;
      form.dataset.formDraftKey = `${PREFIX}${encodeURIComponent(userKey())}:${route}:${encodeURIComponent(identity)}`;
    }
    return form.dataset.formDraftKey;
  };

  const controlsFor = (form) => [...form.elements].filter((control) =>
    (control.name || control.id || control.dataset.draftField) && !control.disabled && !isExcluded(control)
  );
  const controlKey = (control) => control.name || control.dataset.draftField || control.id;

  const snapshot = (form) => ({
    savedAt: Date.now(),
    entries: controlsFor(form).map((control) => ({
      name: controlKey(control),
      type: String(control.type || '').toLowerCase(),
      value: control.multiple
        ? [...control.selectedOptions].map((option) => option.value)
        : control.value,
      checked: ['checkbox', 'radio'].includes(String(control.type || '').toLowerCase())
        ? Boolean(control.checked)
        : undefined,
    })),
  });

  const save = (form) => {
    if (!(form instanceof HTMLFormElement) || form.matches('[data-no-draft], [autocomplete="off"]')) return;
    const data = snapshot(form);
    if (!data.entries.length) return;
    try {
      sessionStorage.setItem(formKey(form), JSON.stringify(data));
      form.dispatchEvent(new CustomEvent('kingsway:form-draft-saved', { bubbles: true }));
    } catch (_) {
      // Storage may be disabled or full. Form editing and submission still work.
    }
  };

  const clear = (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    try { sessionStorage.removeItem(formKey(form)); } catch (_) {}
    pending.delete(form);
    form.dispatchEvent(new CustomEvent('kingsway:form-draft-cleared', { bubbles: true }));
  };

  const restore = (form) => {
    if (!(form instanceof HTMLFormElement) || restored.has(form) || form.matches('[data-no-draft], [autocomplete="off"]')) return;
    restored.add(form);
    let data;
    try {
      const raw = sessionStorage.getItem(formKey(form));
      if (!raw) return;
      data = JSON.parse(raw);
      if (!data || Date.now() - Number(data.savedAt || 0) > TTL_MS) {
        sessionStorage.removeItem(formKey(form));
        return;
      }
    } catch (_) { return; }

    const byName = new Map();
    for (const control of controlsFor(form)) {
      const key = controlKey(control);
      const list = byName.get(key) || [];
      list.push(control);
      byName.set(key, list);
    }
    const used = new Map();
    for (const entry of data.entries || []) {
      const controls = byName.get(entry.name) || [];
      const index = used.get(entry.name) || 0;
      const control = controls[index];
      if (!control) continue;
      used.set(entry.name, index + 1);
      const type = String(control.type || '').toLowerCase();
      if (type === 'checkbox' || type === 'radio') control.checked = Boolean(entry.checked);
      else if (control.multiple && Array.isArray(entry.value)) {
        const selected = new Set(entry.value.map(String));
        [...control.options].forEach((option) => { option.selected = selected.has(option.value); });
      } else control.value = String(entry.value ?? '');
      control.dispatchEvent(new Event('input', { bubbles: true }));
      control.dispatchEvent(new Event('change', { bubbles: true }));
    }
    form.dispatchEvent(new CustomEvent('kingsway:form-draft-restored', { bubbles: true }));
  };

  const flush = () => {
    document.querySelectorAll('form').forEach((form) => {
      if (timers.has(form)) {
        clearTimeout(timers.get(form));
        timers.delete(form);
      }
      save(form);
    });
  };

  const isSubmitControl = (target) => {
    if (!(target instanceof HTMLElement)) return false;
    const control = target.closest('button, input[type="submit"], input[type="image"]');
    if (!control || !control.form) return false;
    if (control.type === 'submit' || control.type === 'image' || control.dataset.draftSubmit === 'true') return true;
    return /^(save|submit|create|update|commit|approve|confirm)([-_a-z0-9]*)$/i.test(control.id || '');
  };

  const clearPendingAfterMutation = () => {
    for (const form of [...pending]) clear(form);
  };

  const wrapFetch = () => {
    if (typeof window.fetch !== 'function' || window.fetch.__formDraftWrapped) return;
    const original = window.fetch.bind(window);
    const wrapped = async (...args) => {
      const request = args[0];
      const options = args[1] || {};
      const method = String(options.method || request?.method || 'GET').toUpperCase();
      const response = await original(...args);
      if (pending.size && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) && response.ok) {
        let successful = true;
        try {
          const payload = await response.clone().json();
          successful = payload?.success !== false && payload?.status !== 'error' && !payload?.error;
        } catch (_) {}
        if (successful) clearPendingAfterMutation();
      }
      return response;
    };
    wrapped.__formDraftWrapped = true;
    window.fetch = wrapped;
  };

  const start = async () => {
    if (window.AuthContext?.ready) {
      try { await window.AuthContext.ready(); } catch (_) {}
    }
    wrapFetch();
    document.querySelectorAll('form').forEach(restore);
    document.addEventListener('input', (event) => {
      const form = event.target?.form;
      if (!form) return;
      clearTimeout(timers.get(form));
      timers.set(form, setTimeout(() => { timers.delete(form); save(form); }, 350));
    }, true);
    document.addEventListener('change', (event) => {
      const form = event.target?.form;
      if (form) save(form);
    }, true);
    document.addEventListener('click', (event) => {
      if (!isSubmitControl(event.target)) return;
      const form = event.target.closest('button, input')?.form;
      if (!form) return;
      pending.add(form);
      setTimeout(() => pending.delete(form), 60000);
    }, true);
    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      save(form);
      pending.add(form);
      queueMicrotask(() => {
        if (!event.defaultPrevented) pending.delete(form);
      });
    }, true);
    document.addEventListener('reset', (event) => {
      if (event.target instanceof HTMLFormElement) clear(event.target);
    }, true);
    document.addEventListener('shown.bs.modal', (event) => {
      event.target?.querySelectorAll?.('form').forEach(restore);
    });
    window.addEventListener('pagehide', flush);
    const observer = new MutationObserver((records) => {
      for (const record of records) for (const node of record.addedNodes) {
        if (!(node instanceof HTMLElement)) continue;
        if (node.matches?.('form')) restore(node);
        node.querySelectorAll?.('form').forEach(restore);
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  };

  window.FormDraftManager = { save, restore, clear, flush };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => { void start(); }, { once: true });
  else void start();
})();
