# Sandbox mode

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
Sent::channels()->addSmsMarket(['country' => 'US', 'number_type' => 'TEN_DLC', 'sandbox' => true]);
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
