const StaffIdCardsController = {
  cards: [],
  staff: [],
  previewHtml: "",
  previewUrl: "",

  async init() {
    if (!(await StaffAccess.require("staff.id_cards.view"))) return;

    this.bind();
    await Promise.all([this.loadStaff(), this.loadCards()]);

    const expiry = document.getElementById("cardExpiry");
    if (expiry) {
      const date = new Date();
      date.setFullYear(date.getFullYear() + 2);
      expiry.value = date.toISOString().slice(0, 10);
    }
  },

  bind() {
    document
      .getElementById("newStaffCardBtn")
      ?.addEventListener("click", () => {
        const modal = document.getElementById("generateStaffCardModal");
        if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
      });

    document
      .getElementById("generateStaffCardSubmit")
      ?.addEventListener("click", () => this.generate());

    document
      .getElementById("refreshCardsBtn")
      ?.addEventListener("click", () => this.loadCards());

    document
      .getElementById("cardStatus")
      ?.addEventListener("change", () => this.render());

    document
      .getElementById("cardSearch")
      ?.addEventListener("input", () => this.render());

    document
      .getElementById("printStaffCardBtn")
      ?.addEventListener("click", () => this.printPreview());
  },

  async loadStaff() {
    try {
      const data = await API.staff.list({ limit: 500 });
      this.staff = this.staffList(data);

      const select = document.getElementById("cardStaffId");
      if (!select) return;

      select.innerHTML =
        '<option value="">Select staff</option>' +
        this.staff
          .map(
            (staff) =>
              `<option value="${Number(staff.id)}">${this.esc(staff.staff_no || "")} — ${this.esc(
                `${staff.first_name || ""} ${staff.last_name || ""}`.trim(),
              )}</option>`,
          )
          .join("");
    } catch (error) {
      this.notify(error?.message || "Failed to load staff members", "error");
    }
  },

  staffList(data) {
    return Array.isArray(data)
      ? data
      : data?.staff || data?.data?.staff || data?.items || data?.data || [];
  },

  async loadCards() {
    const body = document.getElementById("staffCardsBody");
    if (!body) return;

    body.innerHTML =
      '<tr><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>';

    try {
      const data = await API.staff.getIdCards();
      this.cards = Array.isArray(data) ? data : data?.items || [];
      this.render();
    } catch (error) {
      body.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-4">${this.esc(
        error?.message || "Failed to load staff ID cards",
      )}</td></tr>`;
    }
  },

  render() {
    const query = (document.getElementById("cardSearch")?.value || "").toLowerCase();
    const status = document.getElementById("cardStatus")?.value || "";

    const rows = this.cards.filter((card) => {
      const matchesStatus = !status || card.status === status;
      const searchable = `${card.first_name || ""} ${card.last_name || ""} ${
        card.staff_no || ""
      } ${card.card_number || ""}`.toLowerCase();
      return matchesStatus && (!query || searchable.includes(query));
    });

    this.setText("cardTotal", this.cards.length);
    this.setText(
      "cardGenerated",
      this.cards.filter((card) => card.status === "generated").length,
    );
    this.setText(
      "cardIssued",
      this.cards.filter((card) => card.status === "issued").length,
    );
    this.setText(
      "cardExpired",
      this.cards.filter(
        (card) =>
          card.status === "expired" ||
          (card.expires_at && new Date(card.expires_at) < new Date()),
      ).length,
    );

    const body = document.getElementById("staffCardsBody");
    if (!body) return;

    body.innerHTML = rows.length
      ? rows
          .map((card) => {
            const staffId = Number(card.staff_id);
            const fullName = `${card.first_name || ""} ${card.last_name || ""}`.trim();
            const badge =
              card.status === "issued"
                ? "success"
                : card.status === "generated"
                  ? "primary"
                  : "secondary";

            return `<tr>
              <td>
                <strong>${this.esc(fullName)}</strong><br>
                <small>${this.esc(card.position || "")}</small>
              </td>
              <td>${this.esc(card.staff_no || "—")}</td>
              <td>${this.esc(card.department_name || "—")}</td>
              <td>${this.esc(card.card_number || "—")}</td>
              <td>${this.esc(card.expires_at || "—")}</td>
              <td><span class="badge bg-${badge}">${this.esc(card.status || "unknown")}</span></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="StaffIdCardsController.regenerate(${staffId})" data-permission="staff.id_cards.manage">Regenerate</button>
                ${
                  card.status !== "issued"
                    ? `<button class="btn btn-sm btn-success" onclick="StaffIdCardsController.issue(${staffId})" data-permission="staff.id_cards.manage">Issue</button>`
                    : ""
                }
              </td>
            </tr>`;
          })
          .join("")
      : '<tr><td colspan="7" class="text-center text-muted py-4">No staff ID cards found.</td></tr>';

    StaffAccess.apply();
  },

  async generate(staffId = null) {
    const selectedStaffId =
      Number(staffId) || Number(document.getElementById("cardStaffId")?.value);

    if (!selectedStaffId) {
      this.notify("Select a staff member", "error");
      return;
    }

    const previewBody = document.getElementById("staffCardPreviewBody");
    const generateButton = document.getElementById("generateStaffCardSubmit");

    if (!previewBody) {
      this.notify("Staff ID card preview container was not found", "error");
      return;
    }

    try {
      if (generateButton) {
        generateButton.disabled = true;
        generateButton.innerHTML =
          '<span class="spinner-border spinner-border-sm me-1"></span>Generating…';
      }

      previewBody.innerHTML =
        '<div class="text-center py-5"><span class="spinner-border"></span><div class="mt-2">Preparing preview…</div></div>';

      const payload = {
        staff_id: selectedStaffId,
        expires_at: document.getElementById("cardExpiry")?.value || null,
        format: document.getElementById("cardFormat")?.value || "html",
        side: document.getElementById("cardSide")?.value || "both",
      };

      const result = await API.staff.generateIdCard(payload);
      const documentResponse = result?.document || {};
      const documentData = documentResponse?.data || documentResponse;

      const inlineHtml =
        typeof documentResponse === "string"
          ? documentResponse
          : documentResponse?.html || documentData?.html || "";

      const returnedUrl = documentData?.view_url || documentData?.file_path || "";

      this.previewHtml = "";
      this.previewUrl = "";

      if (inlineHtml) {
        this.previewHtml = inlineHtml;
        previewBody.innerHTML = inlineHtml;
      } else if (returnedUrl) {
        this.previewUrl = this.resolvePreviewUrl(returnedUrl);
        previewBody.innerHTML = `
          <div class="staff-id-card-preview-frame">
            <iframe
              src="${this.esc(this.previewUrl)}"
              title="Generated staff ID card"
              style="width:100%;min-height:520px;border:0;background:#fff"
            ></iframe>
          </div>`;
      } else {
        throw new Error(
          "The ID card was generated, but the server did not return a preview document.",
        );
      }

      const generateModal = document.getElementById("generateStaffCardModal");
      if (generateModal) bootstrap.Modal.getInstance(generateModal)?.hide();

      const previewModal = document.getElementById("staffCardPreviewModal");
      if (previewModal) bootstrap.Modal.getOrCreateInstance(previewModal).show();

      await this.loadCards();
      this.notify("Staff ID card generated", "success");
    } catch (error) {
      previewBody.innerHTML = `<div class="alert alert-danger mb-0">${this.esc(
        error?.message || "Failed to generate staff ID card",
      )}</div>`;
      this.notify(error?.message || "Failed to generate staff ID card", "error");
    } finally {
      if (generateButton) {
        generateButton.disabled = false;
        generateButton.textContent = "Generate";
      }
    }
  },

  regenerate(staffId) {
    const select = document.getElementById("cardStaffId");
    if (select) select.value = String(staffId);
    this.generate(staffId);
  },

  async issue(staffId) {
    try {
      await API.staff.issueIdCard({ staff_id: Number(staffId) });
      await this.loadCards();
      this.notify("Card marked as issued", "success");
    } catch (error) {
      this.notify(error?.message || "Failed to issue staff ID card", "error");
    }
  },

  printPreview() {
    if (this.previewUrl) {
      const printWindow = window.open(this.previewUrl, "_blank");
      if (!printWindow) {
        this.notify(
          "The browser blocked the print window. Allow pop-ups and try again.",
          "error",
        );
        return;
      }

      printWindow.addEventListener(
        "load",
        () => {
          printWindow.focus();
          printWindow.print();
        },
        { once: true },
      );
      return;
    }

    if (this.previewHtml) {
      const printWindow = window.open("", "_blank");
      if (!printWindow) {
        this.notify(
          "The browser blocked the print window. Allow pop-ups and try again.",
          "error",
        );
        return;
      }

      printWindow.document.open();
      printWindow.document.write(`<!DOCTYPE html>
        <html>
          <head>
            <meta charset="UTF-8">
            <title>Staff ID Card</title>
          </head>
          <body>${this.previewHtml}</body>
        </html>`);
      printWindow.document.close();

      printWindow.addEventListener(
        "load",
        () => {
          printWindow.focus();
          printWindow.print();
        },
        { once: true },
      );
      return;
    }

    this.notify("No staff ID card is available to print.", "error");
  },

  resolvePreviewUrl(value) {
    const path = String(value || "").trim();
    if (!path) return "";

    if (/^https?:\/\//i.test(path)) return path;

    const appBase = String(window.APP_BASE || "").replace(/\/$/, "");
    const normalizedPath = path.startsWith("/") ? path : `/${path}`;

    if (appBase && normalizedPath.startsWith(`${appBase}/`)) {
      return normalizedPath;
    }

    return `${appBase}${normalizedPath}`;
  },

  setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = String(value);
  },

  notify(message, type = "info") {
    window.API?.showNotification?.(message, type) || alert(message);
  },

  esc(value) {
    const div = document.createElement("div");
    div.textContent = String(value ?? "");
    return div.innerHTML;
  },
};

document.addEventListener("DOMContentLoaded", () => StaffIdCardsController.init());
