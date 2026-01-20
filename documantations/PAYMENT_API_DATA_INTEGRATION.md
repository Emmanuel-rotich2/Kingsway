# Payment API Data Integration Guide

## Overview

This document describes the data fields returned by external payment APIs (Safaricom M-Pesa Daraja and KCB Buni) and how they're utilized in the Kingsway Academy payment reconciliation system.

---

## 1. Safaricom Daraja API (M-Pesa)

### 1.1 C2B (Customer to Business) Callback - Paybill Payments

When a parent pays to our Paybill number via M-Pesa, we receive:

| Callback Field | Database Column | Table | Description |
|----------------|-----------------|-------|-------------|
| `TransID` | `mpesa_code` | mpesa_transactions | Unique M-Pesa receipt (e.g., LHG31AA5TX) |
| `TransTime` | `transaction_date` | mpesa_transactions | Format: YYYYMMddHHmmss |
| `TransAmount` | `amount` | mpesa_transactions | Amount paid (decimal) |
| `BusinessShortCode` | - (config) | - | Our Paybill number |
| `BillRefNumber` | `bill_ref_number` | mpesa_transactions | Account number (should be admission_no) |
| `MSISDN` | `phone_number` | mpesa_transactions | Payer's phone (254...) |
| `FirstName` | `first_name` | mpesa_transactions | Payer's registered first name |
| `MiddleName` | `middle_name` | mpesa_transactions | Payer's middle name (optional) |
| `LastName` | `last_name` | mpesa_transactions | Payer's last name |
| `OrgAccountBalance` | `org_account_balance` | mpesa_transactions | School balance after payment |
| `ThirdPartyTransID` | `third_party_trans_id` | mpesa_transactions | Optional cross-reference |
| `InvoiceNumber` | - | - | Optional (not stored) |

**Sample C2B Callback:**
```json
{
    "TransactionType": "PayBill",
    "TransID": "LHG31AA5TX",
    "TransTime": "20260118190243",
    "TransAmount": "15000.00",
    "BusinessShortCode": "123456",
    "BillRefNumber": "ADM2024001",
    "InvoiceNumber": "",
    "OrgAccountBalance": "500000.00",
    "ThirdPartyTransID": "",
    "MSISDN": "254712345678",
    "FirstName": "John",
    "MiddleName": "",
    "LastName": "Doe"
}
```

### 1.2 STK Push Callback - School-Initiated Payments

When school initiates payment (student uses STK prompt):

| Callback Field | Database Column | Description |
|----------------|-----------------|-------------|
| `MpesaReceiptNumber` | `mpesa_code` | Unique receipt |
| `TransactionDate` | `transaction_date` | Format: YYYYMMddHHmmss |
| `Amount` | `amount` | Amount paid |
| `PhoneNumber` | `phone_number` | Payer's phone |
| `CheckoutRequestID` | `checkout_request_id` | STK request tracking ID |

**Sample STK Push Callback:**
```json
{
    "Body": {
        "stkCallback": {
            "MerchantRequestID": "21605-295434-4",
            "CheckoutRequestID": "ws_CO_04112017184930742",
            "ResultCode": 0,
            "ResultDesc": "The service request is processed successfully.",
            "CallbackMetadata": {
                "Item": [
                    {"Name": "Amount", "Value": 15000},
                    {"Name": "MpesaReceiptNumber", "Value": "LK451H35OP"},
                    {"Name": "Balance"},
                    {"Name": "TransactionDate", "Value": 20260118184944},
                    {"Name": "PhoneNumber", "Value": 254712345678}
                ]
            }
        }
    }
}
```

---

## 2. KCB Buni API (Bank Transactions)

### 2.1 Incoming Funds Notification

When funds are received in school's KCB account:

| API Field | Database Column | Table | Description |
|-----------|-----------------|-------|-------------|
| `transactionRef` | `transaction_ref` | bank_transactions | Unique bank reference |
| `amount` | `amount` | bank_transactions | Amount received |
| `transactionDate` | `transaction_date` | bank_transactions | Date/time of transaction |
| `senderName` | `sender_name` | bank_transactions | Payer's name |
| `senderPhone` | `sender_phone` | bank_transactions | Payer's phone (if available) |
| `senderAccount` | `sender_account` | bank_transactions | Sender's account number |
| `narration` | `narration` | bank_transactions | Transaction description |
| `bankReference` | `bank_reference` | bank_transactions | Bank's internal reference |

**Note:** Bank transactions may also contain M-Pesa codes in the narration field when M-Pesa funds are received via Paybill and transferred to bank.

---

## 3. Database Schema (Enhanced)

### mpesa_transactions Table
```sql
CREATE TABLE mpesa_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mpesa_code VARCHAR(50) NOT NULL UNIQUE,
    student_id INT UNSIGNED NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_date DATETIME NOT NULL,
    phone_number VARCHAR(20),
    first_name VARCHAR(100),           -- NEW: From C2B callback
    middle_name VARCHAR(100),          -- NEW: From C2B callback
    last_name VARCHAR(100),            -- NEW: From C2B callback
    org_account_balance DECIMAL(15,2), -- NEW: From C2B callback
    third_party_trans_id VARCHAR(100), -- NEW: From C2B callback
    bill_ref_number VARCHAR(100),      -- NEW: What parent entered
    status ENUM('pending','processed','failed') DEFAULT 'pending',
    transaction_type ENUM('STK_PUSH','C2B','B2C') DEFAULT 'STK_PUSH',
    raw_callback LONGTEXT,
    checkout_request_id VARCHAR(100),
    webhook_data LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_mpesa_phone (phone_number),
    INDEX idx_mpesa_bill_ref (bill_ref_number)
);
```

### bank_transactions Table
```sql
CREATE TABLE bank_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_ref VARCHAR(100) NOT NULL UNIQUE,
    student_id INT UNSIGNED NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_date DATETIME NOT NULL,
    bank_name VARCHAR(100),
    account_number VARCHAR(50),
    narration VARCHAR(255),
    sender_name VARCHAR(255),          -- NEW: From bank API
    sender_phone VARCHAR(20),          -- NEW: From bank API
    sender_account VARCHAR(50),        -- NEW: From bank API
    bank_reference VARCHAR(100),       -- NEW: Bank's internal ref
    cheque_number VARCHAR(50),         -- NEW: For cheque payments
    source_type ENUM('api_callback','statement_import','manual_entry') DEFAULT 'manual_entry',
    matched_mpesa_code VARCHAR(50),    -- NEW: For reconciliation tracking
    webhook_data LONGTEXT,
    status ENUM('pending','processed','failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_bank_sender_phone (sender_phone),
    INDEX idx_bank_sender_name (sender_name),
    INDEX idx_bank_matched_mpesa (matched_mpesa_code)
);
```

---

## 4. Auto-Matching Logic

The system uses the following prioritized matching logic for reconciliation:

### 4.1 High Confidence Matches
1. **Student ID + Amount Match**: M-Pesa `student_id` matches bank `student_id` AND amounts match
2. **M-Pesa Code Match**: M-Pesa `mpesa_code` found in bank `narration`
3. **Phone + Amount Match**: M-Pesa `phone_number` matches parent phone AND amounts match

### 4.2 Medium Confidence Matches (Requires Manual Confirmation)
4. **Name Match**: M-Pesa payer name (FirstName + LastName) similar to bank `sender_name`

### 4.3 Disabled (Too Risky)
- Amount-only matching is **disabled** as it could cause false matches

---

## 5. Phone Lookup for Manual Reconciliation

When a payment has no student linked (parent entered wrong/missing admission number):

1. **API Endpoint**: `GET /api/payments/lookup-by-phone?phone=07XXXXXXXX`
2. **Search Sources**:
   - `parents` table via `phone_number`
   - `mpesa_transactions` history (previous successful payments from same phone)
3. **Link Endpoint**: `POST /api/payments/link-student`
   - Links M-Pesa transaction to correct student
   - Updates `student_id` and `bill_ref_number` fields

---

## 6. Recommendations for Improved Data Quality

1. **Educate Parents**: Provide clear instructions on entering admission number when paying
2. **Validation**: Implement C2B validation to reject payments with invalid admission numbers
3. **SMS Confirmation**: Send SMS to parent confirming payment received for correct student
4. **Phone Number Registry**: Maintain updated parent phone numbers for reliable matching
5. **Paybill Account Format**: Consider using structured account format like `ADM-2024-001`

---

## 7. Related Files

- `api/services/payments/MpesaPaymentService.php` - M-Pesa integration
- `api/services/payments/KcbFundsTransferService.php` - KCB Bank integration
- `api/controllers/PaymentsController.php` - Payment endpoints
- `js/dashboards/school_accountant_dashboard.js` - Reconciliation UI
- `database/migrations/enhance_payment_tables_for_api_data.sql` - Schema updates

---

**Document Version**: 1.0  
**Last Updated**: January 2026  
**Author**: Development Team
