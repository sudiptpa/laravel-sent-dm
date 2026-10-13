# Releasing

Use this checklist when preparing a package release.

## 1. Start clean

```bash
git switch main
git pull --ff-only
git status --short --branch
```

Create a release-prep branch before editing release docs:

```bash
git switch -c release-2.x.y-docs
```

## 2. Prepare release notes

Update:

- `CHANGELOG.md`
- `UPGRADE.md`, when users need migration or upgrade guidance

Keep an empty `## [Unreleased]` section at the top of the changelog, then add the
dated release section below it:

```md
## [Unreleased]

## [2.x.y] - YYYY-MM-DD
```

Use `UPGRADE.md` for user action items, not for every internal change.

## 3. Verify

Run the full release check set:

```bash
composer validate --no-check-publish
composer lint:check
composer test
composer stan
git diff --check
```

If PHPStan fails because the sandbox cannot open a local TCP listener, rerun the same
`composer stan` command outside the sandbox and keep the result in the release notes
or PR verification.

## 4. Open and merge the release-docs PR

Before committing, show the exact commit message and wait for approval.

After approval:

```bash
git add CHANGELOG.md UPGRADE.md
git commit -m "Prepare 2.x.y release notes"
git push -u origin release-2.x.y-docs
```

Open a PR with the checks from step 3 in the verification section. Merge it before
tagging.

## 5. Tag from main

After the release-docs PR is merged:

```bash
git switch main
git pull --ff-only
git tag --list v2.x.y
```

Create the annotated tag only when the tag does not already exist:

```bash
git tag -a v2.x.y -m "Release 2.x.y"
```

Verify the tag points at `main`:

```bash
git show --no-patch --format='%H %D%n%s' v2.x.y
git rev-parse HEAD
```

Ask before pushing the tag:

```bash
git push origin v2.x.y
```

## 6. Create the GitHub release

Check whether the release already exists:

```bash
gh release view v2.x.y
```

Create the release from the changelog section:

```bash
gh release create v2.x.y --title "v2.x.y" --notes "<release notes>"
```

Verify:

```bash
gh release view v2.x.y --json url,name,isDraft,isPrerelease,tagName,publishedAt
git status --short --branch
```

## 7. Cleanup

After merged PRs, update local `main`, delete merged local branches, and prune stale
remote refs:

```bash
git switch main
git pull --ff-only
git branch -d release-2.x.y-docs
git fetch --prune origin
git status --short --branch
```
