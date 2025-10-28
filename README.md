# WP2Static GitLab Private Deployment Add-on

Deploy your static WordPress site to a private GitLab repository using the GitLab API. Perfect for Kubernetes environments and CI/CD workflows.

## Features

- 🔒 **Private GitLab Support** - Works with both GitLab.com and self-hosted GitLab instances
- 🚀 **Git-Based Deployment** - Clones the repository, commits site output, and pushes to the target branch
- 🔀 **Merge Request Mode** - Optional MR workflow with auto-merge when CI pipeline succeeds
- 📂 **Deploy Subdirectory** - Deploy into a scoped subdirectory (default: `public/`) to protect CI and repo root files
- 🔄 **Incremental Deployments** - Git handles changes; only modified files are committed
- ☸️ **Kubernetes Ready** - Works in internal Kubernetes environments without internet exposure
- 🛠️ **CI/CD Integration** - Triggers your GitLab CI/CD pipelines automatically
- 🧪 **Connection Testing** - Built-in connection test to verify configuration
- 📝 **Detailed Logging** - Comprehensive logging via WP2Static's logging system

## Requirements

- WordPress 5.0+
- WP2Static 7.2+
- PHP 7.4+
- Outbound HTTPS access to your GitLab instance
- GitLab Project Access Token with `api` scope (recommended) or Personal Access Token with `api` and `write_repository` scopes

## Installation

1. **Download/Clone** this add-on to your WordPress plugins directory:
   ```
   wp-content/plugins/wp2static-addon-gitlab-private/
   ```

2. **Activate** the plugin in WordPress Admin → Plugins

3. **Configure** the add-on in WP2Static → GitLab Private

4. **Enable** the add-on in WP2Static → Add-ons

## Configuration

### 1. Create GitLab Access Token (Project Access Token Recommended)

**Option A: Project Access Token (Recommended)**
1. Go to your target GitLab repository → Settings → Access Tokens
2. Create a new token with:
   - `api` scope - Access the authenticated user's API  
   - `Maintainer` or `Owner` role
3. Set an appropriate expiration date
4. Save the token securely

**Option B: Personal Access Token (Alternative)**
1. Go to your GitLab instance → User Settings → Access Tokens
2. Create a new token with these scopes:
   - `api` - Access the authenticated user's API
   - `write_repository` - Write (push) to repository
3. Set an appropriate expiration date
4. Save the token securely

### 2. Find Your Project ID

1. Navigate to your target GitLab repository
2. Go to Settings → General
3. The Project ID is displayed at the top of the page
4. Copy this number (e.g., `12345`)

### 3. Configure the Add-on

Navigate to **WP2Static → GitLab Private** and fill in:

- **GitLab URL**: Your GitLab instance URL
  - GitLab.com: `https://gitlab.com`
  - Self-hosted: `https://gitlab.yourcompany.com`
- **Project ID**: The numeric project ID from step 2
- **Access Token**: The personal access token from step 1
- **Deploy Strategy**: Choose how deployments are pushed
  - **Direct Push** (default): Commits pushed directly to the branch
  - **Merge Request**: Creates MR that auto-merges when CI pipeline succeeds
- **Branch**: The branch to push to
  - Direct mode: typically `main` or `master`
  - MR mode: typically `wp2static-deploy` or similar working branch
- **MR Target Branch** (MR mode only): The branch the merge request will target (typically `main`)
- **Deploy Subdirectory**: Subdirectory within the repo to receive the site (default: `public`)
- **Commit Message**: Message for deployment commits
- **Author Name & Email**: Git commit author information

### 4. Test Connection

Click **Test Connection** to verify your configuration. You should see:
```
Connection successful! Project: your-project-name
```

### 5. Enable in WP2Static

1. Go to **WP2Static → Add-ons**
2. Find "GitLab Private" and click **Enabled**
3. The add-on is now active for deployments

## Usage

### Automatic Deployment

Once configured, the add-on will automatically deploy when you:

1. Run a full workflow: **WP2Static → Run**
2. Process the job queue that includes a deploy job
3. Use WP-CLI: `wp wp2static deploy`

### Manual Deployment

You can also trigger deployment manually via WP-CLI:

```bash
# Full workflow (detect, crawl, post-process, deploy)
wp wp2static full_workflow

# Deploy only (requires processed site)
wp wp2static deploy
```

## How It Works

### Direct Push Mode (Default)

1. **File Processing**: WP2Static generates static files in `wp-content/uploads/wp2static-processed-site/`
2. **Repo Checkout**: The add-on shallow clones the repository and checks out the configured branch
3. **Scoped Sync**: The add-on copies the processed site into the configured deploy subdirectory (default `public/`)
   - Optional: If "Delete Orphaned Files" is enabled, the deploy subdirectory is cleaned before copy
4. **Git Commit & Push**: Changes are committed and pushed directly to the branch (e.g., `main`)
5. **CI Trigger**: Your GitLab CI/CD pipeline detects the new commit and can automatically deploy

### Merge Request Mode

1. **File Processing**: WP2Static generates static files in `wp-content/uploads/wp2static-processed-site/`
2. **Repo Checkout**: The add-on shallow clones the repository and creates/updates the push branch from the MR target branch
3. **Scoped Sync**: The add-on copies the processed site into the configured deploy subdirectory (default `public/`)
   - Optional: If "Delete Orphaned Files" is enabled, the deploy subdirectory is cleaned before copy
4. **Git Commit & Push with MR**: Changes are committed and pushed to the push branch (e.g., `wp2static-deploy`) with GitLab push options:
   - Creates a merge request targeting the MR target branch (e.g., `main`)
   - Sets auto-merge when CI/CD pipeline succeeds
   - Automatically removes the push branch after merge
5. **CI/CD Pipeline**: GitLab runs the pipeline on the MR; if it passes, the MR auto-merges into the target branch
6. **Deployment**: Your pipeline can deploy the merged changes to production

**Example Configuration for MR Mode:**
- Branch: `wp2static-deploy` (ephemeral, gets recreated each deployment)
- MR Target Branch: `main` (your protected production branch)
- Result: Push to `wp2static-deploy` → Creates MR to `main` → Auto-merges on CI success → Branch deleted

## Git Operations

The add-on performs standard git operations server-side:

- Shallow clone via HTTPS with token credential embedding
- Ensure committer name/email are set locally
- Create or fast-forward the target branch
- Copy site output into the deploy subdirectory
- `git add -A`, `git commit` (no-op if nothing changed), and `git push`

## Kubernetes Deployment

This add-on is specifically designed for Kubernetes environments:

### Advantages
- ✅ **No Git Installation Required** - Uses API calls instead of git commands
- ✅ **Minimal Dependencies** - Only requires WordPress and cURL
- ✅ **Outbound Only** - No inbound network access needed
- ✅ **Stateless** - No local git repositories or SSH keys to manage

### Example Kubernetes Workflow

```yaml
# WordPress Pod (internal)
WordPress + WP2Static + GitLab Add-on
    ↓ (HTTPS API calls)
GitLab Repository
    ↓ (GitLab CI/CD)
Production Deployment
```

## Troubleshooting

### Connection Test Fails

**"Connection failed (HTTP 401)"**
- Check your access token is correct and not expired
- Verify token has `api` and `write_repository` scopes

**"Connection failed (HTTP 404)"**
- Verify the Project ID is correct
- Ensure the token has access to the specified project

**"Missing required configuration"**
- Fill in all required fields: GitLab URL, Project ID, Access Token, Branch

### Deployment Issues

**"git push failed"**
- Verify token has write access to the target branch (Maintainer or higher if protected)
- Confirm the repository allows HTTPS pushes with access tokens (`oauth2` with PAT is supported)

**"No files to deploy"**
- Run WP2Static crawl first to generate static files
- Check that files exist in `wp-content/uploads/wp2static-processed-site/`

### Log Analysis

Check WP2Static logs at **WP2Static → Logs** for detailed error messages:

```
GitLab Private deployment started
Found 45 files to deploy
Deploying batch 1 of 3
Successfully deployed 20 files to GitLab
GitLab Private deployment completed
```

## Security Considerations

1. **Token Type**: Use **Project Access Tokens** instead of Personal Access Tokens when possible for better security isolation.

2. **Access Token Storage**: Tokens are stored in the WordPress database. Consider using environment variables for enhanced security.

3. **Token Permissions**: Use minimal required scopes:
   - Project Access Token: `api` scope only
   - Personal Access Token: `api` and `write_repository` only

4. **Network Security**: Ensure your GitLab instance is accessible over HTTPS.

5. **Token Expiration**: Set reasonable expiration dates and rotate tokens regularly.

## Limitations

- **Server Git Required**: The WordPress environment must have `git` available in `PATH`
- **Deploy Scope**: Deletions (if enabled) are limited to the deploy subdirectory
- **Large Repos**: Shallow clones mitigate cost, but very large histories may still impact performance

## QA Checklist

- Connection test succeeds and shows project path and default branch
- `git --version` available on server; shallow clone succeeds
- Push to target branch works with access token (Maintainer or higher if protected)
- Deploy subdirectory behavior:
  - Delete Orphaned Files OFF: only additions/updates within `public/`
  - Delete Orphaned Files ON: `public/` cleaned before copy; files outside `public/` untouched
- Commit author name/email reflect configured values
- Token is masked in logs and error messages
- No-op deploy logs “Nothing to commit” and push succeeds without error
- CI/CD pipeline triggers on commit and reads from `public/`

## Contributing

This add-on follows WP2Static's add-on architecture. Key files:

- `wp2static-addon-gitlab-private.php` - Main plugin file
- `src/GitLabPrivateDeployer.php` - Core deployment logic
- `views/options-page.php` - Admin interface
- `uninstall.php` - Cleanup script