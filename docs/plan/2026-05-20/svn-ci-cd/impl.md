# SVN CI/CD Pipeline Implementation Plan

**Goal:** Create two GitHub Actions workflows and a release script that automate SVN trunk syncing (CI) and versioned releases (CD) for the UnusPay WordPress plugin.

**Architecture:** CI workflow (`sync-trunk.yml`) triggers on push to `main`, does a sparse SVN checkout, rsyncs `trunk/` and `assets/` from Git to SVN, and commits if there are changes. CD workflow (`release.yml`) triggers on manual dispatch via a skill, calls `scripts/release.sh` which auto-increments the version, creates an SVN tag, updates stable tag, commits to SVN, then **pushes the version bump back to Git** to prevent the next CI sync from reverting it. Both use `SVN_USERNAME` and `SVN_PASSWORD` GitHub secrets. Both share a concurrency group to prevent race conditions.

**Tech Stack:** GitHub Actions, bash, svn, rsync, sed

**Execution:** Sequential

**Prerequisites (before Task 1):**
1. Create a GitHub repository under the UnusPay org
2. Add it as remote: `git remote add origin git@github.com:unuspay/<repo-name>.git`
3. Push existing code: `git push -u origin main`
4. Add GitHub secrets: `SVN_USERNAME` and `SVN_PASSWORD` (generate SVN password at WordPress.org profile → SVN password)

---

### Task 1: Create `.distignore`

**Files:**
- Create: `.distignore`

- [ ] **Step 1: Create `.distignore`**
  Create `.distignore` at repo root. Although the rsync source is `trunk/` (making most root-level excludes redundant), this file serves as documentation and protects against future layout changes:

  ```
  .DS_Store
  ```

- [ ] **Step 2: Commit**
  ```
  git add .distignore
  git commit -m "chore(ci): add .distignore for SVN deploy exclusions"
  ```

---

### Task 2: Create CI workflow `.github/workflows/sync-trunk.yml`

**Files:**
- Create: `.github/workflows/sync-trunk.yml`

- [ ] **Step 1: Create directory**
  ```bash
  mkdir -p .github/workflows
  ```

- [ ] **Step 2: Write `sync-trunk.yml`**
  Create `.github/workflows/sync-trunk.yml` with the complete CI workflow:

  ```yaml
  name: Sync SVN Trunk

  on:
    push:
      branches:
        - main

  concurrency:
    group: svn-pipeline
    cancel-in-progress: false

  jobs:
    sync-trunk:
      name: Sync trunk/ to WordPress.org SVN
      runs-on: ubuntu-latest
      env:
        PLUGIN_SLUG: unuspay-crypto-payments-for-easy-digital-downloads
        SVN_URL: https://plugins.svn.wordpress.org/unuspay-crypto-payments-for-easy-digital-downloads/

      steps:
        - name: Checkout Git repository
          uses: actions/checkout@v4

        - name: Install SVN
          run: sudo apt-get update && sudo apt-get install -y subversion rsync

        - name: Sparse SVN checkout
          run: |
            SVN_DIR="${HOME}/svn-${PLUGIN_SLUG}"
            svn checkout --depth immediates "$SVN_URL" "$SVN_DIR"
            cd "$SVN_DIR"
            svn update --set-depth infinity trunk
            svn update --set-depth infinity assets

        - name: Sync files to SVN
          run: |
            SVN_DIR="${HOME}/svn-${PLUGIN_SLUG}"
            echo "::group::Syncing trunk/"
            rsync -rc --delete \
              --exclude-from=".distignore" \
              "${GITHUB_WORKSPACE}/trunk/" "${SVN_DIR}/trunk/"
            echo "::endgroup::"

            echo "::group::Syncing assets/"
            rsync -rc --delete \
              --exclude=".DS_Store" \
              "${GITHUB_WORKSPACE}/assets/" "${SVN_DIR}/assets/"
            echo "::endgroup::"

        - name: Process SVN additions and deletions
          run: |
            SVN_DIR="${HOME}/svn-${PLUGIN_SLUG}"
            cd "$SVN_DIR"

            # Add all new files
            svn add . --force > /dev/null 2>&1 || true

            # Remove deleted files — safe loop with proper path handling
            svn status | while IFS= read -r line; do
              status="${line:0:1}"
              path="${line:8}"
              if [ "$status" = "!" ]; then
                svn rm -- "$path" || true
              fi
            done

        - name: Set MIME types on image assets
          run: |
            SVN_DIR="${HOME}/svn-${PLUGIN_SLUG}"
            cd "$SVN_DIR"
            if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.png' -print -quit)"; then
              svn propset svn:mime-type image/png assets/*.png || true
            fi
            if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.jpg' -print -quit)"; then
              svn propset svn:mime-type image/jpeg assets/*.jpg || true
            fi
            if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.gif' -print -quit)"; then
              svn propset svn:mime-type image/gif assets/*.gif || true
            fi
            if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.svg' -print -quit)"; then
              svn propset svn:mime-type image/svg+xml assets/*.svg || true
            fi

        - name: Check for changes and commit
          env:
            SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
            SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
          run: |
            SVN_DIR="${HOME}/svn-${PLUGIN_SLUG}"
            cd "$SVN_DIR"

            echo "::group::SVN status"
            svn status
            echo "::endgroup::"

            if [ -z "$(svn status)" ]; then
              echo "No changes to commit. Working tree clean."
              exit 0
            fi

            svn update

            # Check for conflicts after update
            if svn status | grep -q '^C'; then
              echo "ERROR: SVN conflict detected. Resolve manually."
              svn status | grep '^C'
              exit 1
            fi

            svn commit -m "Sync trunk from Git commit ${GITHUB_SHA:0:7}" \
              --no-auth-cache \
              --non-interactive \
              --username "$SVN_USERNAME" \
              --password "$SVN_PASSWORD"

            echo "Trunk synced successfully."
  ```

- [ ] **Step 3: Commit**
  ```
  git add .github/workflows/sync-trunk.yml
  git commit -m "feat(ci): add trunk sync workflow on push to main"
  ```

---

### Task 3: Create release script `scripts/release.sh`

**Files:**
- Create: `scripts/release.sh`

- [ ] **Step 1: Create directory**
  ```bash
  mkdir -p scripts
  ```

- [ ] **Step 2: Write `scripts/release.sh`**
  Create `scripts/release.sh` with the complete release logic:

  ```bash
  #!/usr/bin/env bash
  set -eo pipefail

  # === Configuration ===
  PLUGIN_SLUG="unuspay-crypto-payments-for-easy-digital-downloads"
  SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/"
  PLUGIN_FILE="trunk/unuspay-crypto-payments-for-easy-digital-downloads.php"
  README_FILE="trunk/readme.txt"
  SVN_DIR="${HOME}/svn-${PLUGIN_SLUG}"
  SEMVER_REGEX='^[0-9]+\.[0-9]+\.[0-9]+$'

  # === Portable sed ===
  # Use temp-file approach to avoid GNU vs BSD sed differences
  # Uses [[:space:]] instead of \s for POSIX compatibility
  sed_inplace() {
    local pattern="$1"
    local file="$2"
    local tmp
    tmp=$(mktemp)
    sed -E "$pattern" "$file" > "$tmp" && mv "$tmp" "$file"
  }

  # === Parse arguments ===
  DRY_RUN=false
  FORCE_VERSION=""
  while [[ $# -gt 0 ]]; do
    case $1 in
      --dry-run)
        DRY_RUN=true
        shift
        ;;
      --version)
        FORCE_VERSION="$2"
        shift 2
        ;;
      *)
        echo "Unknown argument: $1"
        echo "Usage: $0 [--dry-run] [--version X.Y.Z]"
        exit 1
        ;;
    esac
  done

  # === Validate environment ===
  if [ -z "$SVN_USERNAME" ] || [ -z "$SVN_PASSWORD" ]; then
    echo "ERROR: SVN_USERNAME and SVN_PASSWORD environment variables are required."
    exit 1
  fi

  echo "==> Starting release process..."

  # === Sparse SVN checkout ===
  # Note: no conflict check needed here — fresh checkout cannot have conflicts
  echo "==> Checking out SVN repository (sparse)..."
  svn checkout --depth immediates "$SVN_URL" "$SVN_DIR"
  cd "$SVN_DIR"
  svn update --set-depth infinity trunk
  svn update --set-depth infinity assets
  svn update --set-depth immediates tags

  # === Read current version ===
  CURRENT_VERSION=$(grep -iE '^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*[0-9]' "$PLUGIN_FILE" | head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')

  if [ -z "$CURRENT_VERSION" ]; then
    echo "ERROR: Could not read current version from $PLUGIN_FILE"
    exit 1
  fi

  if [[ ! "$CURRENT_VERSION" =~ $SEMVER_REGEX ]]; then
    echo "ERROR: Current version '$CURRENT_VERSION' is not valid semver (MAJOR.MINOR.PATCH)"
    exit 1
  fi

  echo "==> Current version: $CURRENT_VERSION"

  # === Compute next version ===
  if [ -n "$FORCE_VERSION" ]; then
    if [[ ! "$FORCE_VERSION" =~ $SEMVER_REGEX ]]; then
      echo "ERROR: Forced version '$FORCE_VERSION' is not valid semver (MAJOR.MINOR.PATCH)"
      exit 1
    fi
    NEXT_VERSION="$FORCE_VERSION"
    echo "==> Using forced version: $NEXT_VERSION"
  else
    IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"
    if [ "$PATCH" -lt 10 ]; then
      NEXT_VERSION="${MAJOR}.${MINOR}.$((PATCH + 1))"
    else
      NEXT_VERSION="${MAJOR}.$((MINOR + 1)).0"
    fi
    echo "==> Next version: $NEXT_VERSION"
  fi

  # === Validate tag doesn't exist ===
  if [ -d "tags/$NEXT_VERSION" ]; then
    echo "ERROR: Version $NEXT_VERSION already exists in SVN tags. Aborting."
    exit 1
  fi

  # === Bump version in plugin file ===
  echo "==> Updating Version in $PLUGIN_FILE..."

  # Verify exactly one Version line exists before modifying
  VERSION_COUNT=$(grep -cE '^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*[0-9]' "$PLUGIN_FILE" || true)
  if [ "$VERSION_COUNT" -ne 1 ]; then
    echo "ERROR: Expected exactly 1 Version line, found $VERSION_COUNT in $PLUGIN_FILE"
    exit 1
  fi

  sed_inplace "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*/\1${NEXT_VERSION}/" "$PLUGIN_FILE"

  # === Update Stable tag in readme.txt ===
  echo "==> Updating Stable tag in $README_FILE..."

  STABLE_COUNT=$(grep -cE '^Stable tag:[[:space:]]*[0-9]' "$README_FILE" || true)
  if [ "$STABLE_COUNT" -ne 1 ]; then
    echo "ERROR: Expected exactly 1 Stable tag line, found $STABLE_COUNT in $README_FILE"
    exit 1
  fi

  sed_inplace "s/^Stable tag:[[:space:]]*.*/Stable tag: ${NEXT_VERSION}/" "$README_FILE"

  # === Create SVN tag ===
  echo "==> Creating SVN tag tags/$NEXT_VERSION..."
  svn cp trunk "tags/$NEXT_VERSION"

  # === Set MIME types on image assets ===
  echo "==> Setting MIME types on image assets..."
  if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.png' -print -quit)"; then
    svn propset svn:mime-type image/png assets/*.png || true
  fi
  if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.jpg' -print -quit)"; then
    svn propset svn:mime-type image/jpeg assets/*.jpg || true
  fi
  if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.gif' -print -quit)"; then
    svn propset svn:mime-type image/gif assets/*.gif || true
  fi
  if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.svg' -print -quit)"; then
    svn propset svn:mime-type image/svg+xml assets/*.svg || true
  fi

  # === Show status ===
  echo "==> SVN status:"
  svn status

  # === Commit or dry-run ===
  if [ "$DRY_RUN" = true ]; then
    echo ""
    echo "==> DRY RUN — no changes committed."
    echo "==> Version would be: $NEXT_VERSION"
    echo "==> Changes preview:"
    svn status
    # Export version for GitHub Actions output even in dry-run
    if [ -n "$GITHUB_OUTPUT" ]; then
      echo "released_version=${NEXT_VERSION}" >> "$GITHUB_OUTPUT"
    fi
    exit 0
  fi

  echo "==> Updating working copy..."
  svn update

  # Check for conflicts after update
  if svn status | grep -q '^C'; then
    echo "ERROR: SVN conflict detected after update. Resolve manually."
    svn status | grep '^C'
    exit 1
  fi

  echo "==> Committing release $NEXT_VERSION..."
  svn commit -m "Release $NEXT_VERSION" \
    --no-auth-cache \
    --non-interactive \
    --username "$SVN_USERNAME" \
    --password "$SVN_PASSWORD"

  # Export version for GitHub Actions output
  if [ -n "$GITHUB_OUTPUT" ]; then
    echo "released_version=${NEXT_VERSION}" >> "$GITHUB_OUTPUT"
  fi

  echo ""
  echo "==> Release $NEXT_VERSION published to SVN successfully!"
  echo "==> Users will see the update within a few hours."
  ```

- [ ] **Step 3: Make executable**
  ```bash
  chmod +x scripts/release.sh
  ```

- [ ] **Step 4: Commit**
  ```
  git add scripts/release.sh
  git commit -m "feat(cd): add release script with auto-versioning"
  ```

---

### Task 4: Create CD workflow `.github/workflows/release.yml`

**Files:**
- Create: `.github/workflows/release.yml`

- [ ] **Step 1: Write `release.yml`**
  Create `.github/workflows/release.yml`:

  ```yaml
  name: Release to WordPress.org

  on:
    workflow_dispatch:
      inputs:
        dry_run:
          description: "Dry run (no commit)"
          required: false
          default: "false"
          type: choice
          options:
            - "false"
            - "true"
        version:
          description: "Force version (leave empty for auto-increment, must be X.Y.Z)"
          required: false
          type: string

  concurrency:
    group: svn-pipeline
    cancel-in-progress: false

  jobs:
    release:
      name: Release new version
      runs-on: ubuntu-latest
      env:
        SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
        SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
        DRY_RUN_INPUT: ${{ inputs.dry_run }}
        VERSION_INPUT: ${{ inputs.version }}

      steps:
        - name: Checkout Git repository
          uses: actions/checkout@v4
          with:
            token: ${{ secrets.GITHUB_TOKEN }}

        - name: Install SVN
          run: sudo apt-get update && sudo apt-get install -y subversion rsync

        - name: Run release script
          id: release
          run: |
            args=()
            if [ "$DRY_RUN_INPUT" = "true" ]; then
              args+=(--dry-run)
            fi
            if [ -n "$VERSION_INPUT" ]; then
              args+=(--version "$VERSION_INPUT")
            fi
            bash scripts/release.sh "${args[@]}"

        - name: Push version bump back to Git
          if: ${{ inputs.dry_run != 'true' }}
          run: |
            # Read the released version from the release script output (set via GITHUB_OUTPUT)
            VERSION="${{ steps.release.outputs.released_version }}"

            if [ -z "$VERSION" ]; then
              echo "ERROR: No released_version from release script. Cannot push back to Git."
              exit 1
            fi

            # Validate version is proper semver before using in sed/commit
            if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
              echo "ERROR: Released version '$VERSION' is not valid semver. Aborting Git push-back."
              exit 1
            fi

            git config user.name "github-actions[bot]"
            git config user.email "github-actions[bot]@users.noreply.github.com"

            # Portable sed (temp-file approach)
            sed_inplace() {
              local pattern="$1"
              local file="$2"
              local tmp
              tmp=$(mktemp)
              sed -E "$pattern" "$file" > "$tmp" && mv "$tmp" "$file"
            }

            # Update Git trunk files to match the released version in SVN
            sed_inplace "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*/\1${VERSION}/" \
              trunk/unuspay-crypto-payments-for-easy-digital-downloads.php
            sed_inplace "s/^Stable tag:[[:space:]]*.*/Stable tag: ${VERSION}/" \
              trunk/readme.txt

            git add trunk/unuspay-crypto-payments-for-easy-digital-downloads.php trunk/readme.txt
            git diff --cached --quiet || git commit -m "chore(release): bump version to $VERSION"
            git push
  ```

- [ ] **Step 2: Commit**
  ```
  git add .github/workflows/release.yml
  git commit -m "feat(cd): add release workflow with manual dispatch"
  ```

---

### Task 5: Create release skill `~/.jun/skills/release/SKILL.md`

**Files:**
- Create: `~/.jun/skills/release/SKILL.md`

- [ ] **Step 1: Create directory**
  ```bash
  mkdir -p ~/.jun/skills/release
  ```

- [ ] **Step 2: Write skill file**
  Create `~/.jun/skills/release/SKILL.md`:

  ```markdown
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
     cd /Users/junguo/code/unuspay/svn-wordplress-plugin
     git remote -v
     git status
     ```

  2. Read the current version from the plugin file:
     ```bash
     grep -iE '^\s*\*?\s*Version:\s*[0-9]' trunk/unuspay-crypto-payments-for-easy-digital-downloads.php
     ```

  3. Ask the user to confirm the release (show current version and what the next version will be):
     - If current is `X.Y.Z` where Z < 10 → next is `X.Y.(Z+1)`
     - If current is `X.Y.10` → next is `X.(Y+1).0`
     - Or the user can specify a custom version (must be `X.Y.Z` format)

  4. Trigger the GitHub Actions workflow:
     ```bash
     cd /Users/junguo/code/unuspay/svn-wordplress-plugin
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
  - This skill assumes the repo is at `/Users/junguo/code/unuspay/svn-wordplress-plugin`
  ```

- [ ] **Step 3: No commit needed** (skill file is outside the repo)

---

### Task 6: Update `.gitignore`

**Files:**
- Modify: `.gitignore`

- [ ] **Step 1: Update `.gitignore`**
  Current `.gitignore` contains only `.svn`. Add `.DS_Store`:

  ```
  .svn
  .DS_Store
  ```

  Note: We do NOT add `docs/plan/` because planning artifacts should be tracked in Git for cross-session continuity.

- [ ] **Step 2: Commit**
  ```
  git add .gitignore
  git commit -m "chore: update .gitignore with OS files"
  ```

---

## File Mapping Summary

| File | Action | Purpose |
|------|--------|---------|
| `.distignore` | Create | Exclude .DS_Store from SVN sync |
| `.github/workflows/sync-trunk.yml` | Create | CI: trunk sync on push to main |
| `.github/workflows/release.yml` | Create | CD: release via workflow_dispatch + Git push-back |
| `scripts/release.sh` | Create | Release logic (auto-version, SVN tag, commit) |
| `~/.jun/skills/release/SKILL.md` | Create | Skill to trigger CD pipeline |
| `.gitignore` | Modify | Add .DS_Store |

## Key Design Decisions (post-review fixes)

1. **Git push-back after release** — CD workflow pushes version bump back to Git `main` after SVN commit, preventing CI from reverting it (F1)
2. **Concurrency group** — Both workflows share `concurrency: svn-pipeline` to prevent simultaneous SVN operations (F8)
3. **Environment variables for inputs** — `inputs.version` passed via `env:` not shell interpolation (F2)
4. **Semver validation** — All versions validated with regex `^[0-9]+\.[0-9]+\.[0-9]+$` before use (F3)
5. **Portable sed** — Temp-file approach avoids GNU vs BSD `sed -i` differences (F4)
6. **SVN conflict detection** — Both CI and CD check for `^C` in `svn status` after `svn update` (F9)
7. **Safe deletion loop** — Replaced fragile `grep | sed | xargs` with a while-read loop using `svn rm -- "$path"` (F10)
8. **Version edit verification** — Script counts matching lines before sed, fails if ≠ 1 (F11)
9. **`docs/plan/` not gitignored** — Planning artifacts should be tracked in Git (F6)
