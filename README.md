<p align="center">
  <img src="art/banner.svg" alt="laravel-sent-dm" width="100%">
</p>

[![Tests](https://github.com/sudiptpa/laravel-sent-dm/actions/workflows/ci.yml/badge.svg)](https://github.com/sudiptpa/laravel-sent-dm/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/sudiptpa/laravel-sent-dm/v/stable)](https://packagist.org/packages/sudiptpa/laravel-sent-dm)
[![Total Downloads](https://poser.pugx.org/sudiptpa/laravel-sent-dm/downloads)](https://packagist.org/packages/sudiptpa/laravel-sent-dm)
[![License](https://poser.pugx.org/sudiptpa/laravel-sent-dm/license)](https://packagist.org/packages/sudiptpa/laravel-sent-dm)

A Laravel package for [Sent.dm](https://sent.dm), the unified messaging API for SMS, WhatsApp, and RCS.

This package wraps the official [sentdm/sent-dm-php](https://github.com/sentdm/sent-dm-php) SDK with a full Laravel integration layer: queued sends, notification channels, webhook handling, message logging, opt-out management, multi-tenancy, and a complete testing suite. All HTTP transport is handled by the official SDK. This package adds the Laravel idioms on top.

---

## What this package handles

These things are wired up for you and work out of the box:

- **Queue-backed sends**: every message goes through a Laravel job; the request cycle never blocks
- **Auto-channel routing**: Sent.dm picks WhatsApp or SMS based on the recipient's reachability
- **Webhook signature verification**: HMAC-SHA256 checked at middleware level before your code runs
- **Idempotent deduplication**: webhook events are deduplicated so retried deliveries don't fire your listeners twice
- **Rate limit handling**: 429 responses re-queue the job with the API's `Retry-After` delay, not a fixed wait
- **Caching**: contacts, templates, profiles, and number lookups are cached per-key with tag-based invalidation
- **Multi-tenancy**: same driver pattern as `Mail` and `Cache`; switch accounts per request with `Sent::connection()`
- **Organization profile scoping**: scope any resource call to one child profile of an organization key with `->profile($id)`
- **Message log**: opt-in DB table that records every send and auto-syncs delivery status from webhooks
- **Opt-out compliance**: STOP/UNSTOP keywords handled automatically; guard blocks sends to opted-out numbers
- **Testing**: `Sent::fake()` with full assertions so you never make real API calls in tests

## What stays in your application

These things belong in your app, not in the package:

- Deciding **when** to send a message: that's business logic
- **Template content**: created and managed in the Sent.dm dashboard
- **Campaign scheduling**: use Laravel's `schedule()` to dispatch bulk sends on a cron
- **Analytics UI**: build your own dashboard using `$user->sentMessages()` data
- **Contact import**: sync from your DB using `Sent::contacts()->create()` in a job or command
- **Custom retry strategies**: listen to `MessageFailed` and re-dispatch with your own logic
- **Per-user notification preferences**: check `$user->optedOutFromSent()` before sending

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

The published config is at `config/sent.php`:

```php
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
    'secret'  => env('SENT_WEBHOOK_SECRET'),
    'path'    => env('SENT_WEBHOOK_PATH', 'sent/webhook'),
],

'cache' => [
    'enabled' => env('SENT_CACHE_ENABLED', true),
    'ttl'     => env('SENT_CACHE_TTL', 3600),
],

'sandbox' => env('SENT_SANDBOX', false),

'logging' => [
    'enabled' => env('SENT_LOGGING_ENABLED', false),
],

'opt_out' => [
    'enabled' => env('SENT_OPT_OUT_ENABLED', false),
    'guard'   => env('SENT_OPT_OUT_GUARD', false),
],
```

---

## Sending messages

### Immediate send

```php
use Sujip\SentDm\Facades\Sent;

Sent::to('+61412345678')
    ->template('otp-verification')
    ->send();
```

Templates are pre-approved in the Sent.dm dashboard. For a plain-text body instead, use `message()` in place of `template()`:

```php
Sent::to('+61412345678')
    ->message('Your table is ready.')
    ->send();
```

If both `template()` and `message()` are set on the same send, the template wins.

Sent.dm auto-routes to WhatsApp if the recipient has it, otherwise falls back to SMS. To force a specific channel:

```php
Sent::to('+61412345678')
    ->template('otp-verification')
    ->channel('sms')      // or 'whatsapp', 'rcs'
    ->send();
```

Pass an array to send on more than one channel at once, one separately-tracked message per channel:

```php
Sent::to('+61412345678')
    ->template('otp-verification')
    ->channel(['sms', 'whatsapp'])
    ->send();
```

### Template variables

```php
Sent::to('+61412345678')
    ->template('otp-verification')
    ->with(['code' => '123456', 'expiry' => '10 minutes'])
    ->send();
```

### Idempotency

Prevent duplicates if your app retries the same operation. A retry with the same key
within 24 hours returns the original response instead of creating a second record.
Available on every method that creates or changes something, not just sends:

```php
Sent::to('+61412345678')
    ->template('order-confirmation')
    ->idempotencyKey("order-{$order->id}")
    ->send();

Sent::webhooks()->create()
    ->name('Order events')
    ->url('https://yourapp.com/webhook')
    ->events(['message'])
    ->idempotencyKey("webhook-{$order->id}")
    ->save();

Sent::contacts()->create()
    ->phone('+61412345678')
    ->idempotencyKey('import-batch-42-row-7')
    ->save();
```

Keys are 1-255 alphanumeric characters, hyphens, or underscores. Never reuse a key for
a different operation. Sent.dm doesn't compare the request body on replay, it just
returns whatever the first call with that key returned.

### Profile override

When your Sent.dm account has multiple profiles, target one per message:

```php
Sent::to('+61412345678')
    ->template('promo')
    ->usingProfile('profile_abc123')
    ->send();
```

### Sandbox mode (per message)

Simulate a send without real delivery, useful in staging:

```php
Sent::to('+61412345678')
    ->template('otp-verification')
    ->sandbox()
    ->send();
```

---

## Queued sends

Use `sendLater()` instead of `send()`. The request returns immediately; Laravel processes it in the background.

```php
Sent::to('+61412345678')
    ->template('welcome')
    ->sendLater();
```

Configure which queue to use:

```env
SENT_QUEUE_CONNECTION=redis
SENT_QUEUE_NAME=messages
```

The job retries up to 3 times with exponential backoff. If the API returns a 429, the job re-queues itself after the `Retry-After` delay the API provides.

### App-level pattern: send on model event

```php
// app/Observers/UserObserver.php
class UserObserver
{
    public function created(User $user): void
    {
        Sent::to($user->phone)
            ->template('welcome')
            ->for($user)
            ->sendLater();
    }
}
```

### App-level pattern: listen to the result

```php
// app/Listeners/HandleMessageSent.php
use Sujip\SentDm\Events\MessageSent;

class HandleMessageSent
{
    public function handle(MessageSent $event): void
    {
        if ($event->message !== null) {
            // job context: $event->message is the SentMessage
            // $event->connectionName is the Sent.dm connection used
        }
    }
}
```

---

## Bulk messaging

Send the same message to a large list. Each recipient is dispatched as an individual queued job, so failures and rate limits are handled per-recipient.

```php
$numbers = ['+61412345678', '+61498765432'];

Sent::bulk($numbers)
    ->template('flash-sale')
    ->with(['discount' => '20%'])
    ->dispatch();
```

Force a channel or profile for the whole batch:

```php
Sent::bulk($numbers)
    ->template('flash-sale')
    ->channel('sms')
    ->usingProfile('profile_abc123')
    ->dispatch();
```

### App-level pattern: scheduled campaign

```php
// app/Console/Kernel.php (or routes/console.php in Laravel 11+)
Schedule::call(function () {
    $numbers = User::subscribed()->pluck('phone')->all();

    Sent::bulk($numbers)
        ->template('weekly-digest')
        ->dispatch();
})->weekly();
```

---

## Notification channel

Use the Sent channel in any Laravel notification. Implement `ProvidesSentMessage` and add `toSent()`:

```php
use Illuminate\Notifications\Notification;
use Sujip\SentDm\Channels\SentChannel;
use Sujip\SentDm\Contracts\ProvidesSentMessage;
use Sujip\SentDm\Messages\SentMessage;

class OrderShippedNotification extends Notification implements ProvidesSentMessage
{
    public function __construct(private Order $order) {}

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

Add `HasSentContact` to any model that has a `phone` attribute:

```php
use Sujip\SentDm\Concerns\HasSentContact;

class User extends Model
{
    use Notifiable, HasSentContact;
}
```

Send the notification:

```php
$user->notify(new OrderShippedNotification($order));
```

### App-level pattern: skip opted-out users

```php
public function via(mixed $notifiable): array
{
    if ($notifiable->optedOutFromSent()) {
        return [];
    }

    return [SentChannel::class];
}
```

### Customising the phone column

If your phone column isn't called `phone`, override `sentPhoneNumber()`:

```php
class User extends Model
{
    use HasSentContact;

    protected function sentPhoneNumber(): string
    {
        return (string) ($this->mobile_number ?? '');
    }
}
```

---

## Sandbox mode

Sent.dm accepts a `sandbox: true` field on almost every write call (create, update,
delete). The request is still validated for real, so a malformed payload still 400s.
But nothing is persisted, sent, or billed, and the response carries an
`X-Sandbox: true` header. Sandbox mode doesn't check that referenced records exist: a
sandboxed call against a made-up id still succeeds, so a follow-up read against that id
won't find anything real.

Builders expose it as a chained method:

```php
Sent::webhooks()->create()->name('Test')->url('https://example.com')->events(['message'])->sandbox(true)->save();
Sent::senderProfiles()->create()->name('Test')->shortName('TST')->sandbox(true)->save();
```

Direct-call methods take it as a parameter:

```php
Sent::users()->updateRole('user-id', 'admin', sandbox: true);
Sent::webhooks()->test('webhook-id', 'message.sent', sandbox: true);
```

`Channels` and `SenderProfiles::submit()`-based calls take it as an array key instead, since they build the request body directly:

```php
Sent::channels()->addRcs(['brand_name' => '...', 'privacy_policy_url' => '...', 'terms_and_conditions_url' => '...', 'sandbox' => true]);
```

Two operations don't support it at all. This is Sent.dm's own design, not a limitation
of this package: `Webhooks::delete()` and `SenderProfiles::delete()` always delete for
real, no matter what `sandbox` is set to, because neither endpoint takes a request body
in the first place.

There's also a global `SENT_SANDBOX=true` env setting. It puts every write call this
package makes into sandbox mode by default, not just message sends. Use it for a whole
local or staging environment instead of passing `sandbox()`/`sandbox: true` on every
call. An explicit `sandbox(false)` (or `sandbox: false`) on a specific call always wins
over the global default.

---

## Organization profile scoping

An organization API key manages several child profiles. Scope any call to run as one of them by chaining `profile()` before it:

```php
Sent::contacts()->profile('child-profile-id')->get();
Sent::templates()->profile('child-profile-id')->find('tpl_123');
Sent::channels()->profile('child-profile-id')->addWhatsapp([...]);
```

This sends the `x-profile-id` header Sent.dm uses to scope a call to one child profile. Every resource accepts it except `SenderProfiles` itself, which has nothing to scope into. It works with a standard API key, not only an organization-tier one.

`profile()` is chainable and returns a new instance, so it composes with pagination and search the same way `page()` and `search()` do:

```php
Sent::contacts()->profile('child-profile-id')->search('555-0142')->get();
```

---

## Webhooks

Sent.dm POSTs events to your app when messages are delivered, read, or fail. The webhook route is opt-in.

### Enable the webhook route

```env
SENT_WEBHOOK_ENABLED=true
SENT_WEBHOOK_SECRET=whsec_...
SENT_WEBHOOK_PATH=sent/webhook
```

### Register the endpoint with Sent.dm

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

### Listen to webhook events

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

### All webhook events

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

### How signature verification works

The `VerifySignature` middleware runs before your controller. It reads `x-webhook-signature`, `x-webhook-id`, and `x-webhook-timestamp`, recomputes HMAC-SHA256 over `{webhook_id}.{timestamp}.{raw_body}`, and rejects requests that don't match or are older than 5 minutes. Duplicate events are deduplicated by message ID + event type, so retried deliveries are safe.

---

## Message log

The message log keeps a local record of every outbound message and syncs delivery status automatically from webhooks. Everything is opt-in, so nothing writes to your database unless you enable it.

### Setup

Publish the migrations and enable logging:

```bash
php artisan vendor:publish --tag=laravel-sent-migrations
php artisan migrate
```

```env
SENT_LOGGING_ENABLED=true
```

### Associate messages with a model

Use `->for($model)` on any message to bind the log entry to an Eloquent model:

```php
Sent::to($user->phone)
    ->template('order-shipped')
    ->with(['tracking' => $order->tracking])
    ->for($user)
    ->sendLater();
```

### HasSentMessages trait

Add to any model to query message history:

```php
use Sujip\SentDm\Concerns\HasSentMessages;

class User extends Model
{
    use HasSentMessages;
}
```

```php
// all messages sent to this user
$user->sentMessages()->latest()->get();

// filter by delivery status
$user->sentMessagesWithStatus(SentLogStatus::Delivered)->count();
$user->sentMessagesWithStatus(SentLogStatus::Failed)->get();

// most recent
$user->lastSentMessage();
```

### Querying the log: SentLog scopes

`SentLog` ships with composable query scopes for app-level analytics. Combine them freely:

```php
use Sujip\SentDm\Models\SentLog;
use Sujip\SentDm\Enums\SentLogStatus;

// count by status across all logs
SentLog::groupByStatus()->get();
// → collection of rows with ->status and ->total

// per-connection breakdown (multi-tenant)
SentLog::forConnection('acme')->groupByStatus()->get();

// last 7 days, WhatsApp only
SentLog::whereSentBetween(now()->subDays(7), now())
    ->forChannel('whatsapp')
    ->groupByStatus()
    ->get();

// all delivered messages for a specific template
SentLog::forTemplate('order-shipped')
    ->forStatus(SentLogStatus::Delivered)
    ->count();

// history for a single recipient
SentLog::forRecipient('+61412345678')->latest()->get();

// compose all filters together
SentLog::forConnection('acme')
    ->forChannel('sms')
    ->forTemplate('otp')
    ->whereSentBetween(now()->startOfMonth(), now()->endOfMonth())
    ->groupByStatus()
    ->get();
```

| Scope | Description |
|---|---|
| `forConnection(string)` | Filter by Sent.dm connection name |
| `forChannel(string)` | Filter by channel (`sms`, `whatsapp`, `rcs`) |
| `forTemplate(string)` | Filter by template name |
| `forStatus(SentLogStatus\|string)` | Filter by delivery status |
| `forRecipient(string)` | Filter by recipient phone number |
| `whereSentBetween($from, $to)` | Filter by `created_at` date range |
| `groupByStatus()` | Aggregate: adds `SELECT status, COUNT(*) as total GROUP BY status` |

The `sent:stats` command uses these same scopes internally. For scheduled reports, per-tenant dashboards, or custom analytics, query `SentLog` directly.

### Status progression

The log is created with status `queued` when the job fires, then updated automatically as webhook events arrive:

```
queued → sent → delivered
                   ↓
                  read
              (WhatsApp only)

queued → sent → failed

queued → filtered   (blocked by a routing or consent rule, never reached a carrier)
queued → blocked    (blocked by an account condition, template, or no open conversation)
queued → scheduled → sent → ...   (deferred to a later window, then continues normally)
```

### App-level pattern: show message history

```php
// In a controller or Livewire component:
$messages = $user->sentMessages()
    ->latest()
    ->paginate(20);
```

### App-level pattern: retry failed messages

```php
use Sujip\SentDm\Events\MessageFailed;

Event::listen(MessageFailed::class, function (MessageFailed $event) {
    if ($event->message === null) {
        return; // webhook context: no SentMessage to re-dispatch
    }

    // re-queue once with a different template
    Sent::to($event->message->getRecipient())
        ->template('delivery-fallback')
        ->sendLater();
});
```

### SentLogStatus enum

```php
use Sujip\SentDm\Enums\SentLogStatus;

SentLogStatus::Queued
SentLogStatus::Sent
SentLogStatus::Delivered
SentLogStatus::Failed
SentLogStatus::Read
SentLogStatus::Filtered
SentLogStatus::Blocked
SentLogStatus::Scheduled
```

> **Inbound messages** (`message.received` webhook events) do not create a `sent_logs` record. The log only tracks outbound messages sent through this package.

---

## Opt-out management

The opt-out layer tracks per-number consent, handles STOP keywords automatically, and can block outbound messages to opted-out numbers. All opt-in, nothing enabled by default.

### Setup

Publish the migrations (same command as above if already done) and enable:

```bash
php artisan vendor:publish --tag=laravel-sent-migrations
php artisan migrate
```

```env
SENT_OPT_OUT_ENABLED=true   # record STOP/UNSTOP from inbound messages
SENT_OPT_OUT_GUARD=true     # block sends to opted-out numbers
```

### Inbound keyword handling

When `SENT_OPT_OUT_ENABLED=true`, these inbound keywords are handled automatically:

| Keyword | Effect |
|---|---|
| `STOP` `UNSUBSCRIBE` `CANCEL` `END` `QUIT` | Contact is marked opted-out |
| `START` `YES` `UNSTOP` | Contact is marked opted-in |

No code needed. The `ProcessInboundOptOut` listener fires on every `MessageReceived` event and updates `sent_opt_outs`.

### HasSentContact opt-out methods

`HasSentContact` includes opt-out management. Any model using the trait gets:

```php
// check before sending
if ($user->optedOutFromSent()) {
    return;
}

// record a manual opt-out (e.g. from a settings page)
$user->optOutFromSent();
$user->optOutFromSent('user-requested'); // with a reason

// re-enable messaging
$user->optInToSent();
```

### Send guard

When `SENT_OPT_OUT_GUARD=true`, `send()` and `sendLater()` throw `ContactOptedOutException` if the recipient has opted out. Catch it where it matters:

```php
use Sujip\SentDm\Exceptions\ContactOptedOutException;

try {
    Sent::to($user->phone)->template('promo')->send();
} catch (ContactOptedOutException $e) {
    Log::info("Skipped send to opted-out number: {$e->phoneNumber}");
}
```

### App-level pattern: settings page

```php
// routes/web.php
Route::post('/settings/messaging/opt-out', function (Request $request) {
    $request->user()->optOutFromSent();

    return back()->with('status', 'You have opted out of SMS messages.');
});

Route::post('/settings/messaging/opt-in', function (Request $request) {
    $request->user()->optInToSent();

    return back()->with('status', 'SMS messaging re-enabled.');
});
```

### App-level pattern: check before notification

```php
public function via(mixed $notifiable): array
{
    if (method_exists($notifiable, 'optedOutFromSent') && $notifiable->optedOutFromSent()) {
        return [];
    }

    return [SentChannel::class];
}
```

---

## Number lookup

Look up carrier information for any phone number. Results are cached:

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

---

## Phone number validation

Validate E.164 format and optionally verify the number against the Sent.dm lookup API. Fails open if the API is unreachable, so a network blip never blocks a valid form submission.

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

---

## Multi-tenant connections

This is for separate Sent.dm accounts, each with its own API key. If you instead have one
organization account with several child profiles under it, see
[Organization profile scoping](#organization-profile-scoping) further up, that uses one API
key with `profile()`, not a config entry per tenant.

Define one connection per Sent.dm API key in `config/sent.php`:

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

Switch at runtime:

```php
// send via the default connection
Sent::to('+61412345678')->template('otp')->send();

// send via a named connection
Sent::connection('acme')->to('+61412345678')->template('otp')->send();

// bulk via a named connection
Sent::connection('acme')->bulk($numbers)->template('promo')->dispatch();
```

### App-level pattern: resolve connection from the authenticated tenant

```php
// app/Http/Middleware/ResolveSentConnection.php
class ResolveSentConnection
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = $request->user()?->tenant;

        if ($tenant) {
            // the connection key matches the tenant slug configured in sent.php
            app()->instance('sent.connection', $tenant->slug);
        }

        return $next($request);
    }
}

// Usage anywhere in the app
$connection = app('sent.connection', 'default');
Sent::connection($connection)->to($user->phone)->template('otp')->send();
```

### App-level pattern: custom driver

Register a completely custom driver if you need to override how the SDK client is built:

```php
// app/Providers/AppServiceProvider.php
use Sujip\SentDm\SentManager;

app(SentManager::class)->extend('custom', function () {
    return new \Sujip\SentDm\Sent(
        client: new \SentDm\Client(apiKey: 'custom-key'),
    );
});
```

---

## Contacts API

```php
// list: chainable query builder
Sent::contacts()->get();
// search matches the contact's national-format phone number exactly, including
// punctuation (e.g. "555-0142", not "5550142"). Contacts have no name field.
Sent::contacts()->search('555-0142')->channel('whatsapp')->page(2)->perPage(25)->get();

// read (cached)
Sent::contacts()->find('contact_id');

// create
Sent::contacts()->create()->phone('+61412345678')->save();
Sent::contacts()->create()->phone('+61412345678')->defaultChannel('sms')->save();

// update (invalidates cache)
Sent::contacts()->update('contact_id')->defaultChannel('whatsapp')->save();
Sent::contacts()->update('contact_id')->optOut(true)->save();

// delete (invalidates cache)
Sent::contacts()->delete('contact_id');

// message summary (cached): count, first/last message timestamps, channels used
$summary = Sent::contacts()->messageSummary('contact_id');
$summary->data->messageCount;
$summary->data->channelsUsed;
```

---

## Templates API

```php
// list (cached per page)
Sent::templates()->get();
Sent::templates()->page(2)->perPage(25)->get();

// filter by category (MARKETING, UTILITY, AUTHENTICATION)
Sent::templates()->category('MARKETING')->get();

// filter by status (APPROVED, PENDING, REJECTED)
Sent::templates()->status('APPROVED')->get();

// filter by welcome playground flag
Sent::templates()->isWelcomePlayground()->get();

// read (cached)
Sent::templates()->find('template_id');
Sent::templates()->findByName('otp-verification');

// create
Sent::templates()->create()
    ->category('UTILITY')
    ->language('en_US')
    ->definition(['body' => [...]])
    ->save();

// create and submit for review immediately
Sent::templates()->create()
    ->category('MARKETING')
    ->definition(['body' => [...]])
    ->submitForReview()
    ->save();

// update (invalidates cache)
Sent::templates()->update('template_id')
    ->name('new-name')
    ->category('UTILITY')
    ->save();

// delete
Sent::templates()->delete('template_id');
```

From the command line:

```bash
php artisan sent:templates
php artisan sent:templates --page=2 --per-page=25
```

---

## Webhooks API

Manage webhook endpoints from code, beyond just receiving events:

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

---

## Profiles API

```php
// list (cached)
Sent::profiles()->get();

// read
Sent::profiles()->find('profile_id');

// create
Sent::profiles()->create()
    ->name('Sales Team')                   // required
    ->shortName('SALES')                   // 3-11 chars
    ->description('Outbound sales')
    ->billingModel('organization')         // 'organization' | 'profile' | 'profile_and_organization'
    ->inheritContacts(true)
    ->inheritTemplates(true)
    ->inheritTcrBrand(true)
    ->inheritTcrCampaign(true)
    ->allowContactSharing(false)
    ->allowTemplateSharing(false)
    ->icon('https://example.com/logo.png')
    ->billingContact([...])                // required when billingModel is 'profile'
    ->brand([...])                         // brand + KYC data
    ->paymentDetails([...])                // card details forwarded to payment processor
    ->whatsappBusinessAccount([...])       // direct WABA credentials from Meta
    ->save();

// update: all fields optional; also exposes sending number overrides
Sent::profiles()->update('profile_id')
    ->name('Support Team')
    ->inheritTemplates(true)
    ->allowNumberChangeDuringOnboarding(true)
    ->sendingPhoneNumber('+61412345678')
    ->sendingPhoneNumberProfileId('other_profile_id')
    ->sendingWhatsappNumberProfileId('other_profile_id')
    ->whatsappPhoneNumber('+61412345678')
    ->save();

// complete profile onboarding (runs in background, calls your webhook when done)
Sent::profiles()->complete('profile_id', 'https://yourapp.com/hooks/profile-complete');

// delete
Sent::profiles()->delete('profile_id');
```

### Campaigns sub-resource

Manage TCR campaigns scoped to a profile:

```php
$campaigns = Sent::profiles()->campaigns('profile_id');

// list
$campaigns->get();

// create
$campaigns->create([
    'name'        => 'OTP Verification',
    'description' => 'One-time passcode delivery',
    'type'        => 'KYC',
    'useCases'    => [
        ['usecase' => 'OTP', 'sample' => 'Your code is {{code}}.'],
    ],
]);

// update
$campaigns->update('campaign_id', [
    'name'        => 'OTP v2',
    'description' => 'Updated OTP campaign',
    'type'        => 'KYC',
    'useCases'    => [
        ['usecase' => 'OTP', 'sample' => 'Your verification code is {{code}}.'],
    ],
]);

// delete
$campaigns->delete('campaign_id');
```

---

## Users API

```php
// list
Sent::users()->get();

// read
Sent::users()->find('user_id');

// invite
Sent::users()->invite()
    ->email('alice@example.com')
    ->name('Alice')
    ->role('member')
    ->save();

// update role (admin, billing, developer)
Sent::users()->updateRole('user_id', 'admin');

// remove
Sent::users()->remove('user_id');
```

---

## Messages API

Check the status of a sent message or retrieve its activity log by message ID:

```php
// get current delivery status
$status = Sent::messages()->retrieve('msg_abc123');
$status->data->messageStatus; // 'QUEUED', 'SENT', 'DELIVERED', 'FAILED', etc.

// get activity log (all events for the message)
$activities = Sent::messages()->activities('msg_abc123');
```

Message IDs are returned in the `MessageSent` event and stored in `sent_logs.message_id` when logging is enabled.

---

## Conversations API

Browse conversation threads (grouped by contact/channel) and the messages within one. Read-only, not cached, same as any other paginated list resource:

```php
// list conversations
Sent::conversations()->get();
Sent::conversations()->page(2)->perPage(25)->get();

// list messages within a conversation
Sent::conversations()->messages('conversation_id');
Sent::conversations()->page(2)->perPage(25)->messages('conversation_id');
```

---

## Account

```php
$account = Sent::account();

$account->data->type;    // 'organization', 'user', or 'profile'
$account->data->name;
$account->data->email;
$account->data->channels->sms->configured;       // bool
$account->data->channels->whatsapp->configured;  // bool
```

Check account health from the command line:

```bash
php artisan sent:health
php artisan sent:health --connection=acme
```

---

## Artisan commands

| Command | Description |
|---|---|
| `sent:install` | Publish `config/sent.php` |
| `sent:health` | Check API connectivity and account status |
| `sent:test-send {number} --template=` | Send a test message |
| `sent:templates` | List templates in a table |
| `sent:lookup {number}` | Carrier lookup for a phone number |
| `sent:setup-webhook {url}` | Create a webhook endpoint on Sent.dm |
| `sent:stats` | Show aggregate message counts from the local `sent_logs` table (not from the Sent.dm API; requires logging migration) |

All commands accept `--connection=` to target a named connection.

```bash
# test a send in sandbox mode
php artisan sent:test-send +61412345678 --template=otp --sandbox

# check a named tenant connection
php artisan sent:health --connection=acme

# create a webhook for specific events
php artisan sent:setup-webhook https://yourapp.com/sent/webhook \
    --events=message.delivered \
    --events=message.failed

# show local message stats (requires logging migration)
php artisan sent:stats
php artisan sent:stats --table=custom_logs_table
```

---

## Testing

Use `Sent::fake()` at the start of any test. It replaces the real driver with an in-memory recorder and gives you full assertions, with no real API calls and no queued jobs.

```php
use Sujip\SentDm\Facades\Sent;

beforeEach(fn () => Sent::fake());

it('sends a welcome message on user registration', function () {
    $user = User::factory()->create(['phone' => '+61412345678']);

    $user->sendWelcomeMessage();

    Sent::assertSentTo('+61412345678');
    Sent::assertSentCount(1);
});
```

### Sent assertions

```php
// assert by recipient
Sent::assertSentTo('+61412345678');

// assert by recipient with a callback
Sent::assertSentTo('+61412345678', function (SentMessage $message) {
    return $message->getTemplateName() === 'welcome';
});

// assert by template
Sent::assertSentWithTemplate('otp');

// assert by template with a callback
Sent::assertSentWithTemplate('otp', function (SentMessage $message) {
    return $message->getTemplateData()['code'] === '123456';
});

// assert with a custom callback
Sent::assertSent(function (SentMessage $message) {
    return $message->getChannel() === 'sms';
});

// count and negative assertions
Sent::assertSentCount(2);
Sent::assertNothingSent();
```

### Queued assertions

```php
// assert queued via sendLater()
Sent::assertQueuedTo('+61412345678');

Sent::assertQueuedTo('+61412345678', function (SentMessage $message) {
    return $message->getTemplateName() === 'order-shipped';
});

Sent::assertQueuedCount(3);
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
$sent   = Sent::sent();    // list<SentMessage>
$queued = Sent::queued();  // list<SentMessage>

Sent::hasSent();    // bool
Sent::hasQueued();  // bool
Sent::reset();      // clear records between tests
```

### Testing opt-out behaviour

The `HasSentContact` opt-out methods hit the database. Use `RefreshDatabase` and create an opt-out record directly:

```php
use Sujip\SentDm\Models\SentOptOut;

it('skips send when user has opted out', function () {
    Sent::fake();

    $user = User::factory()->create(['phone' => '+61412345678']);
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => true]);

    $user->sendWelcomeMessage(); // should check optedOutFromSent() and skip

    Sent::assertNothingSent();
});
```

---

## Sponsoring

[![Sponsor](https://img.shields.io/badge/Sponsor-GitHub%20Sponsors-ea4aaa?logo=githubsponsors&logoColor=white)](https://github.com/sponsors/sudiptpa)

If this package has been useful to you, GitHub Sponsors is a simple way to support ongoing maintenance, improvements, and future releases.

## Contributing

Contributions are welcome. Please open an issue to discuss what you'd like to change, or submit a pull request directly for bug fixes and small improvements. Make sure `composer test`, `composer stan`, and `composer lint:check` all pass before submitting.

## License

This package is open source, licensed under the [MIT license](LICENSE.md).
