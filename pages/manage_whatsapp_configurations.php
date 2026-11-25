<?php
/** Kingsway System Administrator: WhatsApp Provider Configuration (system domain). */

$whatsappFieldDefinitions = \App\API\Services\SystemCommsConfigService::fieldDefinitions('whatsapp');
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>WhatsApp provider configuration</h2>
            <p class="text-muted mb-0">Configure and test the WhatsApp gateway used for school notifications.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="whatsappExportCsv"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Export CSV</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="whatsappPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print / PDF</button>
            <button type="button" class="btn btn-success btn-sm" id="whatsappRefresh"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh</button>
        </div>
    </div>
    <div id="whatsappState" class="alert alert-info" role="status" aria-live="polite">Loading WhatsApp configuration…</div>
    <div class="mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0">WhatsApp provider fields</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive"><table class="table table-sm mb-0" id="whatsappFieldsTable">
                    <caption class="visually-hidden">WhatsApp provider settings</caption>
                    <thead><tr><th scope="col">Setting</th><th scope="col">Value</th><th scope="col">Source</th><th scope="col">Actions</th></tr></thead>
                    <tbody id="whatsappFieldsBody">
                        <?php foreach ($whatsappFieldDefinitions as $field): ?>
                            <tr data-whatsapp-field="<?= htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8') ?>">
                                <td>
                                    <strong data-field-label><?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                    <small class="text-muted" data-field-key><?= htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td>
                                    <?php if ($field['is_secret']): ?>
                                        <span class="badge bg-secondary" data-secret-status>Not set</span>
                                    <?php else: ?>
                                        <code data-field-value>—</code>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary" data-field-source>Environment</span></td>
                                <td>
                                    <?php if (!$field['is_secret']): ?>
                                        <input type="text" class="form-control form-control-sm whatsapp-field-input"
                                               data-key="<?= htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8') ?>"
                                               aria-label="<?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?>"
                                               value="">
                                    <?php else: ?>
                                        <span class="text-muted small">Managed securely</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
    <div class="mt-3 d-flex justify-content-end"><button type="button" class="btn btn-success btn-sm" id="whatsappSaveBtn"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save WhatsApp settings</button></div>
    <hr class="my-4">
    <div class="card">
        <div class="card-header"><h3 class="h6 mb-0"><i class="bi bi-activity me-1" aria-hidden="true"></i>Test WhatsApp connection</h3></div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><button type="button" class="btn btn-outline-success btn-sm" id="whatsappTestBtn"><i class="bi bi-lightning me-1" aria-hidden="true"></i>Test connection</button></div>
                <div class="col-md-9"><div id="whatsappTestResult" class="text-muted small" role="status" aria-live="polite"></div></div>
            </div>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/manage_whatsapp_configurations.js?v=<?= asset_version('js/pages/system/manage_whatsapp_configurations.js') ?>"></script>
