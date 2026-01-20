Frontend ↔ Controller mapping audit

Based on `js/api.js` finance endpoints and `api/controllers/FinanceController.php` / `documantations/REST APIs Endpoints/ENDPOINTS_FOR_FRONTEND.txt`.

Summary:
- `API.finance.index()` -> `GET /api/finance` -> `FinanceController::get()` -> delegates to `FinanceAPI->list()`; patch applied to `js/api.js` to use `/finance`.
- Payment receipt generation: `API.finance.generateReceipt(paymentId)` -> `POST /api/finance/payments-generate-receipt` -> resolved to `FinanceController::postPaymentsGenerateReceipt($data)` — method exists.
- Fees endpoints (term-breakdown, annual-summary) map to `getFeesTermBreakdown` and `getFeesAnnualSummary` — methods exist.
- Payroll endpoints map to various `getPayrollList`, `postPayrollsCalculate`, etc. — controller implements many payroll methods.

Mismatches found and actions taken:
- `/finance/index` used in `ENDPOINT_PERMISSIONS` previously, while frontend now calls `/finance`. Added `/finance` key to `ENDPOINT_PERMISSIONS`.
- `js/pages/finance.js` expected `response.data` structure; controller `handleResponse` returns data in `data` for success — UI reads `response.data` incorrectly in `loadPayments()` (it does `response.data || response || []`). To be robust, UI already handles multiple shapes; no change applied.

Next recommended checks:
- Run integration smoke tests for: `GET /api/finance`, `POST /api/finance/payments-generate-receipt`, `POST /api/finance` (record payment) using sample data.
- Optionally normalize permission keys: prefer `/finance` (without `/index`) consistently in `ENDPOINT_PERMISSIONS` and docs.
