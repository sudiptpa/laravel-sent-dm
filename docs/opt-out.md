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

## Tenant-scoped opt-outs

By default, opt-outs are global for each phone number. Use tenant-scoped opt-outs
only when one Laravel app sends for more than one tenant, brand, client, seller,
or location, and a contact's choice should stay separate between them.

Good fits include:

- multi-tenant SaaS apps
- agency installs that send for many clients
- marketplaces where each seller has its own messaging relationship
- franchise or location systems
- multiple sender profiles mapped to different brands or tenants

Keep the default global opt-out when one STOP should block all messages from the
app, or when your app has only one legal sender identity. Consent is
compliance-sensitive: if the tenant cannot be resolved, the resolver should throw
instead of guessing.

For outbound sends, pass the tenant scope directly when your code already knows
the tenant, brand, client, seller, or location:

```php
Sent::to($user->phone)
    ->template('promo')
    ->tenantScope((string) $tenant->id)
    ->send();
```

The same method works on bulk sends and notification messages:

```php
Sent::bulk($phones)
    ->template('promo')
    ->tenantScope((string) $tenant->id)
    ->dispatch();

return SentMessage::create()
    ->template('promo')
    ->tenantScope((string) $notifiable->tenant_id);
```

For inbound STOP/START webhooks, or to avoid setting the tenant scope at every
outbound send site, set `sent.opt_out.tenant_scope_resolver` to an application class
implementing `Sujip\SentDm\Contracts\ResolvesTenantScope`:

```php
'opt_out' => [
    'tenant_scope_resolver' => App\Messaging\TenantScope::class,
],
```

This example assumes each tenant has a dedicated receiving number, with explicit
maps in `config/services.php`:

```php
namespace App\Messaging;

use Sujip\SentDm\Contracts\ResolvesTenantScope;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Webhooks\WebhookPayload;

class TenantScope implements ResolvesTenantScope
{
    public function forMessage(SentMessage $message, string $connection): string
    {
        // Map the connection and optional child profile to your tenant key.
        $scopes = config('services.sent.outbound_scopes', []);

        return $scopes[$connection][$message->getProfileId() ?? 'default']
            ?? throw new \LogicException('No outbound tenant scope is configured.');
    }

    public function forWebhook(WebhookPayload $payload): string
    {
        // Resolve the tenant from your stored inbound routing information.
        $scopes = config('services.sent.inbound_scopes', []);

        return $scopes[$payload->recipient() ?? '']
            ?? throw new \LogicException('No inbound tenant scope is configured.');
    }
}
```

An explicit `tenantScope()` on a message wins over the resolver for outbound
sends. Webhooks always use the resolver because there is no outbound message to
read from. Both resolver methods must return the same stable tenant key
for matching traffic, with 1 to 191 characters. Throw an exception when a mapping
is missing or ambiguous. The package does not infer a tenant from an inbound
account ID: Sent.dm does not guarantee that it identifies the child profile.
Resolver failures stop sending or propagate from webhook processing so the event
can be retried.

Use the same identifier for manual changes:

```php
$user->optOutFromSent('settings', tenantScope: 'tenant-42');
$user->optInToSent(tenantScope: 'tenant-42');
$user->optedOutFromSent(tenantScope: 'tenant-42');
```

Tenant-scoped opt-outs follow these rules:

| Action | Result |
|---|---|
| Global opt-out | Blocks every tenant scope |
| Tenant opt-out | Blocks only that tenant scope, unless a global block also exists |
| Tenant opt-in | Clears only that tenant scope |
| Unscoped opt-out or opt-in | Writes the global record |
| Unscoped check | Blocks if any global or tenant-scoped record is opted out |

Old rows keep an empty scope and remain global blocks. Review existing global
records before assigning them to tenants. Rollback is blocked while
tenant-scoped rows exist because merging them back into one global row could
change a contact's choice. Disabling the resolver does not silently ignore
tenant-scoped opt-outs.
