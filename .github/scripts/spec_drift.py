#!/usr/bin/env python3
"""SDK spec drift: installed sentdm/sent-dm-php vs the latest Sent.dm OpenAPI spec.

This package never talks to the Sent.dm API directly — it delegates entirely to
the official, Stainless-generated `sentdm/sent-dm-php` SDK, which is itself
generated from Sent.dm's OpenAPI spec. method_audit.php and shape_audit.php
already prove our wrapper code matches whatever SDK version is installed.

This script is the other axis: is the *installed SDK version* itself behind
the *latest* published spec? Stainless writes the exact spec hash a given SDK
release was generated from into that release's `.stats.yml`. Comparing our
installed version's hash against the latest tag's hash tells us whether
there's a newer Sent.dm API surface (new endpoints/fields) that a
`composer update sentdm/sent-dm-php` would pick up.

This is informational only — exits 0 always. Findings are logged for the
owner to act on (bump the SDK dependency), not a CI failure, since we cannot
fix an upstream SDK's generation lag from this repo.
"""

import json
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

REPO = "sentdm/sent-dm-php"
COMPOSER_LOCK = "composer.lock"
FINDINGS_FILE = "openapi/SDK_DRIFT.md"


def fetch(url: str) -> str:
    with urllib.request.urlopen(url, timeout=15) as response:
        return response.read().decode("utf-8")


def installed_version() -> str:
    with open(COMPOSER_LOCK, encoding="utf-8") as fh:
        lock = json.load(fh)
    for package in lock["packages"]:
        if package["name"] == "sentdm/sent-dm-php":
            return package["version"]
    raise SystemExit("sentdm/sent-dm-php not found in composer.lock")


def stats(ref: str) -> dict:
    url = f"https://raw.githubusercontent.com/{REPO}/{ref}/.stats.yml"
    text = fetch(url)
    out = {}
    for line in text.splitlines():
        m = re.match(r"^(\w+):\s*(.+)$", line.strip())
        if m:
            out[m.group(1)] = m.group(2)
    return out


def latest_tag() -> str:
    url = f"https://api.github.com/repos/{REPO}/tags"
    tags = json.loads(fetch(url))
    return tags[0]["name"]


def main() -> int:
    installed = installed_version()
    try:
        installed_stats = stats(installed)
        latest = latest_tag()
        latest_stats = stats(latest)
    except urllib.error.URLError as exc:
        print(f"spec_drift: could not reach GitHub ({exc}); skipping (informational check only).")
        return 0

    if installed_stats.get("openapi_spec_hash") == latest_stats.get("openapi_spec_hash"):
        print(f"SDK spec drift: clean. Installed {installed} matches the latest spec ({latest}).")
        return 0

    finding = (
        f"- Installed `sentdm/sent-dm-php` is `{installed}` "
        f"(spec hash `{installed_stats.get('openapi_spec_hash')}`, "
        f"{installed_stats.get('configured_endpoints')} endpoints).\n"
        f"- Latest release is `{latest}` "
        f"(spec hash `{latest_stats.get('openapi_spec_hash')}`, "
        f"{latest_stats.get('configured_endpoints')} endpoints).\n"
        "- The installed SDK was generated from an older Sent.dm API spec than what's "
        "currently published. Consider `composer update sentdm/sent-dm-php` and re-running "
        "the wrapper audit, since the new spec may add fields/endpoints our Resources/Builders "
        "don't cover yet.\n"
    )

    print("SDK spec drift detected:\n")
    print(finding)

    Path(FINDINGS_FILE).parent.mkdir(parents=True, exist_ok=True)
    with open(FINDINGS_FILE, "a", encoding="utf-8") as fh:
        fh.write(finding)

    return 0


if __name__ == "__main__":
    sys.exit(main())
