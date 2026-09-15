# Message log

The message log records successful sends made through the package's message jobs, including `sendLater()` and bulk sends. It syncs delivery status from webhooks. Everything is opt-in, so nothing writes to your database unless you enable it.

Synchronous `send()` calls and the notification channel do not create a send-time log entry. A later status webhook can create a placeholder row for them, but it does not fill in the send's connection, template, or associated model. Queueing a Laravel notification does not change this, since the notification channel still calls `send()` directly.

## Setup

Publish the migrations and enable logging:

```bash
php artisan vendor:publish --tag=laravel-sent-migrations
php artisan migrate
```

```env
SENT_LOGGING_ENABLED=true
```

## Associate messages with a model

Use `->for($model)` on any message to bind the log entry to an Eloquent model:

```php
Sent::to($user->phone)
    ->template('order-shipped')
    ->with(['tracking' => $order->tracking])
    ->for($user)
    ->sendLater();
```

## HasSentMessages trait

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

Paginating that in a controller or Livewire component is nothing special:

```php
$messages = $user->sentMessages()
    ->latest()
    ->paginate(20);
```

`MessageFailed` gives you enough to retry with a fallback template:

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

## Querying the log: SentLog scopes

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

## Status progression

The log is created with status `queued` after the message job receives a successful API response, then updated as webhook events arrive. That last part needs the [webhook route](webhooks.md) enabled and reachable, logging alone never moves a row past `queued`:

```
queued → sent → delivered
                   ↓
                  read
              (WhatsApp and RCS)

queued → sent → failed

queued → filtered   (blocked by a routing or consent rule, never reached a carrier)
queued → blocked    (blocked by an account condition, template, or no open conversation)
queued → scheduled → sent → ...   (deferred to a later window, then continues normally)
```

Status updates follow [Sent.dm's forward-only status rules](https://docs.sent.dm/build/status-tracking). Late or duplicate events do not overwrite a later status or its metadata. `read`, `failed`, `filtered`, and `blocked` are terminal. `delivered` can advance to `read`, but cannot change to `failed`. A scheduled message can continue through the send pipeline.

The status check is part of the database update. A webhook received before the send job finishes creates a placeholder row; the job later fills in its metadata without resetting its status. These rules affect the stored log only. The webhook controller still dispatches each distinct event for application listeners.

Webhook status updates do not fire Eloquent model update events. Listen to the package's message events for delivery-related work.

> **Inbound messages** (`message.received` webhook events) do not create a `sent_logs` record. Status webhooks for outbound messages can create rows even when the message was sent outside this package.

## SentLogStatus enum

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
