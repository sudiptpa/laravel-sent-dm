# Webhooks

Sent.dm POSTs events to your app when messages are delivered, read, or fail. The webhook route is opt-in.

- [Enable the webhook route](#enable-the-webhook-route)
- [Register the endpoint with Sent.dm](#register-the-endpoint-with-sentdm)
- [Listen to webhook events](#listen-to-webhook-events)
- [All webhook events](#all-webhook-events)
- [How signature verification works](#how-signature-verification-works)
- [Managing webhooks from code](#managing-webhooks-from-code)

## Enable the webhook route

```env
SENT_WEBHOOK_ENABLED=true
SENT_WEBHOOK_SECRET=whsec_...
SENT_WEBHOOK_PATH=sent/webhook
```

## Register the endpoint with Sent.dm

```bash
php artisan sent:setup-webhook https://yourapp.com/sent/webhook
```

This creates the endpoint on Sent.dm and prints the signing secret to add to `.env`.

Subscribe to specific events only:

```bash
php artisan sent:setup-webhook https://yourapp.com/sent/webhook \
    --events=message.delivered \
    --events=message.failed
```

## Listen to webhook events

Register listeners in `AppServiceProvider` or `EventServiceProvider`:

```php
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageSent;

// app/Providers/AppServiceProvider.php
Event::listen(MessageDelivered::class, function (MessageDelivered $event) {
    $messageId = $event->payload->messageId();
    $channel   = $event->payload->channel();
    $recipient = $event->payload->recipient();
});

Event::listen(MessageFailed::class, function (MessageFailed $event) {
    // log or alert
});

Event::listen(MessageReceived::class, function (MessageReceived $event) {
    // inbound message
    $from = $event->payload->sender();
    $text = $event->payload->text();
});
```

## All webhook events

| Event | Triggered when |
|---|---|
| `MessageQueued` | Sent.dm accepted the message |
| `MessageRouted` | Channel selected |
| `MessageSent` | Dispatched to the carrier |
| `MessageDelivered` | Confirmed delivered to the handset |
| `MessageRead` | Recipient opened it (WhatsApp) |
| `MessageFailed` | Delivery failed permanently |
| `MessageFiltered` | Send suppressed by a routing or consent rule, never reached a carrier |
| `MessageBlocked` | Send gated by an account condition, an unapproved template, or no open conversation |
| `MessageScheduled` | Send deferred to a later window (quiet hours or a scheduled send), not final yet |
| `MessageReceived` | Inbound message from a recipient |

Every event carries a `WebhookPayload` with these accessors:

```php
$event->payload->messageId();   // Sent.dm message ID
$event->payload->status();      // message status string
$event->payload->channel();     // sms, whatsapp, rcs
$event->payload->recipient();   // E.164 recipient number
$event->payload->sender();      // E.164 sender number
$event->payload->templateId();  // template used, if any
$event->payload->text();        // inbound text (message.received only)
$event->payload->subType;       // raw event type string, e.g. message.delivered
$event->payload->timestamp;     // ISO 8601 timestamp
```

## How signature verification works

The `VerifySignature` middleware runs before your controller. It reads `x-webhook-signature`, `x-webhook-id`, and `x-webhook-timestamp`, recomputes HMAC-SHA256 over `{webhook_id}.{timestamp}.{raw_body}`, and rejects requests that don't match or are older than 5 minutes. Duplicate events are deduplicated by message ID + event type, so retried deliveries are safe.

## Managing webhooks from code

```php
// list
Sent::webhooks()->get();
Sent::webhooks()->page(2)->perPage(10)->get();

// read
Sent::webhooks()->find('webhook_id');

// create
// events() takes top-level categories ("message", "templates"), not granular event
// names. Subscribing to "message" delivers all ten message.* sub-events; the payload's
// own `event` field carries the specific one (e.g. "message.delivered") once it arrives.
// name() is required on both create and update: Sent.dm rejects the request without
// it even though the SDK types the field optional.
Sent::webhooks()->create()
    ->name('My app webhook')
    ->url('https://yourapp.com/sent/webhook')
    ->events(['message'])
    ->save();

// update
// The live API wants the full payload here too, not just the field being changed.
Sent::webhooks()->update('webhook_id')
    ->name('My app webhook')
    ->url('https://yourapp.com/new-path')
    ->events(['message'])
    ->save();

// enable / disable
Sent::webhooks()->enable('webhook_id');
Sent::webhooks()->disable('webhook_id');

// rotate the signing secret
Sent::webhooks()->rotateSecret('webhook_id');

// send a test event to the endpoint
Sent::webhooks()->test('webhook_id');
Sent::webhooks()->test('webhook_id', 'message.delivered');

// list delivery events for an endpoint (paginated)
Sent::webhooks()->listEvents('webhook_id');
Sent::webhooks()->listEvents('webhook_id', page: 2, pageSize: 25);

// list all supported event types (cached)
Sent::webhooks()->listEventTypes();

// delete
Sent::webhooks()->delete('webhook_id');
```
