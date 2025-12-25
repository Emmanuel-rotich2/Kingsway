#!/bin/bash

################################################################################
# Payment API Test Suite - With Real Payloads
# Tests all webhook endpoints with production-like payloads
################################################################################

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
API_BASE_URL="http://localhost/Kingsway/api/payments"
TEST_TOKEN="test-token-123"
OUTPUT_FILE="test_payments_results.txt"

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

################################################################################
# Helper Functions
################################################################################

print_header() {
    echo ""
    echo "================================================================================"
    echo "                    PAYMENT API TEST SUITE (Real Payloads)"
    echo "================================================================================"
    echo "Base URL: $API_BASE_URL"
    echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "================================================================================"
    echo ""
}

section() {
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE} $1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════════${NC}"
}

test_endpoint() {
    local test_name="$1"
    local method="$2"
    local endpoint="$3"
    local payload="$4"
    local expected_status="${5:-200}"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo ""
    echo -e "${CYAN}📋 Test #${TOTAL_TESTS}: ${test_name}${NC}"
    echo "   Method: $method $endpoint"
    
    # Build curl command
    local curl_cmd="curl -s -w '\n%{http_code}' -X $method"
    curl_cmd="$curl_cmd -H 'Content-Type: application/json'"
    curl_cmd="$curl_cmd -H 'X-Test-Token: $TEST_TOKEN'"
    
    if [ -n "$payload" ] && [ "$payload" != '""' ]; then
        curl_cmd="$curl_cmd -d '$payload'"
    fi
    
    curl_cmd="$curl_cmd '$API_BASE_URL$endpoint'"
    
    # Execute request
    local response=$(eval $curl_cmd)
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n-1)
    
    # Check status
    if [ "$http_code" -eq "$expected_status" ]; then
        echo -e "   ${GREEN}✓ PASSED${NC} (HTTP $http_code)"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        echo "PASS: $test_name - $method $endpoint" >> "$OUTPUT_FILE"
    else
        echo -e "   ${RED}✗ FAILED${NC} (Expected: $expected_status, Got: $http_code)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo "FAIL: $test_name - $method $endpoint - Expected $expected_status, Got $http_code" >> "$OUTPUT_FILE"
        echo "Response: $body" >> "$OUTPUT_FILE"
    fi
}

print_summary() {
    local pass_rate=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    
    echo ""
    echo "================================================================================"
    echo "                              TEST SUMMARY"
    echo "================================================================================"
    echo "Total Tests:  $TOTAL_TESTS"
    echo "Passed:       $PASSED_TESTS"
    echo "Failed:       $FAILED_TESTS"
    echo "Pass Rate:    ${pass_rate}%"
    echo "================================================================================"
    echo ""
    echo "📄 Full test results saved to: $OUTPUT_FILE"
    echo ""
    
    # Exit with error if any tests failed
    if [ $FAILED_TESTS -gt 0 ]; then
        exit 1
    fi
}

################################################################################
# Main Test Suite
################################################################################

# Clear previous results
> "$OUTPUT_FILE"

print_header

# ============================================================================
# STEP 1: Basic Index Test
# ============================================================================

section "STEP 1: BASIC OPERATIONS"

test_endpoint \
    "Payment API Index" \
    "GET" \
    "/index"

# ============================================================================
# STEP 2: M-Pesa B2C Callbacks (Real M-Pesa Result Format)
# ============================================================================

section "STEP 2: M-PESA B2C CALLBACKS"

test_endpoint \
    "M-Pesa B2C Callback (Success)" \
    "POST" \
    "/mpesa-b2c-callback" \
    '{
        "Result": {
            "ResultType": 0,
            "ResultCode": 0,
            "ResultDesc": "The service request is processed successfully.",
            "OriginatorConversationID": "AG_20231213_00004e2a8b5f6c7d8e9f",
            "ConversationID": "AG_20231213_12345678901234567890",
            "TransactionID": "QKJ4PZ7MNO",
            "ResultParameters": {
                "ResultParameter": [
                    {"Key": "TransactionAmount", "Value": 25000},
                    {"Key": "TransactionReceipt", "Value": "QKJ4PZ7MNO"},
                    {"Key": "ReceiverPartyPublicName", "Value": "254712345678 - John Doe"},
                    {"Key": "TransactionCompletedDateTime", "Value": "13.12.2023 14:30:15"}
                ]
            }
        }
    }'

test_endpoint \
    "M-Pesa B2C Callback (Failure)" \
    "POST" \
    "/mpesa-b2c-callback" \
    '{
        "Result": {
            "ResultType": 0,
            "ResultCode": 2001,
            "ResultDesc": "The initiator information is invalid.",
            "OriginatorConversationID": "AG_20231213_00004e2a8b5f6c7d8e9f",
            "ConversationID": "AG_20231213_98765432109876543210",
            "TransactionID": "QKJ4PZ7MNP",
            "ResultParameters": {
                "ResultParameter": []
            }
        }
    }'

test_endpoint \
    "M-Pesa B2C Timeout" \
    "POST" \
    "/mpesa-b2c-timeout" \
    '{
        "Result": {
            "ResultType": 0,
            "ResultCode": 1,
            "ResultDesc": "The request timed out",
            "OriginatorConversationID": "AG_20231213_00004e2a8b5f6c7d8e9f",
            "ConversationID": "AG_20231213_timeout123456789"
        }
    }'

# ============================================================================
# STEP 3: M-Pesa C2B Confirmation (Real M-Pesa C2B Format)
# ============================================================================

section "STEP 3: M-PESA C2B CONFIRMATION"

test_endpoint \
    "M-Pesa C2B Confirmation (Paybill)" \
    "POST" \
    "/mpesa-c2b-confirmation" \
    '{
        "TransactionType": "Pay Bill",
        "TransID": "RKL5QX8YZA",
        "TransTime": "20231213143015",
        "TransAmount": "15000.00",
        "BusinessShortCode": "600987",
        "BillRefNumber": "STU001",
        "InvoiceNumber": "",
        "OrgAccountBalance": "250000.00",
        "ThirdPartyTransID": "",
        "MSISDN": "254712345678",
        "FirstName": "Jane",
        "MiddleName": "Mary",
        "LastName": "Kamau"
    }'

test_endpoint \
    "M-Pesa C2B Confirmation (Buy Goods)" \
    "POST" \
    "/mpesa-c2b-confirmation" \
    '{
        "TransactionType": "Buy Goods and Services",
        "TransID": "RKL5QX8YZB",
        "TransTime": "20231213150000",
        "TransAmount": "8500.00",
        "BusinessShortCode": "600987",
        "BillRefNumber": "STU002",
        "InvoiceNumber": "",
        "OrgAccountBalance": "258500.00",
        "ThirdPartyTransID": "",
        "MSISDN": "254723456789",
        "FirstName": "Peter",
        "MiddleName": "",
        "LastName": "Omondi"
    }'

# ============================================================================
# STEP 4: KCB Bank Webhooks (Real KCB Format)
# ============================================================================

section "STEP 4: KCB BANK WEBHOOKS"

test_endpoint \
    "KCB Validation Request" \
    "POST" \
    "/kcb-validation" \
    '{
        "requestId": "REQ-20231213-001",
        "customerReference": "STU001",
        "organizationReference": "ORG-KWA-2023",
        "requestTimestamp": "2023-12-13T14:35:00Z",
        "channel": "MOBILE"
    }'

test_endpoint \
    "KCB Transfer Callback (Success)" \
    "POST" \
    "/kcb-transfer-callback" \
    '{
        "transactionReference": "KCB-TXN-20231213-001",
        "requestId": "REQ-20231213-002",
        "transactionAmount": 50000.00,
        "status": "SUCCESS",
        "statusDescription": "Transaction completed successfully",
        "creditAccountNumber": "1234567890",
        "creditAccountName": "John Doe Staff",
        "debitAccountNumber": "9876543210",
        "charges": 50.00,
        "narration": "Salary disbursement",
        "timestamp": "20231213143500"
    }'

test_endpoint \
    "KCB Transfer Callback (Failure)" \
    "POST" \
    "/kcb-transfer-callback" \
    '{
        "transactionReference": "KCB-TXN-20231213-002",
        "requestId": "REQ-20231213-003",
        "transactionAmount": 30000.00,
        "status": "FAILED",
        "statusDescription": "Insufficient funds in debit account",
        "creditAccountNumber": "1234567890",
        "creditAccountName": "Jane Smith Staff",
        "debitAccountNumber": "9876543210",
        "charges": 0.00,
        "narration": "Salary disbursement",
        "timestamp": "20231213144000"
    }'

test_endpoint \
    "KCB Payment Notification" \
    "POST" \
    "/kcb-notification" \
    '{
        "transactionReference": "KCB-NOTIF-20231213-001",
        "requestId": "REQ-20231213-004",
        "customerReference": "STU001",
        "transactionAmount": 20000.00,
        "customerName": "Alice Wanjiru",
        "customerMobileNumber": "254734567890",
        "narration": "School fees payment",
        "timestamp": "20231213150000",
        "currency": "KES",
        "channelCode": "MOBILE",
        "organizationShortCode": "KWA001",
        "balance": "280000.00"
    }'

# ============================================================================
# STEP 5: Generic Bank Webhooks (Real Bank Format)
# ============================================================================

section "STEP 5: GENERIC BANK WEBHOOKS"

test_endpoint \
    "Bank Webhook (Credit - School Fees)" \
    "POST" \
    "/bank-webhook" \
    '{
        "event_type": "credit",
        "bank": "KCB",
        "transaction_ref": "BANK-CR-20231213-001",
        "account_number": "ADM101",
        "reference": "ADM101",
        "amount": 30000.00,
        "currency": "KES",
        "narration": "School fees payment",
        "transaction_date": "2023-12-13T15:15:00Z",
        "status": "completed",
        "customer_name": "Robert Mwangi",
        "customer_phone": "254745678901"
    }'

test_endpoint \
    "Bank Webhook (Debit - Charges)" \
    "POST" \
    "/bank-webhook" \
    '{
        "event_type": "debit",
        "bank": "Equity Bank",
        "transaction_ref": "BANK-DB-20231213-001",
        "account_number": "ADM102",
        "reference": "ADM102",
        "amount": 5000.00,
        "currency": "KES",
        "narration": "Bank charges",
        "transaction_date": "2023-12-13T15:20:00Z",
        "status": "completed"
    }'

test_endpoint \
    "Bank Webhook (Pending)" \
    "POST" \
    "/bank-webhook" \
    '{
        "event_type": "credit",
        "bank": "Cooperative Bank",
        "transaction_ref": "BANK-PD-20231213-001",
        "account_number": "ADM103",
        "reference": "ADM103",
        "amount": 12000.00,
        "currency": "KES",
        "narration": "School fees - pending verification",
        "transaction_date": "2023-12-13T15:25:00Z",
        "status": "pending"
    }'

test_endpoint \
    "Bank Webhook (Reversal)" \
    "POST" \
    "/bank-webhook" \
    '{
        "event_type": "reversal",
        "bank": "KCB",
        "transaction_ref": "BANK-RV-20231213-001",
        "original_transaction_ref": "BANK-CR-20231213-001",
        "account_number": "ADM101",
        "reference": "ADM101",
        "amount": 30000.00,
        "currency": "KES",
        "narration": "Payment reversal - insufficient funds",
        "transaction_date": "2023-12-13T15:30:00Z",
        "status": "reversed"
    }'

# ============================================================================
# Final Summary
# ============================================================================

print_summary
