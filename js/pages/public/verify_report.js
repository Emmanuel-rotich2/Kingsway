/**
 * Report verification page: looks up the code printed (as a QR) on a report
 * book and shows whether it is genuine, with the summary printed on it.
 */
(function () {
  const form = document.getElementById('vrForm');
  const input = document.getElementById('vrCode');
  const result = document.getElementById('vrResult');
  if (!form || !input || !result) return;

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const row = (label, value) => value === null || value === undefined || value === '' ? ''
    : `<div class="vr-row"><span>${esc(label)}</span><span>${esc(value)}</span></div>`;

  async function verify(code) {
    const clean = String(code || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (clean.length !== 12) {
      result.innerHTML = '<div class="vr-status bad"><i class="bi bi-exclamation-circle" aria-hidden="true"></i>The code has 12 letters and numbers. Check it and try again.</div>';
      return;
    }
    result.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Checking…</div>';
    try {
      const data = await window.callAPI('/public/report-verification', 'GET', null, { code: clean });
      const s = data?.summary || {};
      const valid = data?.status === 'valid';
      result.innerHTML = `
        <div class="vr-status ${valid ? 'ok' : 'bad'}">
          <i class="bi bi-${valid ? 'patch-check-fill' : 'x-octagon-fill'}" aria-hidden="true"></i>
          ${valid ? 'This report is genuine.' : 'This report has been withdrawn by the school.'}
        </div>
        <div class="vr-rows">
          ${row('Learner', s.learner_name)}
          ${row('Admission number', s.admission_no)}
          ${row('Grade and stream', s.class)}
          ${row('Term', [s.term, s.year].filter(Boolean).join(' '))}
          ${row('Term average', s.overall_percentage !== null && s.overall_percentage !== undefined ? s.overall_percentage + '%' : '')}
          ${row('Overall level', s.overall_grade)}
          ${row('Issued', data?.issued_at ? new Date(String(data.issued_at).replace(' ', 'T')).toLocaleDateString('en-KE', { day: 'numeric', month: 'long', year: 'numeric' }) : '')}
        </div>
        <p class="vr-note">The details above must match the printed report. If they do not, contact the school office.</p>`;
    } catch (error) {
      result.innerHTML = `<div class="vr-status bad"><i class="bi bi-x-octagon-fill" aria-hidden="true"></i>${esc(error?.message || 'No report matches this code.')}</div>`;
    }
  }

  form.addEventListener('submit', (event) => { event.preventDefault(); verify(input.value); });
  const fromLink = new URLSearchParams(window.location.search).get('code');
  if (fromLink) { input.value = fromLink; verify(fromLink); }
})();
