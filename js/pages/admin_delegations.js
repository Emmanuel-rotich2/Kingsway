const delegationsController = (function () {
  let state = { page: 1, limit: 20, search: "", active: "" };

  async function load() {
    const qs = new URLSearchParams({
      page: state.page,
      limit: state.limit,
      search: state.search,
      active: state.active,
    });
    const res = await window.API.apiCall(
      "/api/delegations?" + qs.toString(),
      "GET"
    );
    if (!res || res.status !== "success") {
      document.getElementById("delegationsTableContainer").innerHTML =
        '<p class="text-danger">Failed to load delegations</p>';
      return;
    }
    renderTable(res.data.items, res.data.total);
  }

  function renderTable(items, total) {
    if (!items || items.length === 0) {
      document.getElementById("delegationsTableContainer").innerHTML =
        '<p class="text-muted">No delegations found</p>';
      return;
    }
    let html =
      '<table class="table table-striped"><thead><tr><th>ID</th><th>Delegator</th><th>Delegate</th><th>Menu Item</th><th>Route</th><th>Active</th><th>Expires At</th><th>Actions</th></tr></thead><tbody>';
    for (const r of items) {
      html += `<tr>
                <td>${r.id}</td>
                <td>${r.delegator_username} (${r.delegator_user_id})</td>
                <td>${r.delegate_username} (${r.delegate_user_id})</td>
                <td>${r.menu_label ?? r.menu_item_id}</td>
                <td>${r.route_name ?? ""}</td>
                <td>${r.active == 1 ? "Yes" : "No"}</td>
                <td>${r.expires_at ?? ""}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="delegationsController.edit(${
                      r.id
                    })">Edit</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="delegationsController.remove(${
                      r.id
                    })">Delete</button>
                </td>
            </tr>`;
    }
    html += "</tbody></table>";
    document.getElementById("delegationsTableContainer").innerHTML = html;
  }

  function handleSearch(q) {
    state.search = q;
    state.page = 1;
    load();
  }
  function handleActiveFilter(v) {
    state.active = v;
    state.page = 1;
    load();
  }

  function showCreateModal() {
    document.getElementById("delegationForm").reset();
    document.getElementById("delegationId").value = "";
    const modal = new bootstrap.Modal(
      document.getElementById("delegationModal")
    );
    modal.show();
  }

  async function save() {
    const id = document.getElementById("delegationId").value;
    const delegator = parseInt(
      document.getElementById("delegatorUserId").value
    );
    const delegate = parseInt(document.getElementById("delegateUserId").value);
    const menuItem = parseInt(document.getElementById("menuItemId").value);
    const expiresAt = document.getElementById("expiresAt").value || null;
    if (!delegator || !delegate || !menuItem) {
      alert("Delegator, Delegate and Menu Item are required");
      return;
    }
    const payload = {
      delegator_user_id: delegator,
      delegate_user_id: delegate,
      menu_item_id: menuItem,
      expires_at: expiresAt,
    };
    const res = await window.API.apiCall("/api/delegations", "POST", payload);
    if (!res || res.status !== "success") {
      alert("Failed to create delegation: " + (res?.message || "unknown"));
      return;
    }
    bootstrap.Modal.getInstance(
      document.getElementById("delegationModal")
    ).hide();
    load();
  }

  async function edit(id) {
    const res = await window.API.apiCall("/api/delegations/" + id, "GET");
    if (!res || res.status !== "success") {
      alert("Failed to load");
      return;
    }
    const r = res.data;
    document.getElementById("delegationId").value = r.id;
    document.getElementById("delegatorUserId").value = r.delegator_user_id;
    document.getElementById("delegateUserId").value = r.delegate_user_id;
    document.getElementById("menuItemId").value = r.menu_item_id;
    document.getElementById("expiresAt").value = r.expires_at
      ? r.expires_at.replace(" ", "T")
      : "";
    const modal = new bootstrap.Modal(
      document.getElementById("delegationModal")
    );
    modal.show();

    // Change save button behavior to PUT (simple approach: reuse save but detect id and call PUT)
    document.querySelector("#delegationModal .btn-primary").onclick =
      async function () {
        const payload = {
          active: 1,
          expires_at: document.getElementById("expiresAt").value || null,
        };
        const upd = await window.API.apiCall(
          "/api/delegations/" + id,
          "PUT",
          payload
        );
        if (!upd || upd.status !== "success") {
          alert("Update failed");
          return;
        }
        bootstrap.Modal.getInstance(
          document.getElementById("delegationModal")
        ).hide();
        load();
      };
  }

  async function remove(id) {
    if (
      !confirm(
        "Delete this delegation? This will not automatically revoke permissions if other delegations still require them."
      )
    )
      return;
    const res = await window.API.apiCall("/api/delegations/" + id, "DELETE");
    if (res === "") {
      // 204 No Content returns empty
      load();
      return;
    }
    if (!res || res.status !== "success") {
      alert("Delete failed: " + (res?.message || "unknown"));
      return;
    }
    load();
  }

  // Public API
  return {
    load,
    handleSearch,
    handleActiveFilter,
    showCreateModal,
    save,
    edit,
    remove,
  };
})();

// Auto-load when page is ready
document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("delegationsTableContainer")) {
    delegationsController.load();
  }
});
