<?php

declare(strict_types=1);

use Sujip\SentDm\Events\CallAgentConnected;
use Sujip\SentDm\Events\CallAnswered;
use Sujip\SentDm\Events\CallCompleted;
use Sujip\SentDm\Events\CallFailed;
use Sujip\SentDm\Events\CallInitiated;
use Sujip\SentDm\Events\CallRecordingReady;
use Sujip\SentDm\Events\ChannelActivated;
use Sujip\SentDm\Events\ChannelApproved;
use Sujip\SentDm\Events\ChannelChangesRequested;
use Sujip\SentDm\Events\ChannelDeactivated;
use Sujip\SentDm\Events\ChannelRejected;
use Sujip\SentDm\Events\ChannelRemoved;
use Sujip\SentDm\Events\ChannelSubmitted;
use Sujip\SentDm\Events\ContactCustomKeyword;
use Sujip\SentDm\Events\ContactHelpRequested;
use Sujip\SentDm\Events\ContactOptedIn;
use Sujip\SentDm\Events\ContactOptedOut;
use Sujip\SentDm\Events\LinkClicked;
use Sujip\SentDm\Events\LinkDownloaded;
use Sujip\SentDm\Events\LinkExpired;
use Sujip\SentDm\Events\LinkRevoked;
use Sujip\SentDm\Events\MessageBlocked;
use Sujip\SentDm\Events\MessageCancelled;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageFiltered;
use Sujip\SentDm\Events\MessageQueued;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Events\MessageRouted;
use Sujip\SentDm\Events\MessageScheduled;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Events\TemplateApproved;
use Sujip\SentDm\Events\TemplateCategoryUpdated;
use Sujip\SentDm\Events\TemplateDeleted;
use Sujip\SentDm\Events\TemplateDisabled;
use Sujip\SentDm\Events\TemplatePaused;
use Sujip\SentDm\Events\TemplatePending;
use Sujip\SentDm\Events\TemplateQualityUpdated;
use Sujip\SentDm\Events\TemplateRejected;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Webhooks\WebhookPayload;

/**
 * @param  array<string, mixed>  $data
 */
function webhookPayload(string $subType, array $data = []): WebhookPayload
{
    return WebhookPayload::fromArray([
        'field' => 'message',
        'event' => $subType,
        'timestamp' => '2025-10-31T10:10:42Z',
        'request_id' => 'req_1',
        'payload' => array_merge([
            'message_id' => 'msg_1',
            'message_status' => 'DELIVERED',
            'channel' => 'sms',
            'inbound_number' => '+61412345678',
            'outbound_number' => '+61498765432',
            'template_id' => 'tpl_1',
            'template_name' => 'otp',
            'body' => 'Your code is 123456.',
            'updated_at' => '2026-09-25T07:41:34Z',
            'agent_id' => 'agent_1',
        ], $data),
    ]);
}

it('parses a webhook payload', function () {
    $payload = webhookPayload('message.delivered');

    expect($payload->field)->toBe('message')
        ->and($payload->subType)->toBe('message.delivered')
        ->and($payload->messageId())->toBe('msg_1')
        ->and($payload->status())->toBe('DELIVERED')
        ->and($payload->channel())->toBe('sms')
        ->and($payload->recipient())->toBe('+61412345678')
        ->and($payload->sender())->toBe('+61498765432')
        ->and($payload->templateId())->toBe('tpl_1')
        ->and($payload->templateName())->toBe('otp')
        ->and($payload->requestId())->toBe('req_1')
        ->and($payload->body())->toBe('Your code is 123456.')
        ->and($payload->updatedAt())->toBe('2026-09-25T07:41:34Z')
        ->and($payload->agentId())->toBe('agent_1')
        ->and($payload->dedupKey())->toBe('msg_1.message.delivered');
});

it('preserves every current message webhook payload field from the OpenAPI schema', function () {
    $data = [
        'updated_at' => '2026-09-25T07:41:34Z',
        'account_id' => 'acc_1',
        'message_id' => 'msg_1',
        'template_id' => 'tpl_1',
        'template_name' => 'otp',
        'outbound_number' => '+61498765432',
        'agent_id' => 'agent_1',
        'message_status' => 'SCHEDULED',
        'channel' => 'rcs',
        'body' => 'Your code is 123456.',
        'scheduled_at' => '2026-09-25T08:41:34Z',
        'schedule_reason' => 'quiet_hours',
    ];

    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'event' => 'message.scheduled',
        'timestamp' => '2026-09-25T07:41:34Z',
        'request_id' => 'req_msg_1',
        'payload' => $data,
    ]);

    expect($payload->data)->toBe($data)
        ->and($payload->field)->toBe('message')
        ->and($payload->subType)->toBe('message.scheduled')
        ->and($payload->timestamp)->toBe('2026-09-25T07:41:34Z')
        ->and($payload->requestId())->toBe('req_msg_1')
        ->and($payload->updatedAt())->toBe('2026-09-25T07:41:34Z')
        ->and($payload->accountId())->toBe('acc_1')
        ->and($payload->messageId())->toBe('msg_1')
        ->and($payload->templateId())->toBe('tpl_1')
        ->and($payload->templateName())->toBe('otp')
        ->and($payload->recipient())->toBe('+61498765432')
        ->and($payload->agentId())->toBe('agent_1')
        ->and($payload->status())->toBe('SCHEDULED')
        ->and($payload->channel())->toBe('rcs')
        ->and($payload->body())->toBe('Your code is 123456.')
        ->and($payload->scheduledAt())->toBe('2026-09-25T08:41:34Z')
        ->and($payload->scheduleReason())->toBe('quiet_hours');
});

it('reads template auto-reply metadata from webhook payloads', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'templates',
        'event' => 'templates.approved',
        'request_id' => 'req_tpl_1',
        'payload' => [
            'account_id' => 'acc_1',
            'template_id' => 'tpl_1',
            'whatsapp_template_id' => 'w_tpl_1',
            'template_name' => 'opt_out',
            'status' => 'APPROVED',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'channel' => 'rcs',
            'auto_reply_action' => 'OPT_OUT',
            'reason' => 'approved',
        ],
    ]);

    expect($payload->requestId())->toBe('req_tpl_1')
        ->and($payload->accountId())->toBe('acc_1')
        ->and($payload->templateId())->toBe('tpl_1')
        ->and($payload->whatsappTemplateId())->toBe('w_tpl_1')
        ->and($payload->templateName())->toBe('opt_out')
        ->and($payload->status())->toBe('APPROVED')
        ->and($payload->channel())->toBe('rcs')
        ->and($payload->autoReplyAction())->toBe('OPT_OUT')
        ->and($payload->reason())->toBe('approved');
});

it('preserves every current template webhook payload field from the OpenAPI schema', function () {
    $data = [
        'account_id' => 'acc_1',
        'template_id' => 'tpl_1',
        'whatsapp_template_id' => 'w_tpl_1',
        'template_name' => 'opt_out',
        'status' => 'APPROVED',
        'language' => 'en_US',
        'category' => 'UTILITY',
        'channel' => 'rcs',
        'auto_reply_action' => 'OPT_OUT',
        'reason' => 'approved',
    ];

    $payload = WebhookPayload::fromArray([
        'field' => 'templates',
        'event' => 'templates.approved',
        'timestamp' => '2026-09-25T07:41:34Z',
        'request_id' => 'req_tpl_1',
        'payload' => $data,
    ]);

    expect($payload->data)->toBe($data)
        ->and($payload->field)->toBe('templates')
        ->and($payload->subType)->toBe('templates.approved')
        ->and($payload->timestamp)->toBe('2026-09-25T07:41:34Z')
        ->and($payload->requestId())->toBe('req_tpl_1')
        ->and($payload->accountId())->toBe('acc_1')
        ->and($payload->templateId())->toBe('tpl_1')
        ->and($payload->whatsappTemplateId())->toBe('w_tpl_1')
        ->and($payload->templateName())->toBe('opt_out')
        ->and($payload->status())->toBe('APPROVED')
        ->and($payload->channel())->toBe('rcs')
        ->and($payload->autoReplyAction())->toBe('OPT_OUT')
        ->and($payload->reason())->toBe('approved');
});

it('preserves every current inbound message webhook payload field from the OpenAPI schema', function () {
    $data = [
        'message_id' => 'msg_in_1',
        'updated_at' => '2026-09-25T07:41:34Z',
        'account_id' => 'acc_1',
        'inbound_number' => '+61412345678',
        'outbound_number' => '+61498765432',
        'text' => 'STOP',
        'channel' => 'sms',
        'received_at' => '2026-09-25T07:41:34Z',
    ];

    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'event' => 'message.received',
        'timestamp' => '2026-09-25T07:41:35Z',
        'request_id' => 'req_inbound_1',
        'payload' => $data,
    ]);

    expect($payload->data)->toBe($data)
        ->and($payload->field)->toBe('message')
        ->and($payload->subType)->toBe('message.received')
        ->and($payload->timestamp)->toBe('2026-09-25T07:41:35Z')
        ->and($payload->requestId())->toBe('req_inbound_1')
        ->and($payload->accountId())->toBe('acc_1')
        ->and($payload->messageId())->toBe('msg_in_1')
        ->and($payload->updatedAt())->toBe('2026-09-25T07:41:34Z')
        ->and($payload->sender())->toBe('+61412345678')
        ->and($payload->recipient())->toBe('+61498765432')
        ->and($payload->text())->toBe('STOP')
        ->and($payload->channel())->toBe('sms');
});

it('preserves every current link webhook payload field from the OpenAPI schema', function () {
    $data = [
        'customer_id' => 'cust_1',
        'sender_profile_id' => 'sp_1',
        'message_id' => 'msg_1',
        'record_id' => 'link_1',
        'link_kind' => 'click',
        'channel' => 'sms',
        'reference_key' => 'ref_1',
        'occurred_at' => '2026-09-25T07:41:34Z',
        'request_method' => 'GET',
        'status_code' => 302,
        'traffic_class' => 'human',
        'access_country' => 'AU',
        'device' => 'mobile',
        'browser' => 'Safari',
        'referrer_host' => 'example.com',
        'bytes_served' => 512,
        'access_outcome' => 'redirected',
    ];

    $payload = WebhookPayload::fromArray([
        'field' => 'link',
        'event' => 'link.clicked',
        'timestamp' => '2026-09-25T07:41:35Z',
        'request_id' => 'req_link_1',
        'payload' => $data,
    ]);

    expect($payload->data)->toBe($data)
        ->and($payload->field)->toBe('link')
        ->and($payload->subType)->toBe('link.clicked')
        ->and($payload->timestamp)->toBe('2026-09-25T07:41:35Z')
        ->and($payload->requestId())->toBe('req_link_1')
        ->and($payload->messageId())->toBe('msg_1')
        ->and($payload->channel())->toBe('sms');
});

it('reads the recipient from the current outbound status payload', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'event' => 'message.delivered',
        'payload' => ['outbound_number' => '+14155550101'],
    ]);

    expect($payload->recipient())->toBe('+14155550101')->and($payload->sender())->toBeNull();
});

it('distinguishes the contact and the receiving number on inbound messages', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'event' => 'message.received',
        'payload' => ['inbound_number' => '+14155550101', 'outbound_number' => '+14155550102'],
    ]);

    expect($payload->sender())->toBe('+14155550101')->and($payload->recipient())->toBe('+14155550102');
});

it('returns a content-hash dedup key when message id is absent', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'event' => 'message.received',
        'payload' => ['from' => '+61412345678'],
    ]);

    expect($payload->dedupKey())->toStartWith('inbound.')
        ->and($payload->messageId())->toBeNull();
});

it('MessageSent holds message and response in job context', function () {
    $message = SentMessage::create()->to('+61412345678');
    $event = new MessageSent($message, ['id' => 'msg_123'], connectionName: 'acme');

    expect($event->message)->toBe($message)
        ->and($event->response)->toBe(['id' => 'msg_123'])
        ->and($event->payload)->toBeNull()
        ->and($event->connectionName)->toBe('acme');
});

it('MessageSent can be built from a webhook payload', function () {
    $payload = webhookPayload('message.sent');
    $event = MessageSent::fromWebhook($payload);

    expect($event->message)->toBeNull()
        ->and($event->response)->toBeNull()
        ->and($event->payload)->toBe($payload);
});

it('MessageFailed holds message and exception in job context', function () {
    $message = SentMessage::create()->to('+61412345678');
    $exception = new RuntimeException('API error');
    $event = new MessageFailed($message, $exception, connectionName: 'acme');

    expect($event->message)->toBe($message)
        ->and($event->exception)->toBe($exception)
        ->and($event->payload)->toBeNull()
        ->and($event->connectionName)->toBe('acme');
});

it('MessageFailed can be built from a webhook payload', function () {
    $payload = webhookPayload('message.failed');
    $event = MessageFailed::fromWebhook($payload);

    expect($event->message)->toBeNull()
        ->and($event->exception)->toBeNull()
        ->and($event->payload)->toBe($payload);
});

it('webhook-only events carry the payload', function () {
    $payload = webhookPayload('message.delivered');

    expect((new MessageQueued($payload))->payload)->toBe($payload)
        ->and((new MessageRouted($payload))->payload)->toBe($payload)
        ->and((new MessageDelivered($payload))->payload)->toBe($payload)
        ->and((new MessageRead($payload))->payload)->toBe($payload)
        ->and((new MessageFiltered($payload))->payload)->toBe($payload)
        ->and((new MessageBlocked($payload))->payload)->toBe($payload)
        ->and((new MessageScheduled($payload))->payload)->toBe($payload)
        ->and((new MessageCancelled($payload))->payload)->toBe($payload)
        ->and((new MessageReceived($payload))->payload)->toBe($payload);
});

it('WebhookPayload returns accountId from payload', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'event' => 'message.delivered',
        'payload' => ['account_id' => 'acc_xyz', 'message_id' => 'msg_1'],
    ]);

    expect($payload->accountId())->toBe('acc_xyz');
});

it('WebhookPayload string() returns null when field value is non-string', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'event' => 'message.delivered',
        'request_id' => 12345,
        'payload' => [
            'message_id' => 12345,
            'channel' => null,
            'template_name' => [],
            'whatsapp_template_id' => new stdClass,
            'body' => false,
            'updated_at' => true,
            'agent_id' => 123,
            'scheduled_at' => [],
            'schedule_reason' => false,
            'reason' => 0,
            'auto_reply_action' => 1,
        ],
    ]);

    expect($payload->messageId())->toBeNull()
        ->and($payload->channel())->toBeNull()
        ->and($payload->requestId())->toBeNull()
        ->and($payload->templateName())->toBeNull()
        ->and($payload->whatsappTemplateId())->toBeNull()
        ->and($payload->body())->toBeNull()
        ->and($payload->updatedAt())->toBeNull()
        ->and($payload->agentId())->toBeNull()
        ->and($payload->scheduledAt())->toBeNull()
        ->and($payload->scheduleReason())->toBeNull()
        ->and($payload->reason())->toBeNull()
        ->and($payload->autoReplyAction())->toBeNull();
});

it('WebhookPayload exposes call event fields', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'call',
        'event' => 'call.completed',
        'payload' => [
            'call_id' => 'call_1',
            'number' => '+61498765432',
            'mode' => 'agent',
            'channel' => 'voice',
            'agent_id' => 'agent_1',
            'duration_seconds' => 42,
            'price' => 0.15,
            'reason' => 'callback_timeout',
            'recording_id' => 'rec_1',
        ],
    ]);

    expect($payload->callId())->toBe('call_1')
        ->and($payload->number())->toBe('+61498765432')
        ->and($payload->mode())->toBe('agent')
        ->and($payload->channel())->toBe('voice')
        ->and($payload->agentId())->toBe('agent_1')
        ->and($payload->durationSeconds())->toBe(42)
        ->and($payload->price())->toBe(0.15)
        ->and($payload->reason())->toBe('callback_timeout')
        ->and($payload->recordingId())->toBe('rec_1');
});

it('call accessors are null when the field is absent or the wrong type', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'call',
        'event' => 'call.initiated',
        'payload' => ['call_id' => 'call_1', 'duration_seconds' => 'x', 'price' => 'free'],
    ]);

    expect($payload->durationSeconds())->toBeNull()
        ->and($payload->price())->toBeNull()
        ->and($payload->recordingId())->toBeNull()
        ->and($payload->number())->toBeNull();
});

it('call dedup keys include the call id, event, and recording id', function () {
    $base = ['field' => 'call', 'payload' => ['call_id' => 'call_1']];

    $answered = WebhookPayload::fromArray([...$base, 'event' => 'call.answered']);
    $completed = WebhookPayload::fromArray([...$base, 'event' => 'call.completed']);
    $recording = WebhookPayload::fromArray(['field' => 'call', 'event' => 'call.recording_ready', 'payload' => ['call_id' => 'call_1', 'recording_id' => 'rec_1']]);

    expect($answered->dedupKey())->toBe('call_1.call.answered')
        ->and($completed->dedupKey())->toBe('call_1.call.completed')
        ->and($recording->dedupKey())->toBe('call_1.call.recording_ready.rec_1');
});

it('every non-message event carries the payload', function () {
    $payload = WebhookPayload::fromArray(['field' => 'call', 'event' => 'call.initiated', 'payload' => ['call_id' => 'call_1']]);

    foreach ([CallInitiated::class, CallAnswered::class, CallAgentConnected::class, CallCompleted::class, CallFailed::class, CallRecordingReady::class, ContactOptedIn::class, ContactOptedOut::class, ContactHelpRequested::class, ContactCustomKeyword::class, TemplateApproved::class, TemplateRejected::class, TemplatePending::class, TemplateDeleted::class, TemplateCategoryUpdated::class, TemplateQualityUpdated::class, TemplatePaused::class, TemplateDisabled::class, LinkClicked::class, LinkDownloaded::class, LinkExpired::class, LinkRevoked::class, ChannelSubmitted::class, ChannelChangesRequested::class, ChannelApproved::class, ChannelActivated::class, ChannelDeactivated::class, ChannelRejected::class, ChannelRemoved::class] as $class) {
        expect((new $class($payload))->payload)->toBe($payload);
    }
});

it('WebhookPayload exposes contact event fields', function () {
    $payload = WebhookPayload::fromArray(['field' => 'contact', 'event' => 'contact.opt_out', 'payload' => [
        'contact_id' => 'c_1', 'opt_out' => true, 'source' => 'keyword', 'from' => '+61400000001', 'to' => '+61400000002',
        'message_id' => 'msg_1', 'template_id' => 'tpl_1', 'text' => 'STOP', 'channel' => 'sms', 'agent_id' => 'agent_1',
    ]]);

    expect($payload->contactId())->toBe('c_1')
        ->and($payload->optOut())->toBeTrue()
        ->and($payload->source())->toBe('keyword')
        ->and($payload->from())->toBe('+61400000001')
        ->and($payload->to())->toBe('+61400000002')
        ->and($payload->text())->toBe('STOP')
        ->and($payload->templateId())->toBe('tpl_1');
});

it('optOut is null unless the field is a boolean', function () {
    expect(WebhookPayload::fromArray(['payload' => ['opt_out' => 'yes']])->optOut())->toBeNull()
        ->and(WebhookPayload::fromArray(['payload' => []])->optOut())->toBeNull();
});

it('WebhookPayload exposes template event fields', function () {
    $payload = WebhookPayload::fromArray(['field' => 'templates', 'event' => 'templates.category_updated', 'payload' => [
        'status' => 'APPROVED', 'whatsapp_template_id' => 'wa_1', 'template_id' => 'tpl_1', 'template_name' => 'otp',
        'category' => 'UTILITY', 'language' => 'en_US', 'reason' => 'none', 'auto_reply_action' => 'HELP',
    ]]);

    expect($payload->status())->toBe('APPROVED')
        ->and($payload->whatsappTemplateId())->toBe('wa_1')
        ->and($payload->category())->toBe('UTILITY')
        ->and($payload->language())->toBe('en_US')
        ->and($payload->templateName())->toBe('otp')
        ->and($payload->autoReplyAction())->toBe('HELP');
});

it('WebhookPayload exposes channel event fields', function () {
    $payload = WebhookPayload::fromArray(['field' => 'channel', 'event' => 'channel.approved', 'payload' => [
        'country' => 'US', 'number_type' => '10dlc', 'sender_value' => 'ACME', 'status' => 'active', 'reason' => 'ok',
        'reason_code' => 'R1', 'updated_at' => '2026-11-04T07:46:29Z', 'compliance' => ['brand' => ['inherit' => true]],
    ]]);

    expect($payload->country())->toBe('US')
        ->and($payload->numberType())->toBe('10dlc')
        ->and($payload->senderValue())->toBe('ACME')
        ->and($payload->status())->toBe('active')
        ->and($payload->reasonCode())->toBe('R1')
        ->and($payload->compliance())->toBe(['brand' => ['inherit' => true]])
        ->and(WebhookPayload::fromArray(['payload' => ['compliance' => 'x']])->compliance())->toBeNull();
});

it('WebhookPayload exposes link event fields', function () {
    $payload = WebhookPayload::fromArray(['field' => 'link', 'event' => 'link.clicked', 'payload' => [
        'customer_id' => 'cust_1', 'sender_profile_id' => 'sp_1', 'message_id' => 'msg_1', 'record_id' => 'rec_1',
        'link_kind' => 'short', 'channel' => 'sms', 'reference_key' => 'ref', 'occurred_at' => '2026-11-04T07:46:29Z',
        'request_method' => 'GET', 'status_code' => 302, 'traffic_class' => 'human', 'access_country' => 'AU',
        'device' => 'mobile', 'browser' => 'safari', 'referrer_host' => 'example.com', 'bytes_served' => 120,
        'access_outcome' => 'served',
    ]]);

    expect($payload->customerId())->toBe('cust_1')
        ->and($payload->senderProfileId())->toBe('sp_1')
        ->and($payload->recordId())->toBe('rec_1')
        ->and($payload->linkKind())->toBe('short')
        ->and($payload->referenceKey())->toBe('ref')
        ->and($payload->occurredAt())->toBe('2026-11-04T07:46:29Z')
        ->and($payload->requestMethod())->toBe('GET')
        ->and($payload->statusCode())->toBe(302)
        ->and($payload->trafficClass())->toBe('human')
        ->and($payload->accessCountry())->toBe('AU')
        ->and($payload->device())->toBe('mobile')
        ->and($payload->browser())->toBe('safari')
        ->and($payload->referrerHost())->toBe('example.com')
        ->and($payload->bytesServed())->toBe(120)
        ->and($payload->accessOutcome())->toBe('served')
        ->and(WebhookPayload::fromArray(['payload' => ['status_code' => '302', 'bytes_served' => 'x']])->statusCode())->toBeNull();
});

it('non-message dedup keys use the delivery id when present and a content hash otherwise', function () {
    $withId = WebhookPayload::fromArray(['field' => 'contact', 'event' => 'contact.opt_in', 'timestamp' => 't', 'payload' => ['message_id' => 'msg_1']], 'evt_1');
    $without = WebhookPayload::fromArray(['field' => 'contact', 'event' => 'contact.opt_in', 'timestamp' => 't', 'payload' => ['message_id' => 'msg_1']]);
    $other = WebhookPayload::fromArray(['field' => 'contact', 'event' => 'contact.opt_in', 'timestamp' => 't2', 'payload' => ['message_id' => 'msg_1']]);

    expect($withId->dedupKey())->toBe('delivery.evt_1')
        ->and($without->dedupKey())->toStartWith('contact.opt_in.')
        ->and($without->dedupKey())->not->toBe($other->dedupKey());
});

it('message dedup keys are unchanged by the delivery id', function () {
    $payload = WebhookPayload::fromArray(['field' => 'message', 'event' => 'message.delivered', 'payload' => ['message_id' => 'msg_1']], 'evt_1');

    expect($payload->dedupKey())->toBe('msg_1.message.delivered');
});
