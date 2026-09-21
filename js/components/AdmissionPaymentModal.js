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
            document.body.insertAdjacentHTML('beforeend', `<div class="modal fade" id="sharedAdmissionPaymentModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered"><div class="modal-content">
                    <form id="sharedAdmissionPaymentForm">
                        <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i>Admissions Payment</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body"><div class="row g-3">
                            <div class="col-12"><div class="alert alert-info small mb-0">Use <strong>${esc(reference || 'the application reference')}</strong> as the payment account. Parents may pay before placement. Cash is not accepted.</div></div>
                            <div class="col-md-6"><label class="form-label">Action</label><select name="payment_action" id="sharedAdmissionPaymentAction" class="form-select" required><option value="instructions">Send payment details (SMS + email)</option><option value="stk">Send M-Pesa STK Push</option><option value="bank">Record bank / M-Pesa payment for verification</option></select></div>
                            <div class="col-md-6"><label class="form-label">Amount</label><div class="form-text mb-1">Required admission amount due: KES ${fee.toLocaleString()}. Add school-fee amount above it where applicable.</div><input type="number" name="amount" class="form-control" min="${fee}" step="0.01" value="${fee}" required></div>
                            <div class="col-md-6" id="sharedAdmissionPaymentPhoneWrap"><label class="form-label">Parent M-Pesa phone</label><input type="tel" name="phone" class="form-control" value="${esc(phone)}" placeholder="07XXXXXXXX" required><div class="form-text">Loaded from the parent record; change it if another number is paying.</div></div>
                            <div class="col-md-6" id="sharedAdmissionPaymentMethodWrap" style="display:none"><label class="form-label">Method</label><select name="method" class="form-select"><option value="bank_transfer">Bank transfer</option><option value="mpesa">M-Pesa</option></select></div>
                            <div class="col-md-6" id="sharedAdmissionPaymentReferenceWrap" style="display:none"><label class="form-label">Bank / M-Pesa transaction reference</label><input type="text" name="reference" class="form-control" placeholder="KCB reference or M-Pesa code"></div>
                            <div class="col-md-6" id="sharedAdmissionPaymentDateWrap" style="display:none"><label class="form-label">Payment date</label><input type="date" name="payment_date" class="form-control" value="${new Date().toISOString().slice(0, 10)}"></div>
                            <div class="col-12" id="sharedAdmissionPaymentNotesWrap" style="display:none"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Optional verification notes"></textarea></div>
                        </div></div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Continue</button></div>
                    </form>
                </div></div>
            </div>`);
            const element = document.getElementById('sharedAdmissionPaymentModal');
            const modal = bootstrap.Modal.getOrCreateInstance(element);
            const form = document.getElementById('sharedAdmissionPaymentForm');
            const action = document.getElementById('sharedAdmissionPaymentAction');
            const toggle = () => {
                const bank = action.value === 'bank';
                const stk = action.value === 'stk';
                document.getElementById('sharedAdmissionPaymentPhoneWrap').style.display = stk ? '' : 'none';
                document.getElementById('sharedAdmissionPaymentMethodWrap').style.display = bank ? '' : 'none';
                document.getElementById('sharedAdmissionPaymentReferenceWrap').style.display = bank ? '' : 'none';
                document.getElementById('sharedAdmissionPaymentDateWrap').style.display = bank ? '' : 'none';
                document.getElementById('sharedAdmissionPaymentNotesWrap').style.display = bank ? '' : 'none';
                form.elements.amount.required = bank || stk;
                form.elements.phone.required = stk;
                form.elements.reference.required = bank;
            };
            action.addEventListener('change', toggle);
            toggle();
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
                        await apiCall('/admission/record-fee-payment', 'POST', { application_id: Number(application.id), amount: data.amount, phone: data.phone, method: data.method, reference: data.reference, payment_date: data.payment_date, notes: data.notes });
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
