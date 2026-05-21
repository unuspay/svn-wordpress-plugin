# Spec: SVN CI/CD Pipeline for UnusPay WordPress Plugin

## Context

The UnusPay Crypto Payments plugin (`unuspay-crypto-payments-for-easy-digital-downloads`) is hosted on WordPress.org SVN at `https://plugins.svn.wordpress.org/unuspay-crypto-payments-for-easy-digital-downloads/`. Currently all SVN operations are manual. The Git repo has no remote, no CI/CD, and no build system. The plugin is pure PHP (no npm/composer/webpack).

We need two automated pipelines:
1. **CI** — keep SVN trunk synced with the GitHub repo on every push to main
2. **CD** — release a new version to users by creating an SVN tag and updating the stable tag

## Goal

Every push to `main` on GitHub automatically syncs `trunk/` and `assets/` to WordPress.org SVN. A separate release pipeline auto-increments the version, creates an SVN tag, and updates the stable tag — releasing the update to all WordPress users.

## Decisions

| Decision | Choice |
|----------|--------|
| GitHub repo | UnusPay org |
| Repo structure | Mirror SVN layout (trunk/, tags/, assets/ at Git root) |
| CI trigger | Push to `main` |
| CD trigger | Manual dispatch via skill (auto-increment version) |
| SVN tag strategy | CI: trunk-only. CD: trunk + tag + stable tag update |
| Implementation | CI: inline workflow. CD: `scripts/release.sh` called from workflow |
| Versioning | Semver: patch auto-increments (caps at 10 → minor bump). Major manual |

## Acceptance Criteria

1. Pushing to `main` triggers a GitHub Actions workflow that syncs `trunk/` and `assets/` to WordPress.org SVN
2. The CI workflow detects when there are no changes and skips the commit
3. Running the CD pipeline auto-computes the next version (patch +1, or minor +1 if patch = 10)
4. The CD pipeline creates `tags/<version>` in SVN and updates `Stable tag` in `readme.txt`
5. The CD pipeline updates `Version:` in the main plugin PHP file
6. Image assets have correct `svn:mime-type` properties set
7. SVN credentials are stored as GitHub Actions secrets (not in code)
8. A skill MD file exists to trigger the CD release from the CLI
9. Both workflows handle errors gracefully with clear messages

## Architecture

```
┌─────────────────────────────────────┐
│  Developer                          │
├─────────────────────────────────────┤
│  Step 1: edit trunk/ files          │
│  Step 2: git commit & push to main  │
└──────────┬──────────────────────────┘
           │ `push to main`
           ▼
┌─────────────────────────────────────┐
│  CI: sync-trunk.yml                 │
├─────────────────────────────────────┤
│  Step 1: checkout Git repo          │
│  Step 2: svn checkout --depth       │
│          immediates (sparse)         │
│  Step 3: svn up trunk + assets      │
│          (--set-depth infinity)      │
│  Step 4: rsync -rc trunk/ → SVN     │
│          trunk/ (--delete)           │
│  Step 5: rsync -rc assets/ → SVN    │
│          assets/ (--delete)          │
│  Step 6: svn add . --force          │
│  Step 7: svn rm missing files       │
│  Step 8: set MIME types on images   │
│  Step 9: svn status → check changes │
│    if changes:                       │
│      → svn update (safety)          │
│      → svn commit --non-interactive │
│    if no changes:                    │
│      → skip, report "clean"         │
└──────────┬──────────────────────────┘
           │ `svn commit`
           ▼
┌─────────────────────────────────────┐
│  WordPress.org SVN                  │
├─────────────────────────────────────┤
│  trunk/ updated with latest code    │
│  assets/ updated with latest images │
└─────────────────────────────────────┘

# CD: release.yml (manual dispatch via skill)

┌─────────────────────────────────────┐
│  Skill: release                     │
├─────────────────────────────────────┤
│  Step 1: trigger workflow_dispatch   │
│          on release.yml              │
└──────────┬──────────────────────────┘
           │ `workflow_dispatch`
           ▼
┌─────────────────────────────────────┐
│  scripts/release.sh                 │
├─────────────────────────────────────┤
│  Step 1: read current Version:      │
│          from trunk/*.php            │
│  Step 2: auto-increment version     │
│          patch+1 (caps at 10)        │
│          or minor+1 if patch=10      │
│  Step 3: validate tag not in SVN    │
│  Step 4: sed Version: → new version │
│  Step 5: sed Stable tag: → new ver  │
│  Step 6: svn cp trunk tags/<ver>    │
│  Step 7: set MIME types on assets   │
│  Step 8: svn update                 │
│  Step 9: svn commit                 │
│    → "Release <version>"            │
└─────────────────────────────────────┘
```

## Data Flow — CI (Trunk Sync)

```python
# Step 1: Trigger
on: push to main

# Step 2: Checkout Git repo (mirror layout: trunk/, tags/, assets/)
git_checkout()

# Step 3: Sparse SVN checkout
svn_checkout --depth immediates "https://plugins.svn.wordpress.org/unuspay-crypto-payments-for-easy-digital-downloads/"
svn_update --set-depth infinity trunk
svn_update --set-depth infinity assets

# Step 4: Sync Git → SVN
rsync -rc git/trunk/ → svn/trunk/ --delete
rsync -rc git/assets/ → svn/assets/ --delete

# Step 5: SVN add/rm
svn add . --force
svn rm missing files (from svn status | grep '^!')

# Step 6: Set MIME types on images in assets/
svn propset svn:mime-type on *.png, *.jpg, *.gif, *.svg

# Step 7: Change detection
if svn_status is empty:
    → report "no changes", exit
else:
    → svn update (safety against "out of date")
    → svn commit --non-interactive --no-auth-cache
```

## Data Flow — CD (Release)

```python
# Step 1: Trigger via skill
skill_dispatch("release")

# Step 2: Sparse SVN checkout (trunk + assets + tags/immediates)
svn_checkout --depth immediates
svn_update --set-depth infinity trunk
svn_update --set-depth infinity assets
svn_update --set-depth immediates tags

# Step 3: Read current version from plugin PHP file
current_version = grep "Version:" from trunk/unuspay-crypto-payments-for-easy-digital-downloads.php
# e.g. "1.0.9"

# Step 4: Auto-increment
parse into (major, minor, patch)
if patch < 10:
    next = (major, minor, patch + 1)   → "1.0.10"
else:
    next = (major, minor + 1, 0)        → "1.1.0"

# Step 5: Validate tag doesn't exist
if tags/next exists in SVN:
    → abort: "version already released"

# Step 6: Bump version
sed "Version: .*" → "Version: next" in trunk/*.php
sed "Stable tag: .*" → "Stable tag: next" in trunk/readme.txt

# Step 7: Create SVN tag
svn cp trunk tags/next

# Step 8: Set MIME types on assets
svn propset svn:mime-type on images

# Step 9: Commit
svn update
svn commit -m "Release next" --non-interactive --no-auth-cache
```

## Versioning Rules

- **Pattern**: semver (`MAJOR.MINOR.PATCH`)
- **Auto-increment**: patch +1 on each release
- **Patch cap**: 10 — after `x.y.10`, next is `x.(y+1).0`
- **Major bump**: manual only (user edits the version in the plugin file before releasing)
- **Current version**: `1.0.0`
- **Next release**: `1.0.1`

## Error Handling

- **SVN auth failure** → workflow fails, message: "Access denied — check SVN_USERNAME and SVN_PASSWORD secrets"
- **Tag already exists** → script aborts, message: "version already released"
- **No changes to commit** → skip commit, log "no changes"
- **svn update conflict** → abort with "SVN conflict — resolve manually"
- **Version parsing failure** → abort with "check Version header in plugin PHP file"
- **SVN server timeout** → retry once

## Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `.github/workflows/sync-trunk.yml` | GitHub Actions | CI: trunk sync on push to main |
| `.github/workflows/release.yml` | GitHub Actions | CD: release via workflow_dispatch |
| `scripts/release.sh` | Repo root | Release logic (version bump + SVN tag + commit) |
| `.distignore` | Repo root | Exclude dev files from SVN sync |
| `~/.jun/skills/release/SKILL.md` | Skills dir | Skill to trigger CD pipeline |
| GitHub Secrets | `SVN_USERNAME`, `SVN_PASSWORD` | WordPress.org SVN credentials |

## Testing

- **CI** — Push a small change to `main`, verify SVN trunk updates via `svn log`
- **CD dry-run** — Run `scripts/release.sh --dry-run` locally, verify version computation and file edits
- **MIME types** — After first deploy, verify screenshots display inline on WordPress.org
- **Credentials** — Test SVN auth with `svn list` before first real deploy
- **Edge case** — Test version at `x.y.10` → verify it bumps to `x.(y+1).0`

## Plugin Metadata

| Field | Value |
|-------|-------|
| **Plugin slug** | `unuspay-crypto-payments-for-easy-digital-downloads` |
| **SVN URL** | `https://plugins.svn.wordpress.org/unuspay-crypto-payments-for-easy-digital-downloads/` |
| **SVN user** | `unustech01` |
| **Current version** | `1.0.0` |
| **Main plugin file** | `trunk/unuspay-crypto-payments-for-easy-digital-downloads.php` |
