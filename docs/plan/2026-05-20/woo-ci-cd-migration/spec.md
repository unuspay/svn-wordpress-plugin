---
date: 2026-05-20
topic: woo-ci-cd-migration
status: draft
---

# Multi-Plugin Repo Migration Spec

## Context

The UnusPay WordPress plugin repo currently serves a single plugin (Easy Digital Downloads) in a flat SVN-mirror layout. A second plugin (WooCommerce) needs CI/CD automation (JG-22). Rather than maintaining two separate repos, both plugins will live under one shared-root repo with separate top-level directories.

The current auto-sync CI step is redundant — SVN trunk freshness doesn't matter because users only download tagged versions. The migration consolidates into release-only workflows.

## Goal

Restructure the repo into a multi-plugin layout where each plugin has its own directory (`/easy-digital-downloads`, `/woocommerce`), its own release workflow, and its own skill reference — all sharing one root `AGENTS.md` and `docs/skills/` directory.

## Target Layout

```
repo root/
├── AGENTS.md                          ← shared rules + per-plugin skill references
├── .github/workflows/
│   ├── release-edd.yml                ← EDD: sync + bump + tag + commit + GitHub Release
│   └── release-woocommerce.yml        ← Woo: sync + bump + tag + commit + GitHub Release
├── docs/skills/
│   ├── edd.md                         ← EDD-specific edit paths, conventions, release rules
│   └── woocommerce.md                 ← Woo-specific edit paths, conventions, release rules
├── easy-digital-downloads/
│   ├── trunk/                         ← EDD plugin code
│   │   ├── unuspay-crypto-payments-for-easy-digital-downloads.php
│   │   ├── assets/{css,js,images}/
│   │   └── readme.txt
│   ├── assets/                        ← WP.org banners, icons, screenshots
│   ├── tags/                          ← SVN release snapshots (auto-generated)
│   └── scripts/release.sh            ← EDD release logic
├── woocommerce/
│   ├── trunk/                         ← WooCommerce plugin code
│   │   ├── unuspay-payments.php
│   │   ├── includes/
│   │   ├── assets/{css,js,images}/
│   │   └── readme.txt
│   ├── assets/                        ← WP.org banners, icons, screenshots
│   ├── tags/                          ← SVN release snapshots (auto-generated)
│   └── scripts/release.sh            ← Woo release logic
└── (root-level files: .gitignore, .distignore, README.md)
```

## Architecture

### Per-Plugin SVN Mirrors

Each plugin directory mirrors its own WordPress.org SVN repo independently:

```
easy-digital-downloads/ → SVN: plugins.svn.wordpress.org/unuspay-crypto-payments-for-easy-digital-downloads/
woocommerce/            → SVN: plugins.svn.wordpress.org/unuspay-crypto-payments-for-woocommerce/
```

### Release Workflows (no sync step)

One release workflow per plugin. Each workflow handles the full cycle:

```
┌─────────────────────────────────────┐
│  release-{plugin}.yml               │
├─────────────────────────────────────┤
│  Step 1: rsync trunk/ → SVN trunk  │
│  Step 2: bump version               │
│  Step 3: svn cp → tags/<version>   │
│  Step 4: svn commit all            │
│  Step 5: push version bump to Git  │
│  Step 6: create GitHub Release     │
│    title: v<version>                │
│    body: WP.org plugin URL          │
└─────────────────────────────────────┘
```

Concurrency groups are per-plugin (no cross-plugin blocking):

```
svn-pipeline-edd
svn-pipeline-woocommerce
```

### AGENTS.md Reference Model

Root `AGENTS.md` contains shared repo rules and references each plugin's skill:

```
AGENTS.md
  shared repo rules
  EDD → docs/skills/edd.md
  Woo → docs/skills/woocommerce.md
```

## Data Flow

```python
# Release flow — per plugin (triggered manually)

# Step 1: Trigger
gh workflow run release-{plugin}.yml --field dry_run=false

# Step 2: Sparse SVN checkout
svn checkout --depth immediates {SVN_URL}

# Step 3: Sync trunk
rsync -rc --delete {plugin_dir}/trunk/ SVN_ROOT/trunk/
svn add --force
svn rm (deleted files)

# Step 4: Sync WP.org assets
rsync -rc --delete {plugin_dir}/assets/ SVN_ROOT/assets/

# Step 5: Read current version
current = grep "Version:" SVN_ROOT/trunk/{plugin_file}

# Step 6: Compute next version
if patch < 10:
  next = bump patch
else:
  next = bump minor, patch = 0

# Step 7: Validate no tag collision
assert not exists(SVN_ROOT/tags/{next})

# Step 8: Update version in SVN trunk
sed "Version:" and "Stable tag:" in SVN files

# Step 9: Set MIME types on images
svn propset svn:mime-type on images

# Step 10: Create SVN tag
svn cp SVN_ROOT/trunk SVN_ROOT/tags/{next}

# Step 11: Commit to SVN
svn commit -m "Release {next}"

# Step 12: Push version bump back to Git
git commit + git push origin main

# Step 13: Create GitHub Release
gh release create v{next} --title "v{next}" \
  --notes "https://wordpress.org/plugins/{slug}/"
```

## Error Handling

- **SVN tag collision** — abort with error, suggest manual `--version X.Y.Z`
- **SVN commit rejected** — credentials or server error → fail, no Git push-back, no GitHub Release
- **Git push-back conflict** — new commits on main since release started → abort push-back, log warning
- **rsync no changes** — skip SVN commit for trunk sync, but proceed if version bump creates a tag
- **Dry run mode** — execute all steps except svn commit, git push, gh release create
- **GitHub Release failure** — non-fatal, log warning, can be created manually

## Testing

- **Dry run first** — validate version computation, rsync paths, tag collision check without committing
- **Trunk sync verification** — diff plugin trunk/ against SVN trunk post-release
- **Version round-trip** — verify Git push-back updated both Version: and Stable tag:
- **GitHub Release check** — confirm release on repo page with correct tag and WP.org link
- **Concurrency test** — trigger both releases simultaneously; verify independent execution
- **Rollback path** — revert Git push-back commit + manually delete SVN tag
- **Post-release SVN inspection** — `svn ls tags/` and `svn cat tags/X.Y.Z/readme.txt`

## Acceptance Criteria

- [ ] Repo restructured with `/easy-digital-downloads/` and `/woocommerce/` top-level directories
- [ ] Each directory contains its own `trunk/`, `assets/`, `tags/`, `scripts/release.sh`
- [ ] Two release workflows exist: `release-edd.yml` and `release-woocommerce.yml`
- [ ] No sync workflows exist (auto-sync removed)
- [ ] Root `AGENTS.md` references per-plugin skills in `docs/skills/`
- [ ] `docs/skills/edd.md` contains EDD-specific edit rules, paths, release instructions
- [ ] `docs/skills/woocommerce.md` contains Woo-specific edit rules, paths, release instructions
- [ ] Dry run release succeeds for both plugins
- [ ] GitHub Release is created with WP.org plugin URL on actual release
- [ ] Concurrency groups are per-plugin, no cross-blocking
