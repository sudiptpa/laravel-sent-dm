# Sending messages

## Immediate send

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

## Template variables

```php
Sent::to('+61412345678')
    ->template('otp-verification')
    ->with(['code' => '123456', 'expiry' => '10 minutes'])
    ->send();
```

## MMS: media, subject, and scheduled sends

```php
Sent::to('+61412345678')
    ->message('Your table is ready.')
    ->mediaUrls(['https://yourapp.com/images/receipt.jpg'])
    ->subject('Table confirmation')
    ->send();
```

`mediaUrls()` takes publicly reachable HTTPS URLs. Sent.dm's carrier fetches each one
after the send is accepted, so a link that expires or needs auth arrives as a failed
message. Attaching media is also what makes a `channel('sent')` auto-detected send
eligible for MMS; without it, the same message goes out as SMS. `subject()` is MMS-only
and ignored on every other channel.

To send later instead of now:

```php
Sent::to('+61412345678')
    ->template('appointment-reminder')
    ->scheduledAt('2026-10-01T09:00:00+02:00')
    ->send();
```

`scheduledAt()` accepts a `DateTimeInterface` or an ISO-8601 string with an explicit UTC
offset. It must be at least one minute and at most 30 days ahead. The message reports
`SCHEDULED` until it's released for delivery.

## Idempotency

Prevent duplicates if your app retries the same operation. A retry with the same key
within 24 hours returns the original response instead of creating a second record.
This isn't limited to sends, it's on every method that creates or changes something:

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

Keys are 1-255 alphanumeric characters, hyphens, or underscores. Don't reuse a key for
a different operation, Sent.dm doesn't compare the request body on replay, it just
hands back whatever the first call with that key returned.

## Profile override

When your Sent.dm account has multiple profiles, target one per message:

```php
Sent::to('+61412345678')
    ->template('promo')
    ->usingProfile('profile_abc123')
    ->send();
```

Same idea as [`profile()`](multi-tenancy.md#organization-profile-scoping) on the resource
builders (`Sent::contacts()->profile($id)`), just named `usingProfile()` here because
`SentMessage` isn't one of those resource classes.

## Sandbox mode per message

```php
Sent::to('+61412345678')
    ->template('otp-verification')
    ->sandbox()
    ->send();
```

Simulates a send without real delivery, useful for staging. There's also a global
switch that covers every resource, not just sends, see [sandbox mode](sandbox.md).

## Queued sends

Use `sendLater()` instead of `send()` to dispatch a Laravel job. Configure an
asynchronous queue connection and run a worker to process sends in the background.
Laravel's `sync` connection runs the job in the current process.

```php
Sent::to('+61412345678')
    ->template('welcome')
    ->sendLater();
```

Point it at a specific queue:

```env
SENT_QUEUE_CONNECTION=redis
SENT_QUEUE_NAME=messages
```

The job retries up to 3 times with exponential backoff. A 429 re-queues the job after
the `Retry-After` delay the API sends back, not a fixed wait.

Sending from a model event looks like this (`->for($user)` binds the send to that model
for the [message log](message-log.md), skip it if you're not using that):

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

And if you need to react once the job actually runs, `MessageSent` carries the
context you'd expect:

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

## Bulk messaging

Send the same message to a large list. Each recipient gets its own queued job, so one
failure or rate limit doesn't hold up the rest of the batch.

```php
$numbers = ['+61412345678', '+61498765432'];

Sent::bulk($numbers)
    ->template('flash-sale')
    ->with(['discount' => '20%'])
    ->dispatch();
```

A channel or profile forces the same choice across the whole batch:

```php
Sent::bulk($numbers)
    ->template('flash-sale')
    ->channel('sms')
    ->usingProfile('profile_abc123')
    ->dispatch();
```

A weekly digest is just `Schedule::call()` around a bulk dispatch:

```php
// app/Console/Kernel.php (or routes/console.php in Laravel 11+)
Schedule::call(function () {
    $numbers = User::subscribed()->pluck('phone')->all();

    Sent::bulk($numbers)
        ->template('weekly-digest')
        ->dispatch();
})->weekly();
```

## Notification channel

Use the Sent channel in any Laravel notification. Add `toSent()` returning a `SentMessage`. The `ProvidesSentMessage` interface is optional:

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

The model needs `HasSentContact` for its `phone` attribute to be usable:

```php
use Sujip\SentDm\Concerns\HasSentContact;

class User extends Model
{
    use Notifiable, HasSentContact;
}
```

Then it's a normal `notify()` call:

```php
$user->notify(new OrderShippedNotification($order));
```

Skip opted-out recipients right in `via()`:

```php
public function via(mixed $notifiable): array
{
    if ($notifiable->optedOutFromSent()) {
        return [];
    }

    return [SentChannel::class];
}
```

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

## Default channel

Set `sent.default_channel` (or `SENT_DEFAULT_CHANNEL`) to use a channel when a
message does not specify one. A message's `channel()` selection takes precedence.
Leave the default `null` to let Sent.dm choose the route.

A missing `toSent()` method or an invalid return value throws an exception.
Delivery is skipped when no recipient is available.
