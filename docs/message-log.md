# Message log

The message log keeps a local record of every outbound message and syncs delivery status automatically from webhooks. Everything is opt-in, so nothing writes to your database unless you enable it.

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

The log is created with status `queued` when the job fires, then updated automatically as webhook events arrive. That last part needs the [webhook route](webhooks.md) enabled and reachable, logging alone never moves a row past `queued`:

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

> **Inbound messages** (`message.received` webhook events) do not create a `sent_logs` record. The log only tracks outbound messages sent through this package.

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
