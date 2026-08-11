# Git Command Reference — AssetWise

Quick reference for the git workflow used on this project.

- **Remote:** `origin` → https://github.com/kalpeshkhandelwal11/AssetWise.git
- **Main branch:** `main`
- **Working branch:** `daniels_branch`

## Daily Workflow

```bash
# Check current status (modified/staged/untracked files)
git status

# Check current branch and all branches (local + remote)
git branch -a

# See what changed (unstaged)
git diff

# See what changed (staged)
git diff --staged

# View commit history
git log --oneline -10
git log --oneline --graph --all -20
```

## Staging & Committing

```bash
# Stage specific files (preferred over `git add -A`/`git add .`)
git add path/to/file1 path/to/file2

# Stage all changes in a directory
git add app/Services/

# Commit staged changes
git commit -m "feat: short description of the change"

# Amend the most recent commit (only if NOT yet pushed/shared)
git commit --amend
```

## Pulling & Pushing

```bash
# Pull latest changes for the current branch (daniels_branch)
git pull origin daniels_branch

# Pull latest main into local main
git checkout main
git pull origin main

# Push local commits to your branch
git push origin daniels_branch

# First push of a new local branch (sets upstream tracking)
git push -u origin daniels_branch

# After -u is set once, subsequent pushes/pulls can just be:
git push
git pull
```

## Branching

```bash
# Create and switch to a new feature branch (from current branch)
git checkout -b feature/my-new-feature

# Switch branches
git checkout daniels_branch
git checkout main

# Update your feature branch with latest main
git checkout daniels_branch
git fetch origin
git merge origin/main
# (or, if the team prefers a linear history)
git rebase origin/main

# Delete a local branch (after it's merged)
git branch -d feature/my-new-feature

# Delete a remote branch
git push origin --delete feature/my-new-feature
```

## Syncing with Remote

```bash
# Fetch remote changes without merging (safe, read-only)
git fetch origin

# See how local branch compares to remote
git status
git log origin/daniels_branch..daniels_branch   # commits you have that remote doesn't
git log daniels_branch..origin/daniels_branch   # commits remote has that you don't
```

## Undoing Changes (use with caution)

```bash
# Discard uncommitted changes to a specific file
git checkout -- path/to/file
# (modern syntax)
git restore path/to/file

# Unstage a file (keep the edits)
git restore --staged path/to/file

# Revert a commit by creating a new "undo" commit (safe for shared history)
git revert <commit-hash>

# Reset local branch to match a specific commit
# --soft  = keep changes staged
# --mixed = keep changes unstaged (default)
# --hard  = DISCARD changes entirely (destructive, confirm before running)
git reset --soft <commit-hash>
git reset --mixed <commit-hash>
git reset --hard <commit-hash>
```

## Stashing (temporary shelving of changes)

```bash
# Stash current changes (including untracked files)
git stash push -u -m "wip: description"

# List stashes
git stash list

# Re-apply the most recent stash and remove it from the stash list
git stash pop

# Apply a stash without removing it
git stash apply stash@{0}

# Drop a stash
git stash drop stash@{0}
```

## Inspecting History

```bash
# Show details of a specific commit
git show <commit-hash>

# Show who last changed each line of a file
git blame path/to/file

# Search commit messages
git log --grep="M11"

# Search commit history for a code string
git log -S "MaintenanceService" --oneline
```

## Pull Requests (via GitHub CLI)

```bash
# Create a PR from daniels_branch into main
gh pr create --base main --head daniels_branch --title "M11: Maintenance module" --body "Summary of changes"

# View PR status/checks
gh pr status
gh pr checks

# View PR comments
gh api repos/kalpeshkhandelwal11/AssetWise/pulls/<PR_NUMBER>/comments
```

## Notes / Safety Reminders

- **Never force-push (`git push --force`) to `main`** — only ever force-push your own feature branch, and only if you're sure no one else has pulled it.
- **Never use `--no-verify`** to skip commit hooks unless explicitly instructed — hooks catch real issues (formatting, tests, etc.).
- Prefer `git add <specific files>` over `git add -A`/`git add .` to avoid accidentally committing `.env`, credentials, or build artifacts.
- Run `git status` before any destructive command (`reset --hard`, `checkout .`, `clean -f`) to make sure you're not discarding uncommitted work.
- Use `git revert` instead of `git reset --hard` when undoing commits that have already been pushed/shared.
