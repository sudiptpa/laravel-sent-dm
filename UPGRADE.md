# Upgrade Guide

## Upgrading to 2.0 from 1.x

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

Track [sentdm/sent-dm-php](https://github.com/sentdm/sent-dm-php) for a fix; this
note gets removed once the SDK's pagination object carries the full set again.

## Deprecated, still supported

### `Profiles` and `Campaigns`

Still fully supported, no removal planned yet. Sent.dm deprecated the underlying
API on their side; `SenderProfiles` and a channel's `compliance.campaign` field are
the replacement for new integrations. A removal version will be set once Sent.dm
actually drops the endpoints.
