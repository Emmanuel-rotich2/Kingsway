<?php
/**
 * Route Registry Management Page
 * 
 * Allows System Administrators to:
 * - View all registered routes
 * - Create new routes
 * - Edit existing routes
 * - Enable/disable routes
 * 
 * Logic handled by: js/pages/route_registry.js
 * 
 * @package App\Pages\System
 * @since 2025-01-01
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="mb-1"><i class="fas fa-route me-2"></i>Route Registry</h2>
                    <p class="text-muted mb-0">Manage all application routes and URL mappings</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRouteModal">
                    <i class="fas fa-plus me-2"></i>Add Route
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="searchRoutes"
                            placeholder="Search routes...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterDomain">
                        <option value="">All Domains</option>
                        <option value="SYSTEM">SYSTEM</option>
                        <option value="SCHOOL">SCHOOL</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="RouteRegistryController.refresh()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Routes Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="routesTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>URL</th>
                            <th>Domain</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="routesTableBody">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="text-muted mb-0 mt-2">Loading routes...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <div id="routeStats" class="text-muted small">
                Showing <span id="routeCount">0</span> routes
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="routesPagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Create/Edit Route Modal -->
<div class="modal fade" id="createRouteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-route me-2"></i><span id="modalTitle">Create Route</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="routeForm">
                <div class="modal-body">
                    <input type="hidden" id="routeId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Route Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="routeName" required
                                placeholder="e.g., manage_users" pattern="[a-z0-9_]+"
                                title="Lowercase letters, numbers, and underscores only">
                            <small class="text-muted">Unique identifier (lowercase, underscores)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">URL Path <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="routeUrl" required
                                placeholder="e.g., home.php?route=manage_users">
                            <small class="text-muted">Full URL path to the page</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Domain <span class="text-danger">*</span></label>
                            <select class="form-select" id="routeDomain" required>
                                <option value="SCHOOL">SCHOOL</option>
                                <option value="SYSTEM">SYSTEM</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="routeStatus">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="routeDescription" rows="2"
                                placeholder="Brief description of what this route does"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Controller</label>
                            <input type="text" class="form-control" id="routeController"
                                placeholder="e.g., UsersController">
                            <small class="text-muted">Optional - for API routes</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Action</label>
                            <input type="text" class="form-control" id="routeAction" placeholder="e.g., index">
                            <small class="text-muted">Optional - controller method</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveRouteBtn">
                        <i class="fas fa-save me-1"></i>Save Route
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Page Controller -->
<script src="/Kingsway/js/pages/route_registry.js?v=<?php echo time(); ?>"></script>
