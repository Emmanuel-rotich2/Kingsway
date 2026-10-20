(function () {
  "use strict";

  function boot() {
    if (!document.getElementById("messagesTable")) return;
    window.MessagingConsole?.create({
      channel: "whatsapp",
      realtimeKey: "manageWhatsapp",
      permPrefix: "whatsapp",
      title: "WhatsApp",
    });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();
