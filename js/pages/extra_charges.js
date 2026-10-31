/**
 * Extra Charges — JS controller
 * Manages flexible, database-driven charges that are NOT part of the normal fee structure.
 * Registration, caution, trips, activities, etc. Transport and uniforms are
 * billed by their own entitlement/sales modules.
 */
(function () {
    'use strict';

    var charges = [];
    var academicYears = [];
    var glAccounts = [];
    var classes = [];
    var currentYearId = null;

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function notify(msg, type) {
        if (typeof window.showNotification === 'function') {
            window.showNotification(msg, type);
        }
    }

    // ── Filters ──────────────────────────────────────────────────────────

    async function loadFilters() {
        var yearSel = document.getElementById('filterYear');
        yearSel.innerHTML = '<option value="">Loading...</option>';

        try {
            var res = await window.API.finance.getExtraChargesAcademicYears();
            var data = res?.data || res;
            academicYears = data.years || [];
            yearSel.innerHTML = '<option value="">Current Year</option>';
            academicYears.forEach(function (y) {
                var opt = document.createElement('option');
                opt.value = y.id;
                opt.textContent = y.year_code || y.year_name || ('Year ' + y.id);
                if (y.is_current) opt.selected = true;
                yearSel.appendChild(opt);
                if (y.is_current && !currentYearId) currentYearId = y.id;
            });
            if (!currentYearId && academicYears.length) {
                currentYearId = academicYears[0].id;
                yearSel.value = currentYearId;
            }
        } catch (e) {
            yearSel.innerHTML = '<option value="">Failed to load years</option>';
            notify('Academic years could not be loaded. Check your permissions or API route.', 'error');
        }

        try {
            var acctRes = await window.API.finance.getExtraChargesGLAccounts();
            var acctData = acctRes?.data || acctRes;
            glAccounts = acctData.accounts || [];
        } catch (e) {
            glAccounts = [];
        }

        try {
            var clsRes = await window.API.academic.listClasses({ limit: 200, academic_year_id: currentYearId || undefined });
            var clsData = clsRes?.data || clsRes;
            classes = Array.isArray(clsData) ? clsData : (clsData.classes || []);
        } catch (e) {
            classes = [];
        }
    }

    async function loadCharges() {
        var yearId = document.getElementById('filterYear').value;
        var status = document.getElementById('filterStatus').value;
        var targetScope = document.getElementById('filterTarget').value;
        var billingModel = document.getElementById('filterBilling').value;
        currentYearId = yearId || currentYearId;

        var params = {};
        if (yearId) params.academic_year = yearId;
        if (status) params.status = status;
        if (targetScope) params.target_scope = targetScope;
        if (billingModel) params.billing_model = billingModel;

        try {
            var res = await window.API.finance.listExtraCharges(params);
            var data = res?.data || res;
            charges = data.charges || [];
            renderTable();
        } catch (e) {
            document.getElementById('chargesBody').innerHTML =
                '<tr><td colspan="10" class="text-center text-danger py-3">Failed to load charges</td></tr>';
        }
    }

    // ── Table Rendering ──────────────────────────────────────────────────

    var freqLabels = {
        one_time: 'One Time', daily: 'Daily', weekly: 'Weekly',
        monthly: 'Monthly', per_term: 'Per Term', per_year: 'Per Year'
    };
    var targetLabels = {
        all_students: 'All Students', new_admissions: 'New Admissions',
        existing_students: 'Existing Students', boarders: 'Boarders',
        day_students: 'Day Students', specific_class: 'Specific Class'
    };
    var billingLabels = {
        added_to_fees: 'Added to Fees', paid_separately: 'Paid Separately', optional: 'Optional'
    };
    var calcLabels = { fixed: 'Fixed', per_unit: 'Per Unit' };
    var statusBadges = {
        draft: '<span class="badge bg-secondary">Draft</span>',
        submitted: '<span class="badge bg-info">Submitted</span>',
        active: '<span class="badge bg-success">Active</span>',
        inactive: '<span class="badge bg-dark">Inactive</span>',
    };

    function renderTable() {
        var tbody = document.getElementById('chargesBody');
        if (!tbody) return;
        if (!charges.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No extra charges found</td></tr>';
            return;
        }

        tbody.innerHTML = charges.map(function (c, i) {
            var canEdit = c.status === 'draft';
            var canSubmit = c.status === 'draft';
            var canApprove = c.status !== 'active' && c.status !== 'inactive' && canApproveCharges();
            var canDelete = c.status === 'draft' || c.status === 'inactive';

            var hasTiers = Array.isArray(c.pricing_tiers) && c.pricing_tiers.length > 0;
            var amountStr;
            if (hasTiers) {
                amountStr = c.pricing_tiers.map(function (t) {
                    return '<div><small>' + escHtml(t.label || 'Tier') + ': '
                        + Number(t.amount).toLocaleString('en-KE', { minimumFractionDigits: 0 }) + '</small></div>';
                }).join('');
            } else {
                amountStr = Number(c.amount).toLocaleString('en-KE', { minimumFractionDigits: 0 });
                if (c.calculation_mode === 'per_unit' && c.unit_label) {
                    amountStr += ' <small class="text-muted">/ ' + escHtml(c.unit_label) + '</small>';
                }
            }

            var targetStr = targetLabels[c.target_scope] || c.target_scope;
            if (c.target_scope === 'specific_class' && c.class_name) {
                targetStr += '<br><small class="text-muted">' + escHtml(c.class_name) + '</small>';
            }

            var actions = '';
            if (canEdit) actions += '<button class="btn btn-outline-primary btn-sm me-1" onclick="window.extraCharges.edit(' + c.id + ')" title="Edit"><i class="bi bi-pencil"></i></button>';
            if (canSubmit) actions += '<button class="btn btn-outline-info btn-sm me-1" onclick="window.extraCharges.submit(' + c.id + ')" title="Submit for Review"><i class="bi bi-send"></i></button>';
            if (canApprove) actions += '<button class="btn btn-outline-success btn-sm me-1" onclick="window.extraCharges.approve(' + c.id + ')" title="Approve"><i class="bi bi-check-lg"></i></button>';
            if (canApprove) actions += '<button class="btn btn-outline-warning btn-sm me-1" onclick="window.extraCharges.reject(' + c.id + ')" title="Reject"><i class="bi bi-x-lg"></i></button>';
            if (canDelete) actions += '<button class="btn btn-outline-danger btn-sm" onclick="window.extraCharges.remove(' + c.id + ')" title="Delete"><i class="bi bi-trash"></i></button>';

            return '<tr>'
                + '<td>' + (i + 1) + '</td>'
                + '<td><strong>' + escHtml(c.name) + '</strong>'
                    + (c.description ? '<br><small class="text-muted">' + escHtml(c.description) + '</small>' : '')
                    + (c.gl_account_name ? '<br><small class="text-info"><i class="bi bi-bank"></i> ' + escHtml(c.gl_account_name) + '</small>' : '')
                + '</td>'
                + '<td class="text-end">' + amountStr + '</td>'
                + '<td>' + (calcLabels[c.calculation_mode] || c.calculation_mode) + '</td>'
                + '<td>' + (freqLabels[c.billing_frequency] || c.billing_frequency) + '</td>'
                + '<td>' + (billingLabels[c.billing_model] || c.billing_model) + '</td>'
                + '<td>' + targetStr + '</td>'
                + '<td class="text-center">' + (c.visible_on_fee_structure ? '<i class="bi bi-eye text-success"></i>' : '<i class="bi bi-eye-slash text-muted"></i>') + '</td>'
                + '<td>' + (statusBadges[c.status] || escHtml(c.status)) + '</td>'
                + '<td>' + actions + '</td>'
                + '</tr>';
        }).join('');
    }

    // ── Pricing Tiers ────────────────────────────────────────────────────

    // Approval authority: Director, School Administrator, System Administrator only.
    function canApproveCharges() {
        var ctx = window.AuthContext;
        if (!ctx) return false;
        return ctx.hasRole('director')
            || ctx.hasRole('school administrator')
            || ctx.hasRole('school admin')
            || ctx.hasRole('system administrator');
    }

    function renderTiers(tiers) {
        var container = document.getElementById('pricingTiersContainer');
        tiers = tiers || [];
        if (!tiers.length) {
            container.innerHTML = '';
            return;
        }
        container.innerHTML = tiers.map(function (t, idx) {
            return '<div class="row g-2 mb-2 tier-row" data-tier-idx="' + idx + '">'
                + '<div class="col-md-4">'
                + '<input type="text" class="form-control form-control-sm tier-label" placeholder="Label (e.g. New Parents)" value="' + escHtml(t.label || '') + '">'
                + '</div>'
                + '<div class="col-md-3">'
                + '<input type="number" class="form-control form-control-sm tier-amount" placeholder="Amount" min="0" step="0.01" value="' + (t.amount || '') + '">'
                + '</div>'
                + '<div class="col-md-3">'
                + '<input type="text" class="form-control form-control-sm tier-condition" placeholder="Condition (e.g. new)" value="' + escHtml(t.condition || '') + '">'
                + '</div>'
                + '<div class="col-md-2">'
                + '<button type="button" class="btn btn-outline-danger btn-sm w-100 btn-remove-tier" data-idx="' + idx + '"><i class="bi bi-x"></i></button>'
                + '</div>'
                + '</div>';
        }).join('');
    }

    function collectTiers() {
        var rows = document.querySelectorAll('#pricingTiersContainer .tier-row');
        var tiers = [];
        rows.forEach(function (row) {
            var label = row.querySelector('.tier-label').value.trim();
            var amount = parseFloat(row.querySelector('.tier-amount').value) || 0;
            var condition = row.querySelector('.tier-condition').value.trim();
            if (label && amount > 0) {
                tiers.push({ label: label, amount: amount, condition: condition });
            }
        });
        return tiers.length ? tiers : null;
    }

    function addTier() {
        var container = document.getElementById('pricingTiersContainer');
        var existing = collectTiers() || [];
        existing.push({ label: '', amount: 0, condition: '' });
        renderTiers(existing);
    }

    // ── Dynamic Form Behavior ────────────────────────────────────────────

    function updateCalcModeUI() {
        var mode = document.getElementById('chargeCalcMode').value;
        document.getElementById('unitLabelGroup').style.display = mode === 'per_unit' ? '' : 'none';
        document.getElementById('unitPriceGroup').style.display = mode === 'per_unit' ? '' : 'none';
    }

    function updateTargetScopeUI() {
        var scope = document.getElementById('chargeTargetScope').value;
        var classGroup = document.getElementById('classSelectGroup');
        classGroup.style.display = scope === 'specific_class' ? '' : 'none';

        if (scope === 'specific_class') {
            var sel = document.getElementById('chargeClassId');
            sel.innerHTML = '<option value="">Select Class</option>';
            classes.forEach(function (c) {
                sel.innerHTML += '<option value="' + c.id + '">' + escHtml(c.name) + '</option>';
            });
        }
    }

    function populateGLAccounts() {
        var sel = document.getElementById('chargeGLAccount');
        sel.innerHTML = '<option value="">None</option>';
        glAccounts.forEach(function (a) {
            sel.innerHTML += '<option value="' + a.id + '">' + escHtml(a.account_code + ' — ' + a.account_name) + '</option>';
        });
    }

    function populateAcademicYearSelect(selectedId) {
        var sel = document.getElementById('chargeAcademicYearId');
        sel.innerHTML = '<option value="">Select academic year</option>';
        academicYears.forEach(function (y) {
            var opt = document.createElement('option');
            opt.value = y.id;
            opt.textContent = y.year_code || y.year_name || ('Year ' + y.id);
            if (String(y.id) === String(selectedId || currentYearId || '')) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    // ── Modal Open / Save ────────────────────────────────────────────────

    function openCreateModal() {
        document.getElementById('chargeModalTitle').textContent = 'New Extra Charge';
        document.getElementById('chargeForm').reset();
        document.getElementById('chargeId').value = '';
        document.getElementById('chargeVisible').checked = true;
        populateAcademicYearSelect(currentYearId);
        renderTiers([]);
        updateCalcModeUI();
        updateTargetScopeUI();
        populateGLAccounts();
        new bootstrap.Modal(document.getElementById('chargeModal')).show();
    }

    async function saveCharge() {
        var id = document.getElementById('chargeId').value;
        var data = {
            name: document.getElementById('chargeName').value.trim(),
            amount: parseFloat(document.getElementById('chargeAmount').value) || 0,
            calculation_mode: document.getElementById('chargeCalcMode').value,
            unit_label: document.getElementById('chargeUnitLabel').value.trim() || null,
            unit_price: null,
            billing_model: document.getElementById('chargeBillingModel').value,
            billing_frequency: document.getElementById('chargeBillingFreq').value,
            visible_on_fee_structure: document.getElementById('chargeVisible').checked ? 1 : 0,
            gl_account_id: document.getElementById('chargeGLAccount').value || null,
            target_scope: document.getElementById('chargeTargetScope').value,
            starts_on: document.getElementById('chargeStartsOn').value || null,
            ends_on: document.getElementById('chargeEndsOn').value || null,
            due_day: parseInt(document.getElementById('chargeDueDay').value) || null,
            academic_year_term_id: parseInt(document.getElementById('chargeTermId').value) || null,
            class_id: null,
            description: document.getElementById('chargeDescription').value.trim(),
            display_order: parseInt(document.getElementById('chargeDisplayOrder').value) || 0,
            pricing_tiers: collectTiers(),
        };

        if (!data.name) return notify('Name is required', 'error');
        if (data.amount <= 0) return notify('Amount must be greater than zero', 'error');

        if (data.calculation_mode === 'per_unit') {
            data.unit_price = parseFloat(document.getElementById('chargeUnitPrice').value) || 0;
        }

        if (data.target_scope === 'specific_class') {
            data.class_id = parseInt(document.getElementById('chargeClassId').value) || null;
        }

        var yearId = document.getElementById('chargeAcademicYearId').value || document.getElementById('filterYear').value;
        if (yearId) data.academic_year_id = parseInt(yearId);
        if (!data.academic_year_id) return notify('Academic year is required', 'error');

        if (data.gl_account_id) data.gl_account_id = parseInt(data.gl_account_id);

        try {
            if (id) {
                await window.API.finance.updateExtraCharge(id, data);
                notify('Charge updated', 'success');
            } else {
                await window.API.finance.createExtraCharge(data);
                notify('Charge created', 'success');
            }
            bootstrap.Modal.getInstance(document.getElementById('chargeModal')).hide();
            loadCharges();
        } catch (e) {
            notify(e.message || 'Save failed', 'error');
        }
    }

    async function editCharge(id) {
        try {
            var res = await window.API.finance.getExtraCharge(id);
            var data = res?.data || res;
            var c = data.charge
                || (Array.isArray(data.charges) ? data.charges.find(function (x) { return String(x.id) === String(id); }) : null)
                || data;

            document.getElementById('chargeModalTitle').textContent = 'Edit Extra Charge';
            document.getElementById('chargeId').value = c.id;
            document.getElementById('chargeName').value = c.name || '';
            document.getElementById('chargeAmount').value = c.amount || '';
            document.getElementById('chargeCalcMode').value = c.calculation_mode || 'fixed';
            document.getElementById('chargeUnitLabel').value = c.unit_label || '';
            document.getElementById('chargeUnitPrice').value = c.unit_price || '';
            document.getElementById('chargeBillingModel').value = c.billing_model || 'paid_separately';
            document.getElementById('chargeBillingFreq').value = c.billing_frequency || 'one_time';
            document.getElementById('chargeVisible').checked = !!c.visible_on_fee_structure;
            document.getElementById('chargeTargetScope').value = c.target_scope || 'all_students';
            document.getElementById('chargeDescription').value = c.description || '';
            document.getElementById('chargeDisplayOrder').value = c.display_order || 0;
            document.getElementById('chargeAcademicYearId').value = c.academic_year_id || '';
            populateAcademicYearSelect(c.academic_year_id);
            document.getElementById('chargeStartsOn').value = c.starts_on || '';
            document.getElementById('chargeEndsOn').value = c.ends_on || '';
            document.getElementById('chargeDueDay').value = c.due_day || '';
            document.getElementById('chargeTermId').value = c.academic_year_term_id || '';

            populateGLAccounts();
            if (c.gl_account_id) document.getElementById('chargeGLAccount').value = c.gl_account_id;

            updateCalcModeUI();
            updateTargetScopeUI();

            if (c.target_scope === 'specific_class' && c.class_id) {
                setTimeout(function () {
                    document.getElementById('chargeClassId').value = c.class_id;
                }, 100);
            }

            renderTiers(c.pricing_tiers || []);

            new bootstrap.Modal(document.getElementById('chargeModal')).show();
        } catch (e) {
            notify('Failed to load charge', 'error');
        }
    }

    // ── Actions ──────────────────────────────────────────────────────────

    async function submitCharge(id) {
        try {
            await window.API.finance.submitExtraCharge(id);
            notify('Submitted for review', 'success');
            loadCharges();
        } catch (e) {
            notify(e.message || 'Submit failed', 'error');
        }
    }

    async function approveCharge(id) {
        try {
            await window.API.finance.approveExtraCharge(id);
            notify('Charge approved', 'success');
            loadCharges();
        } catch (e) {
            notify(e.message || 'Approve failed', 'error');
        }
    }

    function rejectCharge(id) {
        document.getElementById('rejectChargeId').value = id;
        document.getElementById('rejectNotes').value = '';
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    }

    async function confirmReject() {
        var id = document.getElementById('rejectChargeId').value;
        var notes = document.getElementById('rejectNotes').value.trim();
        try {
            await window.API.finance.rejectExtraCharge(id, { notes: notes });
            notify('Charge rejected', 'success');
            bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
            loadCharges();
        } catch (e) {
            notify(e.message || 'Reject failed', 'error');
        }
    }

    async function deleteCharge(id) {
        if (!confirm('Remove this extra charge?')) return;
        try {
            await window.API.finance.deleteExtraCharge(id);
            notify('Charge removed', 'success');
            loadCharges();
        } catch (e) {
            notify(e.message || 'Delete failed', 'error');
        }
    }

    // ── Init ─────────────────────────────────────────────────────────────

    function init() {
        loadFilters().then(function () {
            loadCharges();
        });

        document.getElementById('btnCreateCharge').addEventListener('click', openCreateModal);
        document.getElementById('btnSaveCharge').addEventListener('click', saveCharge);
        document.getElementById('btnClearFilters').addEventListener('click', function () {
            document.getElementById('filterYear').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterTarget').value = '';
            document.getElementById('filterBilling').value = '';
            loadCharges();
        });
        document.getElementById('filterStatus').addEventListener('change', loadCharges);
        document.getElementById('filterTarget').addEventListener('change', loadCharges);
        document.getElementById('filterBilling').addEventListener('change', loadCharges);
        document.getElementById('filterYear').addEventListener('change', loadCharges);
        document.getElementById('chargeCalcMode').addEventListener('change', updateCalcModeUI);
        document.getElementById('chargeTargetScope').addEventListener('change', updateTargetScopeUI);
        document.getElementById('btnConfirmReject').addEventListener('click', confirmReject);
        document.getElementById('btnAddTier').addEventListener('click', addTier);

        document.getElementById('pricingTiersContainer').addEventListener('click', function (e) {
            if (e.target.closest('.btn-remove-tier')) {
                var idx = parseInt(e.target.closest('.btn-remove-tier').getAttribute('data-idx'));
                var tiers = collectTiers() || [];
                tiers.splice(idx, 1);
                renderTiers(tiers);
            }
        });
    }

    window.extraCharges = { edit: editCharge, submit: submitCharge, approve: approveCharge, reject: rejectCharge, remove: deleteCharge, load: loadCharges };
    window.APIRealtime?.register?.('extraCharges', window.extraCharges);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
