# Review Packet: Round 1

## Header
- **Artifact:** `docs/plan/2026-05-20/woo-ci-cd-migration/impl.md`
- **Round:** 1
- **Review Mode:** consensus
- **Status:** pass with notes
- **Previous Packet:** none

## Findings

### F-1.1: Task 6 hardcoded local SVN checkout path
- **Severity:** high
- **Category:** blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Task 6 rsync source was `/Users/junguo/code/unuspay/svn-unuspay-woocommerce/`
- **Required Fix:** Parameterize with `WOO_SVN_SOURCE` env var, add existence check, document as human gate
- **Consensus:** 2/2
- **Re-check:** Verify rsync commands use `$WOO_SVN_SOURCE` variable

### F-1.2: `docs/base/_guide/svn-ci-cd.md` not updated
- **Severity:** medium
- **Category:** non-blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Guide references `sync-trunk.yml`, `release.yml`, single-plugin paths
- **Required Fix:** Add step in Task 10 to update or archive the guide
- **Consensus:** 2/2
- **Re-check:** Verify Task 10 includes guide update step

### F-1.3: Root `.svn/` cleanup not addressed
- **Severity:** medium
- **Category:** non-blocking
- **First Found:** round 1
- **Status:** resolved (no action needed)
- **Evidence:** `.svn/` at repo root becomes stale after restructure
- **Required Fix:** No action — `.svn/` is already in `.gitignore`. After restructure, the local SVN working copy becomes irrelevant since all SVN operations happen in CI/CD via sparse checkout. Developer can optionally `rm -rf .svn` locally.
- **Consensus:** 1/2 (only original flagged)
- **Re-check:** N/A

### F-1.4: GitHub Release tag format breaking change
- **Severity:** low
- **Category:** non-blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Task 3 changes from `v{version}` to `v{version}-edd` / `v{version}-woo`
- **Required Fix:** Add note in Task 3 acknowledging breaking change
- **Consensus:** 1/2 (only alt flagged)
- **Re-check:** Verify note is present in Task 3

### F-1.5: Task 3 → Tasks 4-5 implicit dependency
- **Severity:** low
- **Category:** non-blocking
- **First Found:** round 1
- **Status:** resolved (no action needed)
- **Evidence:** Workflows reference scripts that don't exist until Tasks 4-5
- **Required Fix:** No action — sequential execution makes this harmless. Scripts are only called at runtime (workflow_dispatch), not at commit time.
- **Consensus:** 1/2 (only alt flagged)
- **Re-check:** N/A

## Fixes Applied

### Fix for F-1.1
- **After Fix:** resolved
- **Change:** Replaced hardcoded path with `WOO_SVN_SOURCE` env var, added existence check, added human gate note
- **Regression Risk:** None — rsync behavior unchanged when source exists

### Fix for F-1.2
- **After Fix:** resolved
- **Change:** Added Step 5 to Task 10: update or archive `docs/base/_guide/svn-ci-cd.md`
- **Regression Risk:** None — documentation-only change

### Fix for F-1.4
- **After Fix:** resolved
- **Change:** Added breaking change note to Task 3 after the tag format explanation
- **Regression Risk:** None — documentation-only note

## Human Gate Candidates

| Gate | Location | Reason |
|------|----------|--------|
| SVN credentials | Tasks 11, 12 | `SVN_USERNAME` and `SVN_PASSWORD` must be set in GitHub repo secrets |
| WooCommerce SVN slug ownership | Before Task 6 | `unuspay-crypto-payments-for-woocommerce` must exist on WordPress.org owned by `unustech01` |
| First actual release | After Task 12 | Dry runs verify structure; actual release is irreversible (SVN commit + tag) |

## Re-check Focus for Round 2
- Verify Task 6 uses `$WOO_SVN_SOURCE` variable and has existence check
- Verify Task 10 includes guide update step (Step 5)
- Verify Task 3 has breaking change note
