# Testing

Use `Sent::fake()` at the start of any test. It replaces the real driver with an in-memory recorder and gives you full assertions, with no real API calls and no queued jobs.

This covers sending and queueing. `Sent::lookup()` isn't part of the fake, there's
nothing to record for a lookup, so calling it after `Sent::fake()` throws instead of
quietly hitting the real API or returning fake data. Don't exercise number lookup in a
faked test.

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

## Sent assertions

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

## Queued assertions

```php
// assert queued via sendLater()
Sent::assertQueuedTo('+61412345678');

Sent::assertQueuedTo('+61412345678', function (SentMessage $message) {
    return $message->getTemplateName() === 'order-shipped';
});

Sent::assertQueuedCount(3);
Sent::assertNothingQueued();
```

## Multi-tenant assertions

```php
Sent::assertSentViaConnection('acme');

Sent::assertSentViaConnection('acme', function (SentMessage $message) {
    return $message->getRecipient() === '+61412345678';
});

Sent::assertQueuedViaConnection('globex');
```

## Introspection

```php
$sent   = Sent::sent();    // list<SentMessage>
$queued = Sent::queued();  // list<SentMessage>

Sent::hasSent();    // bool
Sent::hasQueued();  // bool
Sent::reset();      // clear records between tests
```

## Testing opt-out behaviour

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
