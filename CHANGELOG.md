# Changelog

All notable changes to `laravel-sent` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added

- `Sent::me()`, a chainable entry point for `GET /v3/me` that accepts `profile()`, the
  same way every other resource does. `Sent::account()` (the existing shortcut) had no
  way to scope the call to a child profile at all.
- `Templates::search()`, entirely missing despite the SDK supporting it.
- `Templates::delete()`'s `$deleteFromMeta` parameter, to also remove a template from
  WhatsApp/Meta, entirely missing despite the SDK supporting it.
- `SentMessage::channel()` accepts an array to fan out a send across more than one
  channel in a single call, matching what the SDK and Sent.dm's API already support
  (`channel: ["sms", "whatsapp"]` sends a separate, separately-tracked message per
  channel). `getChannels()` added alongside the existing `getChannel()` (now returns
  the first channel, for callers that only ever set one). Fixed a related bug this
  otherwise would have hit immediately: `LogSentMessage` only ever logged the first
  entry in a send response, silently dropping every other one, now logs one row per
  entry.
- `SenderProfileBuilder::attach(string $complianceKey, FileParam $file)`, for markets
  that pre-register a sender with supporting documents (e.g. business registration,
  letter of authorization). Sends the request as `multipart/form-data`: the whole
  profile goes in one `profile` field, and each file goes under the compliance key it
  satisfies. This matches Sent.dm's own documented convention exactly. Create only.
- `Channels::addSmsMarket()` also accepts a `FileParam` now, keyed by compliance field
  name, for the same kind of document upload. The spec doesn't document the field
  convention for this endpoint the way it does for `sender-profiles`, so this was
  found by testing several encodings live against the sandbox: this endpoint's
  multipart form reads field names without underscores, so `number_type`/
  `sender_value` go as `numberType`/`senderValue` here, unlike everywhere else in this
  package. `country`/`sandbox` are one word each, so they're unaffected.
- `idempotencyKey()` on every builder that creates or updates something
  (`ContactBuilder`, `TemplateBuilder`, `UserInviteBuilder`, `WebhookBuilder`,
  `SenderProfileBuilder`, the deprecated `ProfileBuilder`), plus an `$idempotencyKey`
  parameter on the direct write calls that don't go through a builder
  (`Users::updateRole()`, `Webhooks::rotateSecret()`/`test()`/`enable()`/`disable()`,
  `Campaigns::create()`/`update()`, `Profiles::complete()`,
  `Channels::addSmsMarket()`/`updateSmsMarket()`/`addWhatsapp()`/`addRcs()`). Sent.dm
  puts `Idempotency-Key` on every `POST`/`PUT`/`PATCH` operation in its v3 spec, this
  package only exposed it on `Sent::send()` before. A retry with the same key within 24
  hours now returns the original response instead of creating a duplicate, for all 23
  of those operations, not just message sends.

### Fixed

- `WebhookBuilder::save()` didn't check that `url()` was called before sending the
  request. The spec doesn't list `endpoint_url` as required, but a webhook without one
  fails live with "Endpoint URL must be a valid HTTP or HTTPS URL." We hit this
  directly. It's the same trap `name()`/`events()` were already guarded against. Now
  throws before the request goes out, matching those two.
- `Sent::contacts()`, `Sent::templates()`, `Sent::profiles()`, and `Sent::users()` were
  constructed without the global `SENT_SANDBOX` config, unlike `webhooks()`,
  `senderProfiles()`, and `channels()`. Setting `SENT_SANDBOX=true` had no effect at all
  on any contact, template, profile, or user operation, they always ran for real. Fixed;
  all four now respect the global default the same way every other resource does.
- `ContactBuilder`, the deprecated `ProfileBuilder`, and `UserInviteBuilder` had no
  `sandbox()` method at all, and `Campaigns::create()`/`update()`/`delete()`,
  `Profiles::complete()`, `Webhooks::enable()`/`disable()`/`test()`, and the `delete()`
  method on `Contacts`, `Templates`, `Profiles`, and `Users` had no `$sandbox` parameter,
  despite the SDK and Sent.dm's spec supporting it on all of them. Added, following the
  same explicit-call-wins-over-global-config precedence as every other sandboxed method.
- `SenderProfileBuilder::update()` accepted `billing()`/`channels()`/`compliance()`, but
  the PATCH endpoint rejects all three as unrecognized fields. If nothing else was set,
  it then fails with "supply at least one field to change," a misleading error since the
  caller did supply a field. Now throws a clear `InvalidArgumentException` before the
  request goes out; those three stay create-only, matching what the endpoint accepts.
- `WebhookBuilder` was missing `eventFilters()`, `retryCount()`, and `timeoutSeconds()`,
  three fields the SDK and Sent.dm's spec both support on create and update. Added.
- `TemplateBuilder` was missing `creationSource()` (create-only, like `name()` is
  update-only). Added.
- `Contacts` was missing the `phone` filter (a separate, exact-match alternative to
  `search()`). Added as `phone()`.
- `Webhooks::get()` was missing the `search`/`isActive` filters, and `listEvents()` was
  missing `search`, despite the SDK supporting all three. Added.
- `Webhooks::test()` didn't check that `$eventType` was given before sending the
  request. The spec marks `event_type` as required, and Sent.dm rejects a call without
  one with "Event type is required." Now throws before the request goes out.

### Changed

- `sandbox()` and `idempotencyKey()` were duplicated identically across `SentMessage`,
  `WebhookBuilder`, and `SenderProfileBuilder`. Extracted into `Concerns\HasSandbox` and
  `Concerns\HasIdempotencyKey` traits; every builder that supports either now uses the
  shared implementation instead of its own copy.

## [1.4.0] - 2026-09-06

### Added

- `Resource::profile(string $id)`, a chainable method on every resource (`Sent::contacts()->profile($id)->get()`, etc.) that sends the `x-profile-id` header Sent.dm uses to scope a call to one child profile. Every v3 operation accepts this header except `/v3/sender-profiles` itself, which has nothing to scope into. The header works with a standard API key, not only an organization-tier one as the SDK's own docstring claims. Also adds `Sent::numbers()` as a chainable entry point alongside the existing `Sent::lookup()` shortcut, so `Numbers::lookup()` can take `profile()` too.
- `Sent::senderProfiles()`, `Sent::channels()`, `Sent::compliance()`. These cover 13 v3
  operations (`/v3/sender-profiles`, `/v3/channels/*`, `/v3/compliance/requirements`) that exist
  on Sent.dm's API but aren't in any published `sentdm/sent-dm-php` version yet. They use the
  SDK's own generic `Client::request()` internally, same transport, auth, and retries as every
  typed SDK call, just no generated request/response classes yet (see `CONTRIBUTING.md`).
  `SenderProfiles` is an immutable query builder plus CRUD (`create()`/`update()` return a
  `SenderProfileBuilder`). `Channels` and `Compliance` are flat action methods, matching how
  `Sent::lookup()`/`Sent::account()` already work. `SenderProfiles` replaces the
  now-`@deprecated` `Profiles` resource (1.3.2).

### Fixed

- `SenderProfileBuilder::create()->save()` would pass client-side validation and fail server-side
  with a 400 if `shortName()` was never called. Sent.dm requires it alongside `name` on create,
  and the builder didn't check for it. Now throws an `InvalidArgumentException` before the
  request goes out, matching the existing `name` check.
- The global `SENT_SANDBOX` config only affected `Sent::send()`. `SenderProfileBuilder::save()`
  and every `Channels` write (`addSmsMarket()`, `updateSmsMarket()`, `addWhatsapp()`, `addRcs()`)
  ignored it entirely, sandbox only worked there if you called `->sandbox()` on every single call.
  Both now fall back to the global config the same way message sends do, and an explicit
  `sandbox(false)` (or `'sandbox' => false` on `Channels`) still wins over it.
- `Compliance::requirements()` made `country`/`type` optional. Sent.dm requires both for the
  currently-published `sms` channel; omitting either 400s. Both are now required parameters;
  `channel` stays optional, defaulting to `sms`.
- `Contacts::search()` and its README examples used a name-shaped placeholder (`search('John')`).
  Contacts have no name field; `search` matches the exact national-format phone number, including
  punctuation. Fixed the docblock and both examples.
- `Webhooks::create()`/`update()`'s `events()` accepts top-level categories only (`"message"`,
  `"templates"`), not the ten granular event names (`message.sent`, `message.delivered`, etc.)
  this package's own README examples used, both of which fail with a 400 ("Allowed types:
  message, templates"). Those granular names are `sub_types` delivered in the webhook payload's
  own `event` field once subscribed to `"message"`, not values you subscribe with. Fixed both
  examples; added a docblock on `WebhookBuilder::events()`.
- `Webhooks::test()`'s `$eventType` is typed optional in the SDK, but Sent.dm rejects a call
  without one ("Event type is required"). Documented on the method so it's clear before you hit
  the error.
- `WebhookBuilder` had no way to set a webhook's name at all, so `Webhooks::create()` could never
  succeed, `display_name` is required and there was no method to set it. Added `name()`; both
  `create()` and `update()` now throw before the request goes out if it's missing, matching the
  pattern already used for `SenderProfileBuilder`.
- `WebhookBuilder` also had no `sandbox()`, unlike every other write builder in this
  package. Added it, wired through `Webhooks::create()`/`update()` and the global
  `SENT_SANDBOX` config the same way `SenderProfileBuilder` and `Channels` already work.
- `sent:setup-webhook`'s default event list used the ten granular event names
  (`message.sent`, `message.delivered`, etc.), which fail with the same 400 as the `events()`
  bug above. Fixed to `['message']`, and the command never set a webhook name either, now
  defaults to the URL's host with a new `--name=` option to override it.
- `Webhooks::delete()` and `SenderProfiles::delete()` failed with "the value for 'body' is
  invalid or has the wrong type". The SDK's typed `delete()` sends an empty-string body with
  `Content-Type: application/json`, which Sent.dm's server can't parse. A non-empty JSON body
  avoids it; both methods now call `Resource::raw()` with a filler body instead of the SDK's
  typed method.
- `Webhooks::rotateSecret()` returned a real signing secret for a webhook ID that was never
  created, unlike every sibling operation on the same kind of ID (`retrieve`, `toggleStatus`,
  `test`, `listEvents`), which correctly 404 for one that doesn't exist. `rotateSecret()` now
  calls `retrieve()` first, so an unknown ID throws before it reaches the rotate call. Pass
  `sandbox: true` to skip the check when the id is intentionally not a real one.

## [1.3.2] - 2026-09-05

### Deprecated

- `Sent::profiles()`, the `Profiles` and `Campaigns` resource classes, and `ProfileBuilder`
  are now marked `@deprecated`. `phpstan/phpstan-deprecation-rules` (added this release)
  caught that these classes call SDK methods the SDK itself marks deprecated internally.
  Sent.dm's August 2026 platform changelog deprecated the entire `profiles` service in
  favor of the new `sender-profiles` resource; this just makes that visible in our own
  types instead of only in the SDK's. Everything still works unchanged. No replacement
  exists in the SDK yet, so there's nothing to migrate to.

### Changed

- Dev dependencies bumped within existing constraints: `laravel/pint`, `phpstan/phpstan`,
  `larastan/larastan`, `orchestra/testbench`, `mockery/mockery`. `pestphp/pest` stays on
  `^3.0`; v4+ requires PHP 8.3 and this package still supports PHP 8.2.
- Added `phpstan/phpstan-deprecation-rules` as a dev dependency. This is what caught the
  deprecation gap above.

## [1.3.1] - 2026-09-05

### Deprecated

- `Templates::isWelcomePlayground()`. Sent.dm's August 2026 platform changelog removed this
  filter server-side; `GET /v3/templates` still accepts the parameter but ignores it, so
  calling this method is a silent no-op, not an error. Kept only because the SDK still
  declares the param.
- `Contacts::delete()`, in favor of `contacts()->update($id)->optOut(true)->save()`. The
  platform changelog deprecates `DELETE /v3/contacts/{id}`: opting out stops every send and
  keeps the record of who the contact was and that they asked, where a hard delete loses
  both. The endpoint and this method still work unchanged.

### Fixed

- `Campaigns::create()`/`update()`'s `$campaign` docblock now documents a silent-cost gotcha
  from the same platform changelog: omitting `volume` doesn't error, it registers the
  campaign at the standard (higher-fee) tier with nothing surfaced to flag it. No code
  change, this is a documentation fix so the behavior is visible before it costs money.

## [1.3.0] - 2026-09-04

### Changed

- Bumped `sentdm/sent-dm-php` from `^0.27` to `^0.29`. Its own changelog claims "no
  breaking changes" for v0.29.0 ("primarily maintenance"), but it silently renames the
  SDK's response classes package-wide: every `APIResponseOf*`/`APIResponseTemplate`/
  `APIResponseWebhook` collapsed into resource- and operation-specific names
  (`ContactGetResponse`, `WebhookNewResponse`, `ConversationListMessagesResponse`, etc.).
  PHPStan caught 105 errors against it before a single test ran. Do not trust this SDK's
  changelog for breaking-change detection. Same pattern as the `sub_type` to `event`
  webhook rename and the `CampaignListResponse` rename that hit `v0.27`; always diff
  PHPStan output version-by-version instead.
- Every method whose return type used to be a single `APIResponseOf*` class now returns a
  union of the operation-specific response classes it can actually produce (e.g.
  `ContactBuilder::save()` is now `ContactNewResponse|ContactUpdateResponse`). No behavior
  change: these are the exact objects the SDK always returned, just correctly typed now.
- `Profiles::billingContact()`/`brand()`/`paymentDetails()` and `Campaigns::create()`/
  `update()`'s `$campaign` parameter now take a plain array only. The SDK stopped exposing
  unified `BillingContactInfo`/`BrandsBrandData`/`PaymentDetails`/`CampaignData` types in
  v0.29.0. `create()` and `update()` each get their own nested params class now, and this
  builder doesn't know at call time which one `save()` will hit. **This is a breaking
  change if you were passing typed SDK objects into these methods.** Pass plain arrays
  matching the same shape instead (see `docs.sent.dm/reference/api`); array usage is
  unaffected.
- v0.28.0 (included in this bump) adds typed webhook payload models upstream
  (`MessageEvent`, `InboundMessageEvent`, `TemplateEvent`) and deprecates `csp_id` on the
  brand object (no replacement; this package doesn't reference it).

### Deprecated

- The SDK marks its entire `profiles` service (`create`/`retrieve`/`update`/`list`/
  `complete`) and the `campaigns` sub-service `@deprecated` as of v0.29.0. Still fully
  functional, no removal date given, but this strongly correlates with 13 new endpoints
  (`/v3/sender-profiles`, `/v3/channels/*`, `/v3/compliance/requirements`) that appeared on
  Sent.dm's live spec the same week and don't exist in any published SDK version yet. Read
  as: Sent.dm is migrating profiles to a new `sender-profiles` resource, the SDK has
  started marking the old one deprecated ahead of that, but hasn't generated the
  replacement client code yet. `Sent::profiles()`/`Sent::profiles()->campaigns()` keep
  working unchanged for now, nothing to migrate to yet. Tracked in `PROGRESS.md`.

### Fixed

- Sent.dm renamed the webhook envelope field from `sub_type` to `event` in May 2026. This package still read `sub_type`, so every webhook event was silently dropped. Webhooks now work again.
- An unrecognized webhook event type now logs a warning instead of being dropped with no trace.

### Added

- `MessageFiltered`, `MessageBlocked`, and `MessageScheduled` events, and matching `SentLogStatus` cases, for the three message statuses Sent.dm added in July 2026.
- A new CI check, `changelog_drift.py`, that compares Sent.dm's own changelog page against a saved copy and flags any change for review. This catches things the other checks cannot see, such as a renamed field or a new status value, since those never show up as a class or method change.

## [1.2.0] - 2026-08-15

### Added

- `Sent::conversations()` resource: `get()` lists conversations, `messages($id)` lists the messages within one. Both paginated via `page()`/`perPage()`, read-only, not cached.
- `Contacts::messageSummary($id)`: message count, first/last message timestamps, channels used, and per-channel scores for a contact. Cached like `find()`.

### Fixed

- `spec_drift.py` compared `openapi_spec_hash` between installed and latest `.stats.yml`, a field Stainless has since dropped from that file. Both sides silently resolved to `None`, so the check always reported "clean" even when the SDK was genuinely behind. It now falls back to comparing `configured_endpoints`, and refuses to report clean when neither field is comparable.
- `Campaigns::get()`/`create()`/`update()` return types updated to match SDK v0.27.0's renamed response classes (`CampaignListResponse` → `APIResponseOfListOfBrandCampaign`, `APIResponseOfTcrCampaignWithUseCases` → `APIResponseOfBrandCampaign`).
- `Contacts::delete()` now also evicts the `messageSummary()` cache entry, not just `find()`, so a deleted contact's message summary no longer keeps serving stale cached data until TTL expiry.

### Changed

- Bumped `sentdm/sent-dm-php` from `^0.26` to `^0.27`.

## [1.1.0] - 2026-07-15

### Added

- `SentMessage::message()` now sends as a plain-text message when no template is set, via the SDK's new `text` param. Template still takes priority when both are set.

### Fixed

- The scheduled SDK-drift audit (`spec_drift.py`) no longer crashes on `FileNotFoundError`. It now creates the `openapi/` directory before writing findings.

### Changed

- Bumped `sentdm/sent-dm-php` from `^0.23` to `^0.26`.
