# Git Tag on Release Implementation Plan

**Goal:** After pushing the version bump back to `main`, tag the commit with the release version so Git history has a matching tag for each release.

**Architecture:** Add a `git tag` + `git push --tags` step between the existing "Push version bump back to Git" and "Create GitHub Release" steps in both release workflows. The tag format matches the GitHub Release tag: `v{version}-edd` for EDD, `v{version}-woo` for WooCommerce.

**Tech Stack:** GitHub Actions, git CLI

**Execution:** Sequential

---

### Task 1: Add Git tag step to EDD release workflow

**Files:**
- Modify: `.github/workflows/release-edd.yml:89-91`

- [ ] **Step 1: Add `git tag` and push between version commit and GitHub Release**

  Insert after `git push` (line 91) in the "Push version bump back to Git" step:
  ```bash
  git tag "v${VERSION}-edd"
  git push origin "v${VERSION}-edd"
  ```

  The full "Push version bump back to Git" step becomes:
  ```yaml
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
      if git ls-remote --tags origin "refs/tags/v${VERSION}-edd" | grep -q "v${VERSION}-edd"; then
        echo "Tag v${VERSION}-edd already exists on remote — skipping."
      else
        git tag "v${VERSION}-edd"
        git push origin "v${VERSION}-edd"
      fi
  ```

  Note: The `git ls-remote` guard handles re-runs gracefully — if the tag already exists on the remote (e.g., from a previous run where the GitHub Release step failed), the step skips instead of failing. We push the specific tag instead of `git push --tags` to avoid accidentally pushing other local tags. With this change, `gh release create` will use the pre-existing tag rather than auto-creating one — this is intentional and eliminates the race condition where `gh release create` could tag a different commit.

- [ ] **Step 2: Commit**
  ```bash
  git add .github/workflows/release-edd.yml
  git commit -m "feat(release-edd): add Git tag push on release"
  ```

- [ ] **Step 3: Verify**
  After a real release, confirm the tag exists:
  ```bash
  git ls-remote --tags origin | grep "v.*-edd"
  ```

---

### Task 2: Add Git tag step to WooCommerce release workflow

**Files:**
- Modify: `.github/workflows/release-woocommerce.yml:89-93`

- [ ] **Step 1: Add `git tag` and push between version commit and GitHub Release**

  Insert after `git push` (line 93) in the "Push version bump back to Git" step:
  ```bash
  git tag "v${VERSION}-woo"
  git push origin "v${VERSION}-woo"
  ```

  The full "Push version bump back to Git" step becomes:
  ```yaml
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
      if git ls-remote --tags origin "refs/tags/v${VERSION}-woo" | grep -q "v${VERSION}-woo"; then
        echo "Tag v${VERSION}-woo already exists on remote — skipping."
      else
        git tag "v${VERSION}-woo"
        git push origin "v${VERSION}-woo"
      fi
  ```

- [ ] **Step 2: Commit**
  ```bash
  git add .github/workflows/release-woocommerce.yml
  git commit -m "feat(release-woo): add Git tag push on release"
  ```

- [ ] **Step 3: Verify**
  After a real release, confirm the tag exists:
  ```bash
  git ls-remote --tags origin | grep "v.*-woo"
  ```
