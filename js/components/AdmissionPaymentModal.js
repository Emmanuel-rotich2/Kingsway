/* Shared admissions payment-stage modal. */
(function (window) {
    'use strict';
    const esc = value => { const node = document.createElement('div'); node.textContent = String(value ?? ''); return node.innerHTML; };

    window.AdmissionPaymentModal = {
        open: function ({ application, registrationFee = 0, apiCall, onSuccess }) {
            document.getElementById('sharedAdmissionPaymentModal')?.remove();
            const reference = application.application_no || application.admission_number || '';
            const phone = application.phone_1 || application.parent_phone_1 || application.parent_phone || application.phone || '';
            const fee = Number(registrationFee || 0);
            const relief = application.financial_relief || {};
            const reliefParts = [];
            if (relief.registration_fee_waived) reliefParts.push('registration fee waived');
            if (relief.school_fee_waiver_type === 'full') reliefParts.push('full school-fee sponsorship');
            if (relief.school_fee_waiver_type === 'percentage') reliefParts.push(`${Number(relief.school_fee_waiver_value || 0)}% school-fee waiver`);
            if (relief.school_fee_waiver_type === 'fixed') reliefParts.push(`KES ${Number(relief.school_fee_waiver_value || 0).toLocaleString()} school-fee waiver`);
            const reliefNotice = reliefParts.length ? `<div class="alert alert-success small mb-0"><i class="bi bi-shield-check me-1"></i><strong>Approved financial relief:</strong> ${esc(reliefParts.join(' + '))}. This relief is applied to the obligations and is not treated as a payment.</div>` : '';
            const workflowContext = `<div class="border rounded-3 bg-light p-3"><div class="d-flex justify-content-between gap-2"><strong>Payment-stage context</strong><span class="badge text-bg-primary">${esc(application.current_stage || 'fees_payment')}</span></div><div class="row g-2 small mt-1"><div class="col-md-4"><span class="text-muted d-block">Learner</span>${esc(application.applicant_name || '—')}</div><div class="col-md-4"><span class="text-muted d-block">Grade</span>${esc(application.grade_applying_for || '—')}</div><div class="col-md-4"><span class="text-muted d-block">Parent / guardian</span>${esc([application.parent_first_name, application.parent_last_name].filter(Boolean).join(' ') || '—')}</div></div></div>`;
            const feeInstruction = fee > 0
                ? `Required admission amount due: KES ${fee.toLocaleString()}. Add school-fee amount above it where applicable.`
                : 'No registration/admission amount is currently payable; the approved relief has covered it.';
            document.body.insertAdjacentHTML('beforeend', `<div class="modal fade" id="sharedAdmissionPaymentModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered"><div class="modal-content">
                    <form id="sharedAdmissionPaymentForm">
                        <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i>Admissions Payment</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body"><div class="row g-3">
                            <div class="col-12">${workflowContext}</div><div class="col-12"><div class="alert alert-info small mb-0">Use <strong>${esc(reference || 'the application reference')}</strong> as the payment account. Parents may pay before placement. Cash is not accepted.</div></div>
                            ${reliefNotice}
                            <div class="col-12"><details class="border rounded-3 p-3" id="admissionFinancialReliefDetails"><summary class="fw-semibold"><i class="bi bi-shield-check me-1"></i>Authorize registration-fee / school-fee relief</summary><div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Registration fee</label><select id="admissionReliefRegistration" class="form-select"><option value="0" ${relief.registration_fee_waived ? '' : 'selected'}>Keep registration fee due</option><option value="1" ${relief.registration_fee_waived ? 'selected' : ''}>Waive registration fee completely</option></select></div><div class="col-md-6"><label class="form-label">School fees</label><select id="admissionReliefSchoolType" class="form-select"><option value="none" ${!relief.school_fee_waiver_type || relief.school_fee_waiver_type === 'none' ? 'selected' : ''}>No school-fee relief</option><option value="percentage" ${relief.school_fee_waiver_type === 'percentage' ? 'selected' : ''}>Waive a percentage</option><option value="fixed" ${relief.school_fee_waiver_type === 'fixed' ? 'selected' : ''}>Waive a fixed KES amount</option><option value="full" ${relief.school_fee_waiver_type === 'full' ? 'selected' : ''}>Waive all school fees</option></select></div><div class="col-md-6" id="admissionReliefValueWrap"><label class="form-label">Relief value</label><input id="admissionReliefValue" type="number" min="0" step="0.01" class="form-control" value="${esc(relief.school_fee_waiver_value || '')}" placeholder="Percentage or KES amount"><div class="form-text">For example, enter 50 for half of school fees.</div></div><div class="col-md-6"><label class="form-label">Exception type</label><select id="admissionReliefReasonCode" class="form-select"><option value="sponsored">Sponsored learner</option><option value="exempted">Exempted</option><option value="special_case">Special case</option><option value="administrative_exception">Administrative exception</option><option value="other">Other</option></select></div><div class="col-12"><label class="form-label">Authorization reason <span class="text-danger">*</span></label><textarea id="admissionReliefReason" class="form-control" rows="2" placeholder="Record who authorized the concession and why."></textarea></div><div class="col-12 d-flex justify-content-end"><button type="button" class="btn btn-outline-success" id="applyAdmissionReliefButton"><i class="bi bi-shield-check me-1"></i>Save and apply relief</button></div></div></details></div>
                            <div class="col-md-6"><label class="form-label">Action</label><select name="payment_action" id="sharedAdmissionPaymentAction" class="form-select" required><option value="instructions">Send payment details (SMS + email)</option><option value="stk">Send M-Pesa STK Push</option><option value="bank">Record bank / M-Pesa payment for verification</option></select></div>
                            <div class="col-md-6"><label class="form-label">Amount</label><div class="form-text mb-1">${feeInstruction}</div><input type="number" name="amount" class="form-control" min="${fee}" step="0.01" value="${fee}" required></div>
                            <div class="col-md-6" id="sharedAdmissionPaymentPhoneWrap"><label class="form-label">Parent M-Pesa phone</label><input type="tel" name="phone" class="form-control" value="${esc(phone)}" placeholder="07XXXXXXXX" required><div class="form-text">Loaded from the parent record; change it if another number is paying.</div></div>
                            <div class="col-md-6" id="sharedAdmissionPaymentMethodWrap" style="display:none"><label class="form-label">Method</label><select name="method" class="form-select"><option value="bank_transfer">Bank transfer</option><option value="mpesa">M-Pesa</option></select></div>
                            <div class="col-md-6" id="sharedAdmissionPaymentReferenceWrap" style="display:none"><label class="form-label">Bank / M-Pesa transaction reference</label><input type="text" name="reference" class="form-control" placeholder="KCB reference or M-Pesa code"></div>
                            <div class="col-md-6" id="sharedAdmissionPaymentDateWrap" style="display:none"><label class="form-label">Payment date</label><input type="date" name="payment_date" class="form-control" value="${new Date().toISOString().slice(0, 10)}"></div>
                            <div class="col-12" id="sharedAdmissionPaymentReceiptWrap" style="display:none"><label class="form-label">Bank payment receipt</label><input type="file" name="receipt_file" class="form-control" accept="application/pdf,image/jpeg,image/png"><div class="form-text">Required for a physical bank payment. The receipt is supporting evidence; Finance must still match the transaction to an imported KCB statement.</div></div>
                            <div class="col-12" id="sharedAdmissionPaymentNotesWrap" style="display:none"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Optional verification notes"></textarea></div>
                        </div></div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Continue</button></div>
                    </form>
                </div></div>
            </div>`);
            const element = document.getElementById('sharedAdmissionPaymentModal');
            const modal = bootstrap.Modal.getOrCreateInstance(element);
            const form = document.getElementById('sharedAdmissionPaymentForm');
            const registrationControl = document.getElementById('admissionReliefRegistration');
            if (registrationControl) {
                registrationControl.innerHTML = `<option value="none">Keep registration fee due</option><option value="full">Waive registration fee completely</option><option value="percentage">Waive a percentage</option><option value="fixed">Waive a fixed KES amount</option>`;
                registrationControl.value = relief.registration_fee_waiver_type || (relief.registration_fee_waived ? 'full' : 'none');
                registrationControl.parentElement?.insertAdjacentHTML('afterend', `<div class="col-md-6" id="admissionRegistrationReliefValueWrap"><label class="form-label">Registration relief value</label><input id="admissionRegistrationReliefValue" type="number" min="0" step="0.01" class="form-control" value="${esc(relief.registration_fee_waiver_value || '')}" placeholder="Percentage or KES amount"><div class="form-text">For percentage, enter 50 for half.</div></div>`);
            }
            const action = document.getElementById('sharedAdmissionPaymentAction');
            const toggle = () => {
                const bank = action.value === 'bank';
                const stk = action.value === 'stk';
                document.getElementById('sharedAdmissionPaymentPhoneWrap').style.display = stk ? '' : 'none';
                document.getElementById('sharedAdmissionPaymentMethodWrap').style.display = bank ? '' : 'none';
                document.getElementById('sharedAdmissionPaymentReferenceWrap').style.display = bank ? '' : 'none';
                document.getElementById('sharedAdmissionPaymentDateWrap').style.display = bank ? '' : 'none';
                document.getElementById('sharedAdmissionPaymentReceiptWrap').style.display = bank && form.elements.method.value === 'bank_transfer' ? '' : 'none';
                document.getElementById('sharedAdmissionPaymentNotesWrap').style.display = bank ? '' : 'none';
                form.elements.amount.required = bank || stk;
                form.elements.phone.required = stk;
                form.elements.reference.required = bank;
            };
            action.addEventListener('change', toggle);
            form.elements.method.addEventListener('change', toggle);
            toggle();
            const reliefType = document.getElementById('admissionReliefSchoolType');
            const reliefValueWrap = document.getElementById('admissionReliefValueWrap');
            const registrationValueWrap = document.getElementById('admissionRegistrationReliefValueWrap');
            const syncReliefValue = () => {
                const hidden = !reliefType || reliefType.value === 'none' || reliefType.value === 'full';
                if (reliefValueWrap) reliefValueWrap.classList.toggle('d-none', hidden);
                if (registrationValueWrap) registrationValueWrap.classList.toggle('d-none', !registrationControl || registrationControl.value === 'none' || registrationControl.value === 'full');
            };
            registrationControl?.addEventListener('change', syncReliefValue);
            reliefType?.addEventListener('change', syncReliefValue);
            syncReliefValue();
            document.getElementById('applyAdmissionReliefButton')?.addEventListener('click', async (event) => {
                const registrationWaiverType = registrationControl?.value || 'none';
                const registrationWaived = registrationWaiverType !== 'none';
                const registrationWaiverValue = Number(document.getElementById('admissionRegistrationReliefValue')?.value || 0);
                const schoolWaiverType = reliefType?.value || 'none';
                const schoolWaiverValue = Number(document.getElementById('admissionReliefValue')?.value || 0);
                const reason = String(document.getElementById('admissionReliefReason')?.value || '').trim();
                if ((!registrationWaived && schoolWaiverType === 'none') || !reason) {
                    window.showNotification?.('Select a relief option and enter the authorization reason.', 'error');
                    return;
                }
                if ((schoolWaiverType === 'percentage' || registrationWaiverType === 'percentage') && (schoolWaiverType === 'percentage' ? schoolWaiverValue : registrationWaiverValue) > 100) {
                    window.showNotification?.('Percentage relief must be between 0 and 100.', 'error');
                    return;
                }
                const button = event.currentTarget;
                button.disabled = true;
                try {
                    const reliefResponse = await apiCall('/admission/apply-financial-relief', 'POST', {
                        application_id: Number(application.id),
                        registration_fee_waived: registrationWaived ? 1 : 0,
                        registration_fee_waiver_type: registrationWaiverType,
                        registration_fee_waiver_value: registrationWaiverValue,
                        school_fee_waiver_type: schoolWaiverType,
                        school_fee_waiver_value: schoolWaiverValue,
                        reason_code: document.getElementById('admissionReliefReasonCode')?.value || 'sponsored',
                        reason
                    });
                    window.showNotification?.(reliefResponse?.advanced_to_id_generation
                        ? 'Financial relief applied. No payment was required, so the application advanced to ID Generation.'
                        : 'Financial relief saved and applied to the fee obligations.', 'success');
                    modal.hide();
                    onSuccess?.();
                } catch (error) {
                    window.showNotification?.(error.message || 'Unable to apply financial relief.', 'error');
                } finally { button.disabled = false; }
            });
            form.addEventListener('submit', async event => {
                event.preventDefault();
                const data = Object.fromEntries(new FormData(form));
                const button = form.querySelector('button[type="submit"]');
                button.disabled = true;
                try {
                    if (data.payment_action === 'instructions') {
                        await apiCall('/admission/payment-instructions', 'POST', { application_id: Number(application.id) });
                    } else if (data.payment_action === 'stk') {
                        await apiCall('/payments/mpesa-stk-push', 'POST', { account_reference: reference, phone: data.phone, amount: data.amount, description: 'Kingsway admission and school fees' });
                    } else {
                        let receiptNote = '';
                        let receiptDocumentId = null;
                        if (data.method === 'bank_transfer') {
                            const receipt = form.elements.receipt_file.files?.[0];
                            if (!receipt) throw new Error('Upload the physical bank payment receipt before recording a bank payment.');
                            const receiptForm = new FormData();
                            receiptForm.append('application_id', Number(application.id));
                            receiptForm.append('document_type', 'payment_receipt');
                            receiptForm.append('document', receipt);
                            const uploaded = await apiCall('/admission/upload-document', 'POST', receiptForm, {}, { isFile: true });
                            receiptDocumentId = uploaded?.data?.document_id || uploaded?.document_id || null;
                            receiptNote = receiptDocumentId ? `Bank receipt document #${receiptDocumentId}` : 'Bank receipt uploaded to the application';
                        }
                        await apiCall('/admission/record-fee-payment', 'POST', { application_id: Number(application.id), amount: data.amount, phone: data.phone, method: data.method, reference: data.reference, receipt_document_id: receiptDocumentId, payment_date: data.payment_date, notes: [data.notes, receiptNote].filter(Boolean).join(' | ') });
                    }
                    window.showNotification?.('Admission payment action completed successfully.', 'success');
                    modal.hide();
                    onSuccess?.();
                } catch (error) { window.showNotification?.(error.message || 'Unable to complete the admission payment action.', 'error'); }
                finally { button.disabled = false; }
            });
            element.addEventListener('hidden.bs.modal', () => element.remove(), { once: true });
            modal.show();
        }
    };
})(window);
