<?php

namespace App\API\Services\sms;
// api/services/sms/AfricasTalkingWebhookHandler.php
// Handles Africa's Talking delivery reports and incoming messages (SMS, WhatsApp, MMS)
use App\Config;
class AfricasTalkingWebhookHandler
{
    // Handle delivery report webhook
    public static function handleDeliveryReport()
    {
        // Africa's Talking sends delivery reports as POST params
        $data = $_POST;
        // Example fields: id, status, phoneNumber, networkCode, failureReason, retryCount, messageParts
        $logFile = __DIR__ . '/../../../logs/africastalking_delivery_reports.log';
        file_put_contents($logFile, date('c') . ' ' . json_encode($data) . PHP_EOL, FILE_APPEND);
        // Optionally, update message status in DB here
        http_response_code(200);
        echo 'DELIVERY REPORT RECEIVED';
    }

    // Handle incoming message webhook (SMS, WhatsApp, MMS)
    public static function handleIncomingMessage()
    {
        $data = $_POST;
        // Example fields: text, from, to, date, linkId, networkCode, messageId, type
        $logFile = __DIR__ . '/../../../logs/africastalking_incoming_messages.log';
        file_put_contents($logFile, date('c') . ' ' . json_encode($data) . PHP_EOL, FILE_APPEND);
        // Optionally, trigger business logic (auto-reply, ticket, etc.)
        http_response_code(200);
        echo 'INCOMING MESSAGE RECEIVED';
    }
}

// Simple router for webhook endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uri = $_SERVER['REQUEST_URI'];
    if (strpos($uri, 'africastalking_delivery_report') !== false) {
        AfricasTalkingWebhookHandler::handleDeliveryReport();
        exit;
    } elseif (strpos($uri, 'africastalking_incoming_message') !== false) {
        AfricasTalkingWebhookHandler::handleIncomingMessage();
        exit;
    }
}
