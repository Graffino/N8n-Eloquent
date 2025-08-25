#!/bin/bash

# Version synchronization script for n8n-eloquent monorepo
# This script ensures both packages have the same version

set -e

# Get the new version from command line or git tag
if [ -z "$1" ]; then
    echo "Usage: $0 <version>"
    echo "Example: $0 2.1.1"
    exit 1
fi

NEW_VERSION=$1
echo "🔄 Syncing versions to $NEW_VERSION..."

# Update composer.json (Laravel package)
echo "📦 Updating Laravel package version..."
sed -i.bak "s/\"version\": \"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" composer.json
rm composer.json.bak

# Update package.json (n8n extension)
echo "🔌 Updating n8n extension version..."
sed -i.bak "s/\"version\": \"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" n8n-extension/package.json
rm n8n-extension/package.json.bak

# Update package-lock.json if it exists
if [ -f "n8n-extension/package-lock.json" ]; then
    echo "🔒 Updating package-lock.json..."
    cd n8n-extension
    npm install --package-lock-only
    cd ..
fi

# Verify changes
echo "✅ Version sync completed!"
echo "📋 Current versions:"
echo "  - Laravel package: $(grep '"version"' composer.json)"
echo "  - n8n extension: $(grep '"version"' n8n-extension/package.json)"

# Show git status
echo ""
echo "📝 Git status:"
git status --porcelain | grep -E "(composer\.json|package\.json|package-lock\.json)" || echo "No version files changed"
