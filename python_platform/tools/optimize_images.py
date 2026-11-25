#!/usr/bin/env python3
"""Optimize existing raster images in place (backlog and generated artwork).

This is a manual maintenance tool, not a cron job: scheduling in this project
lives in ``schedules.yaml``. It only rewrites files that get smaller, keeps the
original extension and file mode, and never follows directory symlinks.

Examples::

    python3 python_platform/tools/optimize_images.py uploads/school_assets
    python3 python_platform/tools/optimize_images.py path/to/one.jpg --dry-run
"""

from __future__ import annotations

import argparse
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.image_optimization import EXTENSION_FORMAT, optimise_file


def collect(paths: list[str]) -> list[Path]:
    files: list[Path] = []
    for raw in paths:
        target = Path(raw)
        if target.is_file():
            if target.suffix.lower().lstrip(".") in EXTENSION_FORMAT:
                files.append(target)
            continue
        if not target.is_dir():
            continue
        for root, directories, names in os.walk(target, followlinks=False):
            directories[:] = [
                name for name in directories if not (Path(root) / name).is_symlink()
            ]
            for name in names:
                candidate = Path(root) / name
                if candidate.suffix.lower().lstrip(".") in EXTENSION_FORMAT:
                    files.append(candidate)
    return files


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("paths", nargs="+", help="files or directories to scan")
    parser.add_argument("--max-edge", type=int, default=1600)
    parser.add_argument("--quality", type=int, default=80)
    parser.add_argument(
        "--min-bytes",
        type=int,
        default=256 * 1024,
        help="skip files at or below this size (default: 256 KiB)",
    )
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args(argv)

    files = collect(args.paths)
    changed = 0
    saved = 0
    for path in files:
        if args.dry_run:
            result = None
        else:
            result = optimise_file(
                path,
                max_edge=args.max_edge,
                quality=args.quality,
                min_bytes=args.min_bytes,
            )
        if result:
            changed += 1
            saved += result["saved_bytes"]
            print(
                f"optimized {result['path']}: "
                f"{result['before_bytes']} -> {result['after_bytes']} bytes"
            )

    print(f"scanned {len(files)} image(s); optimized {changed}; saved {saved} bytes")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
