<?php
/**
 * Manage Family Groups Page
 * HTML structure only - all logic in js/pages/family_groups.js (FamilyGroupsController)
 * Embedded in app_layout.php via DashboardRouter
 * 
 * Stateless design - authentication handled by JWT tokens in JavaScript
 */
?>

<style>
    .family-card {
        transition: all 0.3s ease;
        border-left: 4px solid #0d6efd;
    }

    .family-card:hover {
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .child-card {
        border-left: 3px solid #198754;
        background: #f8f9fa;
    }

    .relationship-badge {
        font-size: 0.75rem;
        font-weight: 500;
    }

    .search-highlight {
        background-color: #fff3cd;
        padding: 0 2px;
        border-radius: 2px;
    }

    .stats-card {
        border: none;
        border-radius: 10px;
        transition: transform 0.2s;
    }

    .stats-card:hover {
        transform: translateY(-3px);
    }

    .stats-icon {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
    }
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-users me-2"></i>Family Groups</h2>
            <p class="text-muted mb-0">Manage parent/guardian family groups and student relationships</p>
        </div>
        <div class="btn-group">
            <button class="btn btn-primary" onclick="FamilyGroupsController.showCreateParentModal()" data-permission="family_groups_create">
                <i class="fas fa-plus me-1"></i>Add Parent/Guardian
            </button>
            <button class="btn btn-outline-primary" onclick="FamilyGroupsController.refresh()">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stats-card bg-primary text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stats-icon bg-white bg-opacity-25 me-3">
                        <i class="fas fa-user-tie fa-lg"></i>
                    </div>
                    <div>
                        <h3 class="mb-0" id="statTotalParents">0</h3>
                        <small>Total Parents</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stats-card bg-success text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stats-icon bg-white bg-opacity-25 me-3">
                        <i class="fas fa-link fa-lg"></i>
                    </div>
                    <div>
                        <h3 class="mb-0" id="statParentsWithChildren">0</h3>
                        <small>With Children Linked</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stats-card bg-warning text-dark h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stats-icon bg-white bg-opacity-25 me-3">
                        <i class="fas fa-child fa-lg"></i>
                    </div>
                    <div>
                        <h3 class="mb-0" id="statAvgChildren">0</h3>
                        <small>Avg Children/Parent</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stats-card bg-danger text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stats-icon bg-white bg-opacity-25 me-3">
                        <i class="fas fa-unlink fa-lg"></i>
                    </div>
                    <div>
                        <h3 class="mb-0" id="statStudentsWithoutParents">0</h3>
                        <small>Students w/o Parents</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchFamilyGroups" class="form-control"
                            placeholder="Search by name, ID number, phone, or student...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filterStatus" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filterChildrenCount" class="form-select">
                        <option value="">All</option>
                        <option value="0">No Children</option>
                        <option value="1">1 Child</option>
                        <option value="2">2 Children</option>
                        <option value="3+">3+ Children</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <span class="text-muted" id="resultCount">0 results</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Family Groups List -->
    <div class="row" id="familyGroupsContainer">
        <!-- Family group cards will be loaded here -->
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2">Loading family groups...</p>
        </div>
    </div>

    <!-- Pagination -->
    <nav class="mt-4">
        <ul class="pagination justify-content-center" id="familyPagination"></ul>
    </nav>
</div>

<!-- Create/Edit Parent Modal -->
<div class="modal fade" id="parentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="parentModalTitle">Add Parent/Guardian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="parentForm">
                <div class="modal-body">
                    <input type="hidden" id="parentId" name="parent_id">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" id="parentFirstName" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" id="parentMiddleName" name="middle_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" id="parentLastName" name="last_name" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">ID Number (National ID)</label>
                            <input type="text" id="parentIdNumber" name="id_number" class="form-control"
                                placeholder="e.g., 12345678">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select id="parentGender" name="gender" class="form-select">
                                <option value="">Select...</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" id="parentDob" name="date_of_birth" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Phone (Primary) <span class="text-danger">*</span></label>
                            <input type="tel" id="parentPhone1" name="phone_1" class="form-control"
                                placeholder="e.g., 254712345678" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone (Secondary)</label>
                            <input type="tel" id="parentPhone2" name="phone_2" class="form-control"
                                placeholder="e.g., 254722345678">
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" id="parentEmail" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Occupation</label>
                            <input type="text" id="parentOccupation" name="occupation" class="form-control">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Address</label>
                        <textarea id="parentAddress" name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveParentBtn">
                        <i class="fas fa-save me-1"></i>Save Parent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Family Details Modal -->
<div class="modal fade" id="viewFamilyModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-users me-2"></i>Family Group Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="familyDetailsContent">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="FamilyGroupsController.showLinkChildModal()" data-permission="family_groups_edit">
                    <i class="fas fa-link me-1"></i>Link Child
                </button>
                <button type="button" class="btn btn-primary" id="editFamilyBtn" data-permission="family_groups_edit">
                    <i class="fas fa-edit me-1"></i>Edit Parent
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Link Child Modal -->
<div class="modal fade" id="linkChildModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-link me-2"></i>Link Child to Parent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="linkChildForm">
                <div class="modal-body">
                    <input type="hidden" id="linkParentId">

                    <div class="mb-3">
                        <label class="form-label">Select Student</label>
                        <select id="linkStudentId" class="form-select" required>
                            <option value="">Loading students...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Relationship</label>
                        <select id="linkRelationship" class="form-select" required>
                            <option value="guardian">Guardian</option>
                            <option value="father">Father</option>
                            <option value="mother">Mother</option>
                            <option value="step_father">Step Father</option>
                            <option value="step_mother">Step Mother</option>
                            <option value="grandparent">Grandparent</option>
                            <option value="uncle">Uncle</option>
                            <option value="aunt">Aunt</option>
                            <option value="sibling">Sibling</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="linkIsPrimary">
                                <label class="form-check-label" for="linkIsPrimary">Primary Contact</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="linkIsEmergency">
                                <label class="form-check-label" for="linkIsEmergency">Emergency Contact</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Financial Responsibility (%)</label>
                        <input type="number" id="linkFinancialResp" class="form-control" value="100" min="0"
                            max="100" step="5">
                        <small class="text-muted">Percentage of fees this parent is responsible for</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="linkChildBtn">
                        <i class="fas fa-link me-1"></i>Link Child
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Page-specific JavaScript -->
<script src="/Kingsway/js/pages/family_groups.js"></script>
<script>
    // Initialize controller when page loads
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof FamilyGroupsController !== 'undefined') {
            FamilyGroupsController.init();
        }
    });
</script>
