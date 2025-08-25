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
- Single version bump updates both `composer.json` and `package.json`

### **Release Process**
1. **Main Workflow** (`release.yml`): Coordinates the entire release process
   - Syncs versions between packages
   - Publishes to both npm and Packagist
   - Creates GitHub release with assets
   
2. **Individual Workflows**: Can still be run separately if needed
   - `publish-npm.yml`: Publishes n8n extension to npm
   - `publish-packagist.yml`: Publishes Laravel package to Packagist

### **Triggers**
- **Automatic**: Push a new version tag (e.g., `v2.1.1`)
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

The main `release.yml` workflow:
1. **Prepares Release**: Syncs versions and commits changes
2. **Publishes npm**: Builds and publishes n8n extension
3. **Publishes Packagist**: Tests and publishes Laravel package
4. **Creates Release**: Generates GitHub release with both packages
