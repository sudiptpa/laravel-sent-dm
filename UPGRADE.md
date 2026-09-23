# Upgrade Guide

## Upgrading to 2.1 from 2.0

- Publish the new migration before running it:

  ```bash
  php artisan vendor:publish --tag=laravel-sent-migrations
  php artisan migrate
  ```

- The new `sent_opt_outs.scope` column keeps existing records as global consent.
  Tenant isolation is optional and uses an application-supplied resolver. See
  [tenant-scoped consent](docs/opt-out.md#tenant-scoped-consent). Existing global
  opt-outs continue to block every scope. Rollback is refused while scoped records
  exist; review and migrate those records before reverting.
- Resource cache keys now include the connection, API key, and child profile.
  Old entries expire normally. Template list pages are no longer cached; lookup
  caching remains available and template writes invalidate name lookups.
- `sent.default_channel` now applies when a message has no explicit channel.
  Leave it `null` to let Sent.dm choose the route.
- Notifications may define `toSent()` without implementing `ProvidesSentMessage`.
  Missing methods and invalid return values now throw clear exceptions. A missing
  recipient still skips delivery.
- Mobile-number validation tolerates connection failures, rate limits, and server
  errors. Configuration, authentication, and programming errors now surface.
- `sent:setup-webhook` saves its signing secret to a private local file instead of
  displaying it. See [webhook setup](docs/webhooks.md).
- Inbound consent now uses the contact number in the documented webhook payload.
  Review any consent records created from inbound events before this update.

## Upgrading to 2.0 from 1.x

### Message-log status only advances

Late and duplicate webhooks no longer overwrite a later status in `sent_logs`.
`read`, `failed`, `filtered`, and `blocked` are terminal. `delivered` can advance to
`read`, but cannot change to `failed`. Application listeners still receive distinct
webhook events; this change only controls the stored log.

Status changes now use a conditional database update, which does not fire Eloquent
`saving`, `updating`, `updated`, or `saved` model events. Use the package's message
events for delivery-related listeners instead of `SentLog` update observers.

No database migration is needed for this change.

### `sent_logs.message_id` is now unique, run the new migrations

Three new migrations ship with this release:

1. Deduplicates `sent_logs` by `message_id`, keeping the most recently updated row
   for each duplicate. Only does anything if your install hit the old status-update
   race (two webhooks logging the same message before the fix above), most installs
   will have nothing to clean up.
2. Adds a unique index on `message_id`.
3. Adds indexes on `status`, `recipient`, and `created_at`, the columns
   `SentLog::forRecipient()`, `whereSentBetween()`, and the other query scopes
   already filter on.

Run `php artisan vendor:publish --tag=laravel-sent-migrations`, then
`php artisan migrate`. No application code changes are needed.

### `Sent::fake()` now fakes bulk sends too

`Sent::bulk([...])->dispatch()` used to push a real job to your actual queue even
inside `Sent::fake()`, so a test asserting against the fake wouldn't see it. It's
now recorded like any other queued message: `Sent::assertQueuedCount()` and
`Sent::assertQueuedTo()` both see one entry per recipient. If you added a
`Queue::fake()`-based workaround for this, it's no longer needed.

### Pagination metadata is gone from six list methods

`Contacts::get()`, `Templates::get()`, `Webhooks::get()`, `Webhooks::listEvents()`,
`Conversations::get()`, and `Conversations::messages()` now return the SDK's own
paginated page object instead of a plain response. Reading the results is unchanged:

```php
Sent::contacts()->get()->data->contacts;
Sent::templates()->get()->data->templates;
```

But the pagination metadata on that same object lost most of its fields:

```php
$result = Sent::contacts()->get();

$result->data->pagination->hasMore;      // still works
$result->data->pagination->page;         // throws RuntimeException
$result->data->pagination->pageSize;     // throws RuntimeException
$result->data->pagination->totalCount;   // throws RuntimeException
$result->data->pagination->totalPages;   // throws RuntimeException
$result->data->pagination->cursors;      // throws RuntimeException
```

This comes from `sentdm/sent-dm-php` ^0.32, bumped in this release. Sent.dm's API
still sends `page`, `page_size`, `total_count`, `total_pages`, and `cursors` on every
one of these responses. The SDK's own model class for this new page type just
doesn't declare those properties anymore, so reading them throws instead of
returning a value. This package can't work around it, the fault is in the SDK
itself, not in how it maps the API's fields. `hasMore` is supported; the rest will
be picked up in a future release once the official SDK exposes them.

If your code reads any of those five fields off one of the six methods above, it
will break on this upgrade. There's no drop-in replacement while the SDK stays this
way, `hasMore` is the only pagination signal left, use `page()`/`perPage()` on the
resource itself to control how you page through results, that hasn't changed:

```php
Sent::contacts()->page(2)->perPage(25)->get();
```

## Deprecated, still supported

### `Profiles` and `Campaigns`

Still fully supported, no removal planned yet. Sent.dm deprecated the underlying
API on their side; `SenderProfiles` and a channel's `compliance.campaign` field are
the replacement for new integrations. A removal version will be set once Sent.dm
actually drops the endpoints.
