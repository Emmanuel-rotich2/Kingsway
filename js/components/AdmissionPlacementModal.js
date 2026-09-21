/* Shared admissions class/stream placement modal. */
(function (window) {
    'use strict';

    const esc = (value) => {
        const node = document.createElement('div');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    };

    window.AdmissionPlacementModal = {
        open: function ({ application, classes, apiCall, onSuccess }) {
            const rows = Array.isArray(classes) ? classes.filter(row => row.academic_year_class_stream_id && row.stream_id) : [];
            if (!rows.length) throw new Error('No active class streams are configured for placement.');

            document.getElementById('admissionSharedPlacementModal')?.remove();
            const modalHtml = `<div class="modal fade" id="admissionSharedPlacementModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
                    <form class="modal-content" id="admissionSharedPlacementForm">
                        <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Place Student in Class Stream</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label">Application No.</label><input class="form-control" value="${esc(application.application_no || '—')}" readonly></div>
                                <div class="col-md-5"><label class="form-label">Applicant</label><input class="form-control" value="${esc(application.applicant_name || '—')}" readonly></div>
                                <div class="col-md-3"><label class="form-label">Grade</label><input class="form-control" value="${esc(application.grade_applying_for || '—')}" readonly></div>
                                <div class="col-12"><label class="form-label fw-semibold">Class / stream <span class="text-danger">*</span></label><select class="form-select" name="placement_option" required><option value="">Select class stream...</option>${rows.map(row => `<option value="${Number(row.academic_year_class_stream_id)}:${Number(row.id)}:${Number(row.stream_id)}">${esc(`${row.name || 'Class'}${row.stream_name ? ` — ${row.stream_name}` : ''}`)}</option>`).join('')}</select><div class="form-text">Select the configured academic-year class and stream. Capacity is validated by the server.</div></div>
                                <div class="col-12"><label class="form-label">Placement remarks</label><textarea class="form-control" name="remarks" rows="3" placeholder="Optional placement note"></textarea></div>
                                <div class="col-12"><div class="alert alert-info small mb-0">Placement creates the academic enrollment, learning areas, fee obligations, attendance context, and boarding assignment where applicable.</div></div>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Save placement</button></div>
                    </form>
                </div>
            </div>`;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const element = document.getElementById('admissionSharedPlacementModal');
            const modal = bootstrap.Modal.getOrCreateInstance(element);
            const form = document.getElementById('admissionSharedPlacementForm');
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const button = form.querySelector('button[type="submit"]');
                const selected = String(new FormData(form).get('placement_option') || '').split(':');
                if (!selected[0] || !selected[1] || !selected[2]) return;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
                try {
                    await apiCall('/admission/complete-enrollment', 'POST', {
                        application_id: Number(application.id),
                        academic_year_class_stream_id: Number(selected[0]),
                        class_id: Number(selected[1]),
                        stream_id: Number(selected[2]),
                        remarks: new FormData(form).get('remarks') || ''
                    });
                    window.showNotification?.('Student placed successfully. Fee obligations and school records were updated.', 'success');
                    modal.hide();
                    onSuccess?.();
                } catch (error) {
                    window.showNotification?.(error.message || 'Unable to save class placement.', 'error');
                } finally {
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Save placement';
                }
            });
            element.addEventListener('hidden.bs.modal', () => element.remove(), { once: true });
            modal.show();
        }
    };
})(window);
