/**
 * js/pages/parents/messages.js — Messages with school (read + compose).
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  function renderMessages(msgs, child, el) {
    var countEl = document.getElementById('ppMsgCount');
    if (countEl) countEl.textContent = (msgs || []).length + ' message(s)';
    var html = '<div class="d-flex justify-content-between align-items-center mb-3">' +
      '<h6 class="mb-0 text-muted">Messages with School</h6>' +
      '<button class="btn btn-success btn-sm rounded-pill" id="btnComposeMessage"><i class="bi bi-pencil me-1"></i>Compose</button></div>';
    if (!msgs.length) html += '<div class="alert alert-info">No messages yet. Click "Compose" to send a message to the school.</div>';
    else {
      html += '<div class="list-group mb-3">';
      msgs.forEach(function (m) {
        var isParent = m.sender_type === 'parent';
        html += '<div class="list-group-item list-group-item-action ' + (isParent ? '' : 'bg-light') + '">' +
          '<div class="d-flex justify-content-between"><small class="fw-bold">' + P.esc(m.sender_name || (isParent ? 'You' : 'School')) + '</small>' +
          '<small class="text-muted">' + (m.created_at || '').substring(0, 16) + '</small></div>' +
          '<strong class="d-block small">' + P.esc(m.subject || '') + '</strong>' +
          '<p class="mb-0 small">' + P.esc(m.message || '') + '</p></div>';
      });
      html += '</div>';
    }
    html += '<div id="composeForm" style="display:none" class="card border-0 shadow-sm p-3">' +
      '<h6 class="text-muted mb-3">Send Message to School</h6>' +
      '<div class="mb-2"><input type="text" id="msgSubject" class="form-control form-control-sm" placeholder="Subject"></div>' +
      '<div class="mb-2"><textarea id="msgBody" class="form-control" rows="3" placeholder="Your message..."></textarea></div>' +
      '<div id="msgError" class="alert alert-danger d-none"></div>' +
      '<div><button class="btn btn-success btn-sm" id="btnSendMessage"><i class="bi bi-send me-1"></i>Send</button>' +
      '<button class="btn btn-outline-secondary btn-sm ms-2" id="btnCancelMessage">Cancel</button></div></div>';
    el.innerHTML = html;
    var composeBtn = document.getElementById('btnComposeMessage');
    if (composeBtn) composeBtn.addEventListener('click', function () { document.getElementById('composeForm').style.display = 'block'; });
    var cancelBtn = document.getElementById('btnCancelMessage');
    if (cancelBtn) cancelBtn.addEventListener('click', function () {
      document.getElementById('composeForm').style.display = 'none';
      document.getElementById('msgError').classList.add('d-none');
    });
    var sendBtn = document.getElementById('btnSendMessage');
    if (sendBtn) sendBtn.addEventListener('click', function () { sendMessage(child.id, el); });
  }

  function sendMessage(studentId, el) {
    var subject = (document.getElementById('msgSubject').value || '').trim();
    var message = (document.getElementById('msgBody').value || '').trim();
    var errEl = document.getElementById('msgError');
    errEl.classList.add('d-none');
    if (!subject || !message) {
      errEl.textContent = 'Subject and message are required';
      errEl.classList.remove('d-none');
      return;
    }
    P.apiFetch('/send-message', 'POST', { student_id: studentId, subject: subject, message: message })
      .then(function () {
        document.getElementById('msgSubject').value = '';
        document.getElementById('msgBody').value = '';
        document.getElementById('composeForm').style.display = 'none';
        P.showLoading(el);
        return P.apiFetch('/messages/' + studentId, 'GET');
      })
      .then(function (r) { renderMessages(r.data !== undefined ? r.data : r, null, el); })
      .catch(function (err) {
        errEl.textContent = err.message || 'Failed to send message';
        errEl.classList.remove('d-none');
      });
  }

  P.childPage({
    contentId: 'ppMessagesContent',
    defaultTab: 'messages',
    loadTab: function (tab, child, el) {
      P.apiFetch('/messages/' + child.id, 'GET')
        .then(function (r) { renderMessages(r.data !== undefined ? r.data : r, child, el); })
        .catch(function (e) { P.showError(el, e.message); });
    },
  });
})();