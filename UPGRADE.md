# Upgrade Guide

## Unreleased

### `Profiles` and `Campaigns`

Still fully supported, no removal planned yet. Sent.dm deprecated the underlying
API on their side; `SenderProfiles` and a channel's `compliance.campaign` field are
the replacement for new integrations. A removal version will be set once Sent.dm
actually drops the endpoints.
