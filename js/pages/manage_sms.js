(function () {
  "use strict";

  function boot() {
    if (!document.getElementById("messagesTable")) return;
    window.MessagingConsole?.create({
      channel: "sms",
      realtimeKey: "manageSms",
      permPrefix: "sms",
      title: "SMS",
    });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();
