# Changelog

All notable changes to `laravel-sent` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.2.1] - 2026-08-15

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
