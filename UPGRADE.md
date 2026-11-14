# Upgrade Guide

## Upgrading from 2.6

This is a major release. `sentdm/sent-dm-php` moves from 0.46 to 0.47, and the package now returns
the SDK's own models where it used to return its own classes. Arguments you pass in stay the same,
with the stricter checks listed under "Earlier validation".

### Steps

1. Run `composer update sudiptpa/laravel-sent-dm`. It moves `sentdm/sent-dm-php` to 0.47 with it.
2. Search your code for the calls in the tables below, and for `Sujip\SentDm\Responses\`. Each
   match needs a change. Calls that are not listed behave as before.
3. Read results from `->data`, and read optional fields with `??`. The tables show each call.
4. Change type hints and `instanceof` checks to the SDK classes in the tables.
5. Run your tests. The most common failures are `Error: must not be accessed before
   initialization` (an optional field read without `??`) and a missing `Responses` class.
6. The package's own cache needs nothing from you. Entries saved by the old version are read as
   missing and fetched again. If your code stores these results somewhere itself, such as a
   database column or your own cache key, refresh those copies, because the shape is different.

### What changed in this release

- Sender profiles, channels (SMS, WhatsApp, RCS), compliance requirements and message cancel
  use the SDK's typed methods and return its models.
- The SDK renamed several response types. Their properties are unchanged.
- The package's `Responses\*` classes are removed, with no replacement alias.
- `addSmsMarket()`, `updateSmsMarket()`, `addWhatsapp()` and `addRcs()` check their input before
  the request, and refuse keys they do not send.
- `senderProfiles()->profile()` no longer sends `x-profile-id`. The API rejects it on that route.
- Ids you pass to sender profile, SMS market and message cancel calls are URL-encoded in the path.

### Sender profiles, channels and compliance return SDK models

These calls no longer return the package's own `*Data` classes. They return the SDK's models,
with the result under `->data`.

| Call | Before | Now |
| --- | --- | --- |
| `senderProfiles()->find()`, `create()->save()`, `update()->save()` | `SenderProfileData` (`$p->organizationId`) | `$r->data->organizationID` |
| `senderProfiles()->get()` | `SenderProfileListData` (`->senderProfiles`, `->pagination`) | a page: `->getItems()` |
| `channels()->get()` | `ChannelsStateData` (`->customerId`) | `$r->data->customerID` |
| `channels()->smsMarkets()` | a list of `SmsMarketData` | `$r->data`, a list of SDK markets |
| `findSmsMarket()`, `addSmsMarket()`, `updateSmsMarket()` and the SMS builders | `SmsMarketData` | `$r->data->numberType`, `->senderValue`, `->status` |
| `addWhatsapp()` and the WhatsApp builder | `WhatsappChannelData` (`->wabaId`) | `$r->data->wabaID`, `->phoneNumberID` |
| `addRcs()` and the RCS builder | `RcsAgentData` (`->privacyPolicyUrl`) | `$r->data->privacyPolicyURL`, `->websiteURL` |
| `compliance()->requirements()` | `ComplianceRequirementsData` (`->senderIdPattern`) | `$r->data->senderIDPattern` |
| `messages()->cancel()` | a message status response | `APIResponseOfCancelledMessage`, the cancelled message is `$r->data` and has `scheduledAt` |

Before and after, for the three most common changes:

```php
// A sender profile
$profile = Sent::senderProfiles()->find($id);
$profile->organizationId;                 // before
$profile->data->organizationID;           // now

// SMS markets
foreach (Sent::channels()->smsMarkets() as $market) { /* ... */ }          // before
foreach (Sent::channels()->smsMarkets()->data ?? [] as $market) { /* ... */ } // now

// An optional field
$agent->reason;                           // before: null when missing
$agent->data->reason ?? null;             // now: unset when missing, so use ??
```

Property names change case. Every name that ended in `Id` or `Url` now ends in `ID` or `URL`:

| Before | Now |
| --- | --- |
| `organizationId`, `customerId` | `organizationID`, `customerID` |
| `wabaId`, `phoneNumberId`, `solutionId`, `ownerBusinessId` | `wabaID`, `phoneNumberID`, `solutionID`, `ownerBusinessID` |
| `privacyPolicyUrl`, `termsAndConditionsUrl`, `websiteUrl` | `privacyPolicyURL`, `termsAndConditionsURL`, `websiteURL` |
| `senderIdPattern` | `senderIDPattern` |

Other differences in what you read back:

- A field the API leaves out is unset on an SDK model. Reading it with `->` throws an `Error`
  ("must not be accessed before initialization"). Read optional fields with `??` or `isset()`,
  for example `$agent->data->officialAddress ?? null`. The old `*Data` classes returned `null`.
- Dates such as `createdAt` are `DateTimeInterface` objects, not strings.
- `billing`, `channels`, `compliance` and `officialAddress` are objects (`$agent->data->officialAddress->postalCode`,
  `$market->data->compliance->brand`), not arrays.
- Each compliance requirement is an object with `key` and `label`, not an array.
- To send a market's `compliance` back through `updateSmsMarketBuilder()->compliance()`, turn the
  object into an array first: `json_decode(json_encode($market->compliance), true)`.
- SMS markets no longer have a `note` field, and an RCS agent no longer has `createdAt`.
- A sender profile's `apiKey` is returned only when the profile is created. `find()` and `get()`
  leave it out, so store it at creation.

The `Sujip\SentDm\Responses\*` classes and `Messages::statusResponseDataFromRawData()` are
removed. Replace any use of them with the SDK class the call returns, shown in the table.

### Renamed SDK response types

The SDK renamed these types. Only a type hint or `instanceof` check needs to change. Property
names are unchanged.

| Call | Now returns |
| --- | --- |
| `account()` | `SentDm\Me\APIResponseOfAccount` |
| `lookup()` | `SentDm\Numbers\APIResponseOfNumberLookupResult` |
| `messages()->retrieve()` | `SentDm\Messages\APIResponseOfMessage` |
| `messages()->activities()` | `SentDm\Messages\APIResponseOfMessageActivities` |
| `profiles()->get()` | `SentDm\Profiles\APIResponseOfProfilesList` |
| `users()->get()` | `SentDm\Users\APIResponseOfUsersList` |
| `webhooks()->rotateSecret()` | `SentDm\Webhooks\APIResponseOfWebhookSecret` |
| `webhooks()->test()` | `SentDm\Webhooks\APIResponseOfWebhookTest` |
| `webhooks()->listEventTypes()` | `SentDm\Webhooks\APIResponseOfWebhookEventTypesList` |

`webhooks()->listEvents()` pages now hold `SentDm\Webhooks\WebhookDelivery` items, and
`conversations()` pages hold `SentDm\Messages\Message` items.

### Earlier validation

These calls now throw `InvalidArgumentException` before any request is made. Before, the API
answered some of them with a 400 and ignored the rest.

- `channels()->addSmsMarket()` throws when `number_type` is not one of the values the API lists,
  also when you send a document. `addRcs()` does the same for `agent_use_case`. Upper or lower
  case both work, and so does the SDK enum. `addWhatsapp()` throws when `waba_id` is missing.
- `addSmsMarket()`, `updateSmsMarket()`, `addWhatsapp()` and `addRcs()` throw when the array has a
  key the call does not send, such as `numberType` instead of `number_type`, or `numbers` on an
  SMS market update. The typed calls would have dropped these without a word. The message names
  the key and lists the allowed ones.
- In `addRcs()`, `official_address` accepts `postal_code` or `postalCode`.

### Sender profiles ignore `profile()`

`Sent::senderProfiles()->profile($id)` used to send an `x-profile-id` header, and the API answers
400 to it on `/v3/sender-profiles`. The call now sends no header, so it works, and `profile()` has
no effect on that resource. Remove the `profile()` call from your code there if you like. Other
resources scope as before.

### Results of document uploads and null-clearing updates

A document upload (`attach()`) and an update that clears a value with `null` still go through the
SDK client's request method. They return the same model as the typed calls, with `success`,
`data` and `meta` set.

## Upgrading to 2.6.0

No migration or code change is needed. `composer update` moves `sentdm/sent-dm-php` to 0.46.

### Template names on create

`Sent::templates()->create()->name('...')` now sends the name instead of throwing an
exception. If your code caught that exception, or created the template first and set the name
with `update()` afterwards, you can set the name in the create call. The name is optional and up
to 255 characters. When it is left out, Sent generates one.

## Upgrading to 2.5.0

No migration or code change is needed. One behaviour change if you use opt-out tracking.

### Consent webhooks now update the opt-out table

With `SENT_OPT_OUT_ENABLED=true`, `contact.opt_in` and `contact.opt_out` webhooks are recorded as
well as inbound keywords such as STOP. Some numbers may now show as opted out that did not
before: the ones a network opted out without an inbound message (`source` is `PROVIDER_SIGNAL`).
If you use the send guard (`SENT_OPT_OUT_GUARD=true`), messages to those numbers are now blocked.

Subscribe your webhook to the `contact` category to receive these events. See
[docs/opt-out.md](docs/opt-out.md).

## Upgrading to 2.4.0

No migration is needed. Check these three behaviour changes.

### Call, contact, template, channel and link webhooks have their own events

These webhooks used to dispatch `UnknownWebhookEvent`. They now dispatch a typed event, for
example `CallCompleted`, `ContactOptedOut`, `TemplateApproved`, `ChannelActivated` or
`LinkClicked`. If you listened to `UnknownWebhookEvent` to handle any of them, move that
listener to the new event class. The full list is in [docs/webhooks.md](docs/webhooks.md). A
channel milestone Sent adds later still arrives as `UnknownWebhookEvent`.

Deduplication keys changed for these webhooks too. The first deliveries after you upgrade may
be handled once more if they were already handled under the old key, then dedup works as before.

### `profiles()->create()` refuses update-only fields

`sendingPhoneNumber`, `sendingPhoneNumberProfileId`, `sendingWhatsappNumberProfileId`,
`whatsappPhoneNumber` and `allowNumberChangeDuringOnboarding` only work on update. They used to
be dropped silently on create. Now `create()` throws `InvalidArgumentException`: create the
profile first, then set them with `profiles()->update($id)`. In the same way, `update()` refuses
`whatsappBusinessAccount`, which only works on create.

### `Rule::sentMobileNumber()` fails validation instead of throwing

A well-formed number the lookup API rejects (HTTP 400 or 422) now produces a normal validation
error. Remove any `try`/`catch` you added around validation to work around the exception.

## Upgrading to the next release

This release updates `sentdm/sent-dm-php` to `^0.40`. Calls now use the SDK's voice
service, and voice tokens use its tokens service. The Laravel methods remain the same.

The SDK moved call classes from `SentDm\Calls` to `SentDm\Voice\Calls`, and moved
`CallParticipantTarget` to `SentDm\Voice\Calls\Participants`. It moved the voice-token
response class from `SentDm\Channels\Voice` to `SentDm\Voice\Tokens`. If your application
imports these SDK classes directly, update those namespaces.

Updating a voice number's `callbackUrl` now keeps its current status. It no longer turns
an inactive number on by itself. Pass `status: 'ACTIVE'` in the same update when you
want to activate the number while changing its callback URL.

## Upgrading to 2.1.0

This release keeps the 2.x API line and updates the package for `sentdm/sent-dm-php`
`^0.36`. It adds voice call helpers, tenant-aware opt-outs, message resend support,
MMS media helpers, scheduled sends, richer webhook payload helpers, and expanded
local diagnostics in `sent:health`.

### Run the new opt-out migration if you use opt-outs

Tenant-aware opt-outs add a nullable `tenant` column to `sent_opt_outs`. If you have
published the package migrations and use opt-out storage, run:

```bash
php artisan migrate
```

Existing opt-outs remain global because their tenant value is `null`. A global
opt-out still blocks every tenant.

### Tenant-aware opt-outs are optional

No code change is needed if one STOP should block all messages from your app. Use the
new tenant support only when one Laravel app sends for multiple tenants, brands,
clients, sellers, or locations and each one needs a separate opt-out record.

For outbound sends, call `tenant()` on messages, bulk sends, or notification messages:

```php
Sent::to($user->phone)
    ->template('promo')
    ->tenant((string) $tenant->id)
    ->send();
```

For inbound STOP/START webhooks, or to avoid passing the tenant at each send site,
configure `sent.opt_out.tenant_resolver` with a class that implements
`Sujip\SentDm\Contracts\ResolvesSentTenant`.

### Review webhook and health configuration

`SENT_WEBHOOK_DEDUP_TTL` can now tune the duplicate webhook window. Existing apps keep
the previous default if the variable is not set.

`sent:health` now checks both API connectivity and local package readiness, including
queue, webhook, logging, opt-out, and table configuration. It is safe to run after
deployment:

```bash
php artisan sent:health
```

### New send helpers are opt-in

`mediaUrls()`, `subject()`, `scheduledAt()`, `channels()`, `messages()->resend()`, and
`messages()->cancel()` do not change
existing sends. In live mode, `messages()->resend()` creates another send, so use
`sandbox: true` when testing it. `messages()->cancel()` only applies before a
scheduled message is released.

## Upgrading to 2.0.x

This 2.0.x package update requires `sentdm/sent-dm-php` `^0.33`. Template responses may now
include `DRAFT` status, `rcs` in `channels`, and `auto_reply_action` for Sent.dm
auto-reply templates.

`WebhookPayload` now exposes helpers for the current message and template webhook
payload fields, including `requestId()`, `body()`, `templateName()`,
`whatsappTemplateId()`, `updatedAt()`, `agentId()`, `scheduledAt()`,
`scheduleReason()`, `autoReplyAction()`, and `reason()`. Existing webhook
accessors keep the same behavior.

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
