# Review Packet: Round 1

## Header
- **Artifact:** `docs/plan/2026-05-20/svn-ci-cd/impl.md`
- **Round:** 1
- **Review Mode:** consensus
- **Status:** fail → fixed → pending re-verify
- **Previous Packet:** none

## Findings

### F-1.1: CI reverts CD version bumps (Git never updated after release)
- **Severity:** urgent
- **Category:** blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Task 3 release.sh, Task 4 release.yml
- **Consensus:** 2/2
- **Required Fix:** CD workflow must push version bump back to Git after SVN commit
- **Re-check:** Verify Git push-back step in release.yml works correctly and doesn't cause CI loop

### F-1.2: No GitHub remote exists (prerequisite missing)
- **Severity:** high
- **Category:** blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Prerequisites section added before Task 1
- **Consensus:** 1/2 (primary only)
- **Required Fix:** Document GitHub repo creation as prerequisite
- **Re-check:** Verify prerequisites section is complete

### F-1.3: Race condition — no concurrency control
- **Severity:** high
- **Category:** blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Both workflow files now have `concurrency: svn-pipeline`
- **Consensus:** 1/2 (primary only)
- **Required Fix:** Add shared concurrency group to both workflows
- **Re-check:** Verify concurrency key is identical in both files

### F-1.4: Shell injection via `${{ inputs.version }}`
- **Severity:** high
- **Category:** blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** release.yml now uses env vars and bash array
- **Consensus:** 2/2
- **Required Fix:** Pass inputs via env, use bash array for args
- **Re-check:** Verify no direct `${{ inputs.* }}` in shell context

### F-1.5: No semver validation on --version input
- **Severity:** high
- **Category:** blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** release.sh now validates with regex `^[0-9]+\.[0-9]+\.[0-9]+$`
- **Consensus:** 2/2
- **Required Fix:** Validate all version strings before use
- **Re-check:** Verify validation covers both forced and computed versions

### F-1.6: SVN update conflict detection missing
- **Severity:** high
- **Category:** blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Both CI and CD now check for `^C` in svn status after update
- **Consensus:** 1/2 (alt only)
- **Required Fix:** Add conflict check after every svn update
- **Re-check:** Verify conflict detection in both workflows

### F-1.7: SVN deletion pipeline fragile
- **Severity:** high
- **Category:** blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Replaced with while-read loop and `svn rm -- "$path"`
- **Consensus:** 1/2 (alt only)
- **Required Fix:** Use safe loop instead of grep|sed|xargs
- **Re-check:** Verify deletion logic handles special characters

### F-1.8: `sed -i -E` not portable to macOS
- **Severity:** medium
- **Category:** non-blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** release.sh uses temp-file `sed_inplace()` function
- **Consensus:** 2/2
- **Required Fix:** Use portable sed approach
- **Re-check:** Verify sed_inplace works on both macOS and Ubuntu

### F-1.9: sed replacements too broad, no match verification
- **Severity:** medium
- **Category:** non-blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** release.sh now counts matching lines before sed, fails if ≠ 1
- **Consensus:** 1/2 (alt only)
- **Required Fix:** Verify exactly one match before modifying
- **Re-check:** Verify count checks are correct

### F-1.10: `.distignore` mostly ineffective for mirror layout
- **Severity:** low
- **Category:** non-blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Simplified to just `.DS_Store`
- **Consensus:** 2/2
- **Re-check:** Minimal — file is now honest about its limited scope

### F-1.11: `docs/plan/` in .gitignore conflicts with planning workflow
- **Severity:** low
- **Category:** non-blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Removed `docs/plan/` from .gitignore update
- **Consensus:** 2/2
- **Re-check:** Verify .gitignore only adds .DS_Store

### F-1.12: Assets rsync doesn't exclude .DS_Store
- **Severity:** low
- **Category:** non-blocking
- **First Found:** round 1
- **Status:** resolved
- **Evidence:** Assets rsync now has `--exclude=".DS_Store"`
- **Consensus:** 1/2 (primary only)
- **Re-check:** Verify exclude flag in assets rsync command

## Fixes Applied

### Fix for F-1.1 (Git push-back)
- **After Fix:** resolved
- **Change:** Added "Push version bump back to Git" step in release.yml that updates Git trunk files and pushes to main after SVN commit
- **Regression Risk:** Push-back triggers CI (push to main → sync-trunk). Since Git and SVN now have identical versions, the CI rsync should produce no changes → clean exit. Infinite loop prevented.

### Fix for F-1.2 (Prerequisites)
- **After Fix:** resolved
- **Change:** Added explicit prerequisites section before Task 1 with GitHub repo creation steps

### Fix for F-1.3 (Concurrency)
- **After Fix:** resolved
- **Change:** Both workflows have `concurrency: group: svn-pipeline, cancel-in-progress: false`

### Fix for F-1.4 + F-1.5 (Injection + Validation)
- **After Fix:** resolved
- **Change:** Inputs passed via env vars (`VERSION_INPUT`, `DRY_RUN_INPUT`). Bash array for args. Semver regex validation in release.sh.

### Fix for F-1.6 (Conflict detection)
- **After Fix:** resolved
- **Change:** Both CI and CD check `svn status | grep -q '^C'` after `svn update`

### Fix for F-1.7 (Deletion safety)
- **After Fix:** resolved
- **Change:** Replaced `grep|sed|xargs` with `while IFS= read -r line` loop and `svn rm -- "$path"`

### Fix for F-1.8 + F-1.9 (Portable sed + verification)
- **After Fix:** resolved
- **Change:** `sed_inplace()` function using temp-file approach. Line count verification before each sed.

## Re-check Focus for Round 2
- Verify Git push-back step doesn't cause CI infinite loop (SVN and Git should be in sync → no changes to commit)
- Verify no direct `${{ inputs.* }}` interpolation remains in shell context in release.yml
- Verify semver validation covers current version, next version, and forced version
- Verify concurrency key is identical in both workflow files
- Verify `sed_inplace()` function handles the Version: and Stable tag: patterns correctly
