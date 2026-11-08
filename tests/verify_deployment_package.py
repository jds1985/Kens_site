#!/usr/bin/env python3
"""Assert the files each Vercel project root would actually publish.

The repository-root .vercelignore is applied to the Git tree first, using
Git's own ignore rules. A project root then keeps only paths inside that
directory when files outside the root are not part of the deployment.
"""

from __future__ import annotations

import hashlib
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
HOME = ROOT / "public-current/qabbalah.institute/index.html"
HOME_SHA = "d130153a74f4a2812a2f6f36b6ded42d50028d383d42c641726b741f8122756d"
PREVIEW_FILES = ["index.html"]
FAMOUS_FILES = [
    "assets/placeholder.svg",
    "assets/reference.css",
    "demo.css",
    "demo.js",
    "images/6ad8c396a5c573c633339757_1792590849670_dca75a36.webp",
    "images/6ad8c396a5c573c633339757_1792590849688_ee6bdc27.webp",
    "images/6ad8c396a5c573c633339757_1792590874002_11c66410.jpg",
    "images/6ad8c396a5c573c633339757_1792590889838_0df1679b.jpg",
    "images/6ad8c396a5c573c633339757_1792590906457_600cf331.jpg",
    "images/6ad8c396a5c573c633339757_1792590922414_b65abc23.jpg",
    "images/6ad8c396a5c573c633339757_1792590943641_f10b807d.jpg",
    "images/6ad8c396a5c573c633339757_1792590959958_2ad94c40.jpg",
    "images/6ad8c396a5c573c633339757_1792590977323_9af36e6d.jpg",
    "images/6ad8c396a5c573c633339757_1792590994762_0a8594fc.jpg",
    "images/6ad8c396a5c573c633339757_1792591018024_43f6dbbf.jpg",
    "images/6ad8c396a5c573c633339757_1792591033518_b739d280.jpg",
    "images/6ad8c396a5c573c633339757_1792591049537_e1d06c06.jpg",
    "images/6ad8c396a5c573c633339757_1792591066281_d4d050a8.jpg",
    "index.html",
]
FORBIDDEN = (
    ".php",
    ".sql",
    ".htaccess",
    "config.php",
    "config.sample.php",
    "test_demo.mjs",
    "members-demo/",
    "members-fresh-",
    "members-source-only/",
    "historical-reference/",
    "provenance/",
    "public-current/",
    "public-snapshots/",
)


def fail(message: str) -> None:
    print(f"FAIL: {message}")
    sys.exit(1)


def tracked_files() -> list[str]:
    out = subprocess.check_output(["git", "ls-files", "-z"], cwd=ROOT)
    return [item.decode() for item in out.split(b"\0") if item]


def ignored_paths(files: list[str]) -> set[str]:
    ignore = (ROOT / ".vercelignore").read_text(encoding="utf-8")
    with tempfile.TemporaryDirectory() as tmp:
        subprocess.check_call(["git", "init", "-q"], cwd=tmp)
        root = Path(tmp)
        for rel in files:
            path = root / rel
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(b"")
        # Write after the placeholders so the repo's own .gitignore cannot replace it.
        (root / ".gitignore").write_text(ignore, encoding="utf-8")
        subprocess.check_call(["git", "add", "-A"], cwd=tmp)
        result = subprocess.run(
            ["git", "check-ignore", "--stdin"],
            cwd=tmp,
            input="\n".join(files) + "\n",
            text=True,
            capture_output=True,
        )
        if result.returncode not in (0, 1):
            fail(result.stderr or "git check-ignore failed")
        return {line for line in result.stdout.splitlines() if line}


def package(files: list[str], ignored: set[str], root_dir: str) -> list[str]:
    prefix = root_dir.strip("/") + "/"
    published = []
    for rel in files:
        if rel in ignored or not rel.startswith(prefix):
            continue
        published.append(rel[len(prefix):])
    return sorted(published)


def assert_clean(label: str, published: list[str]) -> None:
    blob = "\n".join(published)
    for token in FORBIDDEN:
        if token in blob or any(path.endswith(token) for path in published):
            fail(f"{label} package exposes {token}")
    if any(path.endswith(".php") or path.endswith(".sql") for path in published):
        fail(f"{label} package contains application source")


def main() -> None:
    home = HOME.read_bytes()
    if len(home) != 83168 or hashlib.sha256(home).hexdigest() != HOME_SHA:
        fail("authoritative homepage bytes changed")
    preview = (ROOT / "preview/index.html").read_bytes()
    if preview != home:
        fail("preview/index.html is not the authoritative homepage")

    files = tracked_files()
    ignored = ignored_paths(files)
    preview_pkg = package(files, ignored, "preview")
    famous_pkg = package(files, ignored, "famous-demo")
    if preview_pkg != PREVIEW_FILES:
        fail(f"preview package is {preview_pkg}, expected {PREVIEW_FILES}")
    if famous_pkg != FAMOUS_FILES:
        fail(f"famous package is {famous_pkg}, expected {FAMOUS_FILES}")
    assert_clean("preview", preview_pkg)
    assert_clean("famous", famous_pkg)

    private = [
        rel for rel in files
        if rel.startswith((
            "members-demo/",
            "members-fresh-20261107/",
            "members-source-only/",
            "historical-reference/",
            "provenance/",
            "public-current/",
            "public-snapshots/",
        ))
    ]
    leaked = [rel for rel in private if rel not in ignored]
    if leaked:
        fail("private paths were not ignored: " + ", ".join(leaked[:8]))
    if "famous-demo/index.html" in ignored or "famous-demo/styles.css" in ignored:
        fail("Famous runtime files are still ignored")
    if "preview/index.html" in ignored:
        fail("preview homepage is ignored")
    if "famous-demo/test_demo.mjs" not in ignored:
        fail("Famous test source would be published")

    print(f"ok preview {preview_pkg}; famous {famous_pkg}; ignored {len(ignored)} of {len(files)}")


if __name__ == "__main__":
    main()
