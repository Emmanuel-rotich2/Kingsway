#!/bin/bash

################################################################################
# Payment API Endpoint Test Suite
# Tests all payment endpoints including M-Pesa, KCB, and bank webhooks
################################################################################

# Configuration
API_BASE_URL="http://localhost/Kingsway/api/payments"
TEST_TOKEN="devtest"
OUTPUT_FILE="test_payments_results.txt"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

################################################################################
# Helper Functions
################################################################################

print_header() {
    echo ""
    echo "╔══════════════════════════════════════════════════════════════════════════╗"
    echo "║                  PAYMENT API ENDPOINT TEST SUITE                         ║"
    echo "╚══════════════════════════════════════════════════════════════════════════╝"
    echo ""
}

section() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo -e "  ${BOLD}$1${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
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
# STEP 2: M-Pesa B2C Callbacks
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
            "OriginatorConversationID": "29115-34620561-1",
            "ConversationID": "AG_20191219_00005797af5d7d75f652",
            "TransactionID": "NLJ7RT61SV",
            "ResultParameters": {
                "ResultParameter": [
                    {
                        "Key": "TransactionAmount",
                        "Value": 10000
                    },
                    {
                        "Key": "TransactionReceipt",
                        "Value": "NLJ7RT61SV"
                    },
                    {
                        "Key": "B2CRecipientIsRegisteredCustomer",
                        "Value": "Y"
                    },
                    {
                        "Key": "B2CChargesPaidAccountAvailableFunds",
                        "Value": -4510.00
                    },
                    {
                        "Key": "ReceiverPartyPublicName",
                        "Value": "254708374149 - John Doe"
                    },
                    {
                        "Key": "TransactionCompletedDateTime",
                        "Value": "19.12.2019 11:45:50"
                    },
                    {
                        "Key": "B2CUtilityAccountAvailableFunds",
                        "Value": 10116.00
                    },
                    {
                        "Key": "B2CWorkingAccountAvailableFunds",
                        "Value": 900000.00
                    }
                ]
            },
            "ReferenceData": {
                "ReferenceItem": {
                    "Key": "QueueTimeoutURL",
                    "Value": "https://internalsandbox.safaricom.co.ke/mpesa/b2cresults/v1/submit"
                }
            }
        }
    }'

test_endpoint \
    "M-Pesa B2C Callback (Failed Transaction)" \
    "POST" \
    "/mpesa-b2c-callback" \
    '{
        "Result": {
            "ResultType": 0,
            "ResultCode": 2001,
            "ResultDesc": "The initiator information is invalid.",
            "OriginatorConversationID": "29115-34620562-1",
            "ConversationID": "AG_20191219_00005797af5d7d75f653",
            "TransactionID": "",
            "ReferenceData": {
                "ReferenceItem": {
                    "Key": "QueueTimeoutURL",
                    "Value": "https://internalsandbox.safaricom.co.ke/mpesa/b2cresults/v1/submit"
                }
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
            "ResultDesc": "The balance is insufficient for the transaction",
            "OriginatorConversationID": "29115-34620563-1",
            "ConversationID": "AG_20191219_00005797af5d7d75f654",
            "TransactionID": "",
            "ReferenceData": {
                "ReferenceItem": {
                    "Key": "QueueTimeoutURL",
                    "Value": "https://internalsandbox.safaricom.co.ke/mpesa/b2cresults/v1/submit"
                }
            }
        }
    }'

# ============================================================================
# STEP 3: M-Pesa C2B Confirmation
# ============================================================================

section "STEP 3: M-PESA C2B CONFIRMATION"

test_endpoint \
    "M-Pesa C2B Confirmation (Paybill)" \
    "POST" \
    "/mpesa-c2b-confirmation" \
    '{
        "TransactionType": "Pay Bill",
        "TransID": "NLJ7RT61SV",
        "TransTime": "20191219174509",
        "TransAmount": "10000.00",
        "BusinessShortCode": "600426",
        "BillRefNumber": "12345",
        "InvoiceNumber": "",
        "OrgAccountBalance": "49001.00",
        "ThirdPartyTransID": "",
        "MSISDN": "254708374149",
        "FirstName": "John",
        "MiddleName": "",
        "LastName": "Doe"
    }'

test_endpoint \
    "M-Pesa C2B Confirmation (Buy Goods)" \
    "POST" \
    "/mpesa-c2b-confirmation" \
    '{
        "TransactionType": "CustomerPayBillOnline",
        "TransID": "NLJ7RT61SW",
        "TransTime": "20191219174510",
        "TransAmount": "5000.00",
        "BusinessShortCode": "600426",
        "BillRefNumber": "67890",
        "InvoiceNumber": "",
        "OrgAccountBalance": "54001.00",
        "ThirdPartyTransID": "",
        "MSISDN": "254708374150",
        "FirstName": "Jane",
        "MiddleName": "Mary",
        "LastName": "Smith"
    }'

# ============================================================================
# STEP 4: KCB Bank Validation
# ============================================================================

section "STEP 4: KCB BANK VALIDATION"

test_endpoint \
    "KCB Payment Validation" \
    "POST" \
    "/kcb-validation" \
    '{
        "TransactionType": "PAY",
        "TransID": "KCB123456789",
        "TransTime": "20231213100000",
        "TransAmount": 25000.00,
        "BusinessShortCode": "123456",
        "BillRefNumber": "INV-2023-001",
        "MSISDN": "254712345678",
        "FirstName": "Alice",
        "LastName": "Johnson",
        "AccountReference": "ACC-001"
    }'

test_endpoint \
    "KCB Transfer Callback (Success)" \
    "POST" \
    "/kcb-transfer-callback" \
    '{
        "ResultCode": "0",
        "ResultDesc": "Success",
        "TransactionID": "KCB987654321",
        "ConversationID": "AG_20231213_00001234567890",
        "OriginatorConversationID": "29115-34620564-1",
        "TransactionAmount": 50000.00,
        "TransactionReceipt": "KCB987654321",
        "RecipientAccountNumber": "1234567890",
        "RecipientName": "School Account",
        "TransactionCompletedDateTime": "2023-12-13 10:30:45"
    }'

test_endpoint \
    "KCB Transfer Callback (Failed)" \
    "POST" \
    "/kcb-transfer-callback" \
    '{
        "ResultCode": "1",
        "ResultDesc": "Insufficient funds",
        "TransactionID": "",
        "ConversationID": "AG_20231213_00001234567891",
        "OriginatorConversationID": "29115-34620565-1"
    }'

test_endpoint \
    "KCB Notification" \
    "POST" \
    "/kcb-notification" \
    '{
        "NotificationType": "CREDIT",
        "TransactionID": "KCB111222333",
        "Amount": 15000.00,
        "AccountNumber": "1234567890",
        "TransactionDate": "2023-12-13",
        "TransactionTime": "11:00:00",
        "Narration": "Payment from customer",
        "ReferenceNumber": "REF-001"
    }'

# ============================================================================
# STEP 5: Generic Bank Webhook
# ============================================================================

section "STEP 5: GENERIC BANK WEBHOOK"

test_endpoint \
    "Bank Webhook (Credit Notification)" \
    "POST" \
    "/bank-webhook" \
    '{
        "event_type": "credit",
        "transaction_id": "BANK123456789",
        "amount": 75000.00,
        "currency": "KES",
        "account_number": "9876543210",
        "sender_name": "Parent Association",
        "sender_account": "1111222233",
        "reference": "FEE-PAYMENT-001",
        "timestamp": "2023-12-13T12:00:00Z",
        "bank_code": "01",
        "bank_name": "Kenya Commercial Bank",
        "status": "completed"
    }'

test_endpoint \
    "Bank Webhook (Debit Notification)" \
    "POST" \
    "/bank-webhook" \
    '{
        "event_type": "debit",
        "transaction_id": "BANK987654321",
        "amount": 30000.00,
        "currency": "KES",
        "account_number": "9876543210",
        "recipient_name": "Supplier XYZ",
        "recipient_account": "4444555566",
        "reference": "PAYMENT-OUT-001",
        "timestamp": "2023-12-13T13:00:00Z",
        "bank_code": "01",
        "bank_name": "Kenya Commercial Bank",
        "status": "completed"
    }'

test_endpoint \
    "Bank Webhook (Pending Transaction)" \
    "POST" \
    "/bank-webhook" \
    '{
        "event_type": "credit",
        "transaction_id": "BANK555666777",
        "amount": 20000.00,
        "currency": "KES",
        "account_number": "9876543210",
        "sender_name": "Guardian",
        "reference": "PARTIAL-PAYMENT",
        "timestamp": "2023-12-13T14:00:00Z",
        "bank_code": "01",
        "status": "pending"
    }'

test_endpoint \
    "Bank Webhook (Reversed Transaction)" \
    "POST" \
    "/bank-webhook" \
    '{
        "event_type": "reversal",
        "transaction_id": "BANK123456789",
        "original_transaction_id": "BANK123456780",
        "amount": 10000.00,
        "currency": "KES",
        "account_number": "9876543210",
        "reason": "Duplicate payment",
        "timestamp": "2023-12-13T15:00:00Z",
        "bank_code": "01",
        "status": "reversed"
    }'

# ============================================================================
# FINAL SUMMARY
# ============================================================================

print_summary
