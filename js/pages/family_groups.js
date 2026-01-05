/**
 * Family Groups Controller
 * Manages parent/guardian family groups and student relationships
 */
const FamilyGroupsController = {
  currentParentId: null,
  currentPage: 1,
  pageSize: 12,
  searchTimeout: null,
  parentModal: null,
  viewFamilyModal: null,
  linkChildModal: null,

  /**
   * Initialize the controller
   */
  init: function () {
    this.parentModal = new bootstrap.Modal(
      document.getElementById("parentModal")
    );
    this.viewFamilyModal = new bootstrap.Modal(
      document.getElementById("viewFamilyModal")
    );
    this.linkChildModal = new bootstrap.Modal(
      document.getElementById("linkChildModal")
    );

    this.bindEvents();
    this.loadStats();
    this.loadFamilyGroups();
  },

  /**
   * Bind event listeners
   */
  bindEvents: function () {
    // Search with debounce
    document
      .getElementById("searchFamilyGroups")
      .addEventListener("input", (e) => {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
          this.currentPage = 1;
          this.loadFamilyGroups();
        }, 400);
      });

    // Filter changes
    document.getElementById("filterStatus").addEventListener("change", () => {
      this.currentPage = 1;
      this.loadFamilyGroups();
    });

    document
      .getElementById("filterChildrenCount")
      .addEventListener("change", () => {
        this.currentPage = 1;
        this.loadFamilyGroups();
      });

    // Parent form submit
    document.getElementById("parentForm").addEventListener("submit", (e) => {
      e.preventDefault();
      this.saveParent();
    });

    // Link child form submit
    document.getElementById("linkChildForm").addEventListener("submit", (e) => {
      e.preventDefault();
      this.linkChild();
    });

    // Edit button in view modal
    document.getElementById("editFamilyBtn").addEventListener("click", () => {
      this.viewFamilyModal.hide();
      this.showEditParentModal(this.currentParentId);
    });
  },

  /**
   * Load family group statistics
   */
  loadStats: async function () {
    try {
      const response = await API.students.getFamilyGroupStats();
      if (response.success) {
        const stats = response.data;
        document.getElementById("statTotalParents").textContent =
          stats.total_parents || 0;
        document.getElementById("statParentsWithChildren").textContent =
          stats.parents_with_children || 0;
        document.getElementById("statAvgChildren").textContent = parseFloat(
          stats.average_children_per_parent || 0
        ).toFixed(1);
        document.getElementById("statStudentsWithoutParents").textContent =
          stats.students_without_parents || 0;
      }
    } catch (error) {
      console.error("Error loading stats:", error);
    }
  },

  /**
   * Load family groups based on current filters
   */
  loadFamilyGroups: async function () {
    const container = document.getElementById("familyGroupsContainer");
    container.innerHTML = `
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Loading family groups...</p>
            </div>
        `;

    try {
      const searchTerm = document
        .getElementById("searchFamilyGroups")
        .value.trim();
      const status = document.getElementById("filterStatus").value;
      const childrenFilter = document.getElementById(
        "filterChildrenCount"
      ).value;

      let response;
      if (searchTerm) {
        response = await API.students.searchFamilyGroups(searchTerm, {
          page: this.currentPage,
          limit: this.pageSize,
        });
      } else {
        response = await API.students.getFamilyGroups({
          page: this.currentPage,
          limit: this.pageSize,
          status: status || "active",
        });
      }

      if (response.success && response.data) {
        let parents = response.data.parents || response.data || [];
        const total = response.data.total || parents.length;

        // Apply children count filter if set
        if (childrenFilter) {
          parents = parents.filter((p) => {
            const count = parseInt(p.children_count) || 0;
            if (childrenFilter === "0") return count === 0;
            if (childrenFilter === "1") return count === 1;
            if (childrenFilter === "2") return count === 2;
            if (childrenFilter === "3+") return count >= 3;
            return true;
          });
        }

        // Apply status filter
        if (status && !searchTerm) {
          parents = parents.filter((p) => p.status === status);
        }

        this.renderFamilyGroups(parents, total);
        this.renderPagination(total);
      } else {
        container.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-info-circle me-2"></i>
                            ${response.message || "No family groups found"}
                        </div>
                    </div>
                `;
      }
    } catch (error) {
      console.error("Error loading family groups:", error);
      container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading family groups. Please try again.
                    </div>
                </div>
            `;
    }
  },

  /**
   * Render family group cards
   */
  renderFamilyGroups: function (parents, total) {
    const container = document.getElementById("familyGroupsContainer");
    document.getElementById("resultCount").textContent = `${total} results`;

    if (!parents || parents.length === 0) {
      container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        No parents/guardians found matching your criteria.
                        <button class="btn btn-primary btn-sm ms-3" onclick="FamilyGroupsController.showCreateParentModal()">
                            <i class="fas fa-plus me-1"></i>Add Parent
                        </button>
                    </div>
                </div>
            `;
      return;
    }

    container.innerHTML = parents
      .map((parent) => this.renderFamilyCard(parent))
      .join("");
  },

  /**
   * Render a single family card
   */
  renderFamilyCard: function (parent) {
    const childrenCount = parseInt(parent.children_count) || 0;
    const totalBalance = parseFloat(parent.total_fee_balance) || 0;
    const fullName = [parent.first_name, parent.middle_name, parent.last_name]
      .filter((n) => n)
      .join(" ");

    const statusClass = parent.status === "active" ? "success" : "secondary";
    const balanceClass = totalBalance > 0 ? "text-danger" : "text-success";

    return `
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card family-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title mb-1">${fullName}</h5>
                                <small class="text-muted">
                                    ${
                                      parent.id_number
                                        ? `ID: ${parent.id_number}`
                                        : "No ID on file"
                                    }
                                </small>
                            </div>
                            <span class="badge bg-${statusClass}">${
      parent.status
    }</span>
                        </div>

                        <div class="mb-3">
                            <div class="row g-2 text-muted small">
                                <div class="col-6">
                                    <i class="fas fa-phone me-1"></i>
                                    ${parent.phone_1 || "No phone"}
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-envelope me-1"></i>
                                    ${parent.email || "No email"}
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-primary me-2">
                                    <i class="fas fa-child me-1"></i>${childrenCount} Children
                                </span>
                            </div>
                            <span class="${balanceClass} fw-bold">
                                ${this.formatCurrency(totalBalance)}
                            </span>
                        </div>

                        ${
                          parent.children_names
                            ? `
                            <div class="small text-muted mb-3">
                                <strong>Children:</strong> ${parent.children_names}
                            </div>
                        `
                            : ""
                        }

                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm flex-fill" 
                                    onclick="FamilyGroupsController.viewFamilyGroup(${
                                      parent.id
                                    })">
                                <i class="fas fa-eye me-1"></i>View
                            </button>
                            <button class="btn btn-outline-secondary btn-sm flex-fill"
                                    onclick="FamilyGroupsController.showEditParentModal(${
                                      parent.id
                                    })">
                                <i class="fas fa-edit me-1"></i>Edit
                            </button>
                            <button class="btn btn-outline-success btn-sm"
                                    onclick="FamilyGroupsController.showLinkChildModal(${
                                      parent.id
                                    })"
                                    title="Link a child">
                                <i class="fas fa-link"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
  },

  /**
   * Render pagination controls
   */
  renderPagination: function (total) {
    const pagination = document.getElementById("familyPagination");
    const totalPages = Math.ceil(total / this.pageSize);

    if (totalPages <= 1) {
      pagination.innerHTML = "";
      return;
    }

    let html = "";

    // Previous button
    html += `
            <li class="page-item ${this.currentPage === 1 ? "disabled" : ""}">
                <a class="page-link" href="#" onclick="FamilyGroupsController.goToPage(${
                  this.currentPage - 1
                }); return false;">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;

    // Page numbers
    const startPage = Math.max(1, this.currentPage - 2);
    const endPage = Math.min(totalPages, startPage + 4);

    for (let i = startPage; i <= endPage; i++) {
      html += `
                <li class="page-item ${i === this.currentPage ? "active" : ""}">
                    <a class="page-link" href="#" onclick="FamilyGroupsController.goToPage(${i}); return false;">${i}</a>
                </li>
            `;
    }

    // Next button
    html += `
            <li class="page-item ${
              this.currentPage === totalPages ? "disabled" : ""
            }">
                <a class="page-link" href="#" onclick="FamilyGroupsController.goToPage(${
                  this.currentPage + 1
                }); return false;">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;

    pagination.innerHTML = html;
  },

  /**
   * Go to specific page
   */
  goToPage: function (page) {
    this.currentPage = page;
    this.loadFamilyGroups();
    window.scrollTo({ top: 0, behavior: "smooth" });
  },

  /**
   * Refresh data
   */
  refresh: function () {
    this.loadStats();
    this.loadFamilyGroups();
    this.showSuccess("Data refreshed successfully");
  },

  /**
   * Show create parent modal
   */
  showCreateParentModal: function () {
    document.getElementById("parentModalTitle").textContent =
      "Add Parent/Guardian";
    document.getElementById("parentForm").reset();
    document.getElementById("parentId").value = "";
    this.parentModal.show();
  },

  /**
   * Show edit parent modal
   */
  showEditParentModal: async function (parentId) {
    try {
      document.getElementById("parentModalTitle").textContent =
        "Edit Parent/Guardian";
      document.getElementById("parentId").value = parentId;

      const response = await API.students.getParentDetails(parentId);
      if (response.success && response.data) {
        const parent = response.data;
        document.getElementById("parentFirstName").value =
          parent.first_name || "";
        document.getElementById("parentMiddleName").value =
          parent.middle_name || "";
        document.getElementById("parentLastName").value =
          parent.last_name || "";
        document.getElementById("parentIdNumber").value =
          parent.id_number || "";
        document.getElementById("parentGender").value = parent.gender || "";
        document.getElementById("parentDob").value = parent.date_of_birth || "";
        document.getElementById("parentPhone1").value = parent.phone_1 || "";
        document.getElementById("parentPhone2").value = parent.phone_2 || "";
        document.getElementById("parentEmail").value = parent.email || "";
        document.getElementById("parentOccupation").value =
          parent.occupation || "";
        document.getElementById("parentAddress").value = parent.address || "";

        this.parentModal.show();
      } else {
        this.showError("Failed to load parent details");
      }
    } catch (error) {
      console.error("Error loading parent:", error);
      this.showError("Error loading parent details");
    }
  },

  /**
   * Save parent (create or update)
   */
  saveParent: async function () {
    const btn = document.getElementById("saveParentBtn");
    const originalText = btn.innerHTML;
    btn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    btn.disabled = true;

    try {
      const parentId = document.getElementById("parentId").value;
      const data = {
        first_name: document.getElementById("parentFirstName").value,
        middle_name: document.getElementById("parentMiddleName").value,
        last_name: document.getElementById("parentLastName").value,
        id_number: document.getElementById("parentIdNumber").value,
        gender: document.getElementById("parentGender").value,
        date_of_birth: document.getElementById("parentDob").value,
        phone_1: document.getElementById("parentPhone1").value,
        phone_2: document.getElementById("parentPhone2").value,
        email: document.getElementById("parentEmail").value,
        occupation: document.getElementById("parentOccupation").value,
        address: document.getElementById("parentAddress").value,
      };

      let response;
      if (parentId) {
        response = await API.students.updateParentRecord(parentId, data);
      } else {
        response = await API.students.createParentRecord(data);
      }

      if (response.success) {
        this.parentModal.hide();
        this.showSuccess(
          parentId
            ? "Parent updated successfully"
            : "Parent created successfully"
        );
        this.loadStats();
        this.loadFamilyGroups();
      } else {
        this.showError(response.message || "Failed to save parent");
      }
    } catch (error) {
      console.error("Error saving parent:", error);
      this.showError("Error saving parent");
    } finally {
      btn.innerHTML = originalText;
      btn.disabled = false;
    }
  },

  /**
   * View family group details
   */
  viewFamilyGroup: async function (parentId) {
    this.currentParentId = parentId;
    const content = document.getElementById("familyDetailsContent");
    content.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Loading family details...</p>
            </div>
        `;
    this.viewFamilyModal.show();

    try {
      // Load parent details and children
      const [parentResponse, childrenResponse] = await Promise.all([
        API.students.getParentDetails(parentId),
        API.students.getParentChildren(parentId),
      ]);

      if (parentResponse.success && parentResponse.data) {
        const parent = parentResponse.data;
        const children = childrenResponse.success
          ? childrenResponse.data || []
          : [];

        content.innerHTML = this.renderFamilyDetails(parent, children);
      } else {
        content.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Failed to load family details
                    </div>
                `;
      }
    } catch (error) {
      console.error("Error loading family details:", error);
      content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error loading family details
                </div>
            `;
    }
  },

  /**
   * Render family details
   */
  renderFamilyDetails: function (parent, children) {
    const fullName = [parent.first_name, parent.middle_name, parent.last_name]
      .filter((n) => n)
      .join(" ");
    const totalBalance = children.reduce(
      (sum, c) => sum + (parseFloat(c.fee_balance) || 0),
      0
    );

    return `
            <div class="row">
                <!-- Parent Info Column -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Parent/Guardian</h6>
                        </div>
                        <div class="card-body">
                            <h5 class="mb-3">${fullName}</h5>
                            <dl class="row mb-0">
                                <dt class="col-5">ID Number:</dt>
                                <dd class="col-7">${
                                  parent.id_number || "N/A"
                                }</dd>
                                
                                <dt class="col-5">Gender:</dt>
                                <dd class="col-7">${
                                  parent.gender
                                    ? parent.gender.charAt(0).toUpperCase() +
                                      parent.gender.slice(1)
                                    : "N/A"
                                }</dd>
                                
                                <dt class="col-5">Phone 1:</dt>
                                <dd class="col-7">${
                                  parent.phone_1 || "N/A"
                                }</dd>
                                
                                <dt class="col-5">Phone 2:</dt>
                                <dd class="col-7">${
                                  parent.phone_2 || "N/A"
                                }</dd>
                                
                                <dt class="col-5">Email:</dt>
                                <dd class="col-7">${parent.email || "N/A"}</dd>
                                
                                <dt class="col-5">Occupation:</dt>
                                <dd class="col-7">${
                                  parent.occupation || "N/A"
                                }</dd>
                                
                                <dt class="col-5">Address:</dt>
                                <dd class="col-7">${
                                  parent.address || "N/A"
                                }</dd>
                            </dl>
                        </div>
                    </div>

                    <!-- Summary Card -->
                    <div class="card mt-3">
                        <div class="card-body text-center">
                            <h4 class="mb-0">${children.length}</h4>
                            <small class="text-muted">Children Linked</small>
                            <hr>
                            <h5 class="${
                              totalBalance > 0 ? "text-danger" : "text-success"
                            } mb-0">
                                ${this.formatCurrency(totalBalance)}
                            </h5>
                            <small class="text-muted">Total Fee Balance</small>
                        </div>
                    </div>
                </div>

                <!-- Children Column -->
                <div class="col-md-8">
                    <h6 class="mb-3"><i class="fas fa-child me-2"></i>Children (${
                      children.length
                    })</h6>
                    
                    ${
                      children.length === 0
                        ? `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No children linked to this parent.
                            <button class="btn btn-sm btn-success ms-2" onclick="FamilyGroupsController.showLinkChildModal(${parent.id})">
                                <i class="fas fa-link me-1"></i>Link a Child
                            </button>
                        </div>
                    `
                        : children
                            .map((child) =>
                              this.renderChildCard(child, parent.id)
                            )
                            .join("")
                    }
                </div>
            </div>
        `;
  },

  /**
   * Render child card
   */
  renderChildCard: function (child, parentId) {
    const balance = parseFloat(child.fee_balance) || 0;
    const relationshipBadges = {
      father: "primary",
      mother: "info",
      guardian: "secondary",
      step_father: "primary",
      step_mother: "info",
      grandparent: "warning",
      uncle: "dark",
      aunt: "dark",
      sibling: "success",
      other: "light",
    };

    return `
            <div class="card child-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${
                              child.student_name ||
                              child.first_name + " " + child.last_name
                            }</h6>
                            <span class="badge relationship-badge bg-${
                              relationshipBadges[child.relationship] ||
                              "secondary"
                            } me-2">
                                ${this.formatRelationship(child.relationship)}
                            </span>
                            ${
                              child.is_primary_contact
                                ? '<span class="badge bg-success me-1">Primary</span>'
                                : ""
                            }
                            ${
                              child.is_emergency_contact
                                ? '<span class="badge bg-warning text-dark">Emergency</span>'
                                : ""
                            }
                        </div>
                        <div class="text-end">
                            <span class="${
                              balance > 0 ? "text-danger" : "text-success"
                            } fw-bold">
                                ${this.formatCurrency(balance)}
                            </span>
                            <br>
                            <small class="text-muted">Fee Balance</small>
                        </div>
                    </div>

                    <div class="row mt-2 small text-muted">
                        <div class="col-4">
                            <i class="fas fa-graduation-cap me-1"></i>
                            ${child.class_name || "N/A"}
                        </div>
                        <div class="col-4">
                            <i class="fas fa-id-card me-1"></i>
                            ${child.admission_number || "N/A"}
                        </div>
                        <div class="col-4">
                            <i class="fas fa-percentage me-1"></i>
                            ${
                              child.financial_responsibility || 100
                            }% Responsibility
                        </div>
                    </div>

                    <div class="mt-2">
                        <button class="btn btn-outline-danger btn-sm" 
                                onclick="FamilyGroupsController.unlinkChild(${parentId}, ${
      child.student_id || child.id
    })">
                            <i class="fas fa-unlink me-1"></i>Unlink
                        </button>
                    </div>
                </div>
            </div>
        `;
  },

  /**
   * Show link child modal
   */
  showLinkChildModal: async function (parentId = null) {
    if (!parentId && !this.currentParentId) {
      this.showError("Please select a parent first");
      return;
    }

    const pid = parentId || this.currentParentId;
    document.getElementById("linkParentId").value = pid;

    // Load available students
    const studentSelect = document.getElementById("linkStudentId");
    studentSelect.innerHTML = '<option value="">Loading students...</option>';
    studentSelect.disabled = true;

    this.viewFamilyModal.hide();
    this.linkChildModal.show();

    try {
      const response = await API.students.getAvailableStudentsForParent(pid);
      if (response.success && response.data) {
        const students = response.data;
        if (students.length === 0) {
          studentSelect.innerHTML =
            '<option value="">No available students</option>';
        } else {
          studentSelect.innerHTML =
            '<option value="">Select a student...</option>' +
            students
              .map(
                (s) => `
                            <option value="${s.id}">${s.first_name} ${
                  s.last_name
                } (${s.admission_number || "No Adm#"}) - ${
                  s.class_name || "No Class"
                }</option>
                        `
              )
              .join("");
          studentSelect.disabled = false;
        }
      } else {
        studentSelect.innerHTML =
          '<option value="">Failed to load students</option>';
      }
    } catch (error) {
      console.error("Error loading students:", error);
      studentSelect.innerHTML =
        '<option value="">Error loading students</option>';
    }
  },

  /**
   * Link a child to parent
   */
  linkChild: async function () {
    const btn = document.getElementById("linkChildBtn");
    const originalText = btn.innerHTML;
    btn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1"></span>Linking...';
    btn.disabled = true;

    try {
      const parentId = document.getElementById("linkParentId").value;
      const studentId = document.getElementById("linkStudentId").value;
      const relationship = document.getElementById("linkRelationship").value;
      const isPrimary = document.getElementById("linkIsPrimary").checked;
      const isEmergency = document.getElementById("linkIsEmergency").checked;
      const financialResp = document.getElementById("linkFinancialResp").value;

      if (!studentId) {
        this.showError("Please select a student");
        return;
      }

      const response = await API.students.linkParentToStudent(
        parentId,
        studentId,
        {
          relationship: relationship,
          is_primary_contact: isPrimary,
          is_emergency_contact: isEmergency,
          financial_responsibility: parseFloat(financialResp) || 100,
        }
      );

      if (response.success) {
        this.linkChildModal.hide();
        this.showSuccess("Child linked successfully");
        this.loadStats();
        this.loadFamilyGroups();

        // Refresh the view modal if it was open
        if (this.currentParentId === parseInt(parentId)) {
          this.viewFamilyGroup(parentId);
        }
      } else {
        this.showError(response.message || "Failed to link child");
      }
    } catch (error) {
      console.error("Error linking child:", error);
      this.showError("Error linking child");
    } finally {
      btn.innerHTML = originalText;
      btn.disabled = false;
    }
  },

  /**
   * Unlink a child from parent
   */
  unlinkChild: async function (parentId, studentId) {
    if (
      !confirm("Are you sure you want to unlink this child from the parent?")
    ) {
      return;
    }

    try {
      const response = await API.students.unlinkParentFromStudent(
        parentId,
        studentId
      );
      if (response.success) {
        this.showSuccess("Child unlinked successfully");
        this.loadStats();
        this.loadFamilyGroups();

        // Refresh the view modal
        this.viewFamilyGroup(parentId);
      } else {
        this.showError(response.message || "Failed to unlink child");
      }
    } catch (error) {
      console.error("Error unlinking child:", error);
      this.showError("Error unlinking child");
    }
  },

  /**
   * Format currency value
   */
  formatCurrency: function (amount) {
    return (
      "KES " +
      parseFloat(amount || 0).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  },

  /**
   * Format relationship for display
   */
  formatRelationship: function (relationship) {
    const labels = {
      father: "Father",
      mother: "Mother",
      guardian: "Guardian",
      step_father: "Step Father",
      step_mother: "Step Mother",
      grandparent: "Grandparent",
      uncle: "Uncle",
      aunt: "Aunt",
      sibling: "Sibling",
      other: "Other",
    };
    return labels[relationship] || relationship;
  },

  /**
   * Show success message
   */
  showSuccess: function (message) {
    this.showToast(message, "success");
  },

  /**
   * Show error message
   */
  showError: function (message) {
    this.showToast(message, "danger");
  },

  /**
   * Show toast notification
   */
  showToast: function (message, type = "info") {
    // Remove existing toasts
    document.querySelectorAll(".toast-notification").forEach((t) => t.remove());

    const toast = document.createElement("div");
    toast.className = `toast-notification alert alert-${type} position-fixed`;
    toast.style.cssText =
      "top: 20px; right: 20px; z-index: 9999; min-width: 300px;";
    toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${
                  type === "success"
                    ? "check-circle"
                    : type === "danger"
                    ? "exclamation-circle"
                    : "info-circle"
                } me-2"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
    document.body.appendChild(toast);

    // Auto remove after 4 seconds
    setTimeout(() => toast.remove(), 4000);
  },
};

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  FamilyGroupsController.init();
});
