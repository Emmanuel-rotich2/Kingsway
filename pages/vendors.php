<?php
/**
 * Vendors Management
 *
 * Purpose: Manage vendor/supplier records
 * Features:
 * - Vendor CRUD operations
 * - Contact details management
 * - Payment history tracking
 */
?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-shop me-2"></i>Vendors</h4>
                    <p class="text-muted mb-0">Manage supplier and vendor records</p>
                </div>
                <button class="btn btn-primary" onclick="VendorsController.showAddModal()">
                    <i class="bi bi-plus-lg me-1"></i> Add Vendor
                </button>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i
                                class="bi bi-shop text-primary fa-lg"></i></div>
                        <div>
                            <h6 class="text-muted mb-1">Total Vendors</h6>
                            <h4 class="mb-0" id="statTotal">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i
                                class="bi bi-check-lg-circle text-success fa-lg"></i></div>
                        <div>
                            <h6 class="text-muted mb-1">Active Vendors</h6>
                            <h4 class="mb-0" id="statActive">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i
                                class="bi bi-cash text-warning fa-lg"></i></div>
                        <div>
                            <h6 class="text-muted mb-1">Total Paid</h6>
                            <h4 class="mb-0" id="statPaid">KES 0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3"><i
                                class="bi bi-clock text-danger fa-lg"></i></div>
                        <div>
                            <h6 class="text-muted mb-1">Pending Payments</h6>
                            <h4 class="mb-0" id="statPending">KES 0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" class="form-control" id="searchInput"
                        placeholder="Search vendors..."></div>
                <div class="col-md-3"><select class="form-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="supplies">Supplies</option>
                        <option value="services">Services</option>
                        <option value="food">Food & Catering</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="other">Other</option>
                    </select></div>
                <div class="col-md-3"><select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100"
                        onclick="VendorsController.refresh()"><i class="bi bi-arrow-clockwise"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Vendor List</h6>
            <button class="btn btn-sm btn-outline-success" onclick="VendorsController.exportCSV()"><i
                    class="bi bi-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Vendor Name</th>
                            <th scope="col">Contact Person</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Email</th>
                            <th scope="col">Category</th>
                            <th scope="col">Total Orders</th>
                            <th scope="col">Balance</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="vendorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vendorModalTitle"><i class="bi bi-shop me-2"></i>Add Vendor</h5><button
                    type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="vendorForm">
                    <input type="hidden" id="vendorId">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Vendor Name <span
                                    class="text-danger">*</span></label><input type="text" class="form-control"
                                id="vendorName" required></div>
                        <div class="col-md-6"><label class="form-label">Contact Person</label><input type="text"
                                class="form-control" id="contactPerson"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input type="text"
                                class="form-control" id="vendorPhone"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email"
                                class="form-control" id="vendorEmail"></div>
                        <div class="col-md-6"><label class="form-label">Category</label><select class="form-select"
                                id="vendorCategory">
                                <option value="supplies">Supplies</option>
                                <option value="services">Services</option>
                                <option value="food">Food & Catering</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="other">Other</option>
                            </select></div>
                        <div class="col-md-6"><label class="form-label">Status</label><select class="form-select"
                                id="vendorStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select></div>
                        <div class="col-md-12"><label class="form-label">Address</label><textarea class="form-control"
                                id="vendorAddress" rows="2"></textarea></div>
                        <div class="col-md-6"><label class="form-label">Bank Name</label><input type="text"
                                class="form-control" id="bankName"></div>
                        <div class="col-md-6"><label class="form-label">Account Number</label><input type="text"
                                class="form-control" id="accountNumber"></div>
                        <div class="col-md-12"><label class="form-label">Notes</label><textarea class="form-control"
                                id="vendorNotes" rows="2"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="VendorsController.saveVendor()"><i
                        class="bi bi-check-lg me-1"></i> Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Orders Section -->
<div class="card shadow-sm mt-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Purchase Orders</h6>
    <button class="btn btn-sm btn-outline-secondary" onclick="VendorsController.refresh()">
      <i class="bi bi-arrow-clockwise"></i>
    </button>
  </div>
  <div class="card-body p-0" id="poContainer">
    <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
  </div>
</div>

<div class="modal fade" id="vendorAccountsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-xl"><div class="modal-content">
    <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-credit-card me-2"></i><span id="vendorAccountsTitle">Vendor payment accounts</span></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" id="accountSupplierId">
      <div class="row g-3"><div class="col-lg-6"><div class="card border-0 bg-light"><div class="card-body"><h6>Bank account</h6><div class="row g-2"><div class="col-md-6"><input id="vaBankName" class="form-control form-control-sm" placeholder="Bank name"></div><div class="col-md-6"><input id="vaBankCode" class="form-control form-control-sm" placeholder="Bank code"></div><div class="col-md-6"><input id="vaBankAccountName" class="form-control form-control-sm" placeholder="Account name"></div><div class="col-md-6"><input id="vaBankAccountNumber" class="form-control form-control-sm" placeholder="Account number"></div></div><button class="btn btn-sm btn-outline-primary mt-2" id="vaSaveBank">Save bank account</button></div></div></div><div class="col-lg-6"><div class="card border-0 bg-light"><div class="card-body"><h6>M-Pesa account</h6><div class="row g-2"><div class="col-md-6"><input id="vaMobileName" class="form-control form-control-sm" placeholder="Account name"></div><div class="col-md-6"><input id="vaMobilePhone" class="form-control form-control-sm" placeholder="2547XXXXXXXX"></div></div><button class="btn btn-sm btn-outline-primary mt-2" id="vaSaveMobile">Save M-Pesa account</button></div></div></div></div>
      <hr><div class="row g-3"><div class="col-lg-6"><h6>Bank destinations</h6><div class="table-responsive"><table class="table table-sm" id="vaBankTable"><tbody><tr><td class="text-muted">Loading…</td></tr></tbody></table></div></div><div class="col-lg-6"><h6>M-Pesa destinations</h6><div class="table-responsive"><table class="table table-sm" id="vaMobileTable"><tbody><tr><td class="text-muted">Loading…</td></tr></tbody></table></div></div></div>
      <p class="small text-muted mb-0">New destinations are pending until an authorized finance user verifies them. Only active verified destinations appear in the payment queue.</p>
    </div>
  </div></div>
</div>

<script src="<?= $appBase ?>/js/pages/vendors.js?v=<?php echo time(); ?>"></script>
