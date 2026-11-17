#!/bin/bash
# Builds the installable zip of Bonifica's free edition (local_bonifica):
#   dist/local_bonifica-free.zip
# It unpacks to a top folder "bonifica/", which goes in <moodle>/local/.
# This repository holds only the free edition. Bonifica Pro is a separate
# build that buyers receive; it installs over this one with the same data.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

mkdir -p "$DIST" "$WORK/bonifica"
rm -f "$DIST/local_bonifica-free.zip"
git -C "$ROOT" archive HEAD | tar -x -C "$WORK/bonifica"
find "$WORK/bonifica" \( -name '.DS_Store' -o -name '*.log' \) -delete
(cd "$WORK" && zip -qrX "$DIST/local_bonifica-free.zip" bonifica)
echo "Built dist/local_bonifica-free.zip ($(unzip -Z1 "$DIST/local_bonifica-free.zip" | wc -l) entries)"
