/**
 * js/core/parent_common.js — shared runtime for every parent-portal page.
 *
 * Provides the independent family session (pp_token in sessionStorage),
 * the parent API fetch wrapper, an auth guard, logout, the per-page child
 * sidebar renderer, the shared Apply-for-Admission modal and the M-Pesa
 * modal flow. Page controllers live in js/pages/parents/*.js and use these
 * helpers; they must not re-implement token/transport handling.
 */
(function () {
  'use strict';

  function obfuscate(str) {
    return btoa(
      String(str || '')
        .split('')
        .map(function (c, i) {
          return String.fromCharCode(c.charCodeAt(0) + ((i % 7) + 1));
        })
        .join(''),
    );
  }

  function deobfuscate(str) {
    try {
      return atob(String(str))
        .split('')
        .map(function (c, i) {
          return String.fromCharCode(c.charCodeAt(0) - ((i % 7) + 1));
        })
        .join('');
    } catch (_) {
      return null;
    }
  }

  var TOKEN_KEY = 'pp_token';
  var EXPIRES_KEY = 'pp_expires';
  var GUARDIAN_KEY = 'kw_admissions_guardian';

  function getBase() {
    return window.FAMILY_STAFF_MODE ? '/family' : '/parent-portal';
  }

  /* Base64 helpers (no dependency on browser TextEncoder). These are used to
   * interchange binary 2FA/passkey payloads between the parent session and the
   * shared /auth + /twofactor endpoints. Mirror the SPAs that already send
   * Uint8Array-backed fields so the parent flow accepts the same contract. */
  function bytesToB64(bytes) {
    var binary = '';
    for (var i = 0; i < bytes.length; i++) {
      binary += String.fromCharCode(bytes[i] & 0xff);
    }
    return btoa(binary);
  }

  function b64ToBytes(str) {
    if (!str) return new Uint8Array(0);
    var clean = String(str).replace(/-/g, '+').replace(/_/g, '/');
    while (clean.length % 4) clean += '=';
    var binary = atob(clean);
    var out = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) {
      out[i] = binary.charCodeAt(i);
    }
    return out;
  }

  var ParentCommon = {
    esc: function (s) {
      return String(s || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    },

    /* ── Independent family session ─────────────────────────────────── */

    getToken: function () {
      if (window.FAMILY_STAFF_MODE) {
        return window.AuthContext && window.AuthContext.getToken
          ? window.AuthContext.getToken()
          : null;
      }
      return deobfuscate(sessionStorage.getItem(TOKEN_KEY) || '');
    },

    setToken: function (token, expiresAt) {
      if (window.FAMILY_STAFF_MODE) return;
      try {
        sessionStorage.setItem(TOKEN_KEY, obfuscate(token));
        sessionStorage.setItem(EXPIRES_KEY, expiresAt || '');
      } catch (e) {
        /* sessionStorage may be unavailable; non-fatal */
      }
    },

    clearAuth: function () {
      try {
        sessionStorage.removeItem(TOKEN_KEY);
        sessionStorage.removeItem(EXPIRES_KEY);
        sessionStorage.removeItem('pp_csrf');
        sessionStorage.removeItem(GUARDIAN_KEY);
      } catch (e) {
        /* noop */
      }
    },

    storeGuardian: function (parent) {
      try {
        var name = [parent.first_name, parent.last_name].filter(Boolean).join(' ').trim();
        var email = (parent.email || '').trim();
        var phone = (parent.phone_1 || parent.phone || '').trim();
        if (name || email || phone) {
          sessionStorage.setItem(
            GUARDIAN_KEY,
            JSON.stringify({
              parent_name: name,
              parent_email: email,
              parent_phone: phone,
            }),
          );
        }
      } catch (e) {
        /* prefill is optional */
      }
    },

    /* ── API transport ──────────────────────────────────────────────── */

    apiFetch: function (path, method, body) {
      // Thin pass-through. The unified api.js transport owns token attachment,
      // parent session expiry renewal (X-Parent-Session-Expires) and parent
      // 401 redirects; parent pages must not re-implement token/CSRF handling.
      return Promise.resolve(
        apiCall(getBase() + path, method || 'GET', body, null, { noRedirect: true }),
      ).then(function (data) {
        return data;
      });
    },

    /* ── Auth guard ─────────────────────────────────────────────────── */

    ensureAuth: async function () {
      if (window.FAMILY_STAFF_MODE) {
        if (window.AuthContext && typeof window.AuthContext.ready === 'function') {
          await window.AuthContext.ready();
        }
        if (window.AuthContext && window.AuthContext.isAuthenticated && window.AuthContext.isAuthenticated()) {
          return true;
        }
        window.location.replace((window.APP_BASE || '') + '/home.php');
        return false;
      }
      var token = this.getToken();
      var expires = sessionStorage.getItem(EXPIRES_KEY);
      if (token && expires && new Date(expires) > new Date()) {
        return true;
      }
      if (token && expires) {
        this.clearAuth();
      }
      var login = (window.APP_BASE || '') + '/parent_portal.php';
      var original = window.location.pathname.replace(/^\/+|\/+$/g, '');
      if (original) login += '?next=' + encodeURIComponent('/' + original);
      window.location.replace(login);
      return false;
    },

    async loadDashboard() {
      var resp = await this.apiFetch('/dashboard', 'GET');
      var d = resp && resp.data !== undefined ? resp.data : resp;
      this.setParentName((d.parent || {}).first_name);
      this.storeGuardian(d.parent || {});
      try {
        sessionStorage.setItem('pp_children', JSON.stringify(d.children || []));
      } catch (e) { /* optional */ }
      return d; // { parent, children }
    },

    setParentName: function (firstName) {
      var el = document.getElementById('parentName');
      if (el) el.textContent = firstName || 'Parent';
    },

    /* ── Per-page child sidebar ─────────────────────────────────────── */

    renderChildList: function (children, selectedId) {
      var el = document.getElementById('ppChildList');
      if (!el) return;
      if (!children.length) {
        el.innerHTML =
          '<div class="alert alert-info small mb-0">No children linked to this account. Contact the school office.</div>';
        return;
      }
      el.innerHTML = children
        .map(function (c) {
          var active = String(c.id) === String(selectedId) ? ' active' : '';
          return (
            '<button type="button" class="pp-child-item' + active + '" data-child-id="' + c.id + '">' +
            '<span class="pp-child-avatar">' + ParentCommon.esc((c.first_name || '?')[0].toUpperCase()) + '</span>' +
            '<span><strong>' + ParentCommon.esc(c.first_name + ' ' + c.last_name) + '</strong>' +
            '<small>' + ParentCommon.esc(c.class_name || '') + '</small></span></button>'
          );
        })
        .join('');
      el.querySelectorAll('.pp-child-item').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-child-id');
          document.querySelectorAll('#ppChildList .pp-child-item').forEach(function (b) {
            b.classList.toggle('active', b === btn);
          });
          var child = children.find(function (c) {
            return String(c.id) === String(id);
          });
          if (child && window.ParentSelectChild) window.ParentSelectChild(child);
        });
      });
    },

    setChildNameInHeader: function (child) {
      var el = document.getElementById('ppStudentName');
      if (el) el.textContent = child.first_name + ' ' + child.last_name;
      var classEl = document.getElementById('ppStudentClass');
      if (classEl) classEl.textContent = child.class_name || '';
    },

    /* ── Page navigation helpers ────────────────────────────────────── */

    childUrl: function (section, childId) {
      var url = (window.APP_BASE || '') + '/parent_portal.php?route=' + section;
      var q = [];
      if (childId) q.push('child=' + encodeURIComponent(childId));
      if (window.FAMILY_STAFF_MODE) q.push('staff=1');
      return q.length ? url + '?' + q.join('&') : url;
    },

    /* ── Shared modals ──────────────────────────────────────────────── */

    openApplyAdmissionModal: function () {
      var modalEl = document.getElementById('applyAdmissionModal');
      if (!modalEl || !window.bootstrap) return;
      this.prefillGuardianFromSession();
      var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
      var form = document.getElementById('applyAdmissionForm');
      if (form) {
        if (form.__applySubmit) form.removeEventListener('submit', form.__applySubmit);
        form.__applySubmit = function (e) {
          e.preventDefault();
          ParentCommon.submitApplyAdmission(form);
        };
        form.addEventListener('submit', form.__applySubmit);
      }
    },

    prefillGuardianFromSession: function () {
      var raw = sessionStorage.getItem(GUARDIAN_KEY);
      if (!raw) return;
      var g;
      try {
        g = JSON.parse(raw);
      } catch (_) {
        return;
      }
      var fields = {
        ppParentName: g.parent_name,
        ppParentPhone: g.parent_phone,
        ppParentEmail: g.parent_email,
      };
      Object.keys(fields).forEach(function (id) {
        if (!fields[id]) return;
        var el = document.getElementById(id);
        if (el && !el.value) el.value = fields[id];
      });
    },

    submitApplyAdmission: function (form) {
      var msg = document.getElementById('ppApplyMsg');
      var btn = document.getElementById('ppApplySubmit');
      msg.classList.add('d-none');
      var decl = document.getElementById('ppDeclaration');
      if (!decl || !decl.checked) {
        if (decl) decl.focus();
        msg.textContent = 'Please confirm the declaration before submitting.';
        msg.className = 'alert alert-warning mt-2 mb-0';
        return;
      }
      var fd = new FormData(form);
      var termSel = document.getElementById('ppPreferredStart');
      var termId =
        termSel && termSel.selectedOptions && termSel.selectedOptions[0]
          ? termSel.selectedOptions[0].dataset.termId
          : null;
      if (termId) fd.append('target_term_id', termId);

      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
      apiCall('/public/applications', 'POST', fd, null, {
        isFile: true,
        noRedirect: true,
        checkPermission: false,
      })
        .then(function (json) {
          json = json && json.data !== undefined ? json.data : json;
          if (json && json.success) {
            msg.textContent =
              'Application submitted successfully. Reference: ' + (json.ref || json.application_no || '');
            msg.className = 'alert alert-success mt-2 mb-0';
            form.reset();
            var modal = document.getElementById('applyAdmissionModal');
            if (modal)
              setTimeout(function () {
                var inst = bootstrap.Modal.getInstance(modal);
                if (inst) inst.hide();
              }, 1800);
          } else {
            msg.textContent = json.message || 'Submission failed. Please try again.';
            msg.className = 'alert alert-danger mt-2 mb-0';
          }
        })
        .catch(function () {
          msg.textContent = 'Network error. Please try again or call +254 720 113 030.';
          msg.className = 'alert alert-danger mt-2 mb-0';
        })
        .finally(function () {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-send me-1"></i>Submit Application';
        });
    },

    logout: function () {
      this.apiFetch('/logout', 'POST').catch(function () {});
      this.clearAuth();
      if (window.FAMILY_STAFF_MODE) {
        window.location.replace((window.APP_BASE || '') + '/home.php');
        return;
      }
      window.location.replace((window.APP_BASE || '') + '/parent_portal.php');
    },

    /* ── M-Pesa shared modal ────────────────────────────────────────── */

    openMpesaModal: function (children, amount, defaultStudentId, purpose) {
      var modalEl = document.getElementById('mpesaPaymentModal');
      var studentEl = document.getElementById('mpesaStudent');
      if (studentEl) {
        studentEl.innerHTML = children
          .map(function (c) {
            return (
              '<option value="' + c.id + '">' + ParentCommon.esc(c.first_name + ' ' + c.last_name) + '</option>'
            );
          })
          .join('');
        if (defaultStudentId) studentEl.value = String(defaultStudentId);
      }
      var purposeEl = document.getElementById('mpesaPurpose');
      if (purposeEl) purposeEl.value = purpose || 'fees';
      var amountEl = document.getElementById('mpesaAmount');
      if (amountEl) amountEl.value = amount || '';
      var phoneEl = document.getElementById('mpesaPhone');
      if (phoneEl) phoneEl.value = '';
      this.setView('mpesaPaymentForm', true);
      this.setView('mpesaWaiting', false);
      var err = document.getElementById('mpesaError');
      if (err) err.classList.add('d-none');
      if (!modalEl || !window.bootstrap) return;
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    },

    initiateMpesaPayment: function () {
      var amount = (document.getElementById('mpesaAmount') || {}).value;
      var phone = ((document.getElementById('mpesaPhone') || {}).value || '').trim();
      var provider = (document.getElementById('mpesaProvider') || {}).value;
      var studentId = (document.getElementById('mpesaStudent') || {}).value;
      var purpose = (document.getElementById('mpesaPurpose') || {}).value || 'fees';
      var errEl = document.getElementById('mpesaError');
      var spinner = document.getElementById('mpesaSpinner');
      var self = this;
      errEl.classList.add('d-none');
      if (!amount || parseFloat(amount) <= 0) {
        errEl.textContent = 'Invalid amount';
        errEl.classList.remove('d-none');
        return;
      }
      if (!phone) {
        errEl.textContent = 'Phone number is required';
        errEl.classList.remove('d-none');
        return;
      }
      spinner.classList.remove('d-none');
      // Purpose-aware payment: fees keep the original fee flow; transport and
      // uniforms route through the governed payment services so references are
      // posted to the correct purpose ledger.
      var endpoint = purpose === 'fees' ? '/initiate-mpesa-payment' : '/payment';
      var payload = {
        student_id: studentId,
        purpose: purpose,
        amount: parseFloat(amount),
        phone: phone,
        provider: provider,
      };
      this.apiFetch(endpoint, 'POST', payload)
        .then(function (resp) {
          var d = resp.data !== undefined ? resp.data : resp;
          if (d.checkout_request_id) {
            self.setView('mpesaPaymentForm', false);
            self.setView('mpesaWaiting', true);
            self.startPolling(d.checkout_request_id);
          } else if (d.intent && d.intent.id) {
            self.setView('mpesaPaymentForm', false);
            self.setView('mpesaWaiting', true);
            self.setIntentStatus(d.intent.message || 'M-Pesa prompt sent. Please enter your PIN on your phone.');
          } else {
            errEl.textContent = d.message || 'Failed to initiate payment';
            errEl.classList.remove('d-none');
          }
        })
        .catch(function (err) {
          errEl.textContent = err.message || 'Payment initiation failed';
          errEl.classList.remove('d-none');
        })
        .finally(function () {
          spinner.classList.add('d-none');
        });
    },

    setIntentStatus: function (message) {
      var statusEl = document.getElementById('mpesaPollingStatus');
      if (statusEl) statusEl.textContent = message;
    },

    startPolling: function (checkoutRequestId) {
      var self = this;
      var attempts = 0;
      var maxAttempts = 30;
      var statusEl = document.getElementById('mpesaPollingStatus');
      if (this._mpesaPolling) clearInterval(this._mpesaPolling);
      this._mpesaPolling = setInterval(function () {
        attempts++;
        if (statusEl) statusEl.textContent = 'Checking status... (' + attempts + '/' + maxAttempts + ')';
        if (attempts >= maxAttempts) {
          clearInterval(self._mpesaPolling);
          if (statusEl)
            statusEl.textContent = 'Payment confirmation timed out. Check your M-Pesa messages.';
          return;
        }
        self.checkMpesaStatus(checkoutRequestId, function (done) {
          if (done) {
            clearInterval(self._mpesaPolling);
            if (statusEl) statusEl.textContent = 'Payment confirmed! Refreshing...';
            var modal = bootstrap.Modal.getInstance(document.getElementById('mpesaPaymentModal'));
            if (modal) modal.hide();
            setTimeout(function () {
              window.parentLocation && window.location.reload();
            }, 400);
          }
        });
      }, 4000);
    },

    checkMpesaStatus: function (checkoutRequestId, callback) {
      this.apiFetch('/mpesa-status/' + checkoutRequestId, 'GET')
        .then(function (resp) {
          var d = resp.data !== undefined ? resp.data : resp;
          if (d.ResultCode === '0' || d.resultCode === '0' || d.status === 'completed') {
            callback(true);
          }
        })
        .catch(function () {});
    },

    resetMpesaModal: function () {
      if (this._mpesaPolling) {
        clearInterval(this._mpesaPolling);
        this._mpesaPolling = null;
      }
      this.setView('mpesaPaymentForm', true);
      this.setView('mpesaWaiting', false);
    },

    setView: function (id, visible) {
      var el = document.getElementById(id);
      if (el) el.style.display = visible ? 'block' : 'none';
    },

    showLoading: function (el, message) {
      if (!el) return;
      el.innerHTML =
        '<div class="text-center py-4"><div class="spinner-border text-success"></div>' +
        (message ? '<p class="text-muted mt-2 mb-0">' + ParentCommon.esc(message) + '</p>' : '') +
        '</div>';
    },

    showError: function (el, message) {
      if (!el) return;
      el.innerHTML =
        '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>' +
        ParentCommon.esc(message || 'Something went wrong.') +
        '</div>';
    },

    /* ── CSV / print helpers for data tables (mandatory portal actions) ── */

    csvFromHeaders: function (headers, rows) {
      var self = this;
      var lines = [headers.map(function (h) { return self.csvCell(h); }).join(',')];
      rows.forEach(function (r) {
        lines.push(headers.map(function (_, i) { return self.csvCell(r[i]); }).join(','));
      });
      return lines.join('\r\n');
    },
    csvCell: function (v) {
      var s = String(v === null || v === undefined ? '' : v);
      if (/[",\r\n]/.test(s)) s = '"' + s.replace(/"/g, '""') + '"';
      return s;
    },
    exportCsv: function (filename, csv) {
      if (window.KingswayFileLifecycle && typeof window.KingswayFileLifecycle.exportText === 'function') {
        window.KingswayFileLifecycle.exportText(csv, filename, 'text/csv');
      } else {
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
      }
    },
    printSection: function () {
      document.body.classList.add('pp-printing');
      window.print();
      setTimeout(function () {
        document.body.classList.remove('pp-printing');
      }, 600);
    },
  };

  /* ── Global event wiring (shared chrome) ────────────────────────────── */
  function bindGlobalChrome() {
    var logoutBtn = document.getElementById('btnLogout');
    if (logoutBtn) logoutBtn.addEventListener('click', function () { ParentCommon.logout(); });
    var applyBtn = document.getElementById('btnApplyAdmission');
    if (applyBtn) applyBtn.addEventListener('click', function () { ParentCommon.openApplyAdmissionModal(); });
    var payBtn = document.getElementById('btnMpesaPay');
    if (payBtn) payBtn.addEventListener('click', function () { ParentCommon.initiateMpesaPayment(); });
    var doneBtn = document.getElementById('btnMpesaDone');
    if (doneBtn) doneBtn.addEventListener('click', function () { ParentCommon.resetMpesaModal(); });
    var mpesaModal = document.getElementById('mpesaPaymentModal');
    if (mpesaModal) {
      mpesaModal.addEventListener('hidden.bs.modal', function () { ParentCommon.resetMpesaModal(); });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindGlobalChrome);
  } else {
    bindGlobalChrome();
  }

  /* ── Child-scoped page controller base ───────────────────────────────
   * Powers results/fees/attendance/messages/documents/transport pages:
   * a per-page sidebar lists the parent's children and the page provides
   * sub-tabs. The page calls ParentCommon.childPage({...}).
   *   options.contentId    'ppResultsContent'     content element id
   *   options.defaultTab   'fees'                 initial tab
   *   options.loadTab(tab, child, el, common)     render tab content
   *   options.afterSelect(child, el)              optional page hook
   * Tab buttons already present in the page markup ([data-pp-tab]) are
   * bound; pages without tabs simply always render the default tab.
   * ─────────────────────────────────────────────────────────────────── */

  function childPage(options) {
    var opts = options || {};
    var state = { children: [], child: null, tab: opts.defaultTab || 'fees' };

    function setTabButton(tab) {
      document.querySelectorAll('[data-pp-tab]').forEach(function (btn) {
        btn.classList.toggle('active', btn.dataset.ppTab === tab);
      });
    }
    function loadTab() {
      var el = document.getElementById(opts.contentId || '');
      if (!el || !state.child) return;
      ParentCommon.showLoading(el);
      opts.loadTab(state.tab, state.child, el, ParentCommon);
    }
    function selectChild(child) {
      state.child = child;
      ParentCommon.renderChildList(state.children, child.id);
      ParentCommon.setChildNameInHeader(child);
      setTabButton(state.tab);
      if (typeof opts.afterSelect === 'function') opts.afterSelect(child);
      loadTab();
    }

    (async function run() {
      if (!(await ParentCommon.ensureAuth())) return;
      window.ParentSelectChild = selectChild;
      var d = await ParentCommon.loadDashboard();
      state.children = d.children || [];
      // Bind any existing sub-tab buttons in the page markup.
      document.querySelectorAll('[data-pp-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          state.tab = btn.dataset.ppTab;
          setTabButton(state.tab);
          loadTab();
        });
      });
      var first = state.children[0];
      if (!first) {
        ParentCommon.renderChildList([], null);
        var contentEl = document.getElementById(opts.contentId || '');
        if (contentEl)
          contentEl.innerHTML =
            '<div class="alert alert-info text-center">No children linked to this account. Contact the school office.</div>';
        return;
      }
      // Honor a deep-link (?child=<id>) selecting a specific child.
      var deepLinkId = new URLSearchParams(window.location.search).get('child');
      var target = deepLinkId
        ? state.children.find(function (c) { return String(c.id) === String(deepLinkId); }) || first
        : first;
      selectChild(target);
    })().catch(function (err) {
      var el = document.getElementById(opts.contentId || '');
      if (el) ParentCommon.showError(el, err.message || 'Failed to load this page.');
    });
  }

  ParentCommon.childPage = childPage;

  window.ParentCommon = ParentCommon;
})();