# Multi-Plugin Repo Migration Implementation Plan

**Goal:** Restructure the single-plugin SVN-mirror repo into a multi-plugin layout with `/easy-digital-downloads/` and `/woocommerce/` top-level directories, each with its own release-only workflow and plugin-specific skill reference.

**Architecture:** Each plugin directory mirrors its own WordPress.org SVN repo. Release workflows replace the previous sync+release pair. Root `AGENTS.md` links to per-plugin skills in `docs/skills/`. No auto-sync on push — only manual release triggers.

**Tech Stack:** GitHub Actions (workflow_dispatch), bash release scripts, SVN rsync, `gh` CLI for GitHub Releases

**Execution:** Sequential

---

### Task 1: Create multi-plugin directory structure

**Files:**
- Create: `easy-digital-downloads/` (directory)
- Create: `woocommerce/` (directory)
- Create: `woocommerce/trunk/` (directory, initially empty placeholder)
- Create: `woocommerce/assets/` (directory, initially empty placeholder)
- Create: `woocommerce/tags/` (directory, initially empty placeholder)

- [ ] **Step 1: Move EDD plugin files from root into `easy-digital-downloads/`**
  ```bash
  mkdir -p easy-digital-downloads
  git mv trunk easy-digital-downloads/trunk
  git mv assets easy-digital-downloads/assets
  git mv tags easy-digital-downloads/tags
  git mv scripts easy-digital-downloads/scripts
  ```

- [ ] **Step 2: Create WooCommerce directory structure**
  ```bash
  mkdir -p woocommerce/trunk
  mkdir -p woocommerce/assets
  mkdir -p woocommerce/tags
  mkdir -p woocommerce/scripts
  ```

  Note: WooCommerce trunk contents will be imported in Task 4. For now these are empty placeholders so the directory structure is established.

- [ ] **Step 3: Commit**
  ```bash
  git add easy-digital-downloads/ woocommerce/
  git commit -m "refactor(restructure): move EDD plugin into /easy-digital-downloads, create /woocommerce skeleton"
  ```

---

### Task 2: Delete old sync workflow and old release workflow

**Files:**
- Delete: `.github/workflows/sync-trunk.yml`
- Delete: `.github/workflows/release.yml`

- [ ] **Step 1: Remove sync workflow (no longer needed)**
  ```bash
  git rm .github/workflows/sync-trunk.yml
  ```

- [ ] **Step 2: Remove old single-plugin release workflow (replaced by per-plugin versions)**
  ```bash
  git rm .github/workflows/release.yml
  ```

- [ ] **Step 3: Commit**
  ```bash
  git commit -m "refactor(ci): remove old single-plugin sync and release workflows"
  ```

---

### Task 3: Create per-plugin release workflows

**Files:**
- Create: `.github/workflows/release-edd.yml`
- Create: `.github/workflows/release-woocommerce.yml`

- [ ] **Step 1: Create `release-edd.yml`**
  ```yaml
  name: Release EDD Plugin

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
    group: svn-pipeline-edd
    cancel-in-progress: false

  permissions:
    contents: write

  jobs:
    release:
      name: Release EDD plugin
      runs-on: ubuntu-latest
      env:
        PLUGIN_DIR: easy-digital-downloads
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
            bash easy-digital-downloads/scripts/release.sh "${args[@]}"

        - name: Push version bump back to Git
          if: ${{ inputs.dry_run != 'true' }}
          run: |
            VERSION="${{ steps.release.outputs.released_version }}"

            if [ -z "$VERSION" ]; then
              echo "ERROR: No released_version from release script."
              exit 1
            fi

            if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
              echo "ERROR: Released version '$VERSION' is not valid semver."
              exit 1
            fi

            git config user.name "github-actions[bot]"
            git config user.email "github-actions[bot]@users.noreply.github.com"

            sed_inplace() {
              local pattern="$1"
              local file="$2"
              local tmp
              tmp=$(mktemp)
              sed -E "$pattern" "$file" > "$tmp" && mv "$tmp" "$file"
            }

            sed_inplace "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*/\1${VERSION}/" \
              easy-digital-downloads/trunk/unuspay-crypto-payments-for-easy-digital-downloads.php
            sed_inplace "s/^Stable tag:[[:space:]]*.*/Stable tag: ${VERSION}/" \
              easy-digital-downloads/trunk/readme.txt

            git add easy-digital-downloads/trunk/unuspay-crypto-payments-for-easy-digital-downloads.php easy-digital-downloads/trunk/readme.txt
            git diff --cached --quiet || git commit -m "chore(release-edd): bump version to $VERSION"
            git push

        - name: Create GitHub Release
          if: ${{ inputs.dry_run != 'true' }}
          run: |
            VERSION="${{ steps.release.outputs.released_version }}"
            gh release create "v${VERSION}-edd" \
              --title "EDD v${VERSION}" \
              --notes "https://wordpress.org/plugins/unuspay-crypto-payments-for-easy-digital-downloads/" \
              --repo "${{ github.repository }}"
          env:
            GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
  ```

- [ ] **Step 2: Create `release-woocommerce.yml`**
  ```yaml
  name: Release WooCommerce Plugin

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
    group: svn-pipeline-woocommerce
    cancel-in-progress: false

  permissions:
    contents: write

  jobs:
    release:
      name: Release WooCommerce plugin
      runs-on: ubuntu-latest
      env:
        PLUGIN_DIR: woocommerce
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
            bash woocommerce/scripts/release.sh "${args[@]}"

        - name: Push version bump back to Git
          if: ${{ inputs.dry_run != 'true' }}
          run: |
            VERSION="${{ steps.release.outputs.released_version }}"

            if [ -z "$VERSION" ]; then
              echo "ERROR: No released_version from release script."
              exit 1
            fi

            if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
              echo "ERROR: Released version '$VERSION' is not valid semver."
              exit 1
            fi

            git config user.name "github-actions[bot]"
            git config user.email "github-actions[bot]@users.noreply.github.com"

            sed_inplace() {
              local pattern="$1"
              local file="$2"
              local tmp
              tmp=$(mktemp)
              sed -E "$pattern" "$file" > "$tmp" && mv "$tmp" "$file"
            }

            sed_inplace "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*/\1${VERSION}/" \
              woocommerce/trunk/unuspay-payments.php
            sed_inplace "s/define\('UNUSPAY_PAYMENTS_VERSION',\s*'[^']*'\)/define('UNUSPAY_PAYMENTS_VERSION', '${VERSION}')/" \
              woocommerce/trunk/unuspay-payments.php
            sed_inplace "s/^Stable tag:[[:space:]]*.*/Stable tag: ${VERSION}/" \
              woocommerce/trunk/readme.txt

            git add woocommerce/trunk/unuspay-payments.php woocommerce/trunk/readme.txt
            git diff --cached --quiet || git commit -m "chore(release-woo): bump version to $VERSION"
            git push

        - name: Create GitHub Release
          if: ${{ inputs.dry_run != 'true' }}
          run: |
            VERSION="${{ steps.release.outputs.released_version }}"
            gh release create "v${VERSION}-woo" \
              --title "WooCommerce v${VERSION}" \
              --notes "https://wordpress.org/plugins/unuspay-crypto-payments-for-woocommerce/" \
              --repo "${{ github.repository }}"
          env:
            GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
  ```

  Note: The WooCommerce push-back also updates the `UNUSPAY_PAYMENTS_VERSION` constant in `unuspay-payments.php` line 32. The EDD version tag uses `v{version}-edd` and WooCommerce uses `v{version}-woo` to avoid GitHub Release tag collisions since both plugins share one Git repo. **Breaking change:** the old workflow used bare `v{version}` tags — existing tooling or links referencing that format will need updating.

- [ ] **Step 3: Commit**
  ```bash
  git add .github/workflows/release-edd.yml .github/workflows/release-woocommerce.yml
  git commit -m "feat(ci): add per-plugin release workflows (no auto-sync)"
  ```

---

### Task 4: Adapt EDD release script for new path

**Files:**
- Modify: `easy-digital-downloads/scripts/release.sh`

- [ ] **Step 1: Update configuration section of EDD release script**
  
  Change these lines at the top of `easy-digital-downloads/scripts/release.sh`:
  ```bash
  # === Configuration ===
  PLUGIN_SLUG="unuspay-crypto-payments-for-easy-digital-downloads"
  SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/"
  PLUGIN_FILE="trunk/unuspay-crypto-payments-for-easy-digital-downloads.php"
  README_FILE="trunk/readme.txt"
  SVN_DIR="${HOME}/svn-${PLUGIN_SLUG}"
  ```

  To:
  ```bash
  # === Configuration ===
  PLUGIN_SLUG="unuspay-crypto-payments-for-easy-digital-downloads"
  SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/"
  PLUGIN_FILE="trunk/unuspay-crypto-payments-for-easy-digital-downloads.php"
  README_FILE="trunk/readme.txt"
  SVN_DIR="${HOME}/svn-${PLUGIN_SLUG}"
  GIT_PLUGIN_DIR="easy-digital-downloads"
  ```

  And add the rsync step after the sparse SVN checkout (after `svn update --set-depth immediates tags`), before reading the current version:

  Insert after the sparse checkout block:
  ```bash
  # === Sync from Git to SVN ===
  echo "==> Syncing trunk/ from Git to SVN..."
  rsync -rc --delete \
    --exclude-from="${GITHUB_WORKSPACE}/.distignore" \
    "${GITHUB_WORKSPACE}/${GIT_PLUGIN_DIR}/trunk/" "${SVN_DIR}/trunk/"

  echo "==> Syncing assets/ from Git to SVN..."
  rsync -rc --delete \
    --exclude=".DS_Store" \
    "${GITHUB_WORKSPACE}/${GIT_PLUGIN_DIR}/assets/" "${SVN_DIR}/assets/"

  # Process SVN additions and deletions
  svn add . --force > /dev/null 2>&1 || true
  svn status | while IFS= read -r line; do
    status="${line:0:1}"
    path="${line:8}"
    if [ "$status" = "!" ]; then
      svn rm -- "$path" || true
    fi
  done
  ```

  This replaces the old model where trunk was synced by a separate CI workflow. Now the release script handles sync + bump + tag in one pass.

- [ ] **Step 2: Commit**
  ```bash
  git add easy-digital-downloads/scripts/release.sh
  git commit -m "refactor(release-edd): add rsync sync step to release script"
  ```

---

### Task 5: Create WooCommerce release script

**Files:**
- Create: `woocommerce/scripts/release.sh`

- [ ] **Step 1: Create `woocommerce/scripts/release.sh`**
  
  Adapted from the EDD release script with WooCommerce-specific paths:
  ```bash
  #!/usr/bin/env bash
  set -eo pipefail

  # === Configuration ===
  PLUGIN_SLUG="unuspay-crypto-payments-for-woocommerce"
  SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/"
  PLUGIN_FILE="trunk/unuspay-payments.php"
  README_FILE="trunk/readme.txt"
  SVN_DIR="${HOME}/svn-${PLUGIN_SLUG}"
  GIT_PLUGIN_DIR="woocommerce"
  SEMVER_REGEX='^[0-9]+\.[0-9]+\.[0-9]+$'

  # === Portable sed ===
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
  echo "==> Checking out SVN repository (sparse)..."
  svn checkout --depth immediates "$SVN_URL" "$SVN_DIR"
  cd "$SVN_DIR"
  svn update --set-depth infinity trunk
  svn update --set-depth infinity assets
  svn update --set-depth immediates tags

  # === Sync from Git to SVN ===
  echo "==> Syncing trunk/ from Git to SVN..."
  rsync -rc --delete \
    --exclude-from="${GITHUB_WORKSPACE}/.distignore" \
    "${GITHUB_WORKSPACE}/${GIT_PLUGIN_DIR}/trunk/" "${SVN_DIR}/trunk/"

  echo "==> Syncing assets/ from Git to SVN..."
  rsync -rc --delete \
    --exclude=".DS_Store" \
    "${GITHUB_WORKSPACE}/${GIT_PLUGIN_DIR}/assets/" "${SVN_DIR}/assets/"

  # Process SVN additions and deletions
  svn add . --force > /dev/null 2>&1 || true
  svn status | while IFS= read -r line; do
    status="${line:0:1}"
    path="${line:8}"
    if [ "$status" = "!" ]; then
      svn rm -- "$path" || true
    fi
  done

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

  VERSION_COUNT=$(grep -cE '^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*[0-9]' "$PLUGIN_FILE" || true)
  if [ "$VERSION_COUNT" -ne 1 ]; then
    echo "ERROR: Expected exactly 1 Version line, found $VERSION_COUNT in $PLUGIN_FILE"
    exit 1
  fi

  sed_inplace "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*/\1${NEXT_VERSION}/" "$PLUGIN_FILE"

  # Also update UNUSPAY_PAYMENTS_VERSION constant
  sed_inplace "s/define\('UNUSPAY_PAYMENTS_VERSION',\s*'[^']*'\)/define('UNUSPAY_PAYMENTS_VERSION', '${NEXT_VERSION}')/" "$PLUGIN_FILE"

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
    if [ -n "$GITHUB_OUTPUT" ]; then
      echo "released_version=${NEXT_VERSION}" >> "$GITHUB_OUTPUT"
    fi
    exit 0
  fi

  echo "==> Updating working copy..."
  svn update

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

  if [ -n "$GITHUB_OUTPUT" ]; then
    echo "released_version=${NEXT_VERSION}" >> "$GITHUB_OUTPUT"
  fi

  echo ""
  echo "==> Release $NEXT_VERSION published to SVN successfully!"
  echo "==> Users will see the update within a few hours."
  ```

  Key differences from EDD release script:
  - `PLUGIN_SLUG` = `unuspay-crypto-payments-for-woocommerce`
  - `PLUGIN_FILE` = `trunk/unuspay-payments.php`
  - `GIT_PLUGIN_DIR` = `woocommerce`
  - Also updates `UNUSPAY_PAYMENTS_VERSION` constant (Woo has a second version definition)

- [ ] **Step 2: Make script executable**
  ```bash
  chmod +x woocommerce/scripts/release.sh
  ```

- [ ] **Step 3: Commit**
  ```bash
  git add woocommerce/scripts/release.sh
  git commit -m "feat(release-woo): add WooCommerce release script"
  ```

---

### Task 6: Import WooCommerce plugin files from SVN checkout

**Files:**
- Create: `woocommerce/trunk/` contents
- Create: `woocommerce/assets/` contents

> **Human gate:** This is a one-time local import from an SVN checkout. The source path must exist on the machine running this step. The WooCommerce SVN slug (`unuspay-crypto-payments-for-woocommerce`) must already be registered on WordPress.org and owned by `unustech01`.

- [ ] **Step 1: Set source path (parameterized)**
  ```bash
  # Point to your local WooCommerce SVN checkout
  WOO_SVN_SOURCE="${WOO_SVN_SOURCE:-/Users/junguo/code/unuspay/svn-unuspay-woocommerce}"

  # Verify source exists
  if [ ! -d "$WOO_SVN_SOURCE/trunk" ]; then
    echo "ERROR: WooCommerce SVN checkout not found at $WOO_SVN_SOURCE"
    echo "Set WOO_SVN_SOURCE to your local checkout path, or run:"
    echo "  svn checkout https://plugins.svn.wordpress.org/unuspay-crypto-payments-for-woocommerce/ $WOO_SVN_SOURCE"
    exit 1
  fi
  ```

- [ ] **Step 2: Copy WooCommerce trunk files**
  ```bash
  rsync -rc --delete \
    --exclude='.svn' \
    "$WOO_SVN_SOURCE/trunk/" \
    woocommerce/trunk/
  ```

- [ ] **Step 3: Copy WooCommerce WP.org assets**
  ```bash
  rsync -rc --delete \
    --exclude='.svn' \
    "$WOO_SVN_SOURCE/assets/" \
    woocommerce/assets/
  ```

  This brings in: `unuspay-payments.php`, `includes/` (4 classes), `vendor/` (SDK + autoloader), `assets/images/`, `assets/js/`, `languages/`, `readme.txt`, `LICENSE`, `uninstall.php`, plus banners/screenshots.

  Important: `vendor/` is included because the WooCommerce plugin ships with composer dependencies — do NOT exclude in `.distignore`.

- [ ] **Step 4: Commit**
  ```bash
  git add woocommerce/trunk/ woocommerce/assets/
  git commit -m "feat(woo): import WooCommerce plugin files from SVN checkout"
  ```

---

### Task 7: Create per-plugin skill docs

**Files:**
- Create: `docs/skills/edd.md`
- Create: `docs/skills/woocommerce.md`

- [ ] **Step 1: Create `docs/skills/edd.md`**
  ```markdown
  ---
  name: edd
  description: Project context for the UnusPay EDD plugin. Covers paths, CI/CD, editing rules, and release commands.
  ---

  # UnusPay EDD Plugin — Skill Reference

  ## Plugin Identity

  | Field | Value |
  |-------|-------|
  | **Plugin** | UnusPay Crypto Payments for Easy Digital Downloads |
  | **Directory** | `easy-digital-downloads/` |
  | **SVN slug** | `unuspay-crypto-payments-for-easy-digital-downloads` |
  | **SVN URL** | `https://plugins.svn.wordpress.org/unuspay-crypto-payments-for-easy-digital-downloads/` |
  | **SVN user** | `unustech01` |
  | **Main PHP file** | `easy-digital-downloads/trunk/unuspay-crypto-payments-for-easy-digital-downloads.php` |
  | **Readme** | `easy-digital-downloads/trunk/readme.txt` |
  | **Language** | PHP 7.2+ (procedural, no OOP/namespaces) |
  | **CMS** | WordPress + Easy Digital Downloads 3.x |

  ## Directory Layout

  ```
  easy-digital-downloads/
  ├── trunk/                           ← Edit HERE
  │   ├── unuspay-crypto-payments-...php  ← Main plugin (870 lines, single file)
  │   ├── assets/{css,js,images}/      ← Plugin CSS/JS/images
  │   └── readme.txt                   ← WP.org metadata
  ├── assets/                          ← WP.org banners, icons, screenshots
  ├── tags/                            ← SVN release snapshots (DO NOT EDIT)
  └── scripts/release.sh              ← Release logic
  ```

  ## Code Style

  - Functions: `unuspay_edd_` prefix, snake_case
  - Constants: `UNUSPAY_` prefix, UPPER_SNAKE
  - Globals: `$unuspay_edd_` prefix
  - Procedural only — no classes, no OOP, no namespaces
  - Hooks registered with string callbacks (no closures)
  - REST namespace: `unuspay/edd`

  ## Where to Edit

  | What | Path |
  |------|------|
  | Plugin PHP | `easy-digital-downloads/trunk/unuspay-crypto-payments-for-easy-digital-downloads.php` |
  | Plugin CSS | `easy-digital-downloads/trunk/assets/css/` |
  | Plugin JS | `easy-digital-downloads/trunk/assets/js/` |
  | WP.org metadata | `easy-digital-downloads/trunk/readme.txt` |
  | WP.org banners | `easy-digital-downloads/assets/` |
  | Version | `easy-digital-downloads/trunk/unuspay-crypto-payments-for-easy-digital-downloads.php` → ` * Version: X.Y.Z` |
  | Stable tag | `easy-digital-downloads/trunk/readme.txt` → `Stable tag: X.Y.Z` |

  ## Release

  ```bash
  # Trigger release
  gh workflow run release-edd.yml --field dry_run=false

  # Dry run
  gh workflow run release-edd.yml --field dry_run=true

  # Custom version
  gh workflow run release-edd.yml --field version=2.0.0

  # Monitor
  RUN_ID=$(gh run list --workflow=release-edd.yml --limit 1 --json databaseId --jq '.[0].databaseId')
  gh run watch "$RUN_ID"
  ```

  ## Gotchas

  - `widgets.bundle.js` is pre-built (9MB) — no build step in repo
  - Two `assets/` dirs: `trunk/assets/` (shipped to users) vs top-level `assets/` (WP.org directory page)
  - Empty catch block at line 293 — silently swallows errors
  - Version auto-increment caps at 10: `1.0.10` → `1.1.0`
  ```

- [ ] **Step 2: Create `docs/skills/woocommerce.md`**
  ```markdown
  ---
  name: woocommerce
  description: Project context for the UnusPay WooCommerce plugin. Covers paths, CI/CD, editing rules, and release commands.
  ---

  # UnusPay WooCommerce Plugin — Skill Reference

  ## Plugin Identity

  | Field | Value |
  |-------|-------|
  | **Plugin** | UnusPay Crypto Payments for WooCommerce |
  | **Directory** | `woocommerce/` |
  | **SVN slug** | `unuspay-crypto-payments-for-woocommerce` |
  | **SVN URL** | `https://plugins.svn.wordpress.org/unuspay-crypto-payments-for-woocommerce/` |
  | **SVN user** | `unustech01` |
  | **Main PHP file** | `woocommerce/trunk/unuspay-payments.php` |
  | **Readme** | `woocommerce/trunk/readme.txt` |
  | **Language** | PHP 8.0+ (OOP with strict types) |
  | **CMS** | WordPress + WooCommerce 8.0+ |

  ## Directory Layout

  ```
  woocommerce/
  ├── trunk/                           ← Edit HERE
  │   ├── unuspay-payments.php         ← Main plugin entry (165 lines)
  │   ├── includes/                    ← OOP classes
  │   │   ├── ApiClient.php
  │   │   ├── BlocksIntegration.php
  │   │   ├── WcGatewayUnuspay.php
  │   │   └── WebhookHandler.php
  │   ├── vendor/                      ← Composer deps (UnusPay SDK + autoloader)
  │   ├── assets/{images,js}/          ← Plugin images and JS
  │   ├── languages/                   ← i18n
  │   ├── readme.txt                   ← WP.org metadata
  │   └── uninstall.php               ← Cleanup on uninstall
  ├── assets/                          ← WP.org banners, icons, screenshots
  ├── tags/                            ← SVN release snapshots (DO NOT EDIT)
  └── scripts/release.sh              ← Release logic
  ```

  ## Code Style

  - PHP 8.0+ with `declare(strict_types=1)`
  - OOP with classes in `includes/`
  - Constants: `UNUSPAY_PAYMENTS_*` prefix
  - Text domain: `unuspay-payments`
  - WooCommerce hooks: `woocommerce_*` prefix
  - HPOS compatible

  ## Where to Edit

  | What | Path |
  |------|------|
  | Plugin entry | `woocommerce/trunk/unuspay-payments.php` |
  | Gateway class | `woocommerce/trunk/includes/WcGatewayUnuspay.php` |
  | API client | `woocommerce/trunk/includes/ApiClient.php` |
  | Webhook handler | `woocommerce/trunk/includes/WebhookHandler.php` |
  | Blocks integration | `woocommerce/trunk/includes/BlocksIntegration.php` |
  | Plugin JS | `woocommerce/trunk/assets/js/` |
  | Plugin images | `woocommerce/trunk/assets/images/` |
  | WP.org metadata | `woocommerce/trunk/readme.txt` |
  | WP.org banners | `woocommerce/assets/` |
  | Version | `woocommerce/trunk/unuspay-payments.php` → ` * Version: X.Y.Z` + `UNUSPAY_PAYMENTS_VERSION` constant |
  | Stable tag | `woocommerce/trunk/readme.txt` → `Stable tag: X.Y.Z` |

  ## Release

  ```bash
  # Trigger release
  gh workflow run release-woocommerce.yml --field dry_run=false

  # Dry run
  gh workflow run release-woocommerce.yml --field dry_run=true

  # Custom version
  gh workflow run release-woocommerce.yml --field version=2.0.0

  # Monitor
  RUN_ID=$(gh run list --workflow=release-woocommerce.yml --limit 1 --json databaseId --jq '.[0].databaseId')
  gh run watch "$RUN_ID"
  ```

  ## Gotchas

  - `vendor/` is shipped to users — do NOT exclude in `.distignore`
  - Two version locations: `Version:` header AND `UNUSPAY_PAYMENTS_VERSION` constant — both must be updated
  - JPG banners (not PNG) — MIME type handling must cover `image/jpeg`
  - `Requires PHP: 8.0` — stricter than EDD's PHP 7.2+
  - Existing SVN tags: `1.0.0`, `1.0.1`, `1.1.0` — next auto-increment would be `1.1.1`
  ```

- [ ] **Step 3: Commit**
  ```bash
  git add docs/skills/edd.md docs/skills/woocommerce.md
  git commit -m "docs(skills): add per-plugin skill references for EDD and WooCommerce"
  ```

---

### Task 8: Rewrite root AGENTS.md for multi-plugin model

**Files:**
- Modify: `AGENTS.md`

- [ ] **Step 1: Rewrite `AGENTS.md`**
  ```markdown
  <agents_md>

  <project>
  # UnusPay WordPress Plugins

  Multi-plugin monorepo for UnusPay crypto payment gateway integrations. Each plugin lives in its own top-level directory with independent SVN mirror, release workflow, and skill reference.
  </project>

  <plugins>

  ### Easy Digital Downloads

  - **Directory:** `easy-digital-downloads/`
  - **Skill:** `docs/skills/edd.md` — load for EDD-specific paths, code style, release commands
  - **SVN:** `unuspay-crypto-payments-for-easy-digital-downloads`
  - **PHP:** 7.2+ procedural, single-file plugin (870 lines)
  - **Release:** `gh workflow run release-edd.yml`

  ### WooCommerce

  - **Directory:** `woocommerce/`
  - **Skill:** `docs/skills/woocommerce.md` — load for Woo-specific paths, code style, release commands
  - **SVN:** `unuspay-crypto-payments-for-woocommerce`
  - **PHP:** 8.0+ OOP with strict types, multi-file with `includes/`
  - **Release:** `gh workflow run release-woocommerce.yml`

  </plugins>

  <shared_rules>

  ### Repo Structure

  - Each plugin directory contains: `trunk/` (plugin code), `assets/` (WP.org banners/screenshots), `tags/` (SVN snapshots, DO NOT EDIT), `scripts/release.sh`
  - Shared files at repo root: `AGENTS.md`, `.github/workflows/`, `docs/skills/`, `.distignore`, `.gitignore`

  ### Editing

  - Always edit in `<plugin>/trunk/`, never in `<plugin>/tags/`
  - After editing: `git add <plugin>/trunk/ && git commit -m "fix: ..." && git push origin main`
  - No auto-sync on push — changes reach users only via manual release

  ### Release Flow

  1. Edit plugin files in `<plugin>/trunk/`
  2. Commit and push to `main`
  3. Trigger release: `gh workflow run release-<plugin>.yml`
  4. Workflow: sync SVN trunk → bump version → create SVN tag → commit → push version bump to Git → create GitHub Release
  5. Users see the update within a few hours

  ### Version Locations

  Each plugin has its version in TWO places (both updated automatically by release workflow):
  - `<plugin>/trunk/<main-php-file>` → ` * Version: X.Y.Z`
  - `<plugin>/trunk/readme.txt` → `Stable tag: X.Y.Z`
  - WooCommerce has a third: `UNUSPAY_PAYMENTS_VERSION` constant

  ### Security

  - SVN is entirely managed by CI/CD — never run `svn ci` manually
  - GitHub secrets `SVN_USERNAME` and `SVN_PASSWORD` must be set
  - SVN password is generated at WordPress.org profile (NOT account password)

  </shared_rules>

  <gotchas>
  - **Two `assets/` directories per plugin** — `<plugin>/trunk/assets/` (shipped to users) vs `<plugin>/assets/` (WP.org directory page). Different purposes.
  - **No auto-sync** — pushing to `main` does NOT update SVN trunk. Only the release workflow syncs.
  - **CD pushes back to Git** — after release, the workflow pushes version bump to `main`.
  - **Concurrency is per-plugin** — `svn-pipeline-edd` and `svn-pipeline-woocommerce` run independently.
  - **Version auto-increment caps at 10** — `1.0.10` → `1.1.0` (not `1.0.11`). Major version is always manual.
  - **GitHub Release tags** — EDD uses `v{version}-edd`, WooCommerce uses `v{version}-woo` to avoid tag collisions.
  - **WooCommerce `vendor/` is shipped** — do NOT add to `.distignore`.
  </gotchas>

  <doc_context>
  - **Skill (EDD)**: `docs/skills/edd.md` — EDD-specific paths, code style, release commands
  - **Skill (WooCommerce)**: `docs/skills/woocommerce.md` — Woo-specific paths, code style, release commands
  - **Skill (release)**: `docs/skills/release.md` — how to trigger release workflows
  - **Guide**: `docs/base/_guide/svn-ci-cd.md` — architecture decisions, troubleshooting
  - **EDD release workflow**: `.github/workflows/release-edd.yml`
  - **Woo release workflow**: `.github/workflows/release-woocommerce.yml`
  - **EDD release script**: `easy-digital-downloads/scripts/release.sh`
  - **Woo release script**: `woocommerce/scripts/release.sh`
  </doc_context>

  </agents_md>
  ```

- [ ] **Step 2: Commit**
  ```bash
  git add AGENTS.md
  git commit -m "docs(agents): rewrite AGENTS.md for multi-plugin repo model"
  ```

---

### Task 9: Update release skill for multi-plugin

**Files:**
- Modify: `docs/skills/release.md`

- [ ] **Step 1: Rewrite `docs/skills/release.md`**
  ```markdown
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
  ```

- [ ] **Step 2: Commit**
  ```bash
  git add docs/skills/release.md
  git commit -m "docs(skills): update release skill for multi-plugin model"
  ```

---

### Task 10: Clean up root-level artifacts and update gitignore

**Files:**
- Delete: `docs/skills/unuspay-wp-plugin.md` (replaced by `docs/skills/edd.md`)
- Modify: `docs/base/_guide/svn-ci-cd.md` (update for multi-plugin model)
- Modify: `.gitignore`
- Modify: `.distignore`

- [ ] **Step 1: Remove old single-plugin skill**
  ```bash
  git rm docs/skills/unuspay-wp-plugin.md
  ```

- [ ] **Step 2: Remove tracked tag contents from Git**

  In the new model, tags are SVN-only — created by `svn cp` during release. Git doesn't need tag contents.
  ```bash
  git rm -r easy-digital-downloads/tags/ 2>/dev/null || true
  mkdir -p easy-digital-downloads/tags
  mkdir -p woocommerce/tags
  ```

- [ ] **Step 3: Update `.gitignore` to exclude tags directories**

  Write `.gitignore`:
  ```
  .svn
  .DS_Store
  **/tags/
  ```

- [ ] **Step 4: Keep `.distignore` unchanged**

  `.distignore` stays as `.DS_Store` only. The rsync paths in release scripts already scope to `<plugin>/trunk/` and `<plugin>/assets/`, so root-level files are never synced. No changes needed.

- [ ] **Step 5: Update `docs/base/_guide/svn-ci-cd.md`**

  The existing guide references `sync-trunk.yml`, `release.yml`, and single-plugin paths. Update it to reflect:
  - Multi-plugin layout (`easy-digital-downloads/`, `woocommerce/`)
  - Release-only workflow model (no auto-sync)
  - Per-plugin workflow names (`release-edd.yml`, `release-woocommerce.yml`)
  - GitHub Release creation step

  If the guide is too coupled to the old architecture, mark it as archival:
  ```
  > **Note:** This guide describes the original single-plugin CI/CD architecture.
  > For the current multi-plugin setup, see `docs/skills/edd.md` and `docs/skills/woocommerce.md`.
  ```

- [ ] **Step 6: Commit**
  ```bash
  git add .gitignore docs/skills/unuspay-wp-plugin.md docs/base/_guide/svn-ci-cd.md
  git commit -m "chore: remove old single-plugin skill, update guide, ignore tags/ in Git"
  ```

---

### Task 11: Verify dry run for EDD release

**Files:** None (verification only)

- [ ] **Step 1: Push all changes to remote**
  ```bash
  git push origin main
  ```

- [ ] **Step 2: Trigger EDD dry run**
  ```bash
  gh workflow run release-edd.yml --field dry_run=true
  ```

  Expected output in GitHub Actions logs:
  ```
  ==> Starting release process...
  ==> Checking out SVN repository (sparse)...
  ==> Syncing trunk/ from Git to SVN...
  ==> Syncing assets/ from Git to SVN...
  ==> Current version: 1.0.1
  ==> Next version: 1.0.2
  ==> DRY RUN — no changes committed.
  ==> Version would be: 1.0.2
  ```

- [ ] **Step 3: Verify workflow succeeded**
  ```bash
  RUN_ID=$(gh run list --workflow=release-edd.yml --limit 1 --json databaseId --jq '.[0].databaseId')
  gh run watch "$RUN_ID"
  ```

  Expected: `✓ Release EDD plugin` job completes successfully.

---

### Task 12: Verify dry run for WooCommerce release

**Files:** None (verification only)

- [ ] **Step 1: Trigger WooCommerce dry run**
  ```bash
  gh workflow run release-woocommerce.yml --field dry_run=true
  ```

  Expected output in GitHub Actions logs:
  ```
  ==> Starting release process...
  ==> Checking out SVN repository (sparse)...
  ==> Syncing trunk/ from Git to SVN...
  ==> Syncing assets/ from Git to SVN...
  ==> Current version: 1.1.0
  ==> Next version: 1.1.1
  ==> DRY RUN — no changes committed.
  ==> Version would be: 1.1.1
  ```

- [ ] **Step 2: Verify workflow succeeded**
  ```bash
  RUN_ID=$(gh run list --workflow=release-woocommerce.yml --limit 1 --json databaseId --jq '.[0].databaseId')
  gh run watch "$RUN_ID"
  ```

  Expected: `✓ Release WooCommerce plugin` job completes successfully.
