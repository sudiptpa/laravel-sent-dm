# Security

If you find a security issue in this package, open a [GitHub issue](https://github.com/sudiptpa/laravel-sent-dm/issues) or send a fix as a pull request.

## Supported versions

Only the latest tagged release is supported. Update before reporting an issue on an older version.

## What counts as a security issue here

- Webhook signature verification (`VerifySignature` middleware)
- Handling of the webhook signing secret and API keys
- Anything that could let one profile's data leak into another's
- A dependency this package pulls in with a known vulnerability

General bugs that aren't security-relevant belong in a regular issue, not this file.
