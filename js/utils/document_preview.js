/* In-system preview for admission documents. */
(function () {
  'use strict';

  function escapeHtml(value) {
    var element = document.createElement('div');
    element.textContent = String(value || '');
    return element.innerHTML;
  }

  function sameOriginUrl(value) {
    var raw = String(value || '').trim();
    try {
      var parsed = new URL(raw, window.location.href);
      return parsed.origin === window.location.origin ? parsed.pathname + parsed.search : '';
    } catch (_) { return raw; }
  }

  function isOfficeDocument(source) {
    return /\.(?:docx?|xlsx?|pptx?|odt|ods|odp|odg|rtf)$/i.test(source);
  }

  function renderPdf(target, objectUrl, label) {
    // Firefox's PDF viewer is more reliable in an iframe than in an object
    // element when the authenticated file has first been fetched into a Blob.
    // Keep the object fallback for browsers that do not expose the PDF viewer
    // in an iframe.
    target.innerHTML = '<iframe src="' + escapeHtml(objectUrl) + '" title="' + escapeHtml(label || 'PDF document') + '" style="width:100%;height:70vh;border:0;background:#fff"></iframe>' +
      '<noscript><p class="text-muted">PDF preview requires JavaScript.</p></noscript>';
  }

  function renderShell(label, source) {
    var modal = document.createElement('div');
    modal.id = 'kingswayDocumentPreviewModal';
    modal.className = 'modal fade kw-detail-modal';
    modal.tabIndex = -1;
    modal.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">' +
      '<div class="modal-header"><h5 class="modal-title"><i class="bi bi-file-earmark me-2"></i>' + escapeHtml(label || 'Document preview') + '</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>' +
      '<div class="modal-body"><div class="kw-document-preview-content text-center py-5"><div class="spinner-border text-success" role="status"></div><p class="text-muted mt-3 mb-0">Loading document…</p></div>' +
      '<div class="mt-3 text-end"><a class="btn btn-outline-secondary btn-sm kw-document-open" href="' + escapeHtml(source) + '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Open separately</a></div></div>' +
      '</div></div>';
    document.body.appendChild(modal);
    modal.addEventListener('hidden.bs.modal', function () { modal.remove(); }, { once: true });
    bootstrap.Modal.getOrCreateInstance(modal).show();
    return modal;
  }

  async function loadPreview(modal, source, label, applicationId, documentId) {
    var target = modal.querySelector('.kw-document-preview-content');
    var lower = source.split('?')[0].toLowerCase();
    var isImage = /\.(?:png|jpe?g|gif|webp|bmp|svg)$/i.test(lower);
    var isPdf = /\.pdf$/i.test(lower);

    try {
      if (isOfficeDocument(source)) {
        var apiCall = window.API?.callAPI || window.callAPI;
        if (typeof apiCall !== 'function') throw new Error('The secure document preview service is unavailable.');
        if (!applicationId) throw new Error('The application context for this document is missing.');
        var convertedResponse = await apiCall('/admission/applications/' + encodeURIComponent(applicationId) + '/document-preview?document_id=' + encodeURIComponent(documentId) + '&url=' + encodeURIComponent(source), 'GET');
        var converted = convertedResponse?.data || convertedResponse;
        if (!converted?.content_base64) throw new Error(convertedResponse?.message || 'This document could not be converted for preview.');
        var binary = atob(converted.content_base64);
        var bytes = new Uint8Array(binary.length);
        for (var index = 0; index < binary.length; index += 1) bytes[index] = binary.charCodeAt(index);
        var convertedUrl = URL.createObjectURL(new Blob([bytes], { type: converted.mime || 'application/pdf' }));
        modal.addEventListener('hidden.bs.modal', function () { URL.revokeObjectURL(convertedUrl); }, { once: true });
        renderPdf(target, convertedUrl, label || 'Document preview');
        return;
      }
      var response = await fetch(source, { credentials: 'same-origin' });
      if (!response.ok) throw new Error('Document could not be loaded (' + response.status + ')');
      var blob = await response.blob();
      var objectUrl = URL.createObjectURL(blob);
      modal.addEventListener('hidden.bs.modal', function () { URL.revokeObjectURL(objectUrl); }, { once: true });
      if (isImage || String(blob.type || '').startsWith('image/')) {
        target.innerHTML = '<img src="' + escapeHtml(objectUrl) + '" alt="' + escapeHtml(label || 'Document') + '" class="img-fluid rounded border shadow-sm" style="max-height:70vh;object-fit:contain;">';
      } else if (isPdf || blob.type === 'application/pdf') {
        renderPdf(target, objectUrl, label || 'PDF document');
      } else if (/text\//i.test(blob.type || '') || /\.(?:txt|csv|json|xml)$/i.test(lower)) {
        target.innerHTML = '<pre class="text-start bg-light border rounded p-3" style="max-height:70vh;overflow:auto;white-space:pre-wrap;"></pre>';
        target.querySelector('pre').textContent = await blob.text();
      } else {
        target.innerHTML = '<i class="bi bi-file-earmark display-3 text-secondary"></i><p class="text-muted mt-3 mb-0">This document is available, but this browser does not provide an inline renderer for its format.</p>';
      }
    } catch (error) {
      target.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>' + escapeHtml(error.message || 'The document could not be previewed.') + '<br><small>Use “Open separately” below to access the original file.</small></div>';
    }
  }

  function openPreview(url, label, applicationId, documentId) {
    var source = sameOriginUrl(url).trim();
    if (!source || /^\d+$/.test(source)) return;

    document.getElementById('kingswayDocumentPreviewModal')?.remove();
    var modal = renderShell(label, source);
    loadPreview(modal, source, label, applicationId, documentId);
  }

  window.KingswayDocumentPreview = { open: openPreview };
  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-kw-document-preview]');
    if (!trigger) return;
    event.preventDefault();
    openPreview(trigger.dataset.url, trigger.dataset.label, trigger.dataset.applicationId || '', trigger.dataset.documentId || '');
  });
}());
