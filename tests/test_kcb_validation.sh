#!/bin/bash

# Test KCB Validation Endpoint
echo "Testing KCB Validation Endpoint..."
curl -X POST http://localhost/api/payments/kcb-validation.php \
  -H "Content-Type: application/json" \
  -H "Signature: test_signature_here" \
  -d '{
    "requestId": "TEST-REQ-001",
    "customerReference": "ADM001",
    "organizationReference": "777777"
  }' | jq .

echo ""
echo "Test completed!"
