/** Canonical browser client for backend DownloadService and PrintService. */
window.KingswayFileLifecycle = Object.freeze({
  assetUrl(...segments) {
    const base = String(window.APP_BASE || '').replace(/\/$/, '');
    const clean = segments
      .flat()
      .filter((part) => part !== null && part !== undefined && String(part) !== '')
      .map((part) => encodeURIComponent(String(part).replace(/^\/+|\/+$/g, '')));
    return `${base}/uploads/${clean.join('/')}`;
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
    const response = await fetch(`${window.API_BASE_URL || '/api'}/download/export`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(window.AuthContext?.getAuthHeaders?.() || {}),
      },
      body: JSON.stringify({ content, filename, mime_type: mimeType }),
    });
    const payload = await response.json();
    if (!response.ok || payload.status !== 'success') {
      throw new Error(payload.message || 'Export failed');
    }
    window.location.assign(payload.data.download_url);
  },
  open(file) {
    const url = typeof file === 'string' ? file : file?.preview_url || file?.download_url || file?.url;
    if (!url) throw new Error('File URL unavailable');
    window.open(url, '_blank', 'noopener,noreferrer');
  },
  download(file) {
    const url = typeof file === 'string' ? file : file?.download_url || file?.url;
    if (!url) throw new Error('Download URL unavailable');
    window.location.assign(url);
  },
});
