# Opt-out management

The opt-out layer tracks per-number consent, handles STOP keywords automatically, and can block outbound messages to opted-out numbers. All opt-in, nothing enabled by default.

- [Setup](#setup)
- [Inbound keyword handling](#inbound-keyword-handling)
- [HasSentContact opt-out methods](#hassentcontact-opt-out-methods)
- [Send guard](#send-guard)

## Setup

Publish the migrations (same command as above if already done) and enable:

```bash
php artisan vendor:publish --tag=laravel-sent-migrations
php artisan migrate
```

```env
SENT_OPT_OUT_ENABLED=true   # record STOP/UNSTOP from inbound messages
SENT_OPT_OUT_GUARD=true     # block sends to opted-out numbers
```

## Inbound keyword handling

When `SENT_OPT_OUT_ENABLED=true`, these inbound keywords are handled automatically:

| Keyword | Effect |
|---|---|
| `STOP` `UNSUBSCRIBE` `CANCEL` `END` `QUIT` | Contact is marked opted-out |
| `START` `YES` `UNSTOP` | Contact is marked opted-in |

No code needed. The `ProcessInboundOptOut` listener fires on every `MessageReceived` event and updates `sent_opt_outs`.

## HasSentContact opt-out methods

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

## Send guard

When `SENT_OPT_OUT_GUARD=true`, `send()` and `sendLater()` throw `ContactOptedOutException` if the recipient has opted out. Catch it where it matters:

```php
use Sujip\SentDm\Exceptions\ContactOptedOutException;

try {
    Sent::to($user->phone)->template('promo')->send();
} catch (ContactOptedOutException $e) {
    Log::info("Skipped send to opted-out number: {$e->phoneNumber}");
}
```
