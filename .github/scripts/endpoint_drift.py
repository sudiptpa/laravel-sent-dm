#!/usr/bin/env python3
"""Per-endpoint spec drift: each v3 operation's own required fields and property
list, not just how many operations exist.

spec_drift.py compares one number, the total endpoint count, between the
installed SDK and the live spec. That is exactly what missed this package's own
`POST /v3/channels/rcs` incident: the operation count never changed, only its
`required` list grew from 3 fields to 24. A whole-API count has no way to see
that.

This script walks every `/v3/*` operation in the live spec one at a time,
records its request body's `required` list and property names, and its success
response's top-level fields, then compares that against a snapshot committed in
this repo (.github/ENDPOINT_SNAPSHOT.json). Any operation whose shape changed is
reported on its own line, not folded into a single pass/fail count. A brand new
or removed operation is reported the same way.

Deliberately shallow: only the request body's own top-level fields and the
response's top-level `data` fields are recorded, not full nested schemas. Good
enough to catch a required-field or field-rename change (what actually broke
this package before); a change three levels deep inside a nested object would
still need a person reading the spec, same as spec_drift.py's own trade-off.

Informational only, exits 0 always, same as spec_drift.py and changelog_drift.py.
Updating the snapshot (running this script with --update and committing the
result) is a deliberate, separate step a person takes after reading the diff.
"""

from __future__ import annotations

import json
import sys
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

LIVE_SPEC_URL = "https://api.sent.dm/swagger/v3/swagger.json"
SNAPSHOT_FILE = ".github/ENDPOINT_SNAPSHOT.json"
FINDINGS_FILE = "openapi/ENDPOINT_DRIFT.md"
HTTP_METHODS = ("get", "post", "put", "patch", "delete")


def fetch(url: str) -> str:
    request = urllib.request.Request(url, headers={"User-Agent": "endpoint_drift.py"})
    with urllib.request.urlopen(request, timeout=15) as response:
        return response.read().decode("utf-8")


def resolve(schema: Any, components: dict[str, Any]) -> dict[str, Any]:
    """Follow $ref/allOf/oneOf until we reach a plain object or array schema."""
    if not isinstance(schema, dict):
        return {}

    if "$ref" in schema:
        return resolve(components[schema["$ref"].rsplit("/", 1)[-1]], components)

    if "allOf" in schema:
        # `required`/`properties` can sit directly on the schema, as a sibling of
        # `allOf`, not only nested inside one of its branches (found live on
        # AddRcsChannelRequest: its 24-field `required` list is a top-level key,
        # `allOf` only carries the shared base fields).
        merged: dict[str, Any] = {
            "properties": dict(schema.get("properties", {})),
            "required": list(schema.get("required", [])),
        }
        for sub in schema["allOf"]:
            resolved = resolve(sub, components)
            merged["properties"].update(resolved.get("properties", {}))
            merged["required"] = sorted(set(merged["required"]) | set(resolved.get("required", [])))
            if "type" not in merged and "type" in resolved:
                merged["type"] = resolved["type"]
            if "items" in resolved:
                merged["items"] = resolved["items"]
        return merged

    if "oneOf" in schema and schema["oneOf"]:
        return resolve(schema["oneOf"][0], components)

    return schema


def data_field_names(response_schema: dict[str, Any], components: dict[str, Any]) -> list[str]:
    """Top-level field names under the envelope's `data` key, list-item fields if
    `data` is itself an array, or [] if there's no body (e.g. a 204 delete)."""
    envelope = resolve(response_schema, components)
    data_schema = envelope.get("properties", {}).get("data")
    if data_schema is None:
        return []

    resolved = resolve(data_schema, components)
    if resolved.get("type") == "array":
        resolved = resolve(resolved.get("items", {}), components)

    return sorted(resolved.get("properties", {}).keys())


def endpoint_fingerprint(operation: dict[str, Any], components: dict[str, Any]) -> dict[str, list[str]]:
    request_schema = (
        operation.get("requestBody", {})
        .get("content", {})
        .get("application/json", {})
        .get("schema")
    )
    request = resolve(request_schema, components) if request_schema else {}

    success_response = next(
        (r for code, r in sorted(operation.get("responses", {}).items()) if code.startswith("2")),
        None,
    )
    response_schema = (
        (success_response or {}).get("content", {}).get("application/json", {}).get("schema")
    )

    return {
        "request_required": sorted(request.get("required", [])),
        "request_properties": sorted(request.get("properties", {}).keys()),
        "response_fields": data_field_names(response_schema, components) if response_schema else [],
    }


def live_fingerprints() -> dict[str, dict[str, list[str]]]:
    spec = json.loads(fetch(LIVE_SPEC_URL))
    components = spec.get("components", {}).get("schemas", {})

    fingerprints = {}
    for path, operations in spec.get("paths", {}).items():
        if not path.startswith("/v3/"):
            continue
        for method, operation in operations.items():
            if method.lower() not in HTTP_METHODS:
                continue
            key = f"{method.upper()} {path}"
            fingerprints[key] = endpoint_fingerprint(operation, components)

    return fingerprints


def diff_endpoint(name: str, old: dict[str, list[str]], new: dict[str, list[str]]) -> list[str]:
    lines = []
    for field in ("request_required", "request_properties", "response_fields"):
        added = sorted(set(new[field]) - set(old[field]))
        removed = sorted(set(old[field]) - set(new[field]))
        if added:
            lines.append(f"  + {field}: {', '.join(added)}")
        if removed:
            lines.append(f"  - {field}: {', '.join(removed)}")
    return lines


def main() -> int:
    try:
        live = live_fingerprints()
    except urllib.error.URLError as exc:
        print(f"endpoint_drift: could not reach api.sent.dm ({exc}); skipping (informational check only).")
        return 0

    if "--update" in sys.argv:
        Path(SNAPSHOT_FILE).write_text(json.dumps(live, indent=2, sort_keys=True) + "\n", encoding="utf-8")
        print(f"endpoint_drift: snapshot updated at {SNAPSHOT_FILE} ({len(live)} operations).")
        return 0

    snapshot_path = Path(SNAPSHOT_FILE)
    if not snapshot_path.exists():
        print(
            f"endpoint_drift: no snapshot found at {SNAPSHOT_FILE}. "
            "Run `python3 .github/scripts/endpoint_drift.py --update` to seed one."
        )
        return 0

    snapshot = json.loads(snapshot_path.read_text(encoding="utf-8"))

    added_endpoints = sorted(set(live) - set(snapshot))
    removed_endpoints = sorted(set(snapshot) - set(live))
    changed: dict[str, list[str]] = {}

    for name in sorted(set(live) & set(snapshot)):
        lines = diff_endpoint(name, snapshot[name], live[name])
        if lines:
            changed[name] = lines

    if not added_endpoints and not removed_endpoints and not changed:
        print(f"Endpoint drift: clean. All {len(live)} operations match the last-reviewed snapshot.")
        return 0

    report_lines = ["Endpoint drift detected, one operation at a time:\n"]
    for name in added_endpoints:
        report_lines.append(f"NEW      {name}")
    for name in removed_endpoints:
        report_lines.append(f"REMOVED  {name}")
    for name, lines in changed.items():
        report_lines.append(f"CHANGED  {name}")
        report_lines.extend(lines)

    report = "\n".join(report_lines) + "\n"
    print(report)

    finding = (
        "The live Sent.dm spec has changed on a per-operation basis since the last-reviewed "
        "snapshot. Read each line below, update this package's code, tests, and docs for "
        "anything that applies, then re-run with `--update` and commit the refreshed "
        f"`{SNAPSHOT_FILE}` to mark it reviewed.\n\n"
        "```\n" + report + "```\n"
    )

    Path(FINDINGS_FILE).parent.mkdir(parents=True, exist_ok=True)
    with open(FINDINGS_FILE, "a", encoding="utf-8") as fh:
        fh.write(finding)

    return 0


if __name__ == "__main__":
    sys.exit(main())
