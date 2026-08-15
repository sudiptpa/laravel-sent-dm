#!/usr/bin/env python3
"""Platform changelog drift: Sent.dm's own API changelog vs our last-reviewed copy.

method_audit.php and shape_audit.php check our wrapper against the installed SDK's
classes and methods. spec_drift.py checks the installed SDK version against the
latest one. Neither can see a change that never touches a class or method name:
a new value on a field that was always a plain string, like a message status or a
webhook event type. That is exactly the kind of change that slipped past this
package before: Sent.dm renamed the webhook envelope field from `sub_type` to
`event`, and later added three new message statuses (FILTERED, BLOCKED,
SCHEDULED), and none of the other three checks noticed either one.

Sent.dm's own changelog page is the one place this kind of change is written
down. This script fetches it and compares it against a snapshot committed in
this repo (.github/CHANGELOG_SNAPSHOT.txt). If the live page has changed, it
writes the difference to a findings file for a person to read.

This script does not try to guess what changed or whether it matters. It only
says "this page is different from the last time someone looked at it." Fixing
the snapshot (copying the live text over it) is a deliberate, separate step a
person takes after reading the diff and deciding it's been handled.

Informational only. Exits 0 always, same as spec_drift.py.
"""

from __future__ import annotations

import difflib
import sys
import urllib.error
import urllib.request
from pathlib import Path

CHANGELOG_URL = "https://docs.sent.dm/llms/reference/changelog.txt"
SNAPSHOT_FILE = ".github/CHANGELOG_SNAPSHOT.txt"
FINDINGS_FILE = "openapi/CHANGELOG_DRIFT.md"


def fetch(url: str) -> str:
    # docs.sent.dm returns 403 to urllib's default user agent; a normal browser
    # user agent gets through. GitHub's raw/API hosts (used by spec_drift.py)
    # don't have this problem, so this header lives here, not in a shared helper.
    request = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(request, timeout=15) as response:
        return response.read().decode("utf-8")


def main() -> int:
    try:
        live = fetch(CHANGELOG_URL)
    except urllib.error.URLError as exc:
        print(f"changelog_drift: could not reach docs.sent.dm ({exc}); skipping (informational check only).")
        return 0

    snapshot_path = Path(SNAPSHOT_FILE)

    if not snapshot_path.exists():
        print(
            f"changelog_drift: no snapshot found at {SNAPSHOT_FILE}. "
            "Seed it with the current live changelog before this check has a baseline to compare against."
        )
        return 0

    snapshot = snapshot_path.read_text(encoding="utf-8")

    if live == snapshot:
        print("Changelog drift: clean. Live platform changelog matches the last-reviewed snapshot.")
        return 0

    diff = list(
        difflib.unified_diff(
            snapshot.splitlines(keepends=True),
            live.splitlines(keepends=True),
            fromfile="last-reviewed snapshot",
            tofile="live changelog",
        )
    )

    finding = (
        "The live Sent.dm platform changelog (docs.sent.dm/reference/api/changelog) has "
        "changed since it was last reviewed. This can mean a new status value, a new "
        "webhook event, or a behavior change on an endpoint this package already wraps. "
        "This is the kind of change method_audit.php, shape_audit.php, and spec_drift.py "
        "cannot see, since none of them look at runtime string values.\n\n"
        f"Read the diff below. Update this package's code, tests, and docs for anything "
        f"that applies. Then copy the live content over `{SNAPSHOT_FILE}` to mark it "
        "reviewed.\n\n"
        "```diff\n" + "".join(diff) + "\n```\n"
    )

    print("Changelog drift detected:\n")
    print(finding)

    Path(FINDINGS_FILE).parent.mkdir(parents=True, exist_ok=True)
    with open(FINDINGS_FILE, "a", encoding="utf-8") as fh:
        fh.write(finding)

    return 0


if __name__ == "__main__":
    sys.exit(main())
