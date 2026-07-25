#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

python3 - "$repo_root" <<'PY'
from __future__ import annotations

import json
import re
import sys
from collections import Counter
from pathlib import Path

root = Path(sys.argv[1]).resolve()
errors: list[str] = []

markdown_files = [
    root / "AGENTS.md",
    root / "README.md",
    root / "CONTEXT.md",
    root / "scripts/README.md",
    *sorted((root / "docs").rglob("*.md")),
]

required_fields = ("title:", "status:", "owner:", "last_verified:", "scope:")

for path in markdown_files:
    if not path.is_file():
        errors.append(f"missing Markdown file: {path.relative_to(root)}")
        continue

    lines = path.read_text(encoding="utf-8").splitlines()
    relative = path.relative_to(root)

    if not lines or lines[0] != "---":
        errors.append(f"frontmatter must start on line 1: {relative}")
        continue

    try:
        closing = lines[1:20].index("---") + 1
    except ValueError:
        errors.append(f"frontmatter is not closed near the top: {relative}")
        continue

    frontmatter = "\n".join(lines[1:closing])
    for field in required_fields:
        if not re.search(rf"(?m)^{re.escape(field)}\s*\S", frontmatter):
            errors.append(f"missing frontmatter field {field[:-1]}: {relative}")

link_pattern = re.compile(r"(?<!!)\[[^\]]+\]\(([^)]+)\)")

for path in markdown_files:
    if not path.is_file():
        continue

    text = path.read_text(encoding="utf-8")
    for raw_target in link_pattern.findall(text):
        target = raw_target.strip().strip("<>")
        if not target or target.startswith(("#", "http://", "https://", "mailto:")):
            continue

        target = target.split("#", 1)[0]
        target = target.split(" ", 1)[0]
        if not target:
            continue

        resolved = (root / target.lstrip("/")) if target.startswith("/") else (path.parent / target)
        if not resolved.resolve().exists():
            errors.append(
                f"broken link in {path.relative_to(root)}: {raw_target}"
            )

adr_files = sorted((root / "docs/adr").glob("[0-9][0-9][0-9][0-9]-*.md"))
adr_ids = [path.name[:4] for path in adr_files]
for adr_id, count in Counter(adr_ids).items():
    if count > 1:
        errors.append(f"duplicate ADR id {adr_id}")

registry = (root / "docs/README.md").read_text(encoding="utf-8")
for required_path in (
    "docs/project-overview-pdr.md",
    "docs/system-architecture.md",
    "docs/design-guidelines.md",
    "docs/deployment-guide.md",
    "docs/portal-repos.md",
    "docs/rules/",
    "docs/adr/",
    "docs/api/",
    "docs/features/",
):
    if required_path not in registry and required_path.removeprefix("docs/") not in registry:
        errors.append(f"documentation registry is missing: {required_path}")

grading_pack = root / "docs/features/academic/metropolia-grading-schemes.json"
try:
    decoded = json.loads(grading_pack.read_text(encoding="utf-8"))
    if not isinstance(decoded.get("schemes"), dict):
        errors.append("grading scheme pack must contain a schemes object")
except (OSError, json.JSONDecodeError) as exception:
    errors.append(f"invalid grading scheme pack: {exception}")

for forbidden in (
    "docs/stories/",
    "docs/superpowers/",
    "docs/decisions/",
    "docs/HARNESS",
    "docs/TEST_MATRIX.md",
    "docs/inertiajs-vue-info.md",
    "docs/codebase-summary.md",
):
    for path in markdown_files:
        if path.is_file() and forbidden in path.read_text(encoding="utf-8"):
            errors.append(f"stale documentation reference {forbidden}: {path.relative_to(root)}")

if errors:
    print("Documentation validation failed:", file=sys.stderr)
    for error in sorted(set(errors)):
        print(f"- {error}", file=sys.stderr)
    raise SystemExit(1)

print(
    f"Documentation validation passed: {len(markdown_files)} Markdown files, "
    f"{len(adr_files)} ADRs."
)
PY
