<?php
/**
 * Manage Extra Charges
 * Flexible, database-driven charges that appear on the fee structure.
 * Any amount paid that is NOT part of the normal fee structure —
 * registration, caution, trips, activities, and similar school charges.
 * Transport and uniforms are managed by their own entitlement/sales modules.
 */
?>
<div class="manager-layout" data-user-role="accountant">

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-receipt-cutoff"></i> Extra Charges</h2>
        <p class="text-muted mb-0">School charges outside normal tuition. Transport and uniforms use their own billing modules and accounts.</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" id="btnCreateCharge"><i class="bi bi-plus-lg"></i> New Extra Charge</button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Academic Year</label>
                <select class="form-select form-select-sm" id="filterYear">
                    <option value="">Loading...</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select class="form-select form-select-sm" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Target</label>
                <select class="form-select form-select-sm" id="filterTarget">
                    <option value="">All Targets</option>
                    <option value="new_admissions">New Admissions</option>
                    <option value="existing_students">Existing Students</option>
                    <option value="all_students">All Students</option>
                    <option value="boarders">Boarders</option>
                    <option value="day_students">Day Students</option>
                    <option value="specific_class">Specific Class</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Billing</label>
                <select class="form-select form-select-sm" id="filterBilling">
                    <option value="">All Billing</option>
                    <option value="added_to_fees">Added to Fees</option>
                    <option value="paid_separately">Paid Separately</option>
                    <option value="optional">Optional</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm w-100" id="btnClearFilters"><i class="bi bi-x-lg"></i> Clear</button>
            </div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:3%">#</th>
                        <th style="width:18%">Name</th>
                        <th style="width:10%" class="text-end">Amount</th>
                        <th style="width:10%">Mode</th>
                        <th style="width:10%">Frequency</th>
                        <th style="width:10%">Billing</th>
                        <th style="width:10%">Target</th>
                        <th style="width:8%">Visible</th>
                        <th style="width:8%">Status</th>
                        <th style="width:13%">Actions</th>
                    </tr>
                </thead>
                <tbody id="chargesBody">
                    <tr><td colspan="10" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create / Edit Modal -->
<div class="modal fade" id="chargeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="chargeModalTitle">New Extra Charge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="chargeForm">
                    <input type="hidden" id="chargeId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <select class="form-select" id="chargeAcademicYearId" required>
                                <option value="">Loading academic years...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Academic Term</label>
                            <select class="form-select" id="chargeTermId"><option value="">All terms / schedule-defined</option></select>
                        </div>
                    </div>

                    <!-- Section: Basic Info -->
                    <h6 class="text-muted mb-2"><i class="bi bi-info-circle"></i> Basic Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="chargeName" required placeholder="e.g. Registration Fee, Caution Money, Trip Charge">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="chargeDisplayOrder" value="0" min="0">
                            <small class="form-text text-muted">Lower = appears first</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4"><label class="form-label">Billing Starts</label><input type="date" class="form-control" id="chargeStartsOn"></div>
                        <div class="col-md-4"><label class="form-label">Billing Ends</label><input type="date" class="form-control" id="chargeEndsOn"></div>
                        <div class="col-md-4"><label class="form-label">Due Day (1–31)</label><input type="number" min="1" max="31" class="form-control" id="chargeDueDay"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="chargeDescription" rows="2" placeholder="What is this charge for?"></textarea>
                    </div>

                    <hr>

                    <!-- Section: Pricing -->
                    <h6 class="text-muted mb-2"><i class="bi bi-cash-stack"></i> Pricing</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Calculation Mode</label>
                            <select class="form-select" id="chargeCalcMode">
                                <option value="fixed">Fixed Amount</option>
                                <option value="per_unit">Per Unit</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="chargeAmount" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4" id="unitLabelGroup" style="display:none">
                            <label class="form-label">Unit Label</label>
                            <input type="text" class="form-control" id="chargeUnitLabel" placeholder="e.g. km, month, student">
                        </div>
                    </div>
                    <div class="row mb-3" id="unitPriceGroup" style="display:none">
                        <div class="col-md-4">
                            <label class="form-label">Unit Price (KES)</label>
                            <input type="number" class="form-control" id="chargeUnitPrice" min="0" step="0.01">
                            <small class="form-text text-muted">Price per unit (e.g. per km)</small>
                        </div>
                    </div>

                    <!-- Pricing Tiers -->
                    <div class="mb-3">
                        <label class="form-label">Pricing Tiers <small class="text-muted">(optional — for different amounts per category)</small></label>
                        <div id="pricingTiersContainer"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="btnAddTier"><i class="bi bi-plus"></i> Add Tier</button>
                    </div>

                    <hr>

                    <!-- Section: Billing -->
                    <h6 class="text-muted mb-2"><i class="bi bi-calendar-check"></i> Billing</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Billing Model</label>
                            <select class="form-select" id="chargeBillingModel">
                                <option value="paid_separately">Paid Separately</option>
                                <option value="added_to_fees">Added to School Fees</option>
                                <option value="optional">Optional</option>
                            </select>
                            <small class="form-text text-muted">How is this charge collected?</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Billing Frequency</label>
                            <select class="form-select" id="chargeBillingFreq">
                                <option value="one_time">One Time</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="per_term">Per Term</option>
                                <option value="per_year">Per Year</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">GL Account</label>
                            <select class="form-select" id="chargeGLAccount">
                                <option value="">None</option>
                            </select>
                            <small class="form-text text-muted">Account to credit</small>
                        </div>
                    </div>

                    <hr>

                    <!-- Section: Targeting -->
                    <h6 class="text-muted mb-2"><i class="bi bi-people"></i> Who Does This Apply To?</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Target Scope</label>
                            <select class="form-select" id="chargeTargetScope">
                                <option value="all_students">All Students</option>
                                <option value="new_admissions">New Admissions Only</option>
                                <option value="existing_students">Existing Students Only</option>
                                <option value="boarders">Boarders Only</option>
                                <option value="day_students">Day Students Only</option>
                                <option value="specific_class">Specific Class</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="classSelectGroup" style="display:none">
                            <label class="form-label">Class</label>
                            <select class="form-select" id="chargeClassId">
                                <option value="">Select Class</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="chargeVisible" checked>
                                <label class="form-check-label" for="chargeVisible">Visible on Fee Structure</label>
                            </div>
                            <small class="form-text text-muted">Show this charge on the printed fee structure</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveCharge">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Extra Charge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectChargeId">
                <div class="mb-3">
                    <label class="form-label">Reason for rejection</label>
                    <textarea class="form-control" id="rejectNotes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btnConfirmReject">Reject</button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/extra_charges.js'); ?>
</div>
