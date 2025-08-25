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

1. **npm Publishing**: Automatically publishes when you push a new version tag (e.g., `v2.1.0`)
2. **Packagist Publishing**: Automatically publishes when you push a new version tag (e.g., `v2.1.0`)
3. **Manual Trigger**: Both workflows can be run manually via workflow dispatch

## Testing

1. Push these workflow files to your repository
2. Go to Actions tab to see the workflows
3. Test with manual workflow dispatch
4. Push a new version tag to test automatic publishing:
   ```bash
   git tag v2.1.1
   git push origin v2.1.1
   ```
