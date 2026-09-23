# Security

If you find a security issue in this package, please report it privately with the affected version and steps to reproduce it. Keep vulnerability details out of public issues and pull requests until a fix is available.

## Supported versions

Only the latest tagged release is supported. Update before reporting an issue on an older version.

## What counts as a security issue here

- Webhook signature verification (`VerifySignature` middleware)
- Handling of the webhook signing secret and API keys
- Anything that could let one profile's data leak into another's
- A dependency this package pulls in with a known vulnerability

General bugs that aren't security-relevant belong in a regular issue, not this file.
