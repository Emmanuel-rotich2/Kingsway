<?php
/**
 * Finance approvals workbench.
 *
 * Read-only HTML shell. Data loading, sorting, filtering and decisions live in
 * js/pages/finance_approvals.js. The server remains the authority for every
 * approval action.
 */
$appBase = htmlspecialchars($appBase ?? '', ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $appBase ?>/css/pages/finance-approvals.css?v=<?= asset_version('css/pages/finance-approvals.css') ?>">

<section class="fa-page" id="financeApprovalsPage" data-export-module="finance">
    <header class="fa-masthead">
        <div class="fa-masthead-copy">
            <p class="fa-masthead-label">Finance control desk</p>
            <h1>Approval workbench</h1>
            <p>Review each request with its reason recorded. Expense, budget and payroll decisions are never one-click actions.</p>
        </div>
        <div class="fa-masthead-actions">
            <button type="button" class="fa-button fa-button-quiet" id="faRefresh" title="Reload all finance sources">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Refresh
            </button>
            <button type="button" class="fa-button fa-button-quiet" id="faExport" title="Download the rows currently on screen as CSV">
                <i class="bi bi-filetype-csv" aria-hidden="true"></i> Export CSV
            </button>
            <button type="button" class="fa-button fa-button-light" id="faPrint" title="Print the rows currently on screen">
                <i class="bi bi-printer" aria-hidden="true"></i> Print
            </button>
        </div>
    </header>

    <div class="fa-meta-bar" aria-label="Data scope and freshness">
        <span>Scope: <strong id="faScope">Expenses, department budgets and payroll runs available to your account</strong></span>
        <span>Sources: <strong id="faSources">Loading&hellip;</strong></span>
        <span>As of: <strong id="faUpdated">&mdash;</strong></span>
    </div>

    <div class="fa-state" id="faState" hidden></div>
    <span class="fa-sr-only" id="faAnnouncer" role="status" aria-live="polite"></span>

    <section class="fa-summary" aria-label="Approval summary">
        <article class="fa-summary-card fa-summary-wait">
            <p>Awaiting decision</p>
            <strong id="faPendingCount">&mdash;</strong>
            <span id="faPendingSub">Loading&hellip;</span>
        </article>
        <article class="fa-summary-card fa-summary-value">
            <p>Value awaiting decision</p>
            <strong id="faPendingValue">&mdash;</strong>
            <span id="faPendingValueSub">Loading&hellip;</span>
        </article>
        <article class="fa-summary-card fa-summary-approved">
            <p>Approved in loaded set</p>
            <strong id="faApprovedCount">&mdash;</strong>
            <span id="faApprovedSub">Loading&hellip;</span>
        </article>
        <article class="fa-summary-card fa-summary-rejected">
            <p>Rejected in loaded set</p>
            <strong id="faRejectedCount">&mdash;</strong>
            <span id="faRejectedSub">Loading&hellip;</span>
        </article>
    </section>

    <section class="fa-attention" aria-labelledby="faAttentionTitle">
        <div class="fa-attention-heading">
            <h2 id="faAttentionTitle">Needs attention</h2>
            <p>Open requests that still require an authorized decision.</p>
        </div>
        <div class="fa-attention-items" id="faAttention">
            <p class="fa-loading-note">Loading the decision queue&hellip;</p>
        </div>
    </section>

    <section class="fa-filters" aria-label="Filter approval requests">
        <label class="fa-field fa-field-search">
            <span>Search</span>
            <input type="search" id="faSearch" placeholder="Reference, person or description" autocomplete="off">
        </label>
        <label class="fa-field">
            <span>Decision</span>
            <select id="faStatusFilter">
                <option value="pending" selected>Awaiting decision</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="">All decisions</option>
            </select>
        </label>
        <label class="fa-field">
            <span>Request type</span>
            <select id="faTypeFilter">
                <option value="">All types</option>
                <option value="expense">Expenses</option>
                <option value="budget">Budget proposals</option>
                <option value="payroll">Payroll runs</option>
            </select>
        </label>
        <label class="fa-field fa-field-date">
            <span>From</span>
            <input type="date" id="faDateFrom">
        </label>
        <label class="fa-field fa-field-date">
            <span>To</span>
            <input type="date" id="faDateTo">
        </label>
        <label class="fa-field fa-field-sort">
            <span>Sort</span>
            <select id="faSortBy">
                <option value="date:descending">Newest first</option>
                <option value="date:ascending">Oldest first</option>
                <option value="amount:descending">Highest amount</option>
                <option value="amount:ascending">Lowest amount</option>
                <option value="ref:ascending">Reference A&ndash;Z</option>
                <option value="ref:descending">Reference Z&ndash;A</option>
                <option value="type:ascending">Type A&ndash;Z</option>
                <option value="type:descending">Type Z&ndash;A</option>
                <option value="requested_by:ascending">Requested by A&ndash;Z</option>
                <option value="requested_by:descending">Requested by Z&ndash;A</option>
                <option value="description:ascending">Description A&ndash;Z</option>
                <option value="description:descending">Description Z&ndash;A</option>
                <option value="status:ascending">Decision state A&ndash;Z</option>
                <option value="status:descending">Decision state Z&ndash;A</option>
            </select>
        </label>
        <button type="button" class="fa-clear-button" id="faClearFilters">Clear filters</button>
    </section>

    <section class="fa-queue" aria-labelledby="faQueueTitle">
        <div class="fa-queue-head">
            <div>
                <h2 id="faQueueTitle">Decision queue</h2>
                <p id="faQueueNote">Loading requests&hellip;</p>
            </div>
            <p class="fa-queue-legend"><span class="fa-dot fa-dot-pending" aria-hidden="true"></span>Awaiting decision <span class="fa-dot fa-dot-approved" aria-hidden="true"></span>Approved <span class="fa-dot fa-dot-rejected" aria-hidden="true"></span>Rejected</p>
        </div>

        <div class="fa-table-region" role="region" aria-labelledby="faQueueCaption" tabindex="0">
            <table class="fa-table" id="faQueueTable" data-export-title="Finance approval queue" data-export-module="finance">
                <caption class="fa-sr-only" id="faQueueCaption">Finance approval requests with review actions</caption>
                <thead>
                    <tr>
                        <th scope="col" data-type="text" data-sort-key="ref"><button type="button" commandfor="faQueueTable" command="--sort" data-sort-key="ref">Request<span class="fa-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="text" data-sort-key="type"><button type="button" commandfor="faQueueTable" command="--sort" data-sort-key="type">Type<span class="fa-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="date" data-sort-key="date" aria-sort="descending"><button type="button" commandfor="faQueueTable" command="--sort" data-sort-key="date">Requested<span class="fa-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="text" data-sort-key="requested_by"><button type="button" commandfor="faQueueTable" command="--sort" data-sort-key="requested_by">Requested by<span class="fa-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="text" data-sort-key="description"><button type="button" commandfor="faQueueTable" command="--sort" data-sort-key="description">Request detail<span class="fa-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" class="fa-num" data-type="number" data-sort-key="amount"><button type="button" commandfor="faQueueTable" command="--sort" data-sort-key="amount">Amount (KES)<span class="fa-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="text" data-sort-key="status"><button type="button" commandfor="faQueueTable" command="--sort" data-sort-key="status">Decision<span class="fa-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody id="faQueueBody">
                    <tr><td colspan="8" class="fa-empty-cell">Loading approval requests&hellip;</td></tr>
                </tbody>
            </table>
        </div>

        <nav class="fa-pagination" id="faPagination" aria-label="Approval queue pages"></nav>
    </section>
</section>

<dialog class="fa-dialog" id="faDialog" aria-labelledby="faDialogTitle">
    <div class="fa-dialog-head">
        <div>
            <p class="fa-dialog-label">Approval decision</p>
            <h2 id="faDialogTitle">Review request</h2>
        </div>
        <button type="button" class="fa-dialog-close" id="faCloseDecision" aria-label="Close review">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="fa-dialog-body">
        <section class="fa-brief" aria-label="Request summary">
            <div class="fa-brief-primary">
                <span class="fa-brief-ref" id="faDialogRef">&mdash;</span>
                <h3 id="faDialogDescription">&mdash;</h3>
                <p id="faDialogSource">&mdash;</p>
            </div>
            <dl class="fa-brief-facts">
                <div><dt>Amount</dt><dd id="faDialogAmount">&mdash;</dd></div>
                <div><dt>Requested by</dt><dd id="faDialogRequestedBy">&mdash;</dd></div>
                <div><dt>Requested</dt><dd id="faDialogDate">&mdash;</dd></div>
                <div><dt>Type</dt><dd id="faDialogType">&mdash;</dd></div>
                <div><dt>Status</dt><dd id="faDialogStatus">&mdash;</dd></div>
            </dl>
        </section>

        <section class="fa-evidence" aria-label="Review reminders">
            <h3>Before you decide</h3>
            <ul>
                <li>Confirm the amount against the request, quote or payroll period.</li>
                <li>Open any supporting document linked to the request.</li>
                <li>Record why the decision was made. That reason is kept with the approval.</li>
            </ul>
            <div class="fa-attachments" id="faAttachments" hidden>
                <strong>Supporting document</strong>
                <div id="faAttachmentsList"></div>
            </div>
            <p class="fa-evidence-context" id="faDialogContext"></p>
        </section>

        <fieldset class="fa-decision-panel" id="faDecisionPanel">
            <legend>Your decision</legend>
            <p class="fa-permission-note" id="faPermissionNote" hidden></p>
            <div class="fa-decision-choices">
                <label class="fa-choice fa-choice-approve">
                    <input type="radio" name="faDecision" id="faDecisionApprove" value="approve">
                    <span><strong>Approve</strong><small>Release the request to the next finance step.</small></span>
                </label>
                <label class="fa-choice fa-choice-reject">
                    <input type="radio" name="faDecision" id="faDecisionReject" value="reject">
                    <span><strong>Reject</strong><small>Return the request with a clear reason.</small></span>
                </label>
            </div>
            <div class="fa-decision-fields">
                <label class="fa-field">
                    <span id="faReasonLabel">Reason</span>
                    <select id="faReason" required>
                        <option value="">Choose a reason</option>
                    </select>
                </label>
                <label class="fa-field fa-field-note">
                    <span>Decision note <small>(at least 10 characters)</small></span>
                    <textarea id="faNote" rows="3" minlength="10" maxlength="500" required placeholder="What did you check, and what led to this decision?"></textarea>
                </label>
                <span class="fa-note-count" id="faNoteCount">0 / 500</span>
            </div>
        </fieldset>
    </div>

    <div class="fa-dialog-foot">
        <button type="button" class="fa-button fa-button-quiet" id="faCancelDecision">Cancel</button>
        <button type="button" class="fa-button fa-button-primary" id="faConfirmDecision" data-year-write disabled>Record decision</button>
    </div>
</dialog>

<?php asset_script($appBase, 'js/pages/finance_approvals.js'); ?>
