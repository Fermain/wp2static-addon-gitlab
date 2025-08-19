## Releasing GitLab Private Add-on

### Tag and push

In the `wp2static-addon-gitlab` repo:

```bash
git add -A && git commit -m "Release v1.3.0"
git tag v1.3.0
git push origin main --tags
```

CI sets the version in `wp2static-addon-gitlab-private.php` from the tag and creates the release.


