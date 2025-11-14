#!/bin/bash

# Test KCB Notification Endpoint
echo "Testing KCB Notification Endpoint..."
curl -X POST http://localhost/api/payments/kcb-notification.php \
  -H "Content-Type: application/json" \
  -H "Signature: test_signature_here" \
  -d '{
    "transactionReference": "FT123456789",
    "requestId": "TEST-REQ-001",
    "channelCode": "MOBILE",
    "timestamp": "20251112143000",
    "transactionAmount": 5000,
    "currency": "KES",
    "customerReference": "ADM001",
    "customerName": "John Doe",
    "customerMobileNumber": "254712345678",
    "balance": 100000,
    "narration": "School fees payment",
    "creditAccountIdentifier": "1234567890",
    "organizationShortCode": "777777",
    "tillNumber": ""
  }' | jq .

echo ""
echo "Test completed!"
