<?php
/**
 * Generate Subject Report - Subject Teacher Subject Report Generation
 * Role: Subject Teacher (8)
 * Purpose: Generate various subject reports for their assigned subjects
 * Shows only subjects the teacher is assigned to
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>Generate Subject Report
                    </h5>
                    <small class="text-muted">Generate various reports for your assigned subjects</small>
                </div>
                <div class="card-body">
                    <div id="reportLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading subject data...</p>
                    </div>
                    <div id="reportContent" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="nav nav-tabs flex-nowrap overflow-auto mb-2" role="tablist" aria-label="Subject report views">
                                    <button class="nav-link active" type="button" data-report-tab-target="reportType" data-report-tab-value="performance">Subject Performance</button>
                                    <button class="nav-link" type="button" data-report-tab-target="reportType" data-report-tab-value="assessment">Assessment Summary</button>
                                </div>
                                <select id="reportType" class="d-none" data-permission="reports_view" aria-hidden="true" tabindex="-1">
                                    <option value="">Select Report Type</option>
                                    <option value="performance" selected>Subject Performance Report</option>
                                    <option value="assessment">Assessment Summary</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Academic Year</label>
                                <select id="yearFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Years</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Term</label>
                                <select id="termFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Terms</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Subject</label>
                                <select id="subjectFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Learning Areas</option>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Class</label><select id="classFilter" class="form-select" data-permission="academic_view"><option value="">All Classes</option></select></div>
                        </div>

                        <div class="alert alert-danger d-none" id="reportError" role="alert"></div>
                        <div id="reportSummary" class="d-flex flex-wrap gap-2 mb-3"></div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="printBtn" class="btn btn-outline-primary" data-permission="reports_print">
                                    <i class="bi bi-printer me-1"></i>Print / PDF
                                </button>
                                <button id="exportBtn" class="btn btn-outline-success" data-permission="reports_export"><i class="bi bi-download me-1"></i>Export CSV</button>
                                <button id="refreshBtn" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                            </div>
                            <small class="text-muted">Results update when filters change.</small>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="reportsTable">
                                <thead><tr id="reportsTableHead"></tr></thead>
                                <tbody id="reportsTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Select a report type and subject; use Print Report only when a paper or PDF copy is needed
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/components/report_tabs.js'); ?>
<?php asset_script($appBase, 'js/pages/generate_subject_report.js'); ?>
