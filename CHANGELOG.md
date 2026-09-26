# Changelog

All notable changes to `laravel-sent` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Fixed

- Fixed a rare caching bug that could show up when many requests ran at the same time.

### Changed

- Bumped `sentdm/sent-dm-php` to `^0.33` and documented the new template and webhook fields it exposes.
- Added more tests for webhook signature checks.

### Added

- Added `messages()->resend()` for `POST /v3/messages/{id}/resend`.
- Channel responses now expose MMS market state and the SMS market `note` field from the current OpenAPI spec.
- `WebhookPayload` now exposes helpers for the current message and template webhook payload fields, including `requestId()`, `body()`, `templateName()`, `whatsappTemplateId()`, `updatedAt()`, `agentId()`, `scheduledAt()`, `scheduleReason()`, `autoReplyAction()`, and `reason()`.

## [2.0.0] - 2026-09-16

### Added

- `Resource::profile(string $id)`, a chainable method on every resource
  (`Sent::contacts()->profile($id)->get()`, etc.) that scopes a call to one child
  profile. `Sent::numbers()` added alongside the existing `Sent::lookup()` shortcut so
  `Numbers::lookup()` can take `profile()` too.
- `Sent::senderProfiles()`, `Sent::channels()`, `Sent::compliance()`, covering
  sender profiles, SMS/WhatsApp/RCS channel management, and compliance requirements.
  `SenderProfiles` replaces the now-deprecated `Profiles` resource.
- `Sent::me()`, a profile-scoped way to get account details.
- `Templates::search()`.
- `Templates::delete()` can now also remove the template from WhatsApp/Meta.
- `SentMessage::channel()` accepts an array to send on more than one channel at once.
- `SenderProfileBuilder::attach()` and `Channels::addSmsMarket()`, for uploading
  compliance documents.
- `idempotencyKey()` on every builder and write method that creates or updates
  something, not just message sends.

### Fixed

- Late or duplicate status webhooks no longer overwrite a later message-log status.
- README and message-log docs now distinguish immediate sends from queued sends and
  explain which sends create log entries.
- Composer's development branch alias now maps `dev-main` to `2.x-dev`.
- `LogSentMessage` only logged the first recipient of a send, dropping the rest when a
  message went to more than one channel. It now logs every one.
- Sandbox mode now works for contacts, templates, profiles, users, sender profiles,
  and channels, matching every other resource.
- `WebhookBuilder`/`SenderProfileBuilder` now require the fields Sent.dm actually
  requires (a name, a URL, a short name) before saving, instead of failing on the
  server. `SenderProfileBuilder::update()` and `Channels::addRcs()` reject
  unsupported or missing fields the same way.
- `WebhookBuilder` was missing `eventFilters()`, `retryCount()`, and `timeoutSeconds()`.
- `TemplateBuilder` was missing `creationSource()`.
- `Contacts` was missing a `phone` filter.
- `Webhooks` list methods were missing `search`/`isActive` filters.
- `Webhooks::test()` now requires an event type before sending the request.
- `Webhooks::delete()` and `SenderProfiles::delete()` no longer fail on Sent.dm's
  side over an empty request body.
- `Webhooks::rotateSecret()` no longer returns a secret for a webhook that doesn't
  exist.
- `Compliance::requirements()` now requires `country`/`type`, matching what Sent.dm
  actually requires.
- `sent:setup-webhook` now defaults to a valid event list and sets a webhook name.
- Docs moved from one long README into a `docs/` folder, organized by topic, with
  a couple of outdated examples and a broken link fixed along the way.
- `sent_logs.message_id` had no unique constraint, so two webhooks reconciling the
  same message before it was first logged could each insert their own row. Now
  enforced with a unique index; a migration deduplicates any row already on disk
  first. See `UPGRADE.md`.
- `send()` now rejects an empty recipient outright instead of reaching the API. A
  notifiable model with no phone attribute routed an empty string, not null, which
  the opt-out guard's own empty check let straight through.
- `Sent::fake()` now records `Sent::bulk(...)->dispatch()` like any other queued
  send instead of pushing a real job to the queue. See `UPGRADE.md`.
- `SenderProfiles::update()` now invalidates the cache `find()` writes, matching
  every other resource with the same create/update/cache pattern.
- Added indexes on `sent_logs.status`, `.recipient`, and `.created_at`, the columns
  `SentLog`'s own query scopes filter on.

### Changed

- Moved duplicated sandbox and idempotency-key code into two shared traits.
- Sandbox precedence and opt-out read/write logic, each duplicated across several
  resources, builders, and listeners, now live in one place (`Support\Sandbox` and
  `SentOptOut`'s own static methods).
- Dev dependencies bumped within existing constraints: `laravel/pint`, `phpstan/phpstan`,
  `larastan/larastan`, `orchestra/testbench`, `mockery/mockery`.
- Added `phpstan/phpstan-deprecation-rules` as a dev dependency.
- Bumped `sentdm/sent-dm-php` from `^0.29` to `^0.32`. Its own changelog claims v0.31.0
  only touches webhook responses; it actually consolidates per-operation response
  classes into one shared class each across Contacts, Templates, Users, Webhooks, and
  Conversations too (`WebhookGetResponse`/`WebhookNewResponse`/`WebhookUpdateResponse`
  → `APIResponseWebhook`, and the same pattern for the other four). The deprecated
  `Profiles`/`Campaigns` path also had its param shapes reunified, after v0.29.0 had
  split them apart. v0.30.0 was spec sync only. v0.32.0 changes `Contacts::get()`,
  `Templates::get()`, `Webhooks::get()`, `Webhooks::listEvents()`, `Conversations::get()`,
  and `Conversations::messages()` to return the SDK's new paginated page objects
  (`ContactsPage`, `TemplatesPage`, etc.) instead of a plain response, matching what
  `sentdm/sent-dm-php` itself now returns. `page()`/`perPage()` on this package's own
  resources are unaffected either way. See `UPGRADE.md`.

### Removed

- `->data->pagination->page`, `->pageSize`, `->totalCount`, `->totalPages`, and
  `->cursors` no longer work on the result of `Contacts::get()`, `Templates::get()`,
  `Webhooks::get()`, `Webhooks::listEvents()`, `Conversations::get()`, or
  `Conversations::messages()`, they now throw. This comes from the `sentdm/sent-dm-php`
  ^0.32 bump above: the SDK's own pagination object for these newly-paginated calls only
  declares `hasMore`, even though the Sent.dm API still sends the rest. `hasMore`
  is supported; the rest will be picked up once the official SDK exposes them.
  `->data->contacts`, `->data->templates`, etc. are unaffected, only the pagination
  metadata is gone. See `UPGRADE.md`.

### Deprecated

- `Sent::profiles()`, the `Profiles` and `Campaigns` resource classes, and
  `ProfileBuilder`. Sent.dm deprecated the underlying `profiles` service on their
  side; `SenderProfiles` is the replacement. Everything still works unchanged, no
  removal version set yet.

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
