#!/usr/bin/env python3
"""SDK spec drift: installed sentdm/sent-dm-php vs Sent.dm's live OpenAPI spec.

This package never talks to the Sent.dm API directly — it delegates entirely to
the official, Stainless-generated `sentdm/sent-dm-php` SDK, which is itself
generated from Sent.dm's OpenAPI spec. method_audit.php and shape_audit.php
already prove our wrapper code matches whatever SDK version is installed.

This script is the other axis: has Sent.dm's API surface moved since the
installed SDK was generated? It used to answer that by comparing
`.stats.yml`'s `openapi_spec_hash` between the installed and latest SDK tags.
Stainless dropped that field, and Sent's VP of Eng confirmed (Aug 2026)
they've changed how they use Stainless, so it isn't coming back.

The source of truth is now the live spec Sent.dm's own team pointed us at:
  https://api.sent.dm/swagger/v3/swagger.json
This compares its endpoint count against `configured_endpoints` in the
*installed* SDK version's own `.stats.yml` (a fixed, tag-pinned read — not a
comparison across two moving tags, so there's nothing here for Stainless to
drop out from under us again). No local copy of the spec is kept; both
sides are fetched fresh on every run.

This is a coarse signal, not a diff: it tells you *that* the endpoint count
moved, not what changed. Read the live spec at the URL above to see what's
new before running `composer update sentdm/sent-dm-php`.

Covers the *management* API surface only — it does not document delivered
webhook payload shapes, so it can't catch a field rename like `sub_type` ->
`event`. `changelog_drift.py` covers that.

Informational only — exits 0 always. Findings are logged for the owner to
act on, not a CI failure, since we cannot fix an upstream spec change from
this repo.
"""

from __future__ import annotations

import json
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

REPO = "sentdm/sent-dm-php"
COMPOSER_LOCK = "composer.lock"
FINDINGS_FILE = "openapi/SDK_DRIFT.md"
LIVE_SPEC_URL = "https://api.sent.dm/swagger/v3/swagger.json"
HTTP_METHODS = {"get", "post", "put", "patch", "delete", "head", "options"}


def fetch(url: str) -> str:
    # api.sent.dm 403s the default `Python-urllib/x.y` user agent (GitHub's
    # raw host doesn't care either way, so send it everywhere for simplicity).
    request = urllib.request.Request(url, headers={"User-Agent": "spec_drift.py"})
    with urllib.request.urlopen(request, timeout=15) as response:
        return response.read().decode("utf-8")


def installed_version() -> str:
    with open(COMPOSER_LOCK, encoding="utf-8") as fh:
        lock = json.load(fh)
    for package in lock["packages"]:
        if package["name"] == "sentdm/sent-dm-php":
            return package["version"]
    raise SystemExit("sentdm/sent-dm-php not found in composer.lock")


def installed_configured_endpoints(version: str) -> int | None:
    """Read `configured_endpoints` from the installed SDK's own `.stats.yml`.

    This is a single, tag-pinned historical read (what that release was
    actually generated from) — not a comparison against a moving "latest"
    tag, so it isn't affected by Stainless changing what it writes going
    forward.
    """
    url = f"https://raw.githubusercontent.com/{REPO}/{version}/.stats.yml"
    text = fetch(url)
    for line in text.splitlines():
        m = re.match(r"^configured_endpoints:\s*(\d+)\s*$", line.strip())
        if m:
            return int(m.group(1))
    return None


def live_endpoint_count() -> int:
    spec = json.loads(fetch(LIVE_SPEC_URL))
    return sum(
        1
        for operations in spec.get("paths", {}).values()
        for method in operations
        if method.lower() in HTTP_METHODS
    )


def main() -> int:
    installed = installed_version()

    try:
        installed_endpoints = installed_configured_endpoints(installed)
        live_endpoints = live_endpoint_count()
    except urllib.error.URLError as exc:
        print(f"spec_drift: could not reach a required source ({exc}); skipping (informational check only).")
        return 0

    if installed_endpoints is None:
        print(
            f"spec_drift: `configured_endpoints` not found in {installed}'s .stats.yml. "
            "Not reporting clean; update this script to match the current .stats.yml shape."
        )
        return 0

    if live_endpoints == installed_endpoints:
        print(
            f"SDK spec drift: clean. Installed `sentdm/sent-dm-php` {installed} "
            f"({installed_endpoints} endpoints) matches the live spec ({live_endpoints} endpoints)."
        )
        return 0

    finding = (
        f"- Installed `sentdm/sent-dm-php` is `{installed}`, generated from {installed_endpoints} endpoints.\n"
        f"- The live Sent.dm spec (`{LIVE_SPEC_URL}`) currently has {live_endpoints} endpoints.\n"
        "- The installed SDK's endpoint count no longer matches the live API. Read the spec "
        "at the URL above for what changed, then `composer update sentdm/sent-dm-php` and "
        "re-run the wrapper audit for anything that applies.\n"
    )

    print("SDK spec drift detected:\n")
    print(finding)

    Path(FINDINGS_FILE).parent.mkdir(parents=True, exist_ok=True)
    with open(FINDINGS_FILE, "a", encoding="utf-8") as fh:
        fh.write(finding)

    return 0


if __name__ == "__main__":
    sys.exit(main())
