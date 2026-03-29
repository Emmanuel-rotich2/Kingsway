# WhatsApp Template Guide

## Overview
WhatsApp Business Accounts support message templates for approved, pre-formatted messages. Templates allow you to send structured messages with variables that can be customized per recipient.

## Template Categories
- **MARKETING**: Promotional messages to customers
- **UTILITY**: Transactional messages (receipts, confirmations, alerts)
- **AUTHENTICATION**: One-time passwords, verification codes

## API Endpoints

### 1. Send Template Message
**Endpoint:** `POST /api/communications/send-whatsapp-template`

**Request Body:**
```json
{
  "recipients": ["+254710398690", "+254797630228"],
  "templateId": "template_id_from_approval",
  "variables": ["John", "KES 5000"]
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Sent to 2 recipient(s), failed: 0",
  "data": {
    "sent": 2,
    "failed": 0,
    "sent_ids": [
      {
        "phone": "+254710398690",
        "template_id": "template_123",
        "timestamp": "2025-12-03 14:30:00"
      }
    ],
    "failed_details": []
  }
}
```

### 2. Create Template
**Endpoint:** `POST /api/communications/create-whatsapp-template`

**Note:** Template creation is only available in production environment, not sandbox.

**Request Body - Text Template:**
```json
{
  "name": "school_attendance_alert",
  "language": "en",
  "category": "UTILITY",
  "components": {
    "header": {
      "type": "HEADER",
      "format": "TEXT",
      "text": "Attendance Alert"
    },
    "body": {
      "type": "BODY",
      "text": "Dear {{1}}, {{2}} was absent on {{3}}. Please contact the school.",
      "example": {
        "body_text": ["John", "Jane Smith", "December 3, 2025"]
      }
    },
    "footer": {
      "type": "FOOTER",
      "text": "Kingsway Preparatory School"
    }
  }
}
```

**Text + Image Header Template:**
```json
{
  "name": "school_news_update",
  "language": "en",
  "category": "MARKETING",
  "components": {
    "header": {
      "type": "HEADER",
      "format": "MEDIA",
      "example": {
        "header_handle": ["https://example.com/school-logo.jpg"]
      }
    },
    "body": {
      "type": "BODY",
      "text": "Hi {{1}}, we have great news! {{2}} is scheduled for {{3}}.",
      "example": {
        "body_text": ["John", "Annual Sports Day", "December 10, 2025"]
      }
    },
    "buttons": [
      {
        "type": "URL",
        "text": "Learn More",
        "url": "https://kingsway.ac.ke/events",
        "example": ["https://kingsway.ac.ke/events"]
      }
    ]
  }
}
```

**With Buttons Template:**
```json
{
  "name": "payment_reminder",
  "language": "en",
  "category": "UTILITY",
  "components": {
    "body": {
      "type": "BODY",
      "text": "Dear {{1}}, your school fees of {{2}} are due. Please pay by {{3}}.",
      "example": {
        "body_text": ["Parent Name", "KES 50,000", "December 15, 2025"]
      }
    },
    "buttons": [
      {
        "type": "URL",
        "text": "Pay Now",
        "url": "https://kingsway.ac.ke/pay",
        "example": ["https://kingsway.ac.ke/pay"]
      },
      {
        "type": "PHONE_NUMBER",
        "text": "Call School",
        "phoneNumber": "+254720113030"
      }
    ]
  }
}
```

## Template Variables
- Use `{{1}}`, `{{2}}`, `{{3}}`, etc. for variables
- When sending, provide variables array in the same order
- Example: `"variables": ["John Doe", "December 3", "2025"]`

## Response Status Codes
- **success**: All messages sent successfully
- **partial**: Some messages sent, some failed
- **error**: All messages failed

## Common Errors
- `InvalidSenderId`: Sender ID not registered
- `InvalidPhoneNumber`: Recipient number format invalid
- `InsufficientBalance`: Insufficient balance
- `template_approval_pending`: Template not yet approved
- `template_not_found`: Template ID doesn't exist

## Best Practices
1. **Keep templates concise** - WhatsApp has message length limits
2. **Use clear variables** - Order matters when sending
3. **Test in sandbox first** - Use test number before production
4. **Approve templates first** - WhatsApp team must approve before sending
5. **Monitor delivery** - Check logs for delivery status

## Testing
```bash
# Test sending template
curl -X POST http://localhost/Kingsway/api/communications/send-whatsapp-template \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "recipients": ["+254710398690"],
    "templateId": "test_template_123",
    "variables": ["Test Name", "Test Value"]
  }'
```

## Production Deployment Checklist
- [ ] Template created and approved by WhatsApp
- [ ] API credentials configured (SMS_API_KEY)
- [ ] WhatsApp number configured (SMS_WHATSAPP_NUMBER)
- [ ] Test message sent successfully
- [ ] Logging configured and working
- [ ] Error handling tested
- [ ] Fallback messaging ready if templates fail
