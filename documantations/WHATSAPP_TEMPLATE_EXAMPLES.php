<?php
/**
 * WhatsApp Template Examples
 * 
 * This file contains example templates ready to use with the Kingsway system
 */

// Example 1: Student Attendance Alert Template
$attendanceAlertTemplate = [
    "name" => "student_attendance_alert",
    "language" => "en",
    "category" => "UTILITY",
    "components" => [
        "header" => [
            "type" => "HEADER",
            "format" => "TEXT",
            "text" => "📋 Attendance Report",
            "example" => [
                "header_text" => "Attendance Report"
            ]
        ],
        "body" => [
            "type" => "BODY",
            "text" => "Dear {{1}},\n\nThis is to notify you that {{2}} has been marked absent on {{3}}.\n\nPlease contact the school if you need more information.",
            "example" => [
                "body_text" => [
                    "Mr. Kipchoge",
                    "Jane Kipchoge",
                    "December 3, 2025"
                ]
            ]
        ],
        "footer" => [
            "type" => "FOOTER",
            "text" => "© 2025 Kingsway Preparatory School"
        ]
    ]
];

// Example 2: Payment Reminder Template
$paymentReminderTemplate = [
    "name" => "school_fees_reminder",
    "language" => "en",
    "category" => "UTILITY",
    "components" => [
        "body" => [
            "type" => "BODY",
            "text" => "Dear {{1}},\n\n💰 Reminder: School fees of {{2}} are due by {{3}}.\n\nPlease process payment to avoid late fees.\n\nThank you!",
            "example" => [
                "body_text" => [
                    "Parent Name",
                    "KES 50,000",
                    "December 15, 2025"
                ]
            ]
        ],
        "buttons" => [
            [
                "type" => "URL",
                "text" => "💳 Pay Now",
                "url" => "https://kingsway.ac.ke/payments",
                "example" => ["https://kingsway.ac.ke/payments"]
            ],
            [
                "type" => "PHONE_NUMBER",
                "text" => "📞 Call School",
                "phoneNumber" => "+254720113030"
            ]
        ]
    ]
];

// Example 3: Exam Results Template
$examResultsTemplate = [
    "name" => "exam_results_notification",
    "language" => "en",
    "category" => "UTILITY",
    "components" => [
        "header" => [
            "type" => "HEADER",
            "format" => "TEXT",
            "text" => "🎓 Exam Results Released",
            "example" => [
                "header_text" => "Exam Results Released"
            ]
        ],
        "body" => [
            "type" => "BODY",
            "text" => "Dear {{1}},\n\nGood news! {{2}}'s {{3}} exam results are now available.\n\nPlease log in to the portal to view detailed feedback.",
            "example" => [
                "body_text" => [
                    "Parent Name",
                    "Jane Kipchoge",
                    "Term 1"
                ]
            ]
        ],
        "buttons" => [
            [
                "type" => "URL",
                "text" => "📊 View Results",
                "url" => "https://kingsway.ac.ke/results",
                "example" => ["https://kingsway.ac.ke/results"]
            ]
        ]
    ]
];

// Example 4: Event Announcement Template
$eventAnnouncementTemplate = [
    "name" => "event_announcement",
    "language" => "en",
    "category" => "MARKETING",
    "components" => [
        "header" => [
            "type" => "HEADER",
            "format" => "TEXT",
            "text" => "🎉 Important School Event",
            "example" => [
                "header_text" => "Important School Event"
            ]
        ],
        "body" => [
            "type" => "BODY",
            "text" => "Dear {{1}},\n\nWe are excited to announce {{2}}!\n\n📅 Date: {{3}}\n⏰ Time: {{4}}\n📍 Venue: {{5}}\n\nPlease mark your calendar and confirm attendance.",
            "example" => [
                "body_text" => [
                    "Parents",
                    "Annual Sports Day",
                    "December 10, 2025",
                    "10:00 AM",
                    "School Grounds"
                ]
            ]
        ],
        "buttons" => [
            [
                "type" => "URL",
                "text" => "📝 RSVP Now",
                "url" => "https://kingsway.ac.ke/events",
                "example" => ["https://kingsway.ac.ke/events"]
            ]
        ]
    ]
];

// Example 5: OTP/Authentication Template
$otpTemplate = [
    "name" => "school_portal_otp",
    "language" => "en",
    "category" => "AUTHENTICATION",
    "components" => [
        "body" => [
            "type" => "BODY",
            "text" => "Your Kingsway Portal verification code is: {{1}}\n\nThis code will expire in 10 minutes.\n\nDo not share this code with anyone.",
            "example" => [
                "body_text" => ["123456"]
            ]
        ]
    ]
];

/**
 * API Usage Examples
 */

// Example API Call 1: Send Attendance Alert
$attendanceAlertExample = [
    "endpoint" => "POST /api/communications/send-whatsapp-template",
    "request" => [
        "recipients" => ["+254710398690"],
        "templateId" => "student_attendance_alert_123", // Template ID from WhatsApp approval
        "variables" => ["Mr. Kipchoge", "Jane Kipchoge", "December 3, 2025"]
    ],
    "curl" => 'curl -X POST https://kingsway.ac.ke/api/communications/send-whatsapp-template \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d \'{
    "recipients": ["+254710398690"],
    "templateId": "student_attendance_alert_123",
    "variables": ["Mr. Kipchoge", "Jane Kipchoge", "December 3, 2025"]
  }\''
];

// Example API Call 2: Bulk Payment Reminders
$bulkPaymentReminderExample = [
    "endpoint" => "POST /api/communications/send-whatsapp-template",
    "request" => [
        "recipients" => [
            "+254710398690",
            "+254797630228",
            "+254712345678"
        ],
        "templateId" => "school_fees_reminder_456",
        "variables" => ["Parent Name", "KES 50,000", "December 15, 2025"]
    ]
];

// Example API Call 3: Create New Template
$createTemplateExample = [
    "endpoint" => "POST /api/communications/create-whatsapp-template",
    "note" => "Only available in production with verified WhatsApp Business Account",
    "request" => [
        "name" => "school_fees_reminder",
        "language" => "en",
        "category" => "UTILITY",
        "components" => [
            "body" => [
                "type" => "BODY",
                "text" => "Dear {{1}},\n\n💰 Reminder: School fees of {{2}} are due by {{3}}.",
                "example" => [
                    "body_text" => ["Parent Name", "KES 50,000", "December 15, 2025"]
                ]
            ]
        ]
    ]
];

?>
