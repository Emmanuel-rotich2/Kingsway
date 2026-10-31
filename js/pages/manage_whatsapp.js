(function () {
  "use strict";

  function boot() {
    if (!document.getElementById("messagesTable")) return;
    const root = document.getElementById("kwConsole");
    window.MessagingConsole?.create({
      channel: "whatsapp",
      whatsappEnabled: root?.dataset.whatsappEnabled === "true",
      realtimeKey: "manageWhatsapp",
      permPrefix: "whatsapp",
      title: "WhatsApp",
    });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();
