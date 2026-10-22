<?php
/**
 * YEEDIPAY SMS setup (copy these values into Admin → SMS Gateway).
 *
 * This file is a template only. The live app stores SMS settings in MySQL.
 * Do not put your real API key in this file on the server.
 *
 * Placeholders the gateway can use:
 *   {phone}  {message}  {sender_id}  {template_id}  {otp}
 *   {reference}  {amount}  {status}  {customer_code}  {customer_name}
 *   {account_number}  {rrn}  {utr}
 */

return [
    'fast2sms' => [
        'enabled' => false,
        'gateway_name' => 'Fast2SMS',
        'endpoint_url' => 'https://www.fast2sms.com/dev/bulkV2',
        'http_method' => 'POST',
        'content_type' => 'json',
        'auth_header_name' => 'authorization',
        'auth_prefix' => '',
        'auth_token' => 'PASTE_FAST2SMS_API_KEY',
        'sender_id' => 'YEEDIPAY',
        'request_template' => '{"route":"q","message":"{message}","language":"english","flash":0,"numbers":"{phone}"}',
        'otp_message' => 'Your YEEDIPAY OTP is {otp}. Valid for 5 minutes.',
        'pending_message' => 'YEEDIPAY payment initiated. Ref {reference}. Status PENDING.',
        'success_message' => 'YEEDIPAY payment SUCCESS. Rs.{amount} received. A/c {account_number}. UTR {rrn}. Ref {reference}.',
        'failed_message' => 'YEEDIPAY payment FAILED. Ref {reference}.',
        'expired_message' => 'YEEDIPAY payment window expired. Ref {reference}.',
    ],
    'msg91' => [
        'enabled' => false,
        'gateway_name' => 'MSG91',
        'endpoint_url' => 'https://control.msg91.com/api/v5/flow',
        'http_method' => 'POST',
        'content_type' => 'json',
        'auth_header_name' => 'authkey',
        'auth_prefix' => '',
        'auth_token' => 'PASTE_MSG91_AUTHKEY',
        'sender_id' => 'YEEDIPAY',
        'request_template' => '{"template_id":"{template_id}","short_url":"0","recipients":[{"mobiles":"{phone}","message":"{message}"}]}',
        'otp_template_id' => 'PASTE_DLT_OTP_TEMPLATE_ID',
        'pending_template_id' => '',
        'success_template_id' => '',
        'failed_template_id' => '',
        'expired_template_id' => '',
    ],
];
