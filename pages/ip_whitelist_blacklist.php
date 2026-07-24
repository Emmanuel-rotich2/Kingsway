<?php
/**
 * System Administrator — IP Whitelist/Blacklist
 * Controller: js/pages/ip_whitelist_blacklist.js
 */
?>
<div class="container-fluid py-4" id="ipAccessControlPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">IP Whitelist/Blacklist</h2>
            <p class="text-muted mb-0">
                Manage scheduled IPv4 and IPv6 CIDR rules enforced before
                authentication on every API request.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button
                type="button"
                class="btn btn-outline-secondary"
                id="resetIpRuleFiltersBtn"
            >
                <i class="fas fa-undo me-1"></i> Reset filters
            </button>
            <button
                type="button"
                class="btn btn-outline-primary"
                id="refreshIpRulesBtn"
            >
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
            <button
                type="button"
                class="btn btn-primary"
                id="addIpRuleBtn"
            >
                <i class="fas fa-plus me-1"></i> Add rule
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" id="ipRuleSummary"></div>

    <div
        class="alert alert-secondary d-flex flex-wrap justify-content-between
               align-items-center gap-2"
        id="ipCurrentPolicyState"
        role="status"
        aria-live="polite"
    >
        <span>Waiting for the current IP policy...</span>
        <span class="badge bg-secondary" id="ipCurrentAddress">
            Current IP unavailable
        </span>
    </div>

    <div
        class="alert alert-info"
        id="ipRuleState"
        role="status"
        aria-live="polite"
    >
        Waiting for authentication...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5">
                    <label
                        class="form-label small text-muted"
                        for="ipRuleSearch"
                    >
                        Search
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input
                            class="form-control"
                            id="ipRuleSearch"
                            type="search"
                            maxlength="200"
                            placeholder="CIDR, description or administrator"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="col-sm-4 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="ipRuleTypeFilter"
                    >
                        Rule type
                    </label>
                    <select class="form-select" id="ipRuleTypeFilter">
                        <option value="">All types</option>
                        <option value="allow">Allow</option>
                        <option value="deny">Deny</option>
                    </select>
                </div>

                <div class="col-sm-4 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="ipRuleStatusFilter"
                    >
                        Runtime status
                    </label>
                    <select class="form-select" id="ipRuleStatusFilter">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="expired">Expired</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>

                <div class="col-sm-4 col-lg-3">
                    <label
                        class="form-label small text-muted"
                        for="ipRulePageSize"
                    >
                        Rows per page
                    </label>
                    <select class="form-select" id="ipRulePageSize">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>CIDR</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Schedule</th>
                        <th>Updated by</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="ipRuleTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            Waiting for authentication...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="card-footer bg-white d-flex flex-wrap gap-3
                   justify-content-between align-items-center"
        >
            <span class="text-muted small" id="ipRuleCount">
                No IP rules loaded
            </span>
            <nav aria-label="IP rule pages">
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="ipRulePreviousPage"
                    >
                        <i class="fas fa-chevron-left me-1"></i> Previous
                    </button>
                    <span
                        class="btn btn-outline-secondary disabled"
                        id="ipRulePageIndicator"
                    >
                        Page 1 of 1
                    </span>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="ipRuleNextPage"
                    >
                        Next <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<div
    class="modal fade"
    id="ipRuleModal"
    tabindex="-1"
    aria-labelledby="ipRuleModalTitle"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="ipRuleForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="ipRuleModalTitle">
                        Add IP rule
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="ipRuleId">

                    <div
                        class="alert alert-warning py-2"
                        id="ipRuleValidation"
                        role="alert"
                        hidden
                    ></div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="ipRuleType">
                                Rule type
                            </label>
                            <select class="form-select" id="ipRuleType" required>
                                <option value="allow">Allow</option>
                                <option value="deny">Deny</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label" for="ipRuleCidr">
                                IP address or CIDR
                            </label>
                            <input
                                class="form-control"
                                id="ipRuleCidr"
                                maxlength="100"
                                placeholder="192.0.2.0/24 or 2001:db8::/32"
                                autocomplete="off"
                                required
                            >
                            <div class="form-text">
                                A single IP is normalized to /32 for IPv4 or
                                /128 for IPv6.
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="ipRuleDescription">
                                Description
                            </label>
                            <textarea
                                class="form-control"
                                id="ipRuleDescription"
                                rows="3"
                                maxlength="1000"
                                placeholder="Why this network should be allowed or denied"
                            ></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="ipRuleStartsAt">
                                Starts at
                            </label>
                            <input
                                class="form-control"
                                id="ipRuleStartsAt"
                                type="datetime-local"
                            >
                            <div class="form-text">
                                Leave empty to start immediately.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="ipRuleExpiresAt">
                                Expires at
                            </label>
                            <input
                                class="form-control"
                                id="ipRuleExpiresAt"
                                type="datetime-local"
                            >
                            <div class="form-text">
                                Leave empty for no automatic expiry.
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input
                                    class="form-check-input"
                                    id="ipRuleEnabled"
                                    type="checkbox"
                                    checked
                                >
                                <label
                                    class="form-check-label"
                                    for="ipRuleEnabled"
                                >
                                    Enable this rule
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="saveIpRuleBtn"
                    >
                        Save rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/ip_whitelist_blacklist.js?v=<?= asset_version('js/pages/ip_whitelist_blacklist.js') ?>"></script>
