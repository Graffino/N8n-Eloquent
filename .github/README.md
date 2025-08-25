# GitHub Actions Workflows

This repository includes several GitHub Actions workflows to automate testing, building, and publishing.

## Workflows

### 1. Publish n8n-extension to npm (`publish-npm.yml`)

**Triggers:**
- New release published
- Manual workflow dispatch

**What it does:**
- Builds the n8n-extension package
- Publishes to npm registry
- Creates GitHub release assets

**Required Secrets:**
- `NPM_TOKEN`: Your npm authentication token

**Setup:**
1. Get your npm token from [npmjs.com](https://www.npmjs.com/settings/tokens)
2. Add it to your repository secrets as `NPM_TOKEN`
3. Make sure you're logged in to npm in your package.json

### 2. Test and Build n8n-extension (`test-build.yml`)

**Triggers:**
- Push to main/master branch
- Pull requests to main/master branch
- Only runs when n8n-extension files change

**What it does:**
- Installs dependencies
- Runs linting
- Builds the package
- Verifies build output

### 3. Test Laravel Package (`test-laravel.yml`)

**Triggers:**
- Push to main/master branch
- Pull requests to main/master branch
- Only runs when Laravel source files change

**What it does:**
- Tests against multiple PHP versions (8.1, 8.2, 8.3)
- Tests against multiple Laravel versions (10.*, 11.*, 12.*)
- Runs PHPUnit tests

### 4. Security and Dependencies (`security.yml`)

**Triggers:**
- Weekly (every Monday at 2 AM)
- Manual workflow dispatch

**What it does:**
- Runs npm audit
- Runs composer audit
- Uploads security scan results as artifacts

## Setup Instructions

### 1. Enable GitHub Actions

1. Go to your repository Settings
2. Click on "Actions" in the left sidebar
3. Select "Allow all actions and reusable workflows"

### 2. Add Required Secrets

1. Go to your repository Settings > Secrets and variables > Actions
2. Add the following secrets:

**NPM_TOKEN:**
- Get from [npmjs.com](https://www.npmjs.com/settings/tokens)
- Create a new token with "Automation" type
- Add as `NPM_TOKEN`

### 3. Test the Workflows

1. Make a small change to trigger the test workflows
2. Check the Actions tab to see them running
3. Verify all steps pass

### 4. Publish Your First Release

1. Create a new release on GitHub
2. Tag it with your version (e.g., `v2.1.0`)
3. The publish workflow will automatically run
4. Check npm to verify your package was published

## Troubleshooting

### Common Issues

**Build fails:**
- Check Node.js version compatibility
- Verify all dependencies are properly listed in package.json

**Tests fail:**
- Check PHP version requirements
- Verify test database configuration

**Publish fails:**
- Verify NPM_TOKEN secret is set correctly
- Check if package name is available on npm
- Ensure you're logged in to npm

### Manual Triggers

You can manually trigger any workflow:
1. Go to Actions tab
2. Select the workflow
3. Click "Run workflow"
4. Select branch and click "Run workflow"

## Maintenance

- **Weekly security scans** run automatically
- **Dependency updates** should be reviewed regularly
- **Workflow updates** should be tested on feature branches first
