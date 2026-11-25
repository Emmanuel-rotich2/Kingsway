/* Live still-image capture for physical documents (phone or webcam). */
(function () {
  'use strict';

  if (window.KingswayDocumentCamera) return;

  var MODAL_ID = 'kingswayDocumentCameraModal';
  var AUTO_SELECTOR = 'input[type="file"][name^="doc_"], input[type="file"].workspace-document-file, input[type="file"][data-kw-document-capture]';
  var activeStream = null;

  function escapeHtml(value) {
    var element = document.createElement('div');
    element.textContent = String(value || '');
    return element.innerHTML;
  }

  function friendlyLabel(value) {
    var text = String(value || '')
      .replace(/^doc_/, '')
      .replace(/\[\]/g, '')
      .replace(/[_-]+/g, ' ')
      .replace(/\b\w/g, function (letter) { return letter.toUpperCase(); })
      .trim();
    return text || 'Document';
  }

  function notify(message, type) {
    if (typeof window.showNotification === 'function') {
      window.showNotification(message, type || 'warning');
    } else {
      console.warn(message);
    }
  }

  function stopStream() {
    if (!activeStream) return;
    activeStream.getTracks().forEach(function (track) { track.stop(); });
    activeStream = null;
  }

  function capturedFile(canvas, label) {
    return new Promise(function (resolve, reject) {
      canvas.toBlob(function (blob) {
        if (!blob) {
          reject(new Error('The captured image could not be prepared.'));
          return;
        }
        var base = String(label || 'document')
          .replace(/[^a-zA-Z0-9]+/g, '_')
          .replace(/^_+|_+$/g, '')
          .slice(0, 60) || 'document';
        var stamp = new Date().toISOString().replace(/[:.]/g, '-');
        resolve(new File([blob], base + '_' + stamp + '.jpg', {
          type: 'image/jpeg',
          lastModified: Date.now()
        }));
      }, 'image/jpeg', 0.92);
    });
  }

  function openCamera(options) {
    options = options || {};

    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      notify('Camera capture needs HTTPS and a supported browser. Please choose an existing file instead.', 'warning');
      return Promise.resolve(null);
    }
    if (!window.bootstrap || !window.bootstrap.Modal) {
      notify('The camera dialog is unavailable. Please choose an existing file instead.', 'error');
      return Promise.resolve(null);
    }

    return new Promise(function (resolve) {
      var existing = document.getElementById(MODAL_ID);
      if (existing) existing.remove();

      var label = friendlyLabel(options.label || 'Document');
      var modal = document.createElement('div');
      modal.id = MODAL_ID;
      modal.className = 'modal fade';
      modal.tabIndex = -1;
      modal.innerHTML =
        '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">' +
          '<div class="modal-content">' +
            '<div class="modal-header">' +
              '<h5 class="modal-title"><i class="bi bi-camera me-2"></i>Scan ' + escapeHtml(label) + '</h5>' +
              '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body">' +
              '<p class="small text-muted mb-3">Hold the whole document in frame, keep it still, then capture. Only the captured still image is uploaded.</p>' +
              '<div class="bg-dark rounded d-flex align-items-center justify-content-center" style="min-height:320px;overflow:hidden">' +
                '<video class="img-fluid w-100" playsinline muted autoplay aria-label="Live camera preview" style="display:block"></video>' +
                '<img class="img-fluid d-none" alt="Captured document preview" style="max-height:60vh;object-fit:contain">' +
              '</div>' +
              '<p class="small text-muted mt-2 mb-0" role="status">Opening camera…</p>' +
            '</div>' +
            '<div class="modal-footer flex-wrap">' +
              '<button type="button" class="btn btn-outline-primary kw-camera-start"><i class="bi bi-camera-video me-1"></i>Start camera</button>' +
              '<button type="button" class="btn btn-secondary kw-camera-capture" disabled><i class="bi bi-camera me-1"></i>Capture</button>' +
              '<button type="button" class="btn btn-outline-secondary kw-camera-retake" disabled><i class="bi bi-arrow-counterclockwise me-1"></i>Retake</button>' +
              '<button type="button" class="btn btn-success kw-camera-use" disabled><i class="bi bi-check2 me-1"></i>Use this image</button>' +
              '<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>' +
            '</div>' +
          '</div>' +
        '</div>';

      document.body.appendChild(modal);

      var video = modal.querySelector('video');
      var preview = modal.querySelector('img');
      var status = modal.querySelector('[role="status"]');
      var startButton = modal.querySelector('.kw-camera-start');
      var captureButton = modal.querySelector('.kw-camera-capture');
      var retakeButton = modal.querySelector('.kw-camera-retake');
      var useButton = modal.querySelector('.kw-camera-use');
      var instance = window.bootstrap.Modal.getOrCreateInstance(modal);
      var settled = false;

      function finish(file) {
        if (settled) return;
        settled = true;
        stopStream();
        instance.hide();
        resolve(file || null);
      }

      modal.addEventListener('hidden.bs.modal', function () {
        stopStream();
        modal.remove();
        if (!settled) finish(null);
      }, { once: true });

      startButton.addEventListener('click', async function () {
        startButton.disabled = true;
        status.textContent = 'Starting camera…';
        try {
          stopStream();
          activeStream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
              facingMode: { ideal: 'environment' },
              width: { ideal: 1920 },
              height: { ideal: 1080 }
            }
          });
          video.srcObject = activeStream;
          await video.play();
          video.classList.remove('d-none');
          preview.classList.add('d-none');
          status.textContent = 'Hold the document steady, then capture.';
          captureButton.disabled = false;
          retakeButton.disabled = true;
          useButton.disabled = true;
        } catch (error) {
          status.textContent = 'Camera unavailable: ' + (error && error.message ? error.message : 'permission was not granted');
          notify('Camera unavailable. Please choose an existing file instead.', 'error');
        } finally {
          startButton.disabled = false;
        }
      });

      captureButton.addEventListener('click', function () {
        if (!video.videoWidth || !video.videoHeight) {
          status.textContent = 'The camera is not ready yet. Please try again.';
          return;
        }
        var maxEdge = 2400;
        var scale = Math.min(1, maxEdge / Math.max(video.videoWidth, video.videoHeight));
        var canvas = document.createElement('canvas');
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        modal._kingswayCanvas = canvas;
        preview.src = canvas.toDataURL('image/jpeg', 0.92);
        video.classList.add('d-none');
        preview.classList.remove('d-none');
        status.textContent = 'Check the captured page, then use it or retake.';
        captureButton.disabled = true;
        retakeButton.disabled = false;
        useButton.disabled = false;
      });

      retakeButton.addEventListener('click', function () {
        video.classList.remove('d-none');
        preview.classList.add('d-none');
        status.textContent = 'Hold the document steady, then capture.';
        captureButton.disabled = false;
        retakeButton.disabled = true;
        useButton.disabled = true;
      });

      useButton.addEventListener('click', async function () {
        if (!modal._kingswayCanvas) {
          status.textContent = 'Capture the document first.';
          return;
        }
        useButton.disabled = true;
        status.textContent = 'Preparing captured image…';
        try {
          finish(await capturedFile(modal._kingswayCanvas, label));
        } catch (error) {
          status.textContent = error.message || 'The captured image could not be prepared.';
          useButton.disabled = false;
        }
      });

      instance.show();
      modal.addEventListener('shown.bs.modal', function () { startButton.click(); }, { once: true });
    });
  }

  function setInputFile(input, file) {
    try {
      var transfer = new DataTransfer();
      transfer.items.add(file);
      input.files = transfer.files;
    } catch (_) {
      // Older browsers keep the captured file on the input for the submit helpers.
    }
    input._kingswayCapturedFile = file;
    input.dataset.kwCameraCaptured = '1';
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function fileFromInput(input) {
    if (!input) return null;
    if (input._kingswayCapturedFile) return input._kingswayCapturedFile;
    return input.files && input.files.length ? input.files[0] : null;
  }

  function clearInput(input) {
    if (!input) return;
    try { input.value = ''; } catch (_) { /* read-only file inputs */ }
    delete input._kingswayCapturedFile;
    if (input.dataset) {
      delete input.dataset.kwCameraCaptured;
    }
  }

  /** Patch a FormData built before the camera fallback could set input.files. */
  function syncFormData(formData, form) {
    if (!formData || typeof formData.set !== 'function' || !form) return formData;
    var inputs = form.querySelectorAll
      ? form.querySelectorAll(AUTO_SELECTOR)
      : [];
    Array.prototype.forEach.call(inputs, function (input) {
      var captured = fileFromInput(input);
      if (!captured || input.disabled) return;
      formData.set(input.name, captured, captured.name);
    });
    return formData;
  }

  function inputLabel(input) {
    var row = input.closest ? input.closest('tr') : null;
    var select = row ? row.querySelector('select.workspace-document-type') : null;
    if (select && select.selectedOptions && select.selectedOptions.length) {
      return select.selectedOptions[0].textContent || friendlyLabel(select.value);
    }
    return friendlyLabel(input.name);
  }

  function attach(input, options) {
    if (!input || input.disabled || input.dataset.kwCameraAttached === '1') return;
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-secondary btn-sm mt-1';
    button.innerHTML = '<i class="bi bi-camera me-1"></i>Scan with camera';
    input.insertAdjacentElement('afterend', button);
    attachButton(button, input, options);
  }

  function attachButton(button, input, options) {
    if (!button || !input || button.dataset.kwCameraBound === '1') return;
    input.dataset.kwCameraAttached = '1';
    button.addEventListener('click', async function (event) {
      event.preventDefault();
      var label = (options && options.label) || inputLabel(input);
      if (typeof label === 'function') label = label();
      var file = await openCamera({ label: label });
      if (file) setInputFile(input, file);
    });
  }

  function autoBind(root) {
    (root || document).querySelectorAll(AUTO_SELECTOR).forEach(function (input) {
      if (input.dataset.kwCameraAttached === '1' || input.disabled) return;
      var siblingButton = input.parentElement
        ? input.parentElement.querySelector('[data-kw-document-capture]')
        : null;
      if (siblingButton) {
        attachButton(siblingButton, input, {
          label: siblingButton.dataset.kwDocumentLabel || function () { return inputLabel(input); }
        });
      } else {
        attach(input, { label: function () { return inputLabel(input); } });
      }
    });
  }

  function startAutoBind() {
    function observe() {
      autoBind(document);
      if (!window.MutationObserver) return;
      var observer = new MutationObserver(function (mutations) {
        var needsBind = false;
        mutations.forEach(function (mutation) {
          Array.from(mutation.addedNodes || []).forEach(function (node) {
            if (node.nodeType !== 1) return;
            if (node.matches && node.matches(AUTO_SELECTOR)) needsBind = true;
            if (node.querySelector && node.querySelector(AUTO_SELECTOR)) needsBind = true;
          });
        });
        if (needsBind) autoBind(document);
      });
      observer.observe(document.documentElement, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', observe, { once: true });
    } else {
      observe();
    }
  }

  window.KingswayDocumentCamera = Object.freeze({
    open: openCamera,
    bind: attachButton,
    attach: attach,
    fileFromInput: fileFromInput,
    clear: clearInput,
    syncFormData: syncFormData,
    refresh: autoBind
  });

  startAutoBind();
}());
