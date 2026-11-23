<?php
/**
 * Payroll Ledger
 *
 * Read-only ledger of posted payroll records. Preparation, approval and
 * disbursement remain in the Finance payroll workflow.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/payroll-ledger.css?v=<?= asset_version('css/pages/payroll-ledger.css') ?>">

<div class="payroll-ledger-page kw-clean" id="payrollLedgerPage" data-export-module="finance" data-export-title="Payroll Ledger">
    <header class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
            <div>
                <p class="text-uppercase small fw-semibold mb-1">Payroll</p>
                <h1 class="mb-1">Payroll Ledger</h1>
                <p class="text-muted mb-0">A read-only history of prepared staff payroll records. Use the filters to focus on a period or member of staff.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 no-print" aria-label="Payroll ledger actions">
                <button class="btn btn-sm btn-outline-secondary" type="button" id="exportPayrollCsv" hidden>
                    <i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i> Export CSV
                </button>
                <button class="btn btn-sm btn-outline-secondary" type="button" id="printPayrollLedger" hidden>
                    <i class="bi bi-printer me-1" aria-hidden="true"></i> Print ledger
                </button>
                <div class="btn-group" role="group" aria-label="Batch payslip export">
                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="batchPayslipExportBtn" hidden>
                        <i class="bi bi-download me-1" aria-hidden="true"></i> Batch Payslips
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="batchPayslipExportBtn">
                        <li><h6 class="dropdown-header">Export filtered payslips</h6></li>
                        <li><button class="dropdown-item" type="button" data-batch-format="pdf"><i class="bi bi-file-earmark-pdf me-2"></i>PDF (combined)</button></li>
                        <li><button class="dropdown-item" type="button" data-batch-format="zip"><i class="bi bi-file-earmark-zip me-2"></i>ZIP (individual PDFs)</button></li>
                        <li><button class="dropdown-item" type="button" data-batch-format="csv"><i class="bi bi-file-earmark-spreadsheet me-2"></i>CSV</button></li>
                        <li><button class="dropdown-item" type="button" data-batch-format="excel"><i class="bi bi-file-earmark-excel me-2"></i>Excel</button></li>
                        <li><button class="dropdown-item" type="button" data-batch-format="tsv"><i class="bi bi-filetype-tsv me-2"></i>TSV</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">By payroll run</h6></li>
                        <li><button class="dropdown-item" type="button" id="batchByRunPdf"><i class="bi bi-file-earmark-pdf me-2"></i>PDF for selected run</button></li>
                        <li><button class="dropdown-item" type="button" id="batchByRunZip"><i class="bi bi-file-earmark-zip me-2"></i>ZIP for selected run</button></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <section aria-labelledby="payrollSummaryTitle" class="mb-3">
        <h2 class="visually-hidden" id="payrollSummaryTitle">Payroll ledger summary</h2>
        <div class="row g-0 payroll-summary-strip">
            <div class="col-6 col-lg-3">
                <div class="card">
                    <div class="card-body text-center">
                        <small>Records shown</small>
                        <div class="payroll-summary-value" id="payrollRecordCount">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card">
                    <div class="card-body text-center">
                        <small>Gross payroll</small>
                        <div class="payroll-summary-value" id="payrollGrossTotal">KES 0.00</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card">
                    <div class="card-body text-center">
                        <small>Total deductions</small>
                        <div class="payroll-summary-value" id="payrollDeductionTotal">KES 0.00</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card">
                    <div class="card-body text-center">
                        <small>Net payroll</small>
                        <div class="payroll-summary-value" id="payrollNetTotal">KES 0.00</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card payroll-filter-card mb-3 no-print" aria-labelledby="payrollFilterTitle">
        <div class="card-header">
            <h2 id="payrollFilterTitle">Filter ledger</h2>
            <span class="small text-muted">Results update automatically</span>
        </div>
        <div class="card-body">
            <search aria-labelledby="payrollFilterTitle">
                <form id="payrollFilterForm" method="get" action="" aria-describedby="payrollFilterHelp">
                    <p class="visually-hidden" id="payrollFilterHelp">Choose a date range, status or staff member. The payroll records reload automatically.</p>
                    <div class="row g-3 align-items-end">
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="payrollYearFromFilter">Year from</label>
                            <select class="form-select" id="payrollYearFromFilter" name="year_from">
                                <option value="">Any</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="payrollMonthFromFilter">Month from</label>
                            <select class="form-select" id="payrollMonthFromFilter" name="month_from">
                                <option value="">Any</option>
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="payrollYearToFilter">Year to</label>
                            <select class="form-select" id="payrollYearToFilter" name="year_to">
                                <option value="">Any</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="payrollMonthToFilter">Month to</label>
                            <select class="form-select" id="payrollMonthToFilter" name="month_to">
                                <option value="">Any</option>
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="payrollStatusFilter">Status</label>
                            <select class="form-select" id="payrollStatusFilter" name="status">
                                <option value="">All statuses</option>
                                <option value="draft">Draft</option>
                                <option value="approved">Approved</option>
                                <option value="paid">Paid</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="payrollSearchFilter">Staff member</label>
                            <input class="form-control" type="search" id="payrollSearchFilter" name="search" placeholder="Name or staff number" autocomplete="off" spellcheck="false">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3 no-print">
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="clearPayrollFilters">
                            <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i> Clear filters
                        </button>
                    </div>
                </form>
            </search>
        </div>
    </section>

    <section class="card payroll-records-card" aria-labelledby="payrollRecordsTitle">
        <div class="card-header">
            <div>
                <h2 id="payrollRecordsTitle">Payroll records</h2>
                <p class="small text-muted mb-0" id="payrollResultsSummary" role="status" aria-live="polite">Loading payroll records…</p>
            </div>
        </div>
        <div class="payroll-table-wrapper table-responsive" id="payrollTableWrapper" tabindex="0" role="region" aria-labelledby="payrollLedgerCaption" aria-busy="true">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden" id="payrollLedgerCaption">Payroll ledger records</caption>
                <thead>
                    <tr>
                        <th scope="col">Staff member</th>
                        <th scope="col">Period</th>
                        <th scope="col" class="text-end">Basic salary</th>
                        <th scope="col" class="text-end">Allowances</th>
                        <th scope="col" class="text-end">Gross pay</th>
                        <th scope="col" class="text-end">Deductions</th>
                        <th scope="col" class="text-end">Net pay</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Payslip action</span></th>
                    </tr>
                </thead>
                <tbody id="payrollTableBody">
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Loading payroll records…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="payrollPayslipModal" tabindex="-1" aria-labelledby="payrollPayslipModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="payrollPayslipModalTitle">Payslip</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close payslip"></button>
            </div>
            <div class="modal-body" id="payrollPayslipModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printPayrollPayslip" hidden>
                    <i class="bi bi-printer me-1" aria-hidden="true"></i> Print / save PDF
                </button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/staff_access.js'); ?>
<?php asset_script($appBase, 'js/pages/payroll.js'); ?>
