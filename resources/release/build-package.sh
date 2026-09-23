#!/usr/bin/env bash
#
# Build the InvoiceMuse release package from the committed tree.
#
# Usage: resources/release/build-package.sh <version> <output-dir>
#   <version>     Release version without the "v" prefix, e.g. 1.0.0
#   <output-dir>  Receives invoicemuse-v<version>.zip and invoicemuse-v<version>.zip.sha256
#
# Needs git, php, composer, node, yarn, zip and sha256sum on PATH.
#
# The package holds only this repository's code, in English. It leaves out Crowdin
# translations and the InvoicePlane-Themes and InvoicePlane-e-invoices repositories,
# which state no license; .github/docs/INSTALLATION.md explains how to add those.

set -euo pipefail

VERSION="${1:?usage: build-package.sh <version> <output-dir>}"
OUT_DIR="${2:?usage: build-package.sh <version> <output-dir>}"
ROOT="$(git -C "$(dirname "$0")" rev-parse --show-toplevel)"

app_version="$(sed -n "s/.*define('INVOICEMUSE_VERSION', '\([^']*\)').*/\1/p" "$ROOT/application/config/constants.php")"
package_version="$(sed -n 's/^ *"version": "\([^"]*\)".*/\1/p' "$ROOT/package.json" | head -n 1)"
if [ "$VERSION" != "$app_version" ] || [ "$VERSION" != "$package_version" ]; then
    echo "Version mismatch: requested $VERSION, INVOICEMUSE_VERSION is $app_version, package.json is $package_version" >&2
    exit 1
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

git -C "$ROOT" archive --format=tar HEAD | tar -x -C "$WORK"
cd "$WORK"

composer install --no-dev --no-interaction --optimize-autoloader --no-progress
# vendor/ sits in the web root, so drop package test suites and repository metadata
find vendor -type d \( -name tests -o -name Tests -o -name .github \) -prune -exec rm -rf {} +
yarn install --frozen-lockfile --non-interactive
yarn build

# The same runtime files as resources/docker/Containerfile, plus install and license docs
STAGE="$WORK/_package/invoicemuse"
mkdir -p "$STAGE"
cp -R application assets storage uploads vendor "$STAGE/"
cp favicon.ico index.php robots.txt htaccess ipconfig.php.example LICENSE.txt README.md SECURITY.md "$STAGE/"
cp .github/CHANGELOG.md "$STAGE/CHANGELOG.md"
cp .github/docs/INSTALLATION.md .github/docs/UPGRADE.md "$STAGE/"

PACKAGE="invoicemuse-v${VERSION}.zip"
mkdir -p "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"
rm -f "$OUT_DIR/$PACKAGE" "$OUT_DIR/$PACKAGE.sha256"
(cd "$WORK/_package" && zip -q -r -X "$OUT_DIR/$PACKAGE" invoicemuse)
(cd "$OUT_DIR" && sha256sum "$PACKAGE" > "$PACKAGE.sha256")

echo "Built $OUT_DIR/$PACKAGE"
