#!/bin/bash

PLUGIN_SLUG="klscms-connector"
VERSION=$(grep "Version:" klscms-connector.php | head -1 | awk '{print $2}')
OUTPUT="${PLUGIN_SLUG}-v${VERSION}.zip"

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Remove old build
rm -f "${OUTPUT}"
rm -rf "/tmp/${PLUGIN_SLUG}"

# Create temp directory with correct structure
mkdir -p "/tmp/${PLUGIN_SLUG}"

# Copy plugin files
cp -r . "/tmp/${PLUGIN_SLUG}/"

# Remove unwanted files
rm -rf "/tmp/${PLUGIN_SLUG}/.git"
rm -rf "/tmp/${PLUGIN_SLUG}/.gitignore"
rm -rf "/tmp/${PLUGIN_SLUG}/build.sh"
rm -rf "/tmp/${PLUGIN_SLUG}/node_modules"
rm -rf "/tmp/${PLUGIN_SLUG}/*.zip"

# Create zip from parent of temp dir
cd /tmp
zip -r "${OLDPWD}/${OUTPUT}" "${PLUGIN_SLUG}/"

# Cleanup
rm -rf "/tmp/${PLUGIN_SLUG}"

echo "✓ Created: ${OUTPUT}"
echo "  Structure:"
unzip -l "${OLDPWD}/${OUTPUT}" | head -20
