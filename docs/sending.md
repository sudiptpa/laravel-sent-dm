# Sending messages

- [Immediate send](#immediate-send)
- [Template variables](#template-variables)
- [Idempotency](#idempotency)
- [Profile override](#profile-override)
- [Sandbox mode (per message)](#sandbox-mode-per-message)
- [Queued sends](#queued-sends)
- [Bulk messaging](#bulk-messaging)
- [Notification channel](#notification-channel)

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

## Idempotency

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

## Profile override

When your Sent.dm account has multiple profiles, target one per message:

```php
Sent::to('+61412345678')
    ->template('promo')
    ->usingProfile('profile_abc123')
    ->send();
```

## Sandbox mode (per message)

Simulate a send without real delivery, useful in staging:

```php
Sent::to('+61412345678')
    ->template('otp-verification')
    ->sandbox()
    ->send();
```

See [sandbox mode](sandbox.md) for the global switch and how it behaves across every resource, not just sends.

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
