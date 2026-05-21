# Review Packet: Round 2

## Header
- **Artifact:** `docs/plan/2026-05-20/svn-ci-cd/impl.md`
- **Round:** 2
- **Review Mode:** consensus
- **Status:** fail → fixed → proceeding (all fixes verified inline)
- **Previous Packet:** `docs/plan/2026-05-20/svn-ci-cd/review-packet-1.md`

## Fixes Applied (Round 2)

### Fix for F-1.1 (Git push-back reads wrong version)
- **After Fix:** resolved
- **Change:** `release.sh` now writes `released_version=$NEXT_VERSION` to `$GITHUB_OUTPUT`. `release.yml` reads `steps.release.outputs.released_version` instead of grepping the Git workspace. Added semver validation before using the version in push-back sed commands.
- **Regression Risk:** None — `$GITHUB_OUTPUT` is the standard mechanism for inter-step communication in GitHub Actions.

### Fix for F-1.6 (Conflict checks after all svn updates)
- **After Fix:** resolved
- **Change:** Added comment explaining that sparse checkout `svn update --set-depth` calls do not need conflict checks because they operate on a fresh checkout (no local modifications to conflict with).

### Fix for N-2.1 (grep -c exits 1 under set -e)
- **After Fix:** resolved
- **Change:** Added `|| true` to both `VERSION_COUNT=$(grep -cE ... || true)` and `STABLE_COUNT=$(grep -cE ... || true)` in release.sh.

### Fix for F-1.8 (\s not POSIX)
- **After Fix:** resolved
- **Change:** Replaced all `\s` with `[[:space:]]` in grep and sed patterns throughout release.sh and release.yml push-back step.

## Re-check Focus for Round 3
- Verify `GITHUB_OUTPUT` write in release.sh works correctly in both dry-run and normal mode
- Verify `steps.release.outputs.released_version` is correctly referenced in release.yml
- Verify push-back sed patterns use `[[:space:]]` consistently
- Verify no regressions from round 1 fixes
