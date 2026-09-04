# Changelog

All notable changes to `laravel-sent` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.3.0] - 2026-09-04

### Changed

- Bumped `sentdm/sent-dm-php` from `^0.27` to `^0.29`. Its own changelog claims "no
  breaking changes" for v0.29.0 ("primarily maintenance"), but it silently renames the
  SDK's response classes package-wide — every `APIResponseOf*`/`APIResponseTemplate`/
  `APIResponseWebhook` collapsed into resource- and operation-specific names
  (`ContactGetResponse`, `WebhookNewResponse`, `ConversationListMessagesResponse`, etc.).
  PHPStan caught 105 errors against it before a single test ran. Do not trust this SDK's
  changelog for breaking-change detection — same pattern as the `sub_type`→`event` webhook
  rename and the `CampaignListResponse` rename that hit `v0.27`; always diff PHPStan output
  version-by-version instead.
- Every method whose return type used to be a single `APIResponseOf*` class now returns a
  union of the operation-specific response classes it can actually produce (e.g.
  `ContactBuilder::save()` is now `ContactNewResponse|ContactUpdateResponse`). No behavior
  change — these are the exact objects the SDK always returned, just correctly typed now.
- `Profiles::billingContact()`/`brand()`/`paymentDetails()` and `Campaigns::create()`/
  `update()`'s `$campaign` parameter now take a plain array only. The SDK stopped exposing
  unified `BillingContactInfo`/`BrandsBrandData`/`PaymentDetails`/`CampaignData` types in
  v0.29.0 — `create()` and `update()` each get their own nested params class now, and this
  builder doesn't know at call time which one `save()` will hit. **This is a breaking
  change if you were passing typed SDK objects into these methods** — pass plain arrays
  matching the same shape instead (see `docs.sent.dm/reference/api`); array usage is
  unaffected.
- v0.28.0 (included in this bump) adds typed webhook payload models upstream
  (`MessageEvent`, `InboundMessageEvent`, `TemplateEvent`) and deprecates `csp_id` on the
  brand object (no replacement; this package doesn't reference it).

### Deprecated

- The SDK marks its entire `profiles` service (`create`/`retrieve`/`update`/`list`/
  `complete`) and the `campaigns` sub-service `@deprecated` as of v0.29.0 — still fully
  functional, no removal date given, but this strongly correlates with 15 new endpoints
  (`/v3/sender-profiles`, `/v3/channels/*`, `/v3/compliance/requirements`) that appeared on
  Sent.dm's live spec the same week and don't exist in any published SDK version yet. Read
  as: Sent.dm is migrating profiles to a new `sender-profiles` resource, the SDK has
  started marking the old one deprecated ahead of that, but hasn't generated the
  replacement client code yet. `Sent::profiles()`/`Sent::profiles()->campaigns()` keep
  working unchanged for now — nothing to migrate to yet. Tracked in `PROGRESS.md`.

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

- The scheduled SDK-drift audit (`spec_drift.py`) no longer crashes on `FileNotFoundError` — it now creates the `openapi/` directory before writing findings.

### Changed

- Bumped `sentdm/sent-dm-php` from `^0.23` to `^0.26`.
