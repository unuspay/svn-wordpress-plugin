---
name: release
description: Trigger a WordPress.org SVN plugin release via GitHub Actions.
---

# Release WordPress Plugin

Trigger a new release of the UnusPay WordPress plugin to WordPress.org SVN.

## When to Use

When the user says "release", "deploy", "publish", or "cut a release" for the WordPress plugin.

## Steps

1. Confirm the repo has a GitHub remote and is up to date:
   ```bash
   git remote -v
   git status
   ```

2. Read the current version from the plugin file:
   ```bash
   grep -iE 'Version:' trunk/unuspay-crypto-payments-for-easy-digital-downloads.php
   ```

3. Ask the user to confirm the release (show current version and what the next version will be):
   - If current is `X.Y.Z` where Z < 10 → next is `X.Y.(Z+1)`
   - If current is `X.Y.10` → next is `X.(Y+1).0`
   - Or the user can specify a custom version (must be `X.Y.Z` format)

4. Trigger the GitHub Actions workflow:
   ```bash
   gh workflow run release.yml \
     --field dry_run=false \
     --field version="$VERSION"
   ```

5. Monitor the workflow run:
   ```bash
   RUN_ID=$(gh run list --workflow=release.yml --limit 1 --json databaseId --jq '.[0].databaseId')
   gh run watch "$RUN_ID"
   ```

6. Report the result to the user.

## Dry Run

If the user wants to test without committing, use:
```bash
gh workflow run release.yml --field dry_run=true
```

## Notes

- The release script is at `scripts/release.sh`
- SVN credentials must be set as GitHub secrets: `SVN_USERNAME`, `SVN_PASSWORD`
- WordPress.org SVN password is separate from account password (generate at WordPress.org profile → SVN password)
- After release, the version bump is pushed back to Git automatically to prevent CI from reverting it
- After release, users see the update within a few hours
