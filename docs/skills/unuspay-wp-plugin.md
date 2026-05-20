---
name: unuspay-wp-plugin
description: Project context for the UnusPay WordPress plugin repo. Covers repo structure, CI/CD pipelines, how to edit files, and how releases work. Load this when working in the svn-wordpress-plugin repo.
---

# UnusPay WordPress Plugin — Project Context

Everything an LLM needs to work effectively in this repo.

## Repo Identity

| Field | Value |
|-------|-------|
| **Plugin** | UnusPay Crypto Payments for Easy Digital Downloads |
| **GitHub** | `unuspay/svn-wordpress-plugin` |
| **SVN** | `https://plugins.svn.wordpress.org/unuspay-crypto-payments-for-easy-digital-downloads/` |
| **SVN user** | `unustech01` |
| **Language** | PHP 7.2+ (pure, no composer/npm/webpack) |
| **CMS** | WordPress + Easy Digital Downloads |

## Repo Layout

This repo **mirrors the WordPress.org SVN structure**. Git is the source of truth; SVN is the release target.

```
svn-wordpress-plugin/           ← Git repo root = SVN working copy root
├── trunk/                       ← Active plugin code (developers edit HERE)
│   ├── assets/
│   │   ├── css/admin.css
│   │   ├── css/unuspay.css
│   │   ├── js/checkout.js
│   │   ├── js/widgets.bundle.js  ← pre-built bundle (no build step in CI)
│   │   └── images/
│   ├── unuspay-crypto-payments-for-easy-digital-downloads.php  ← Main plugin file (870 lines)
│   ├── readme.txt               ← WordPress.org metadata (Stable tag, changelog, FAQ)
│   ├── changelog.txt
│   └── LICENSE.txt
├── assets/                      ← WordPress.org directory assets (banners, icons, screenshots)
│   ├── banner-772x250.png
│   ├── icon-256x256.png
│   └── screenshot-1..8.png/gif
├── tags/                        ← SVN release snapshots (DO NOT EDIT — created by CD pipeline)
│   └── 1.0.0/                   ← mirrors trunk at release time
├── .github/workflows/
│   ├── sync-trunk.yml           ← CI: push to main → sync SVN trunk
│   └── release.yml              ← CD: manual dispatch → SVN tag + version bump
├── scripts/
│   └── release.sh               ← Release logic (auto-version, SVN tag, commit)
├── .distignore                  ← Files excluded from SVN sync
├── .gitignore                   ← .svn, .DS_Store
└── README.md                    ← SVN workflow reference
```

## How to Edit Files

### Rule: Always edit in `trunk/`, never in `tags/`

```
✅  trunk/unuspay-crypto-payments-for-easy-digital-downloads.php
✅  trunk/assets/css/admin.css
✅  trunk/readme.txt
❌  tags/1.0.0/unuspay-crypto-payments-for-easy-digital-downloads.php  ← DO NOT EDIT
```

### Where things live

| What | Where |
|------|-------|
| Plugin PHP logic | `trunk/unuspay-crypto-payments-for-easy-digital-downloads.php` |
| Plugin CSS | `trunk/assets/css/` |
| Plugin JS | `trunk/assets/js/` |
| Plugin images | `trunk/assets/images/` |
| WordPress.org metadata | `trunk/readme.txt` (Stable tag, Tested up to, changelog, FAQ) |
| WordPress.org banners/icons/screenshots | `assets/` (top-level, NOT in trunk/) |
| Changelog | `trunk/readme.txt` → `== Changelog ==` section |
| Version number | `trunk/unuspay-crypto-payments-for-easy-digital-downloads.php` → ` * Version: X.Y.Z` |
| Stable tag | `trunk/readme.txt` → `Stable tag: X.Y.Z` |

### After editing: commit and push

```bash
git add trunk/path/to/file
git commit -m "fix: description of change"
git push origin main
```

Pushing to `main` triggers the CI pipeline automatically.

## CI Pipeline — Trunk Sync

**Workflow:** `.github/workflows/sync-trunk.yml`
**Trigger:** Push to `main`
**What it does:**

1. Sparse SVN checkout (trunk/ and assets/ only)
2. `rsync -rc --delete` from Git `trunk/` → SVN `trunk/`
3. `rsync -rc --delete` from Git `assets/` → SVN `assets/`
4. `svn add . --force` for new files
5. `svn rm` for deleted files
6. Set `svn:mime-type` on images (png, jpg, gif, svg)
7. Check `svn status` — if changes exist, `svn commit`
8. If no changes, skip with "clean" message

**Key behaviors:**
- Uses `.distignore` to exclude `.DS_Store` from sync
- Detects no-op pushes (no changes → skips commit)
- Checks for SVN conflicts after `svn update`
- Shares concurrency group `svn-pipeline` with CD (never runs simultaneously)

**What it does NOT do:**
- Does NOT create SVN tags
- Does NOT update `Stable tag` or `Version` — that's CD's job
- Does NOT affect what users download (users get the tagged version)

## CD Pipeline — Release

**Workflow:** `.github/workflows/release.yml`
**Script:** `scripts/release.sh`
**Trigger:** Manual (`workflow_dispatch` via skill or GitHub UI)
**What it does:**

1. Sparse SVN checkout (trunk/, assets/, tags/ names only)
2. Read current `Version:` from SVN trunk plugin file
3. Auto-compute next version:
   - Patch +1 normally (`1.0.0` → `1.0.1`)
   - If patch = 10, bump minor (`1.0.10` → `1.1.0`)
   - Major is always manual
4. Validate new version doesn't already exist in SVN tags
5. `sed` update `Version:` in SVN trunk plugin file
6. `sed` update `Stable tag:` in SVN trunk readme.txt
7. `svn cp trunk tags/<version>` — create SVN tag snapshot
8. Set MIME types on images
9. `svn commit` everything (trunk changes + new tag)
10. Push version bump back to Git `main` (prevents CI from reverting)

### Versioning rules

| Current | Next | Rule |
|---------|------|------|
| `1.0.0` | `1.0.1` | patch < 10 → patch +1 |
| `1.0.9` | `1.0.10` | patch < 10 → patch +1 |
| `1.0.10` | `1.1.0` | patch = 10 → minor +1, patch = 0 |
| `2.5.10` | `2.6.0` | same rule |
| any | `X.Y.Z` (custom) | via `--version X.Y.Z` flag |

### How to trigger a release

Via the release skill:
```
release
```

Via CLI:
```bash
gh workflow run release.yml --field dry_run=false
gh workflow run release.yml --field version=2.0.0
```

Dry run (no commit):
```bash
gh workflow run release.yml --field dry_run=true
```

## Important Version Locations

When the version changes, **three places** must be updated (CD handles all three automatically):

```
trunk/unuspay-crypto-payments-for-easy-digital-downloads.php
  → line: " * Version: X.Y.Z"

trunk/readme.txt
  → line: "Stable tag: X.Y.Z"

tags/X.Y.Z/  ← created by svn cp from trunk
```

## What Users See vs What Trunk Has

```
SVN trunk/       → always latest code (synced by CI)
SVN tags/X.Y.Z/  → frozen release snapshot (created by CD)
SVN readme.txt Stable tag → tells WordPress.org which tag to serve

Users download = tags/<Stable tag>/
Trunk is NOT served to users (unless Stable tag: trunk)
```

So after a CI push, trunk updates but users still get the old version. Only after CD creates a new tag and updates Stable tag do users see the update.

## Concurrency

Both workflows share `concurrency: group: svn-pipeline`. They never run simultaneously. If CD is running and CI triggers, CI queues. If CI is running and CD triggers, CD queues.

## Gotchas

- **Do NOT edit `tags/` manually** — tags are created by the CD pipeline via `svn cp`
- **Do NOT commit directly to SVN** — always use Git. SVN is managed by CI/CD
- **`widgets.bundle.js` is pre-built** — there is no build step in CI. If you need to rebuild it, do so locally before pushing
- **`assets/` is top-level** — banners and screenshots live in the repo root `assets/`, not in `trunk/assets/`
- **Git push-back after release** — CD pushes the version bump back to Git `main`, which triggers CI once more. But since Git and SVN are now in sync, CI finds no changes and exits cleanly (no infinite loop)
- **`.svn/` directory exists** — this repo is both a Git and SVN working copy. Don't touch `.svn/`. It's in `.gitignore`

## GitHub Secrets Required

| Secret | Where to get |
|--------|-------------|
| `SVN_USERNAME` | WordPress.org username (case-sensitive) |
| `SVN_PASSWORD` | WordPress.org profile → SVN password (NOT account password) |

## Quick Reference Commands

```bash
# Edit and deploy trunk
git add trunk/ && git commit -m "fix: ..." && git push origin main

# Check CI status
gh run list --workflow=sync-trunk.yml --limit 1

# Trigger release (dry run)
gh workflow run release.yml --field dry_run=true

# Trigger release (real)
gh workflow run release.yml

# Monitor release
RUN_ID=$(gh run list --workflow=release.yml --limit 1 --json databaseId --jq '.[0].databaseId')
gh run watch "$RUN_ID"

# Read current version
grep -iE 'Version:' trunk/unuspay-crypto-payments-for-easy-digital-downloads.php

# Check SVN trunk status
svn status

# Check remote
git remote -v
```
