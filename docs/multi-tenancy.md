# Organization profile scoping and multi-tenant connections

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

## Multi-tenant connections

This is for separate Sent.dm accounts, each with its own API key. If you instead have one
organization account with several child profiles under it, use
[organization profile scoping](#organization-profile-scoping) above, that uses one API
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

Resolving the connection from the logged-in tenant is usually a middleware:

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

If you need to override how the SDK client is built, register a completely custom driver instead:

```php
// app/Providers/AppServiceProvider.php
use Sujip\SentDm\SentManager;

app(SentManager::class)->extend('custom', function () {
    return new \Sujip\SentDm\Sent(
        client: new \SentDm\Client(apiKey: 'custom-key'),
    );
});
```
