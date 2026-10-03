<?php

declare(strict_types=1);

it('messages()->retrieve() returns message status', function () {
    $result = sentApi([
        'id' => 'msg-1',
        'customer_id' => 'cust-1',
        'contact_id' => 'c-1',
        'phone' => '+14155551234',
        'phone_international' => '+1 415-555-1234',
        'region_code' => 'US',
        'template_id' => 'tpl-1',
        'template_name' => 'Welcome Message',
        'template_category' => 'UTILITY',
        'channel' => 'sms',
        'message_body' => ['header' => null, 'content' => 'Welcome!', 'footer' => null, 'buttons' => null],
        'status' => 'DELIVERED',
        'direction' => 'OUTBOUND',
        'created_at' => '2026-09-11T12:57:37+00:00',
        'price' => 0.0055,
        'active_contact_price' => 0.015,
        'events' => [
            ['status' => 'QUEUED', 'timestamp' => '2026-09-11T12:57:37+00:00', 'description' => 'Message queued for sending'],
            ['status' => 'DELIVERED', 'timestamp' => '2026-09-11T12:57:47+00:00', 'description' => 'Message delivered to recipient'],
        ],
    ])
        ->messages()
        ->retrieve('msg-1');

    expect($result->data->id)->toBe('msg-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->contactID)->toBe('c-1')
        ->and($result->data->phone)->toBe('+14155551234')
        ->and($result->data->phoneInternational)->toBe('+1 415-555-1234')
        ->and($result->data->regionCode)->toBe('US')
        ->and($result->data->templateID)->toBe('tpl-1')
        ->and($result->data->templateName)->toBe('Welcome Message')
        ->and($result->data->templateCategory)->toBe('UTILITY')
        ->and($result->data->channel)->toBe('sms')
        ->and($result->data->messageBody->content)->toBe('Welcome!')
        ->and($result->data->status)->toBe('DELIVERED')
        ->and($result->data->direction)->toBe('OUTBOUND')
        ->and($result->data->price)->toBe(0.0055)
        ->and($result->data->activeContactPrice)->toBe(0.015)
        ->and($result->data->events)->toHaveCount(2)
        ->and($result->data->events[0]->status)->toBe('QUEUED')
        ->and($result->data->events[1]->status)->toBe('DELIVERED');
});

it('messages()->activities() returns message activities', function () {
    $result = sentApi([
        'message_id' => 'msg-1',
        'activities' => [[
            'status' => 'DELIVERED',
            'description' => 'Message delivered to recipient',
            'from' => '+15551234567',
            'timestamp' => '2026-09-11T14:32:37+00:00',
            'reason_code' => 'DELIVERY_007',
            'reason' => 'The recipient is not registered on this channel',
            'price' => '0.0450',
            'active_contact_price' => '0.0050',
        ]],
        'pagination' => null,
    ])->messages()->activities('msg-1');

    expect($result->data->messageID)->toBe('msg-1')
        ->and($result->data->activities[0]->status)->toBe('DELIVERED')
        ->and($result->data->activities[0]->description)->toBe('Message delivered to recipient')
        ->and($result->data->activities[0]->from)->toBe('+15551234567')
        ->and($result->data->activities[0]->reasonCode)->toBe('DELIVERY_007')
        ->and($result->data->activities[0]->reason)->toBe('The recipient is not registered on this channel')
        ->and($result->data->activities[0]->price)->toBe('0.0450')
        ->and($result->data->activities[0]->activeContactPrice)->toBe('0.0050');
});

it('messages()->activities() exposes scheduled_at for a SCHEDULED activity', function () {
    $result = sentApi([
        'message_id' => 'msg-1',
        'activities' => [[
            'status' => 'SCHEDULED',
            'description' => 'Message held until quiet hours end',
            'from' => null,
            'timestamp' => '2026-09-11T14:32:37+00:00',
            'scheduled_at' => '2026-09-12T08:00:00+00:00',
            'price' => null,
            'active_contact_price' => null,
        ]],
        'pagination' => null,
    ])->messages()->activities('msg-1');

    expect($result->data->activities[0]->scheduledAt?->format(DateTimeInterface::ATOM))->toBe('2026-09-12T08:00:00+00:00');
});

it('messages()->resend() resends a message', function () {
    $result = sentApi([
        'status' => 'QUEUED',
        'template_id' => 'tpl-1',
        'template_name' => 'order_confirmation',
        'recipients' => [[
            'message_id' => 'msg-2',
            'to' => '+14155551234',
            'channel' => 'sms',
            'body' => 'Hi John',
        ]],
    ])->messages()->resend('msg-1');

    expect($result->data->status)->toBe('QUEUED')
        ->and($result->data->templateID)->toBe('tpl-1')
        ->and($result->data->templateName)->toBe('order_confirmation')
        ->and($result->data->recipients[0]->messageID)->toBe('msg-2')
        ->and($result->data->recipients[0]->to)->toBe('+14155551234')
        ->and($result->data->recipients[0]->channel)->toBe('sms')
        ->and($result->data->recipients[0]->body)->toBe('Hi John');
});

it('messages()->resend() keeps recipients that do not include a message id', function () {
    $result = sentApi([
        'status' => 'QUEUED',
        'recipients' => [[
            'to' => '+14155551234',
            'channel' => 'sms',
            'body' => 'Hi John',
        ]],
    ])->messages()->resend('msg-1');

    expect($result->data->recipients[0]->to)->toBe('+14155551234')
        ->and($result->data->recipients[0]->channel)->toBe('sms')
        ->and($result->data->recipients[0]->body)->toBe('Hi John');
});

// Conversations ----------------------------------------------------------------

it('conversations()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->conversations();
    $chained = $base->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('conversations()->get() lists conversations', function () {
    $result = sentApi([
        'messages' => [[
            'id' => 'msg-1',
            'customer_id' => 'cust-1',
            'contact_id' => 'c-1',
            'phone' => '+1234567890',
            'phone_international' => '+1 234-567-890',
            'region_code' => 'US',
            'template_id' => null,
            'template_name' => null,
            'template_category' => null,
            'channel' => 'sms',
            'message_body' => null,
            'status' => 'DELIVERED',
            'direction' => 'OUTBOUND',
            'created_at' => '2026-09-11T14:52:37+00:00',
            'price' => 0.0075,
            'active_contact_price' => 0.0,
            'events' => null,
        ]],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 1,
            'total_pages' => 1, 'has_more' => false, 'cursors' => null,
        ],
    ])->conversations()->get();

    $message = $result->data->messages[0];
    expect($message->id)->toBe('msg-1')
        ->and($message->customerID)->toBe('cust-1')
        ->and($message->contactID)->toBe('c-1')
        ->and($message->phone)->toBe('+1234567890')
        ->and($message->regionCode)->toBe('US')
        ->and($message->channel)->toBe('sms')
        ->and($message->status)->toBe('DELIVERED')
        ->and($message->direction)->toBe('OUTBOUND')
        ->and($message->price)->toBe(0.0075)
        ->and($message->activeContactPrice)->toBe(0.0);
});

it('conversations()->messages() lists messages for a conversation', function () {
    $result = sentApi([
        'messages' => [[
            'id' => 'msg-1',
            'customer_id' => 'cust-1',
            'contact_id' => 'c-1',
            'phone' => '+1234567890',
            'channel' => 'whatsapp',
            'status' => 'SENT',
            'direction' => 'INBOUND',
            'created_at' => '2026-09-11T14:52:37+00:00',
        ]],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 1,
            'total_pages' => 1, 'has_more' => false, 'cursors' => null,
        ],
    ])->conversations()->messages('conv-1');

    expect($result->data->messages[0]->id)->toBe('msg-1')
        ->and($result->data->messages[0]->channel)->toBe('whatsapp')
        ->and($result->data->messages[0]->status)->toBe('SENT')
        ->and($result->data->messages[0]->direction)->toBe('INBOUND');
});
