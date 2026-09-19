(function () {
  'use strict';

  function escapeHtml(value) {
    const node = document.createElement('div');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  }

  function renderAnswer(answer, target) {
    const title = escapeHtml(answer.title || (answer.status === 'escalate' ? 'Let us help you' : 'Kingsway assistant'));
    const body = escapeHtml(answer.body || answer.message || 'Please contact the school for assistance.').replace(/\n/g, '<br>');
    const sources = Array.isArray(answer.sources) && answer.sources.length
      ? '<div class="public-ai-sources"><strong>Based on published information:</strong> ' + answer.sources.map(escapeHtml).join(', ') + '</div>'
      : '';
    const referral = answer.human_referral ? '<p class="small text-warning mt-2 mb-0"><i class="bi bi-person-check me-1"></i>' + escapeHtml(answer.human_referral) + '</p>' : '';
    target.innerHTML = '<strong>' + title + '</strong><p class="mb-0 mt-2">' + body + '</p>' + referral + sources;
  }

  function renderHistory(turns, target) {
    if (!target) return;
    target.replaceChildren();
    turns.forEach((turn) => {
      if (!turn || !['user', 'assistant'].includes(turn.role) || !String(turn.content || '').trim()) return;
      const item = document.createElement('div');
      item.className = 'public-ai-message ' + (turn.role === 'user' ? 'public-ai-message-user' : 'public-ai-message-assistant');
      const label = document.createElement('span');
      label.className = 'public-ai-message-label';
      label.textContent = turn.role === 'user' ? 'You' : 'Kingsway assistant';
      const content = document.createElement('p');
      content.className = 'mb-0';
      content.textContent = String(turn.content).slice(0, 5000);
      item.append(label, content);
      target.appendChild(item);
    });
    target.hidden = target.childElementCount === 0;
    if (!target.hidden) target.scrollTop = target.scrollHeight;
  }

  function renderSuggestions(items, container, input, form) {
    if (!container) return;
    container.replaceChildren();
    const questions = Array.isArray(items)
      ? items.map((item) => String(item || '').trim()).filter((item) => item.length > 0 && item.length <= 140).slice(0, 3)
      : [];
    if (!questions.length) {
      container.hidden = true;
      return;
    }
    questions.forEach((question) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'public-ai-suggestion';
      button.textContent = question;
      button.addEventListener('click', () => {
        input.value = question;
        form.requestSubmit();
      });
      container.appendChild(button);
    });
    container.hidden = false;
  }

  document.addEventListener('DOMContentLoaded', function () {
    const assistant = document.getElementById('public-ai-assistant');
    const panel = document.getElementById('public-ai-panel');
    const toggle = document.getElementById('public-ai-toggle');
    const close = document.getElementById('public-ai-close');
    const form = document.getElementById('public-ai-form');
    const input = document.getElementById('public-ai-question');
    const history = document.getElementById('public-ai-history');
    const answer = document.getElementById('public-ai-answer');
    const suggestions = document.getElementById('public-ai-suggestions');
    const status = document.getElementById('public-ai-status');
    const submit = document.getElementById('public-ai-submit');
    if (!assistant || !panel || !toggle || !form || !input || !answer || !submit || !history) return;

    const setOpen = (open) => {
      panel.hidden = !open;
      assistant.classList.toggle('is-open', open);
      toggle.hidden = open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) input.focus();
    };
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close?.addEventListener('click', () => setOpen(false));

    let conversation = [];
    try { conversation = JSON.parse(sessionStorage.getItem('kingsway_public_ai_conversation') || '[]'); } catch (_) { conversation = []; }
    if (!Array.isArray(conversation)) conversation = [];
    conversation = conversation.filter((turn) => turn && ['user', 'assistant'].includes(turn.role) && String(turn.content || '').trim()).slice(-40);
    renderHistory(conversation, history);
    const saveConversation = () => {
      // Keep the visible browser session transcript, while the API receives
      // only a smaller recent window below.
      conversation = conversation.slice(-40);
      try { sessionStorage.setItem('kingsway_public_ai_conversation', JSON.stringify(conversation)); } catch (_) {}
    };
    suggestions?.querySelectorAll('.public-ai-suggestion').forEach((button) => {
      button.addEventListener('click', () => { input.value = button.textContent.trim(); form.requestSubmit(); });
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); }
    });

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const question = input.value.trim();
      if (!question || question.length > 500) {
        status.textContent = 'Enter a question of up to 500 characters.';
        return;
      }
      submit.disabled = true;
      input.value = '';
      renderHistory(conversation, history);
      if (suggestions) suggestions.hidden = true;
      // Keep one loading message only. The answer region is reserved for the
      // returned answer; the status line is the single live progress region.
      status.textContent = 'Preparing an answer…';
      answer.setAttribute('aria-busy', 'true');
      answer.innerHTML = '';
      try {
        const result = await window.callAPI('/public/ai-faq', 'POST', { question, conversation: conversation.slice(-12) });
        renderAnswer(result || {}, answer);
        conversation.push({ role: 'user', content: question });
        conversation.push({ role: 'assistant', content: result?.body || result?.message || '' });
        saveConversation();
        // Keep the latest answer in the rich answer panel and older turns in
        // the transcript, avoiding a duplicate copy of the newest response.
        renderHistory(conversation.slice(0, -2), history);
        renderSuggestions(result?.suggested_questions, suggestions, input, form);
        status.textContent = result?.status === 'escalate' ? 'Human follow-up recommended.' : 'Answer prepared from published sources.';
      } catch (error) {
        input.value = question;
        answer.innerHTML = '<p class="small text-warning mb-0"><i class="bi bi-exclamation-circle me-1"></i>The assistant is temporarily unavailable. Please use Contact Us for help.</p>';
        status.textContent = error?.message || 'Request failed.';
      } finally {
        answer.removeAttribute('aria-busy');
        submit.disabled = false;
      }
    });
  });
})();
