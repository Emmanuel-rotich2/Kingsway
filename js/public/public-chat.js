/**
 * Public visitor assistant widget.
 *
 * Calls the Node agent gateway (same origin resolver used by the authenticated
 * app) and falls back to the PHP public assistant endpoint if the gateway is
 * unavailable. Lead handoff posts to /api/public/leads; Buni and Daraja are
 * payment gateways and are not used for messaging here.
 */
(function () {
  'use strict';

  if (window.KingwayPublicChatLoaded) return;
  window.KingwayPublicChatLoaded = true;

  var APP_BASE = String(window.APP_BASE || '').replace(/\/+$/, '');
  var REALTIME_ORIGIN = String(window.KINGSWAY_REALTIME_ORIGIN || '').replace(/\/+$/, '');
  var STORAGE_KEY = 'kingsway_public_chat_conversation';
  var MAX_TURNS = 6;

  function endpoint() {
    return REALTIME_ORIGIN
      ? REALTIME_ORIGIN + '/api/agents/public-call'
      : APP_BASE + '/api/public/ai-faq';
  }

  function phpEndpoint() {
    return APP_BASE + '/api/public/ai-faq';
  }

  function loadConversation() {
    try {
      var raw = sessionStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      return [];
    }
  }

  function saveConversation(turns) {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(turns.slice(-MAX_TURNS * 2)));
    } catch (e) {
      /* storage disabled */
    }
  }

  function create(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function postJson(url, payload) {
    var controller = typeof AbortController === 'function' ? new AbortController() : null;
    var timer = controller ? setTimeout(function () { controller.abort(); }, 30000) : null;
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
      signal: controller ? controller.signal : undefined
    }).then(function (response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);
      return response.json();
    }).then(function (json) {
      return json && json.data !== undefined ? json.data : json;
    }).finally(function () {
      if (timer) clearTimeout(timer);
    });
  }

  function askAssistant(question, conversation) {
    return postJson(endpoint(), { question: question, conversation: conversation })
      .catch(function () {
        if (endpoint() === phpEndpoint()) throw new Error('assistant unavailable');
        return postJson(phpEndpoint(), { question: question, conversation: conversation });
      });
  }

  function submitLead(payload) {
    return postJson(APP_BASE + '/api/public/leads', payload);
  }

  function buildWidget() {
    var root = create('div', 'kw-chat');
    root.innerHTML = [
      '<button type="button" class="kw-chat-launcher" aria-label="Open school assistant">',
      '  <i class="bi bi-chat-dots-fill" aria-hidden="true"></i>',
      '  <span class="kw-chat-launcher-text">Ask Kingsway</span>',
      '</button>',
      '<section class="kw-chat-panel" role="dialog" aria-modal="false" aria-label="Kingsway school assistant" hidden>',
      '  <header class="kw-chat-header">',
      '    <div><strong>Kingsway Assistant</strong><span>Admissions, fees, term dates</span></div>',
      '    <button type="button" class="kw-chat-close" aria-label="Close assistant"><i class="bi bi-x-lg"></i></button>',
      '  </header>',
      '  <div class="kw-chat-messages" role="log" aria-live="polite"></div>',
      '  <form class="kw-chat-form">',
      '    <label class="visually-hidden" for="kwChatInput">Your question</label>',
      '    <input id="kwChatInput" class="kw-chat-input" type="text" maxlength="500" autocomplete="off" placeholder="Ask about admissions, fees, term dates…" required>',
      '    <button type="submit" class="kw-chat-send" aria-label="Send question"><i class="bi bi-send-fill"></i></button>',
      '  </form>',
      '  <p class="kw-chat-note">Answers use published school information. For a callback, ask to speak to admissions.</p>',
      '</section>'
    ].join('');
    document.body.appendChild(root);
    return root;
  }

  function init() {
    var features = window.AI_FEATURES && window.AI_FEATURES.publicAssistant;
    if (features && features.enabled === false) return;

    var root = buildWidget();
    var launcher = root.querySelector('.kw-chat-launcher');
    var panel = root.querySelector('.kw-chat-panel');
    var messages = root.querySelector('.kw-chat-messages');
    var form = root.querySelector('.kw-chat-form');
    var input = root.querySelector('.kw-chat-input');
    var close = root.querySelector('.kw-chat-close');
    var conversation = loadConversation();
    var busy = false;

    function openPanel() {
      panel.hidden = false;
      launcher.hidden = true;
      if (!messages.childElementCount) {
        addMessage('assistant', 'Hello! How can I help you with Kingsway Preparatory School?');
      }
      setTimeout(function () { input.focus(); }, 50);
    }

    function closePanel() {
      panel.hidden = true;
      launcher.hidden = false;
    }

    function addMessage(role, text) {
      var bubble = create('div', 'kw-chat-message kw-chat-' + role);
      bubble.textContent = text;
      messages.appendChild(bubble);
      messages.scrollTop = messages.scrollHeight;
      return bubble;
    }

    function setBusy(value) {
      busy = value;
      input.disabled = value;
      form.querySelector('.kw-chat-send').disabled = value;
    }

    function renderAnswer(answer) {
      var title = answer && answer.title ? String(answer.title) : 'Kingsway';
      var body = answer && answer.body
        ? String(answer.body)
        : (answer && answer.message ? String(answer.message) : '');
      var bubble = addMessage('assistant', title + (body ? '\n\n' + body : ''));

      if (Array.isArray(answer.next_steps) && answer.next_steps.length) {
        var list = create('ul', 'kw-chat-steps');
        answer.next_steps.slice(0, 5).forEach(function (step) {
          list.appendChild(create('li', '', String(step)));
        });
        bubble.appendChild(list);
      }

      if (Array.isArray(answer.suggested_questions) && answer.suggested_questions.length) {
        var suggestions = create('div', 'kw-chat-suggestions');
        answer.suggested_questions.slice(0, 3).forEach(function (question) {
          var button = create('button', 'kw-chat-suggestion', String(question));
          button.type = 'button';
          button.addEventListener('click', function () {
            input.value = String(question);
            form.dispatchEvent(new Event('submit', { cancelable: true }));
          });
          suggestions.appendChild(button);
        });
        bubble.appendChild(suggestions);
      }

      var escalated = answer && (answer.status === 'escalate' || answer.human_referral);
      if (escalated) showHandoff(bubble, answer.human_referral);
    }

    function showHandoff(afterNode, referralText) {
      if (root.querySelector('.kw-chat-handoff')) return;
      var features = window.AI_FEATURES && window.AI_FEATURES.publicAssistant;
      if (features && features.handoffEnabled === false) return;
      var card = create('div', 'kw-chat-handoff');
      card.innerHTML = [
        '<h6><i class="bi bi-headset"></i> Connect with Kingsway Admissions</h6>',
        '<p>Leave your details and our team will call or WhatsApp you.</p>',
        '<form>',
        '  <label>Your name<input name="name" maxlength="120" required></label>',
        '  <label>Phone / WhatsApp<input name="phone" maxlength="40" inputmode="tel" required></label>',
        '  <label>What do you need help with?<textarea name="details" rows="2" maxlength="2000" required></textarea></label>',
        '  <button type="submit">Request a callback</button>',
        '</form>'
      ].join('');
      var intro = card.querySelector('p');
      if (referralText) intro.textContent = String(referralText);
      messages.insertBefore(card, afterNode ? afterNode.nextSibling : null);
      messages.scrollTop = messages.scrollHeight;

      card.querySelector('form').addEventListener('submit', function (event) {
        event.preventDefault();
        var formData = new FormData(event.currentTarget);
        var submit = event.currentTarget.querySelector('button[type="submit"]');
        submit.disabled = true;
        submit.textContent = 'Sending…';
        submitLead({
          name: String(formData.get('name') || '').trim(),
          phone: String(formData.get('phone') || '').trim(),
          details: String(formData.get('details') || '').trim()
        }).then(function () {
          card.innerHTML = '<p class="kw-chat-lead-ok"><i class="bi bi-check-circle-fill"></i> Thank you. Our admissions team will contact you shortly.</p>';
          messages.scrollTop = messages.scrollHeight;
        }).catch(function () {
          submit.disabled = false;
          submit.textContent = 'Request a callback';
          var error = card.querySelector('.kw-chat-lead-error') || create('p', 'kw-chat-lead-error');
          error.textContent = 'Could not send your details. Please try again or call the school office.';
          card.appendChild(error);
        });
      });
    }

    launcher.addEventListener('click', openPanel);
    close.addEventListener('click', closePanel);

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var question = String(input.value || '').trim();
      if (!question || busy) return;
      input.value = '';
      addMessage('user', question);
      conversation.push({ role: 'user', content: question });
      saveConversation(conversation);
      setBusy(true);
      var pending = addMessage('assistant', 'Thinking…');
      pending.classList.add('kw-chat-pending');

      askAssistant(question, conversation.slice(-MAX_TURNS))
        .then(function (answer) {
          pending.remove();
          var summary = answer && (answer.title || answer.body || answer.message)
            ? [answer.title, answer.body, answer.message].filter(Boolean).join('\n\n')
            : 'I could not find that information.';
          conversation.push({ role: 'assistant', content: String(summary).slice(0, 500) });
          saveConversation(conversation);
          renderAnswer(answer || {});
        })
        .catch(function () {
          pending.remove();
          addMessage('assistant', 'The assistant is temporarily unavailable. Please call the school office or leave your details below.');
          showHandoff(messages.lastElementChild, null);
        })
        .finally(function () { setBusy(false); });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
