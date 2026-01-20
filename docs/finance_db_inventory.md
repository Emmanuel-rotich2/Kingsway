Finance DB inventory (summary)

Source: `database/KingswayAcademy.sql` (schema read)

Key tables and purpose:

- `student_fee_obligations`
  - Purpose: Tracks per-student fee obligations per academic period (amount_due, amount_paid, amount_waived, generated `balance`).
  - Notes: Triggers update status on allocation; used to compute arrears.

- `payment_transactions`
  - Purpose: Records payment attempts/confirmed payments (receipt_no, amount_paid, payment_method, reference_no, status).
  - Notes: Status values include 'pending','confirmed','failed','reversed' (controller code varies; reconcile strings carefully).

- `payment_allocations` / `payment_allocations_detailed`
  - Purpose: Allocate a `payment_transaction` to specific `student_fee_obligations` or fee structure lines.
  - Notes: Triggers update `student_fee_obligations` (amount_paid) and generate ledger entries.

- `financial_transactions`
  - Purpose: General ledger entries for bank/disbursement flows, reconciliations and transfers.

- `payment_reconciliations`
  - Purpose: Store reconciliation metadata linking external bank/mpesa records to internal payments.

- `mpesa_transactions` / `bank_transactions`
  - Purpose: Raw imports of external payment provider events.

- `fee_structures`, `fee_structures_detailed`, `fee_types`
  - Purpose: Define annual/term fee structures and breakdowns used to generate `student_fee_obligations`.

- `fee_discounts_waivers`, `fee_reminders`, `fee_transition_history`, `fee_structure_rollover_log`
  - Purpose: Discount/waiver records and history for rollovers/transitions.

- `financial_periods`
  - Purpose: Define accounting periods and active status for fee generation and payroll cycles.

- `staff_payroll`, `staff_loans`
  - Purpose: Payroll records and staff loan tracking.

Triggers & Stored Procedures (examples):
- `trg_update_obligation_on_payment` — updates obligations when allocations are inserted.
- `trg_emit_payment_event` — emits events into `system_events` on new `financial_transactions`.
- `sp_generate_student_fee_obligations` — creates obligations for students per `fee_structures`.
- `sp_apply_fee_discount`, `sp_allocate_payment` — helper procedures for complex allocation logic.

Caveats:
- Some controllers use different `status` string conventions ('completed' vs 'confirmed' vs 'successful'). Normalize usage in code where safe.
- The DB contains triggers which enforce ledger updates; frontend must call manager APIs (server-side) that insert allocations rather than performing client-side adjustments.

Next step (if requested): produce a full column-by-column CSV inventory for each finance table.
