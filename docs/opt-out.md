# Opt-out management

The opt-out layer tracks per-number consent, handles STOP keywords automatically, and can block outbound messages to opted-out numbers. All opt-in, nothing enabled by default.

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

These lists are the defaults, both are configurable at `opt_out.keywords`/
`opt_out.opt_in_keywords` in `config/sent.php` if you need locale-specific ones
(`ARRET`, `STOPP`, and so on).

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

A settings page toggle is just those two calls behind a route:

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

Worth checking in `via()` too, so a notification doesn't even try:

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

When `SENT_OPT_OUT_GUARD=true`, `send()` throws `ContactOptedOutException` if the recipient has opted out. `sendLater()` queues the message; the guard runs when the job executes and marks a blocked job as failed. Catch it where it matters:

```php
use Sujip\SentDm\Exceptions\ContactOptedOutException;

try {
    Sent::to($user->phone)->template('promo')->send();
} catch (ContactOptedOutException $e) {
    Log::info("Skipped send to opted-out number: {$e->phoneNumber}");
}
```

## Tenant-scoped consent

By default, consent is global for each phone number. To keep tenant consent
separate, set `sent.opt_out.scope_resolver` to an application class implementing
`Sujip\SentDm\Contracts\ResolvesOptOutScope`. This example assumes each tenant
has a dedicated receiving number, with explicit maps in `config/services.php`:

```php
namespace App\Messaging;

use Sujip\SentDm\Contracts\ResolvesOptOutScope;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Webhooks\WebhookPayload;

class ConsentScope implements ResolvesOptOutScope
{
    public function forMessage(SentMessage $message, string $connection): string
    {
        // Map the connection and optional child profile to your tenant ID.
        $scopes = config('services.sent.outbound_scopes', []);

        return $scopes[$connection][$message->getProfileId() ?? 'default']
            ?? throw new \LogicException('No outbound consent scope is configured.');
    }

    public function forWebhook(WebhookPayload $payload): string
    {
        // Resolve your tenant using your stored inbound routing information.
        $scopes = config('services.sent.inbound_scopes', []);

        return $scopes[$payload->recipient() ?? '']
            ?? throw new \LogicException('No inbound consent scope is configured.');
    }
}
```

Both methods must return the same stable tenant identifier for matching traffic,
with 1 to 191 characters. Throw an exception when a mapping is missing or ambiguous.
The package does not infer a tenant from an inbound account ID: Sent.dm does not
guarantee that it identifies the child profile. Resolver failures stop sending or
propagate from webhook processing so the event can be retried.

Use the same identifier for manual changes:

```php
$user->optOutFromSent('settings', scope: 'tenant-42');
$user->optInToSent(scope: 'tenant-42');
$user->optedOutFromSent(scope: 'tenant-42');
```

Old rows keep an empty scope and remain global blocks. An opt-in for one tenant
cannot clear a global block or another tenant's opt-out. An unscoped check blocks
if any scope has an opt-out; unscoped writes only change the global record. Review
existing global records before assigning them to tenants, using your own records
of consent. Disabling the resolver does not silently ignore scoped opt-outs.
