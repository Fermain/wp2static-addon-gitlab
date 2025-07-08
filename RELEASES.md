# Release Process

This repository includes an automated GitHub Actions workflow that creates releases when version tags are pushed.

## How to Create a Release

### 1. Update Version Numbers

Make sure the version is updated in:
- `wp2static-addon-gitlab-private.php` (Plugin header: `Version: X.Y.Z`)
- `wp2static-addon-gitlab-private.php` (Constant: `WP2STATIC_GITLAB_PRIVATE_VERSION`)

### 2. Commit and Push Changes

```bash
git add .
git commit -m "Bump version to X.Y.Z"
git push origin master
```

### 3. Create and Push Version Tag

```bash
git tag v1.2.0
git push origin v1.2.0
```

### 4. Automatic Release Creation

The GitHub Actions workflow will automatically:

1. **Verify** the tag version matches the plugin header version
2. **Create** a clean ZIP file excluding development files:
   - Excludes: `.git*`, `.github/`, `README.md`, `composer.*`, tests, etc.
   - Includes: All plugin files needed for WordPress installation
3. **Generate** release notes from commit messages since the last tag
4. **Create** a GitHub release with the ZIP file attached
5. **Upload** the ZIP as a downloadable asset

## What Gets Included in the ZIP

✅ **Included:**
- `wp2static-addon-gitlab-private.php` (main plugin file)
- `src/` directory (plugin classes)
- `views/` directory (admin templates)  
- `uninstall.php`

❌ **Excluded:**
- `.git*` files and directories
- `.github/` (workflows)
- `README.md` (development documentation)
- `composer.json/lock`
- `phpunit.xml*` (testing config)
- `tests/` directory
- Build artifacts and OS files

## Release Notes

The workflow automatically generates release notes including:
- Version number and plugin info
- Commit messages since the last release
- Installation instructions

## Manual Testing

To test the ZIP creation locally:

```bash
# Create a test build
mkdir -p build/wp2static-addon-gitlab-private

# Copy files (same exclusions as workflow)
rsync -av \
  --exclude='.git*' \
  --exclude='.github' \
  --exclude='build' \
  --exclude='*.md' \
  --exclude='composer.*' \
  ./ build/wp2static-addon-gitlab-private/

# Create ZIP
cd build && zip -r ../test-release.zip wp2static-addon-gitlab-private/
```

## Troubleshooting

### Version Mismatch Error
If the workflow fails with "Version mismatch", ensure:
- The git tag matches the plugin header version exactly
- Both `Version: X.Y.Z` and `WP2STATIC_GITLAB_PRIVATE_VERSION` are updated

### Missing Files in ZIP
Check the exclusion patterns in `.github/workflows/release.yml` and `.gitattributes`

### Permission Issues
The workflow uses `GITHUB_TOKEN` which is automatically provided by GitHub Actions - no setup required.

## Example Workflow

```bash
# 1. Make changes and bump version
vim wp2static-addon-gitlab-private.php  # Update to 1.3.0
git add . && git commit -m "Bump version to 1.3.0"
git push

# 2. Tag and release
git tag v1.3.0
git push origin v1.3.0

# 3. Check GitHub releases page for the new release
```

The release will appear at: `https://github.com/[username]/wp2static-addon-gitlab/releases` 