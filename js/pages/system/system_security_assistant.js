(function () {
  "use strict";
  const panel = document.getElementById("systemSecurityAiPanel");
  if (!panel || !window.API?.system) return;
  const state = panel.querySelector("[data-security-state]");
  const kpis = panel.querySelector("[data-security-kpis]");
  const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;", "'":"&#39;"}[c]));
  async function load() {
    try {
      const response = await window.API.system.getSecuritySignals();
      const data = response?.data ?? response ?? {};
      const items = [["Failed logins (24h)", data.failed_login_count], ["Permission denials (24h)", data.permission_denied_count], ["Security incidents (24h)", data.security_incident_count]];
      kpis.innerHTML = items.map(([label, value]) => `<div class="col-6 col-md-4"><div class="border rounded p-2"><div class="small text-muted">${esc(label)}</div><strong>${esc(value || 0)}</strong></div></div>`).join("");
      state.textContent = `Aggregates refreshed ${esc(data.generated_at || "")}. No identities or IP addresses are sent to the provider.`;
    } catch (_) { state.textContent = "Security aggregates are temporarily unavailable."; }
  }
  panel.querySelector("[data-security-generate]")?.addEventListener("click", async () => {
    try { await window.API.system.queueAiSecurityReview(); state.textContent = "Security advisory queued for administrator review."; }
    catch (e) { state.textContent = e?.message || "Unable to queue security review."; }
  });
  load();
}());
