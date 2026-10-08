/** Canonical browser client for backend DownloadService and PrintService. */
window.KingswayFileLifecycle = Object.freeze({
  resolveUrl(value) {
    const raw = String(value || '').trim();
    const fallback = this.assetUrl('students', 'avatar.jpg');
    if (!raw) return fallback;
    if (/^data:/i.test(raw)) return raw;
    const base = String(window.APP_BASE || '').replace(/\/$/, '');
    const uploadBase = String(window.UPLOAD_URL || `${base}/uploads`).replace(/\/$/, '');
    if (!base && !uploadBase) return raw;
    try {
      // Database values are upload-relative references. Re-anchor them to the
      // runtime upload URL so the same record works in every environment.
      if (!/^(https?:)?\/\//i.test(raw) && !raw.startsWith('/')) {
        return `${uploadBase}/${raw.replace(/^uploads\//i, '').replace(/^\/+/, '')}`;
      }
      const parsed = new URL(raw, window.location.origin);
      const configured = new URL(`${base}/`, window.location.origin);
      const basePath = configured.pathname.replace(/\/$/, '');
      let path = parsed.pathname;
      const uploadPath = new URL(`${uploadBase}/`, window.location.origin).pathname.replace(/\/$/, '');
      if (/^https?:\/\//i.test(raw) && parsed.origin !== window.location.origin && !path.includes('/uploads/')) {
        return raw;
      }
      if (uploadPath && path.indexOf(`${uploadPath}/`) === 0) {
        return `${uploadBase}/${path.slice(uploadPath.length).replace(/^\/+/, '')}${parsed.search}${parsed.hash}`;
      }
      if (basePath && path.indexOf(`${basePath}/`) === 0) path = path.slice(basePath.length);
      if (/^\/uploads\//i.test(path)) {
        return `${uploadBase}/${path.replace(/^\/uploads\//i, '')}${parsed.search}${parsed.hash}`;
      }
      if (/^\/(?:students|staff|admissions|academic|school_assets)\//i.test(path)) {
        return `${uploadBase}/${path.replace(/^\/+/, '')}${parsed.search}${parsed.hash}`;
      }
      return `${base}/${path.replace(/^\/+/, '')}${parsed.search}${parsed.hash}`;
    } catch (error) {
      return fallback;
    }
  },
  assetUrl(...segments) {
    const base = String(window.UPLOAD_URL || `${window.APP_BASE || ''}/uploads`).replace(/\/$/, '');
    const clean = segments
      .flat()
      .filter((part) => part !== null && part !== undefined && String(part) !== '')
      .map((part) => encodeURIComponent(String(part).replace(/^\/+|\/+$/g, '')));
    return `${base}/${clean.join('/')}`;
  },
  avatarUrl() {
    return this.assetUrl('students', 'avatar.jpg');
  },
  downloadBlob(blob, filename = 'download') {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
  openBlob(blob) {
    const url = URL.createObjectURL(blob);
    const popup = window.open(url, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(url), 60000);
    return popup;
  },
  async exportText(content, filename, mimeType = 'text/csv;charset=utf-8') {
    const requestBody = { content, filename, mime_type: mimeType };
    const client = window.API?.apiCall || window.API?.callAPI || window.callAPI;
    let data;

    if (typeof client === 'function') {
      data = await client('/download/export', 'POST', requestBody);
    } else {
      await window.AuthContext?.ready?.();
      const token = window.AuthContext?.getToken?.();
      const apiBase = window.API_BASE_URL || `${String(window.APP_BASE || '').replace(/\/$/, '')}/api`;
      const response = await fetch(`${apiBase}/download/export`, {
        method: 'POST',
        credentials: window.location.hostname === 'localhost' ? 'same-origin' : 'include',
        headers: {
          'Content-Type': 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify(requestBody),
      });
      const payload = await response.json();
      if (!response.ok || payload.status !== 'success') {
        throw new Error(payload.message || 'Export failed');
      }
      data = payload.data;
    }

    const downloadUrl = data?.download_url || data?.url;
    if (!downloadUrl) {
      throw new Error('Export completed without a download URL');
    }
    window.location.assign(downloadUrl);
  },
  open(file) {
    const url = typeof file === 'string' ? file : file?.preview_url || file?.download_url || file?.url;
    if (!url) throw new Error('File URL unavailable');
    const isPdf = /\.pdf(?:$|[?#])/i.test(url) || /\/api\/download\/(?:print|public)/i.test(url);
    if (isPdf && typeof window.PrintManager?.openDocument === 'function') {
      return window.PrintManager.openDocument(url, { title: (typeof file === 'object' && (file.title || file.filename)) || 'Document preview' });
    }
    window.open(url, '_blank', 'noopener,noreferrer');
  },
  download(file) {
    const url = typeof file === 'string' ? file : file?.download_url || file?.url;
    if (!url) throw new Error('Download URL unavailable');
    window.location.assign(url);
  },
});
