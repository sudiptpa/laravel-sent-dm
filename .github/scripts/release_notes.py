#!/usr/bin/env python3
"""Print one version's CHANGELOG.md section plus its compare link, as a starting draft
for that version's GitHub release note.

This package's release notes have drifted from CHANGELOG.md by hand more than once (a
stray em dash, a stale endpoint count, a hand-typed compare link pointing at the wrong
prior version), because writing them meant re-deriving those details from scratch in a
second file. This script fixes the parts that were pure transcription error: it gets
the facts and the compare link right every time.

It does NOT produce a publish-ready release note on its own. This package's own
CHANGELOG.md entries are commit-message detailed; the actual published release notes (see v1.2.1,
v1.3.0, v1.3.1) are shorter than that, still hand-condensed and run through the usual
plain-language pass. Use this script's output as the source facts to condense from, not
as the final text.

Usage:
    python3 .github/scripts/release_notes.py 1.3.1
    python3 .github/scripts/release_notes.py 1.3.1 > /tmp/changelog-section.md
    # then condense /tmp/changelog-section.md into the actual release note by hand
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

CHANGELOG = "CHANGELOG.md"
REPO = "sudiptpa/laravel-sent-dm"

HEADING_RE = re.compile(r"^## \[(?P<version>[^\]]+)\](?: - .+)?$")


def main() -> int:
    if len(sys.argv) != 2:
        print(f"usage: {sys.argv[0]} <version>  (e.g. 1.3.1, no leading v)", file=sys.stderr)
        return 1

    target = sys.argv[1].lstrip("v")
    lines = Path(CHANGELOG).read_text(encoding="utf-8").splitlines()

    versions_in_order: list[str] = []
    sections: dict[str, list[str]] = {}
    current: str | None = None

    for line in lines:
        match = HEADING_RE.match(line)
        if match:
            current = match.group("version")
            versions_in_order.append(current)
            sections[current] = []
            continue
        if current is not None:
            sections[current].append(line)

    if target not in sections:
        print(f"release_notes: no CHANGELOG.md entry for version {target}", file=sys.stderr)
        return 1

    body = "\n".join(sections[target]).strip("\n")

    idx = versions_in_order.index(target)
    previous = versions_in_order[idx + 1] if idx + 1 < len(versions_in_order) else None

    print(body)
    if previous is not None:
        print()
        print(f"**Full changelog**: https://github.com/{REPO}/compare/v{previous}...v{target}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
