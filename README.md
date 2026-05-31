# Laravel Sent DM

A Laravel package for [Sent.dm](https://sent.dm) — send SMS, WhatsApp, and RCS messages from your Laravel application with a fluent API, queue support, webhooks, and a full testing suite.

[![Tests](https://github.com/sudiptpa/laravel-sent-dm/actions/workflows/tests.yml/badge.svg)](https://github.com/sudiptpa/laravel-sent-dm/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/sudiptpa/laravel-sent-dm/v/stable)](https://packagist.org/packages/sudiptpa/laravel-sent-dm)
[![License](https://poser.pugx.org/sudiptpa/laravel-sent-dm/license)](https://packagist.org/packages/sudiptpa/laravel-sent-dm)

---

## What this package does

- Send messages via SMS, WhatsApp, or RCS through Sent.dm's unified API
- Auto-route to the best available channel (WhatsApp first, SMS fallback)
- Queue every send — no blocking the request cycle
- Send to thousands of recipients via bulk dispatch
- Receive delivery events via webhooks with signature verification
- Look up carrier and line type for any phone number
- Validate phone numbers in form requests
- Support multiple Sent.dm accounts in the same app (multi-tenancy)
- Log every outbound message locally and auto-sync delivery status from webhooks
- Track opt-out consent — auto-record STOP keywords and block opted-out contacts
- Use `Sent::fake()` in tests — no real API calls, full assertions

---

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

---

## Installation

```bash
composer require sudiptpa/laravel-sent-dm
```

Publish the config file:

```bash
php artisan sent:install
```

Add your API key to `.env`:

```env
SENT_API_KEY=your-api-key
```

Verify the connection:

```bash
php artisan sent:health
```

---

## Configuration

The published config is at `config/sent.php`. The most important options:

```php
// config/sent.php

'default' => env('SENT_CONNECTION', 'default'),

'connections' => [
    'default' => [
        'api_key' => env('SENT_API_KEY'),
    ],
],

'default_channel' => env('SENT_DEFAULT_CHANNEL'), // null = auto-route

'queue' => [
    'connection' => env('SENT_QUEUE_CONNECTION'),
    'name'       => env('SENT_QUEUE_NAME', 'default'),
],

'webhook' => [
    'enabled' => env('SENT_WEBHOOK_ENABLED', false),
    'secret'  => env('SENT_WEBHOOK_SECRET'),          // whsec_... from Sent.dm
    'path'    => env('SENT_WEBHOOK_PATH', 'sent/webhook'),
],

'cache' => [
    'enabled' => env('SENT_CACHE_ENABLED', true),
    'ttl'     => env('SENT_CACHE_TTL', 3600),
],

'sandbox' => env('SENT_SANDBOX', false),
```

---

## Sending messages

### Basic send

```php
use Sujip\SentDm\Facades\Sent;

Sent::to('+61412345678')
    ->template('otp-verification')
    ->send();
```

Sent.dm auto-routes to WhatsApp if the recipient has it, otherwise falls back to SMS. To force a specific channel:

```php
Sent::to('+61412345678')
    ->template('otp-verification')
    ->channel('sms')      // or 'whatsapp', 'rcs'
    ->send();
```

### Template variables

Pass dynamic values into the template:

```php
Sent::to('+61412345678')
    ->template('otp-verification')
    ->with(['code' => '123456', 'expiry' => '10 minutes'])
    ->send();
```

### Idempotency

Prevent duplicate sends if your app retries the request:

```php
Sent::to('+61412345678')
    ->template('order-confirmation')
    ->idempotencyKey("order-{$order->id}")
    ->send();
```

### Profile override

When your Sent.dm account has multiple profiles, specify which one to use per message:

```php
Sent::to('+61412345678')
    ->template('promo')
    ->usingProfile('profile_abc123')
    ->send();
```

---

## Queued sends

Use `sendLater()` instead of `send()` to push the send onto a queue. The request returns immediately and Laravel processes the message in the background.

```php
Sent::to('+61412345678')
    ->template('welcome')
    ->sendLater();
```

Configure which queue and connection to use in `config/sent.php`:

```env
SENT_QUEUE_CONNECTION=redis
SENT_QUEUE_NAME=messages
```

The job retries up to 3 times with exponential backoff. If the API returns a rate limit (HTTP 429), the job re-queues itself after the delay the API specifies rather than a fixed wait.

On success a `MessageSent` event is dispatched. On final failure a `MessageFailed` event is dispatched.

---

## Bulk messaging

Send the same message to a large list of recipients. Each recipient is dispatched as an individual queued job, so rate limits and failures are handled per-recipient.

```php
use Sujip\SentDm\Facades\Sent;

$phoneNumbers = ['+61412345678', '+61498765432', ...];

Sent::bulk($phoneNumbers)
    ->template('flash-sale')
    ->with(['discount' => '20%'])
    ->dispatch();
```

You can also force a channel or profile for the whole batch:

```php
Sent::bulk($phoneNumbers)
    ->template('flash-sale')
    ->channel('sms')
    ->usingProfile('profile_abc123')
    ->dispatch();
```

---

## Notification channel

Use the Sent channel inside any Laravel notification. Add `via(SentChannel::class)` and implement `toSent()` on the notification class.

```php
use Illuminate\Notifications\Notification;
use Sujip\SentDm\Channels\SentChannel;
use Sujip\SentDm\Contracts\ProvidesSentMessage;
use Sujip\SentDm\Messages\SentMessage;

class OrderShippedNotification extends Notification implements ProvidesSentMessage
{
    public function via(mixed $notifiable): array
    {
        return [SentChannel::class];
    }

    public function toSent(mixed $notifiable): SentMessage
    {
        return SentMessage::create()
            ->template('order-shipped')
            ->with(['tracking' => $this->order->tracking_number]);
    }
}
```

The channel reads the recipient from `routeNotificationFor('sent')` on the notifiable model. Add the `HasSentContact` trait to any model that has a `phone` attribute:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Sujip\SentDm\Concerns\HasSentContact;

class User extends Model
{
    use Notifiable, HasSentContact;
}
```

`HasSentContact` wires up `routeNotificationFor('sent')` to return `$this->phone`. If your phone column has a different name, override the method on the model:

```php
public function routeNotificationForSent(Notification $notification): string
{
    return $this->mobile_number;
}
```

Send the notification:

```php
$user->notify(new OrderShippedNotification($order));
```

---

## Sandbox mode

Turn on sandbox mode to simulate sends without any real delivery. Sent.dm processes the request server-side and returns a real-shaped response — events still fire, queued jobs still run, nothing changes in your app code.

Enable globally in `.env`:

```env
SENT_SANDBOX=true
```

Or on a per-message basis:

```php
Sent::to('+61412345678')
    ->template('otp-verification')
    ->sandbox()
    ->send();
```

---

## Webhooks

Sent.dm can notify your app when a message is delivered, read, or fails. The webhook route is opt-in — it only registers when you enable it in config.

### Enable the webhook route

```env
SENT_WEBHOOK_ENABLED=true
SENT_WEBHOOK_SECRET=whsec_...   # from Sent.dm dashboard
SENT_WEBHOOK_PATH=sent/webhook  # optional, this is the default
```

### Register the webhook with Sent.dm

```bash
php artisan sent:setup-webhook https://yourapp.com/sent/webhook
```

This creates the endpoint on the Sent.dm platform and prints the signing secret to add to your `.env`.

To subscribe to specific events only:

```bash
php artisan sent:setup-webhook https://yourapp.com/sent/webhook \
    --events=message.delivered \
    --events=message.failed
```

### Listen to webhook events

Register listeners in `AppServiceProvider` or `EventServiceProvider`:

```php
use Illuminate\Support\Facades\Event;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageReceived;

Event::listen(MessageDelivered::class, function (MessageDelivered $event) {
    $messageId = $event->payload->messageId();
    $channel   = $event->payload->channel();
    $recipient = $event->payload->recipient();

    // update your database, etc.
});

Event::listen(MessageFailed::class, function (MessageFailed $event) {
    // handle failure
});

Event::listen(MessageReceived::class, function (MessageReceived $event) {
    // inbound message — $event->payload->text(), ->sender(), ->recipient()
});
```

### All webhook events

| Event class | Triggered when |
|---|---|
| `MessageQueued` | Sent.dm accepted the message |
| `MessageRouted` | Channel selected for delivery |
| `MessageSent` | Dispatched to the carrier |
| `MessageDelivered` | Confirmed delivered to the handset |
| `MessageRead` | Recipient opened the message (WhatsApp) |
| `MessageFailed` | Delivery failed permanently |
| `MessageReceived` | Inbound message received from a recipient |

Every event carries a `WebhookPayload` with these accessors:

```php
$event->payload->messageId();   // Sent.dm message ID
$event->payload->status();      // message status string
$event->payload->channel();     // sms, whatsapp, rcs
$event->payload->recipient();   // E.164 recipient number
$event->payload->sender();      // E.164 sender number
$event->payload->templateId();  // template used, if any
$event->payload->text();        // inbound text (message.received only)
$event->payload->subType;       // the raw sub_type string
$event->payload->timestamp;     // ISO 8601 timestamp
```

### How signature verification works

The `VerifySignature` middleware runs before the controller. It reads `x-webhook-signature`, `x-webhook-id`, and `x-webhook-timestamp` from the request, recomputes the HMAC-SHA256 over `{webhook_id}.{timestamp}.{raw_body}`, and rejects requests that don't match or are older than 5 minutes. Webhook events are also deduplicated by message ID + event type, so retried deliveries are safe.

---

## Number lookup

Look up carrier information for any phone number:

```php
$result = Sent::lookup('+61412345678');

$result->data->isValid;       // bool
$result->data->carrierName;   // 'Telstra'
$result->data->lineType;      // 'mobile', 'landline', 'voip'
$result->data->isVoip;        // bool
$result->data->isPorted;      // bool
$result->data->countryCode;   // 'AU'
```

From the command line:

```bash
php artisan sent:lookup +61412345678
```

Results are cached per number for the TTL set in `config/sent.php`.

---

## Phone number validation

Use `Rule::sentMobileNumber()` in any form request to validate an E.164 phone number. The rule checks the format first, then optionally calls the number lookup API to confirm the number is real and reachable.

```php
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', Rule::sentMobileNumber()],
        ];
    }
}
```

Require a mobile line (reject landlines and VoIP):

```php
'phone' => ['required', Rule::sentMobileNumber(requireMobile: true)],
```

If the Sent.dm API is unreachable the rule passes the number through rather than blocking valid form submissions.

---

## Multi-tenant connections

Define multiple Sent.dm API keys in `config/sent.php` — one per tenant, environment, or business unit:

```php
'connections' => [
    'default' => [
        'api_key' => env('SENT_API_KEY'),
    ],
    'acme' => [
        'api_key' => env('SENT_ACME_API_KEY'),
    ],
    'globex' => [
        'api_key' => env('SENT_GLOBEX_API_KEY'),
    ],
],
```

Switch connections at runtime:

```php
// send via the default connection
Sent::to('+61412345678')->template('otp')->send();

// send via a named connection
Sent::connection('acme')->to('+61412345678')->template('otp')->send();

// bulk via a named connection
Sent::connection('acme')->bulk($numbers)->template('promo')->dispatch();
```

---

## Contacts

```php
// list contacts — chainable query builder
Sent::contacts()->get();
Sent::contacts()->search('John')->channel('whatsapp')->page(2)->perPage(25)->get();

// get a single contact (result is cached)
Sent::contacts()->find('contact_id');

// create a contact
Sent::contacts()->create()->phone('+61412345678')->save();
Sent::contacts()->create()->phone('+61412345678')->defaultChannel('sms')->save();

// update a contact
Sent::contacts()->update('contact_id')->defaultChannel('whatsapp')->save();
Sent::contacts()->update('contact_id')->optOut(true)->save();

// delete a contact
Sent::contacts()->delete('contact_id');
```

---

## Templates

```php
// list templates — chainable query builder
Sent::templates()->get();
Sent::templates()->page(2)->perPage(25)->get();

// get by ID (result is cached)
Sent::templates()->find('template_id');

// find by name (result is cached)
Sent::templates()->findByName('otp-verification');

// delete a template
Sent::templates()->delete('template_id');
```

From the command line:

```bash
php artisan sent:templates
php artisan sent:templates --page=2 --per-page=25
```

---

## Profiles

```php
// list all profiles (result is cached)
Sent::profiles()->get();

// get a single profile
Sent::profiles()->find('profile_id');

// delete a profile
Sent::profiles()->delete('profile_id');
```

---

## Users

```php
// list all users
Sent::users()->get();

// get a single user
Sent::users()->find('user_id');

// invite a new user
Sent::users()->invite()->email('alice@example.com')->name('Alice')->role('member')->save();

// remove a user
Sent::users()->remove('user_id');
```

---

## Webhooks API

Beyond the webhook route for incoming events, you can manage webhook endpoints from code:

```php
// list webhooks
Sent::webhooks()->get();
Sent::webhooks()->page(2)->perPage(10)->get();

// get a single webhook
Sent::webhooks()->find('webhook_id');

// create a webhook
Sent::webhooks()->create()
    ->url('https://yourapp.com/sent/webhook')
    ->events(['message.delivered', 'message.failed'])
    ->save();

// update a webhook
Sent::webhooks()->update('webhook_id')
    ->url('https://yourapp.com/new-path')
    ->save();

// enable / disable
Sent::webhooks()->enable('webhook_id');
Sent::webhooks()->disable('webhook_id');

// rotate the signing secret (returns a new whsec_... value)
Sent::webhooks()->rotateSecret('webhook_id');

// delete a webhook
Sent::webhooks()->delete('webhook_id');
```

---

## Account

```php
$account = Sent::account();

$account->data->type;    // 'organization', 'user', or 'profile'
$account->data->name;    // account name
$account->data->email;   // account email
$account->data->channels->sms->configured;       // bool
$account->data->channels->whatsapp->configured;  // bool
```

---

## Testing

Use `Sent::fake()` at the start of any test. It replaces the real driver with an in-memory recorder and gives you assertion methods to inspect what was sent or queued.

```php
use Sujip\SentDm\Facades\Sent;

beforeEach(fn () => Sent::fake());

it('sends a welcome message', function () {
    $user = User::factory()->create(['phone' => '+61412345678']);

    $user->sendWelcomeMessage();

    Sent::assertSentTo('+61412345678');
    Sent::assertSentCount(1);
});
```

### Sent assertions

```php
// assert a specific recipient received a message
Sent::assertSentTo('+61412345678');

// assert with a callback for deeper inspection
Sent::assertSentTo('+61412345678', function (SentMessage $message) {
    return $message->getTemplateName() === 'welcome';
});

// assert a template was used
Sent::assertSentWithTemplate('welcome');

// assert with callback
Sent::assertSentWithTemplate('otp', function (SentMessage $message) {
    return $message->getTemplateData()['code'] === '123456';
});

// assert using a custom callback on any field
Sent::assertSent(function (SentMessage $message) {
    return $message->getChannel() === 'sms';
});

// count and negative assertions
Sent::assertSentCount(3);
Sent::assertNothingSent();
```

### Queued assertions

```php
// assert a message was queued (via sendLater)
Sent::assertQueuedTo('+61412345678');

Sent::assertQueuedTo('+61412345678', function (SentMessage $message) {
    return $message->getTemplateName() === 'order-shipped';
});

Sent::assertQueued(function (SentMessage $message) {
    return $message->getChannel() === 'whatsapp';
});

Sent::assertQueuedCount(2);
Sent::assertNothingQueued();
```

### Multi-tenant assertions

```php
Sent::assertSentViaConnection('acme');

Sent::assertSentViaConnection('acme', function (SentMessage $message) {
    return $message->getRecipient() === '+61412345678';
});

Sent::assertQueuedViaConnection('globex');
```

### Introspection

```php
// access all recorded messages
$sent   = Sent::sent();    // list<SentMessage>
$queued = Sent::queued();  // list<SentMessage>

Sent::hasSent();    // bool
Sent::hasQueued();  // bool
Sent::reset();      // clear all records
```

---

## Artisan commands

| Command | Description |
|---|---|
| `sent:install` | Publish `config/sent.php` |
| `sent:health` | Check API connectivity and account status |
| `sent:test-send {number} --template=` | Send a test message to a number |
| `sent:templates` | List templates in a table |
| `sent:lookup {number}` | Carrier lookup for a phone number |
| `sent:setup-webhook {url}` | Create a webhook endpoint on Sent.dm |

All commands accept `--connection=` to target a specific named connection.

```bash
# check health for a named connection
php artisan sent:health --connection=acme

# send a test message (with sandbox so no real delivery)
php artisan sent:test-send +61412345678 --template=otp --sandbox

# look up a number via a specific connection
php artisan sent:lookup +61412345678 --connection=acme

# set up webhook and subscribe to specific events only
php artisan sent:setup-webhook https://yourapp.com/sent/webhook \
    --events=message.delivered \
    --events=message.failed
```

---

---

## Message log

When enabled, every outbound message is written to a local `sent_logs` table and delivery status is kept in sync automatically as webhook events arrive.

### Setup

Publish the migrations:

```bash
php artisan vendor:publish --tag=laravel-sent-migrations
php artisan migrate
```

Enable in `.env`:

```env
SENT_LOGGING_ENABLED=true
```

### Log messages linked to a model

Use `->for($model)` on the message builder to associate a log entry with any Eloquent model:

```php
Sent::to('+61412345678')
    ->template('order-shipped')
    ->for($order)
    ->sendLater();
```

### Add `HasSentMessages` to your model

```php
use Sujip\SentDm\Concerns\HasSentMessages;

class User extends Model
{
    use HasSentMessages;
}
```

Then query message history directly from the model:

```php
$user->sentMessages()->get();
$user->sentMessages()->where('channel', 'whatsapp')->get();
$user->lastSentMessage();
$user->sentMessagesWithStatus(SentLogStatus::Delivered)->get();
```

### Status sync

Delivery events arriving via webhook update the log automatically — no extra code needed. Status values follow `SentLogStatus`:

| Status | When |
|---|---|
| `queued` | Message accepted by the job |
| `sent` | Dispatched to the carrier |
| `delivered` | Confirmed delivered to the handset |
| `failed` | Delivery failed permanently |
| `read` | Recipient opened the message (WhatsApp) |
| `received` | Inbound message from a recipient |

---

## Opt-out management

When enabled, inbound STOP/UNSUBSCRIBE replies are automatically recorded in `sent_opt_outs`. You can also block outbound messages to opted-out contacts.

### Setup

Publish the migrations (same command as above if not already done):

```bash
php artisan vendor:publish --tag=laravel-sent-migrations
php artisan migrate
```

Enable opt-out tracking and optionally the send guard in `.env`:

```env
SENT_OPT_OUT_ENABLED=true   # record STOP/UNSTOP from inbound messages
SENT_OPT_OUT_GUARD=true     # throw ContactOptedOutException if opted out
```

### Opt-out methods on your model

`HasSentContact` automatically gets opt-out management:

```php
use Sujip\SentDm\Concerns\HasSentContact;

class User extends Model
{
    use HasSentContact;
}
```

```php
$user->optedOutFromSent();           // bool — check consent
$user->optOutFromSent();             // mark opted out (manual)
$user->optOutFromSent('campaign_x'); // with a reason
$user->optInToSent();                // re-enable messaging
```

### Inbound keyword handling

When `SENT_OPT_OUT_ENABLED=true`, these inbound keywords are handled automatically:

| Keyword | Effect |
|---|---|
| STOP, UNSUBSCRIBE, CANCEL, END, QUIT | Opt out — blocks outbound if guard is enabled |
| START, YES, UNSTOP | Opt in — removes the block |

### Send guard

When `SENT_OPT_OUT_GUARD=true`, calling `send()` or `sendLater()` for an opted-out number throws `ContactOptedOutException`:

```php
use Sujip\SentDm\Exceptions\ContactOptedOutException;

try {
    Sent::to($user->phone)->template('promo')->send();
} catch (ContactOptedOutException $e) {
    // $e->phoneNumber — the number that is opted out
}
```

---

## License

This package is open source, licensed under the [MIT license](LICENSE.md).
