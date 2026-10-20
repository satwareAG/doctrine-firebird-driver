#!/usr/bin/env python3
"""
IPADP L2 Privacy Validation: scan tracked repo files for private forge URLs.

Ensures no private forge URLs appear in public repo files. Per IPADP rules,
public-to-private linking is FORBIDDEN.

Forbidden hostnames are constructed at runtime (not stored as literals) so
this script does not flag itself.

Also forbidden: filesystem paths of the form `<home-dir>/internal/<project>`
(checkout locations of private repos are environment-specific and must never
be recorded in this public repo; issue #632).

Exit codes:
  0 = PASS (no private forge URLs found)
  1 = FAIL (private forge URLs found, printed to stderr)

Usage:
  python3 scripts/test_privacy_rules.py
"""

from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path

# Forbidden hostnames - constructed at runtime to avoid self-flagging.
# jane: literal hostnames would make the scanner find itself; assembling
# from fragments keeps the scanner self-policing.
_GL = "gitlab" + "." + "satware" + "." + "com"
_GT = "git" + "." + "satware" + "." + "ai"

FORBIDDEN = [
    (_GL, re.compile(re.escape(_GL))),
    (_GT, re.compile(re.escape(_GT))),
]

# Forbidden path shapes: home-relative checkouts inside the internal tree.
# Assembled at runtime so this scanner never matches its own source (issue
# #632). Covers tilde, dollar-HOME and braced-HOME spellings, with or without
# a trailing separator.
_HOME_TILDE = chr(126)                       # '~'
_HOME_DOLLAR = chr(36) + "HOME"              # '$HOME'
_HOME_BRACED = chr(36) + chr(123) + "HOME" + chr(125)   # '${HOME}'
_INTERNAL_PATH = re.compile(
    "(?:" + re.escape(_HOME_TILDE)
    + "|" + re.escape(_HOME_BRACED)
    + "|" + re.escape(_HOME_DOLLAR) + ")"
    + re.escape(chr(47) + "internal")
    + "(?:" + chr(47) + "|(?![A-Za-z0-9]))"
)

# Directories to skip (not tracked or not relevant).
SKIP_DIRS = {".git", "vendor", "node_modules", "__pycache__"}

# File extensions to skip (binary or non-text).
SKIP_EXTENSIONS = {
    ".png", ".jpg", ".jpeg", ".gif", ".bmp", ".ico", ".so", ".dll",
    ".dylib", ".a", ".o", ".lo", ".la", ".exe", ".zip", ".tar", ".gz",
    ".tgz", ".bz2", ".xz", ".fdb", ".fbk", ".gdb", ".dat", ".bin",
    ".pdf", ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx",
}

# Files to skip by name.
SKIP_NAMES = {".gitignore", ".gitattributes", ".gitmodules"}


def get_tracked_files() -> list[str]:
    """Return list of git-tracked files."""
    result = subprocess.run(
        ["git", "ls-files"],
        capture_output=True,
        text=True,
        check=True,
    )
    return [f for f in result.stdout.strip().splitlines() if f]


def should_scan(path: Path) -> bool:
    """Return True if the file should be scanned."""
    if path.name in SKIP_NAMES:
        return False
    if path.suffix.lower() in SKIP_EXTENSIONS:
        return False
    for part in path.parts:
        if part in SKIP_DIRS:
            return False
    return True


def scan_file(content: bytes) -> list[tuple[int, str, str]]:
    """Scan file content for forbidden hostnames and path shapes.

    Returns list of (line_no, line, what) where `what` is a hostname or the
    path-shape label.
    """
    findings: list[tuple[int, str, str]] = []
    text = content.decode("utf-8", errors="replace")

    for lineno, line in enumerate(text.splitlines(), 1):
        for hostname, pattern in FORBIDDEN:
            if pattern.search(line):
                findings.append((lineno, line.strip(), hostname))
        if _INTERNAL_PATH.search(line):
            findings.append((lineno, line.strip(), "<home>/internal/ path"))
    return findings


def main() -> int:
    files = get_tracked_files()
    total_findings = 0

    for filepath in files:
        path = Path(filepath)
        if not should_scan(path):
            continue
        try:
            content = path.read_bytes()
        except (OSError, PermissionError):
            continue

        findings = scan_file(content)
        for lineno, line, hostname in findings:
            print(
                f"[FAIL] {filepath}:{lineno}: found '{hostname}' in: {line[:120]}",
                file=sys.stderr,
            )
            total_findings += 1

    if total_findings > 0:
        hostnames = ", ".join(h for h, _ in FORBIDDEN)
        print(f"\n[ERROR] {total_findings} private reference(s) found.", file=sys.stderr)
        print(
            f"Forbidden here: private forge URLs ({hostnames}) and "
            "<home>/internal/ checkout paths (see #632).",
            file=sys.stderr,
        )
        print("See: IPADP L2 Privacy Validation (issue #567).", file=sys.stderr)
        return 1

    print("[PASS] No private forge URLs or internal checkout paths found.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
