/**
 * Kingsway canonical file lifecycle client.
 *
 * Pages consume normalized URLs returned by the backend and never construct
 * physical upload paths.
 */
window.KingswayFiles = Object.freeze({
  resolve(file, fallback = "") {
    if (!file) return fallback;

    if (typeof file === "string") {
      return file;
    }

    return (
      file.preview_url
      || file.download_url
      || file.url
      || fallback
    );
  },

  open(file, fallback = "") {
    const url = this.resolve(file, fallback);
    if (!url) {
      throw new Error("The requested file is not available.");
    }
    window.open(url, "_blank", "noopener,noreferrer");
  },

  download(file, fallback = "") {
    const url = typeof file === "object" && file
      ? (file.download_url || file.url || fallback)
      : this.resolve(file, fallback);

    if (!url) {
      throw new Error("The requested download is not available.");
    }

    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.rel = "noopener";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
  },
});
