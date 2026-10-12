#!/bin/bash
#
# Builds build/afrihost/tribe.zip: the app with its runtime packages (no dev tools, tests or docs), to
# upload to ~/tribe on Afrihost and extract there. Composer isn't available in Afrihost's jailed
# Terminal, so vendor/ ships in the zip. See docs/DEPLOY-AFRIHOST.md.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
work="$(mktemp -d)"
out="$root/build/afrihost"
trap 'rm -rf "$work"' EXIT

export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1

git -C "$root" archive --format=tar HEAD | tar -x -C "$work"
cd "$work"

# --prefer-source works where the GitHub zip API is blocked (Claude Code on the web); dist elsewhere.
composer install --no-dev --optimize-autoloader --no-scripts --no-progress --prefer-source

# Keep the zip small: no git folders, tests, docs or test fixtures from the packages.
find vendor -name .git -type d -prune -exec rm -rf {} +
for d in tests Tests test docs doc .github examples benchmarks test_files fixtures Fixtures; do
    find vendor -mindepth 2 -maxdepth 3 -type d -name "$d" -prune -exec rm -rf {} +
done
find vendor -type f \( -name '*.md' -o -name 'phpunit.xml*' \) -delete
rm -f bootstrap/cache/*.php

mkdir -p "$out"
rm -f "$out/tribe.zip"
zip -qr "$out/tribe.zip" . -x 'tests/*' '.github/*' '.claude/*' 'scripts/*' 'docs/*' 'phpunit.xml' 'phpstan.neon' \
    '.styleci.yml' 'CLAUDE.md' '.env' '.env.example'

echo "Built $out/tribe.zip ($(du -h "$out/tribe.zip" | cut -f1))"
