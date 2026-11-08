#!/usr/bin/env python3
"""Check the authoritative homepage and the integrity manifest.

Run from the repository root:

    python3 tests/verify_homepage.py
"""

from __future__ import annotations

import hashlib
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
HOME = ROOT / "public-current" / "qabbalah.institute" / "index.html"
PREVIEW = ROOT / "preview" / "index.html"
MANIFEST = ROOT / "provenance" / "integrity-manifest.json"
MANIFEST_SHA = ROOT / "provenance" / "integrity-manifest.sha256"

EXPECTED_SHA256 = "d130153a74f4a2812a2f6f36b6ded42d50028d383d42c641726b741f8122756d"
EXPECTED_BYTES = 83168
SECTION_IDS = (
    "home",
    "curriculum",
    "lodges",
    "membership",
    "members",
    "hebrew",
    "greek",
    "copyright",
    "zerodegree",
)
MEMBERS_LINK = "https://qabbalah.institute/members/"


def fail(message: str) -> None:
    print(f"FAIL: {message}")
    sys.exit(1)


def main() -> None:
    for path in (HOME, PREVIEW, MANIFEST, MANIFEST_SHA):
        if not path.is_file():
            fail(f"missing {path.relative_to(ROOT)}")

    home = HOME.read_bytes()
    preview = PREVIEW.read_bytes()
    digest = hashlib.sha256(home).hexdigest()
    if len(home) != EXPECTED_BYTES:
        fail(f"homepage is {len(home)} bytes, expected {EXPECTED_BYTES}")
    if digest != EXPECTED_SHA256:
        fail("homepage SHA-256 does not match the authoritative production artifact")
    if preview != home:
        fail("preview/index.html is not a byte-identical copy of the authoritative homepage")

    text = home.decode("utf-8")
    ids = re.findall(r'\bid\s*=\s*["\']([^"\']+)["\']', text, flags=re.I)
    if tuple(ids) != SECTION_IDS:
        fail(f"section ids are {ids}, expected {list(SECTION_IDS)}")
    if re.search(r"<iframe\b", text, flags=re.I):
        fail("homepage contains an iframe")
    if MEMBERS_LINK not in text:
        fail("homepage is missing the absolute members link")

    preview_files = sorted(p.name for p in (ROOT / "preview").iterdir() if p.is_file())
    if preview_files != ["index.html"]:
        fail(f"preview/ contains {preview_files}, expected only index.html")
    if list((ROOT / "preview").rglob("*.php")):
        fail("preview/ contains PHP")

    manifest_bytes = MANIFEST.read_bytes()
    recorded = MANIFEST_SHA.read_text(encoding="utf-8").split()[0]
    if hashlib.sha256(manifest_bytes).hexdigest() != recorded:
        fail("integrity-manifest.sha256 does not match integrity-manifest.json")

    manifest = json.loads(manifest_bytes)
    if manifest["authoritative_homepage"]["sha256"] != EXPECTED_SHA256:
        fail("manifest authoritative hash differs")
    if list(manifest["authoritative_homepage"]["section_ids"]) != list(SECTION_IDS):
        fail("manifest section ids differ")

    account_rx = re.compile(r"wzukusers/user-\d+/sites/\d+")
    for record in manifest["files"]:
        path = ROOT / record["path"]
        if not path.is_file():
            fail(f"manifest path missing: {record['path']}")
        data = path.read_bytes()
        if len(data) != record["bytes"] or hashlib.sha256(data).hexdigest() != record["sha256"]:
            fail(f"manifest hash mismatch: {record['path']}")
        if account_rx.search(data.decode("utf-8", "replace")):
            fail(f"host-account path remains in {record['path']}")

    print(f"ok {digest} {EXPECTED_BYTES} bytes; {len(SECTION_IDS)} section ids; manifest {len(manifest['files'])} files")


if __name__ == "__main__":
    main()
