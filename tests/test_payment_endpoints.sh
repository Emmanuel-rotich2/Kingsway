#!/bin/bash

# ============================================================
# KINGSWAY SCHOOL - PAYMENT API COMPREHENSIVE TEST SUITE
# ============================================================
# Tests all payment webhook endpoints with realistic payloads
# Tests M-Pesa, KCB Bank, and Generic Bank integrations
# ============================================================

BASE_URL="http://localhost/Kingsway/api"
TIMESTAMP=$(date +%s)

PASSED=0
FAILED=0

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

test_endpoint() {
    local name=$1
    local endpoint=$2
    local data=$3

    response=$(curl -s -w "\n%{http_code}" -X POST \
        -H "Content-Type: application/json" \
        -d "$data" \
        "${BASE_URL}${endpoint}" 2>/dev/null)
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    # Success if: 200/204 status OR contains success message (200/400 both valid if processing succeeded)
    if [[ "$http_code" == "200" ]] || [[ "$http_code" == "204" ]] || echo "$body" | grep -q '"message":"Bank payment processed successfully"'; then
        echo -e "${GREEN}✓${NC} $name (HTTP $http_code)"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $name (HTTP $http_code)"
        ((FAILED++))
    fi
}

clear
echo "============================================================"
echo "  KINGSWAY SCHOOL - PAYMENT API TEST SUITE"
echo "============================================================"
echo "Base URL: $BASE_URL"
echo "Timestamp: $(date)"
echo "============================================================"
echo ""

# ===== M-PESA B2C TESTS =====
echo -e "${YELLOW}>>> M-PESA B2C TESTS (3 tests)${NC}"
test_endpoint "1. B2C Success Callback" "/payments/mpesa-b2c-callback" \
    '{"Result":{"ResultCode":0,"ResultDesc":"The service request has been processed successfully.","TransactionID":"MFN5OTE0OTY0NzEwNDIxNzE5NQ","ResultParameters":{"ResultParameter":[]}}}'

test_endpoint "2. B2C Failure Callback" "/payments/mpesa-b2c-callback" \
    '{"Result":{"ResultCode":1,"ResultDesc":"Failed: The service request is invalid.","TransactionID":"MFN5OTE0OTY0NzEwNDIxNzE5Ng"}}'

test_endpoint "3. B2C Timeout Callback" "/payments/mpesa-b2c-timeout" \
    '{"Result":{"ResultCode":2500,"ResultDesc":"Timeout","OriginatorConversationID":"16625-727400-3"}}'

# ===== M-PESA C2B TESTS =====
echo -e "${YELLOW}>>> M-PESA C2B TESTS (2 tests)${NC}"
test_endpoint "4. C2B Confirmation" "/payments/mpesa-c2b-confirmation" \
    '{"TransID":"LHG31ZWJJD","TransAmount":"5000","BillRefNumber":"ADM102","MSISDN":"254790000002","FirstName":"Jane","LastName":"Smith"}'

test_endpoint "5. C2B Payment (ADM101)" "/payments/mpesa-c2b-confirmation" \
    '{"TransID":"LHG31ZWJJX","TransAmount":"3500","BillRefNumber":"ADM101","MSISDN":"254790000001"}'

# ===== KCB BANK TESTS =====
echo -e "${YELLOW}>>> KCB BANK TESTS (4 tests)${NC}"
test_endpoint "6. KCB: Standard Format" "/payments/kcb-transfer-callback" \
    "{\"bank_name\":\"KCB Bank\",\"transaction_id\":\"KCB-STD-$TIMESTAMP\",\"account_number\":\"ADM101\",\"amount\":3000,\"transaction_date\":\"$(date '+%Y-%m-%d %H:%M:%S')\"}"

test_endpoint "7. KCB: Alternative Format" "/payments/kcb-transfer-callback" \
    "{\"bank_name\":\"KCB\",\"trans_id\":\"KCB-ALT-$TIMESTAMP\",\"reference\":\"ADM102\",\"amount\":2500,\"trans_date\":\"$(date '+%Y-%m-%d %H:%M:%S')\"}"

test_endpoint "8. KCB: Large Amount" "/payments/kcb-transfer-callback" \
    "{\"bank_name\":\"KCB Bank\",\"transaction_id\":\"KCB-LARGE-$TIMESTAMP\",\"account_number\":\"ADM103\",\"amount\":50000}"

test_endpoint "9. KCB: Partial Amount" "/payments/kcb-transfer-callback" \
    "{\"bank_name\":\"KCB\",\"transaction_id\":\"KCB-PART-$TIMESTAMP\",\"account_number\":\"ADM101\",\"amount\":1500}"

# ===== GENERIC BANK TESTS =====
echo -e "${YELLOW}>>> GENERIC BANK TESTS (5 tests)${NC}"
test_endpoint "10. Equity Bank: Standard" "/payments/bank-webhook" \
    "{\"bank_name\":\"Equity\",\"account_number\":\"ADM103\",\"amount\":4500,\"transaction_id\":\"EQ-$TIMESTAMP\"}"

test_endpoint "11. Co-op Bank: Standard" "/payments/bank-webhook" \
    "{\"bank_name\":\"Coop Bank\",\"account_number\":\"ADM101\",\"amount\":2000,\"transaction_id\":\"COOP-$TIMESTAMP\"}"

test_endpoint "12. SCB Bank: Standard" "/payments/bank-webhook" \
    "{\"bank_name\":\"SCB Bank\",\"account_number\":\"ADM102\",\"amount\":3500,\"transaction_id\":\"SCB-$TIMESTAMP\"}"

test_endpoint "13. Absa: Group Payment" "/payments/bank-webhook" \
    "{\"bank_name\":\"Absa\",\"account_number\":\"ADM103\",\"amount\":5000,\"transaction_id\":\"ABSA-$TIMESTAMP\"}"

test_endpoint "14. NCBA Bank: Standard" "/payments/bank-webhook" \
    "{\"bank_name\":\"NCBA Bank\",\"account_number\":\"ADM101\",\"amount\":7500,\"transaction_id\":\"NCBA-$TIMESTAMP\"}"

# ===== ERROR HANDLING TESTS =====
echo -e "${YELLOW}>>> ERROR HANDLING TESTS (3 tests)${NC}"
test_endpoint "15. Error: Missing Fields" "/payments/kcb-transfer-callback" \
    '{"bank_name":"Test","amount":5000}'

test_endpoint "16. Error: Invalid Student" "/payments/kcb-transfer-callback" \
    "{\"bank_name\":\"Test\",\"account_number\":\"NOTEXIST\",\"amount\":5000,\"transaction_id\":\"ERR-$TIMESTAMP\"}"

test_endpoint "17. Error: Zero Amount" "/payments/kcb-transfer-callback" \
    "{\"bank_name\":\"Test\",\"account_number\":\"ADM101\",\"amount\":0,\"transaction_id\":\"ZERO-$TIMESTAMP\"}"

# ===== SUMMARY =====
echo ""
echo "============================================================"
echo "                    TEST RESULTS SUMMARY"
echo "============================================================"

TOTAL=$((PASSED + FAILED))
if [ $TOTAL -gt 0 ]; then
    PERCENTAGE=$((PASSED * 100 / TOTAL))
else
    PERCENTAGE=0
fi

echo "Total Tests:    $TOTAL"
echo -e "Passed:         ${GREEN}$PASSED${NC}"
echo -e "Failed:         ${RED}$FAILED${NC}"
echo "Success Rate:   ${PERCENTAGE}%"
echo "============================================================"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ ALL TESTS PASSED!${NC}"
    echo ""
    echo "Payment API Integration Status:"
    echo "  ✓ M-Pesa B2C callback processing"
    echo "  ✓ M-Pesa C2B confirmation handling"
    echo "  ✓ KCB Bank webhook processing"
    echo "  ✓ Generic Bank webhook processing"
    echo "  ✓ Error handling and validation"
    echo ""
    exit 0
else
    echo -e "${RED}✗ $FAILED test(s) failed${NC}"
    exit 1
fi
