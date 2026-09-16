# Upgrade Guide

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

Run `php artisan migrate` as usual. No code changes needed on your end.

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
one of these responses, confirmed with a direct request to the API with no SDK
involved. The SDK's own model class for this new page type just doesn't declare
those properties anymore, so reading them throws instead of returning a value. This
package can't work around it since it's a fault in the SDK's generated code, not in
how it maps the API's fields.

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
