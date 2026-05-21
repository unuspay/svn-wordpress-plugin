---
name: release
description: Trigger a WordPress.org SVN plugin release via GitHub Actions.
---

# Release WordPress Plugin

Trigger a new release of an UnusPay WordPress plugin to WordPress.org SVN.

## When to Use

When the user says "release", "deploy", "publish", or "cut a release" for a WordPress plugin.

## Determine Plugin

Ask which plugin to release if not clear from context:

| Plugin | Workflow | Directory |
|--------|----------|-----------|
| EDD | `release-edd.yml` | `easy-digital-downloads/` |
| WooCommerce | `release-woocommerce.yml` | `woocommerce/` |

## Steps

1. Confirm the repo has a GitHub remote and is up to date:
   ```bash
   git remote -v
   git status
   ```

2. Read the current version from the plugin file:
   - EDD: `grep -iE 'Version:' easy-digital-downloads/trunk/unuspay-crypto-payments-for-easy-digital-downloads.php`
   - WooCommerce: `grep -iE 'Version:' woocommerce/trunk/unuspay-payments.php`

3. Ask the user to confirm the release (show current version and what the next version will be):
   - If current is `X.Y.Z` where Z < 10 → next is `X.Y.(Z+1)`
   - If current is `X.Y.10` → next is `X.(Y+1).0`
   - Or the user can specify a custom version (must be `X.Y.Z` format)

4. Trigger the GitHub Actions workflow:
   ```bash
   gh workflow run release-edd.yml --field dry_run=false
   # or
   gh workflow run release-woocommerce.yml --field dry_run=false
   ```

5. Monitor the workflow run:
   ```bash
   RUN_ID=$(gh run list --workflow=release-edd.yml --limit 1 --json databaseId --jq '.[0].databaseId')
   gh run watch "$RUN_ID"
   ```

6. Report the result to the user.

## Dry Run

```bash
gh workflow run release-edd.yml --field dry_run=true
gh workflow run release-woocommerce.yml --field dry_run=true
```

## Notes

- Each plugin has its own release script at `<plugin>/scripts/release.sh`
- SVN credentials must be set as GitHub secrets: `SVN_USERNAME`, `SVN_PASSWORD`
- After release, version bump is pushed back to Git automatically
- After release, a GitHub Release is created with a link to the WP.org plugin page
- GitHub Release tags: `v{version}-edd` for EDD, `v{version}-woo` for WooCommerce
