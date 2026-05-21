# SVN CI/CD Pipeline Guide

> **Note:** This guide describes the original single-plugin CI/CD architecture.
> For the current multi-plugin setup, see `docs/skills/edd.md` and `docs/skills/woocommerce.md`.

## What Was Built

Two automated pipelines for the UnusPay WordPress plugin that sync code between GitHub and WordPress.org SVN:

- **CI (auto):** Every push to `main` syncs `trunk/` and `assets/` to WordPress.org SVN
- **CD (manual):** Trigger via skill command to auto-version, create SVN tag, and release to users

## Architecture

```
Developer → git push to main → CI: sync SVN trunk
Developer → skill "release"  → CD: auto-version + SVN tag + push back to Git
```

Both pipelines share a concurrency group (`svn-pipeline`) to prevent simultaneous SVN operations.

## Key Design Decisions

1. **Mirror layout** — Git repo mirrors SVN layout (trunk/, tags/, assets/ at root), not a flat plugin structure
2. **Trunk-only CI** — CI only updates SVN trunk, not tags. Users still get the last tagged version until CD runs
3. **Git push-back** — After CD releases a version, it pushes the version bump back to Git `main` to prevent CI from reverting it
4. **Auto-versioning** — Patch auto-increments (caps at 10, then minor bumps). Major version is manual
5. **Portable bash** — Uses temp-file sed approach (works on both macOS and Ubuntu CI)

## Files

| File | Purpose |
|------|---------|
| `.github/workflows/sync-trunk.yml` | CI: trunk sync on push to main |
| `.github/workflows/release.yml` | CD: release via workflow_dispatch |
| `scripts/release.sh` | Release logic (auto-version, SVN tag, commit) |
| `~/.jun/skills/release/SKILL.md` | Skill to trigger CD pipeline from CLI |
| `.distignore` | Exclude .DS_Store from SVN sync |
| `.gitignore` | Ignores .svn and .DS_Store |

## How to Use

### Prerequisites (one-time setup)

1. Create GitHub repo under UnusPay org
2. Add remote: `git remote add origin git@github.com:unuspay/<repo>.git`
3. Push: `git push -u origin main`
4. Add GitHub secrets: `SVN_USERNAME` and `SVN_PASSWORD`
   - SVN password is separate from WordPress.org account password
   - Generate at: WordPress.org profile → Edit → SVN Password

### CI — Automatic trunk sync

Just push to `main`:
```bash
git add .
git commit -m "fix: update checkout flow"
git push origin main
```

CI automatically syncs `trunk/` and `assets/` to WordPress.org SVN.

### CD — Release a new version

Use the skill command:
```bash
# Via the release skill (reads current version, computes next)
release
```

Or manually trigger via GitHub:
```bash
gh workflow run release.yml --field dry_run=false
```

Or with a specific version:
```bash
gh workflow run release.yml --field version=2.0.0
```

### Dry run

Test without committing:
```bash
gh workflow run release.yml --field dry_run=true
```

## Versioning Rules

- **Pattern:** `MAJOR.MINOR.PATCH` (e.g., `1.0.0`)
- **Auto-increment:** Patch +1 (e.g., `1.0.0` → `1.0.1`)
- **Patch cap:** 10 — after `x.y.10`, next is `x.(y+1).0`
- **Major:** Manual only (edit version in plugin PHP file before releasing)

## Monitoring

```bash
# Check latest CI run
gh run list --workflow=sync-trunk.yml --limit 1

# Check latest CD run
gh run list --workflow=release.yml --limit 1

# Watch a specific run
gh run watch <run-id>
```

## Troubleshooting

| Issue | Cause | Fix |
|-------|-------|-----|
| CI skipped commit | No file changes | Normal — trunk was already in sync |
| CD failed: "version already released" | SVN tag exists | Use `--version` to specify a different version |
| CD failed: "SVN conflict" | Concurrent modifications | Resolve manually in SVN, retry |
| Assets downloading instead of displaying | Missing MIME types | CI/CD sets them automatically; re-run if needed |
| CI reverts CD version | Git push-back failed | Check CD workflow logs for push-back step errors |
