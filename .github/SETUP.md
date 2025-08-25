# GitHub Actions Setup Guide

## Required Secrets

### For npm Publishing (`publish-npm.yml`)

1. **NPM_TOKEN**
   - Go to [npmjs.com](https://www.npmjs.com/settings/tokens)
   - Create new "Automation" token
   - Add to GitHub repository secrets as `NPM_TOKEN`

### For Packagist Publishing (`publish-packagist.yml`)

1. **PACKAGIST_USERNAME**
   - Your Packagist username
   - Add to GitHub repository secrets as `PACKAGIST_USERNAME`

2. **PACKAGIST_TOKEN**
   - Go to [packagist.org](https://packagist.org/profile/)
   - Generate new API token
   - Add to GitHub repository secrets as `PACKAGIST_TOKEN`

## Self-Hosted Runners

The workflows use your self-hosted runners with these labels:
- `self-hosted`
- `graffino`
- `node18` (for npm publishing)
- `php8.3` (for Packagist publishing)

## How It Works

### **Version Synchronization**
- Both packages (Laravel + n8n extension) are kept at the same version
- The `scripts/sync-versions.sh` script ensures version consistency
- **Safe approach**: Versions are synced manually via PR, then released via tags

### **Release Process**
1. **Version Sync Workflow** (`sync-versions.yml`): Safely syncs versions
   - Creates a PR with version changes
   - No automatic git pushes or modifications
   
2. **Main Release Workflow** (`release.yml`): Publishes both packages
   - Triggers on version tags (e.g., `v2.1.1`)
   - Publishes to both npm and Packagist
   - Creates GitHub release with assets
   
3. **Individual Workflows**: Can still be run separately if needed
   - `publish-npm.yml`: Publishes n8n extension to npm
   - `publish-packagist.yml`: Publishes Laravel package to Packagist

### **Triggers**
- **Version Sync**: Manual workflow dispatch (creates PR)
- **Release**: Push a new version tag (e.g., `v2.1.1`)
- **Manual**: Use workflow dispatch with custom version input

## Testing

1. Push these workflow files to your repository
2. Go to Actions tab to see the workflows
3. Test with manual workflow dispatch (specify version)
4. Test automatic publishing by pushing a new version tag:
   ```bash
   git tag v2.1.1
   git push origin v2.1.1
   ```

## Release Workflow

### **Version Sync Workflow** (`sync-versions.yml`):
1. **Manual Trigger**: Run workflow dispatch with desired version
2. **Syncs Versions**: Updates both `composer.json` and `package.json`
3. **Creates PR**: Opens pull request for review
4. **Safe Process**: No automatic git pushes or modifications

### **Main Release Workflow** (`release.yml`):
1. **Triggers on Tag**: Runs when you push a version tag
2. **Publishes npm**: Builds and publishes n8n extension
3. **Publishes Packagist**: Tests and publishes Laravel package
4. **Creates Release**: Generates GitHub release with both packages

### **Typical Release Process**:
1. Run "Sync Package Versions" workflow with new version
2. Review and merge the PR
3. Create and push new tag: `git tag v2.1.1 && git push origin v2.1.1`
4. Release workflow automatically publishes both packages
