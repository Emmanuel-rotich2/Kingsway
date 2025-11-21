#!/bin/bash

# Test M-Pesa C2B Validation Endpoint
echo "Testing M-Pesa C2B Validation Endpoint..."
curl -X POST http://localhost/api/payments/mpesa-c2b-validation.php \
  -H "Content-Type: application/json" \
  -d '{
    "TransactionType": "Pay Bill",
    "TransID": "TEST123456",
    "TransTime": "20251112143000",
    "TransAmount": "1000",
    "BusinessShortCode": "247247",
    "BillRefNumber": "ADM001",
    "InvoiceNumber": "",
    "OrgAccountBalance": "",
    "ThirdPartyTransID": "",
    "MSISDN": "254712345678",
    "FirstName": "John",
    "MiddleName": "Kamau",
    "LastName": "Doe"
  }' | jq .

echo ""
echo "Test completed!"
