> ref: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

## Key SVN Commands Explained

**1. Check out your repository** (download local copy):
```bash
svn co https://plugins.svn.wordpress.org/your-plugin-slug my-local-dir
```

**2. Navigate to your local directory**:
```bash
cd my-local-dir
```

**3. Add your plugin files to trunk**:
```bash
# Copy your plugin files into the trunk folder first, then:
svn add trunk/*
```

**4. Commit your changes** (upload to WordPress.org):
```bash
svn ci -m "Your commit message here"
```

**5. Create a release tag** (when ready to publish):
```bash
svn cp trunk tags/1.0
svn ci -m "Tagging version 1.0"
```

**6. Update your local copy** (to sync with remote):
```bash
svn up
```

**7. Check status of changes**:
```bash
svn stat
```

## SVN Folder Structure
- `/trunk/` - Your development code
- `/tags/` - Release versions (1.0, 1.1, etc.)
- `/assets/` - Screenshots and icons

## Important Notes
- Your WordPress.org username is case-sensitive [developer.wordpress.org](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/#your-account)
- Don't commit every small change - SVN is for releases, not development [developer.wordpress.org](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/#dont-use-svn-for-development)
- Always tag releases for proper version management [developer.wordpress.org](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/#always-tag-releases)

The workflow is: develop locally → commit to trunk → tag releases → users download from tags.


## How to ignore with svn
```bash
svn propset svn:ignore ".gitignore
readme.md" .

# commit the changes
svn ci -m "Ignore all .txt files"

# verify the changes
svn propget svn:ignore .
```