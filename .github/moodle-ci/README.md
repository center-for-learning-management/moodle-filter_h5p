# Plugin dependencies

You can add plugin dependencies via github/moodle-ci/dependencies 
see [`.github/moodle-ci/dependencies-dist`](./dependencies-dist) 

# Plugin-specific Configuration

if the plugin requires specific $CFG array settings, you can add them to 
```extra-config.php``` to pass moodle-plugin-ci test/installation.

# Plugin-specific PHPCS exclusions

`phpcs-exclusions.xml` in this folder is where developers can add
plugin-specific PHPCS exceptions (disable a sniff, restrict it to certain
paths, or adjust its settings) without touching the shared Moodle ruleset.

## Why this file exists

`moodle-plugin-ci phpcs` calls PHP_CodeSniffer with `--standard=moodle`
hard-coded on the command line. Because the standard is set explicitly,
PHPCS will **not** auto-discover a plain `phpcs.xml` dropped in the plugin
root. This file must be passed in explicitly instead.

## One-time setup

In your workflow file (typically `.github/workflows/moodle-ci.yml`), find
the PHPCS step and point `--standard` at this file. The repo is checked
out into `./plugin`, so the path must include that prefix, and it's worth
falling back to the default `moodle` standard if the file isn't there yet:

```yaml
- name: Moodle Code Checker
  if: ${{ !cancelled() }}
  run: |
    standardfile="$(pwd)/plugin/.github/moodle-ci/phpcs-exclusions.xml"
    if [ -f "$standardfile" ]; then
      moodle-plugin-ci phpcs --standard="$standardfile" --max-warnings 0
    else
      moodle-plugin-ci phpcs --max-warnings 0
    fi
```

That's it — from then on, everyone can edit `phpcs-exclusions.xml` without
touching the workflow again.

## Adding an exception

Open `phpcs-exclusions.xml` and add a `<rule>` entry under the marker
comment at the bottom. The file has commented-out examples for the most
common cases:

- Disable a sniff entirely (project-wide)
- Disable a sniff only for specific files/paths (`exclude-pattern`)
- Adjust a sniff's settings instead of turning it off (e.g. allow a longer
  line length)
- Exclude whole directories from all checks (e.g. bundled third-party code)

Find the exact sniff code to use from your failing CI run's output — it's
shown in parentheses at the end of each reported line, e.g.
`(Generic.Files.LineLength)` or `(Squiz.Arrays.ArrayBracketSpacing)`.

**Please add a short comment above each exception explaining why it's
needed** — it saves the next person (often future-you) from having to
reverse-engineer the reason later.

## One-off exceptions

For a single line or block rather than a project-wide rule, prefer inline
suppression directly in the code instead of editing this file:

```php
$x = 'a deliberately long line that should not be wrapped'; // phpcs:ignore Generic.Files.LineLength
```

```php
// phpcs:disable Squiz.Arrays.ArrayBracketSpacing
$x = array (1, 2, 3);
// phpcs:enable Squiz.Arrays.ArrayBracketSpacing
```

# Excluding files from checks — `.moodle-plugin-ci.yml`

`phpcs-exclusions.xml` above controls which **sniffs/rules** run. If you
instead need to stop a whole **file or path** from being scanned at all
(by phpcs, phpmd, phpcpd, ...), use a `.moodle-plugin-ci.yml` file instead.
A template with usage notes lives at
[`.github/moodle-ci/moodle-plugin-ci.yml-dist`](./moodle-plugin-ci.yml-dist) —
copy it to the **plugin repo root** (next to `version.php`) and rename it
to `.moodle-plugin-ci.yml`.

## How it works

```yaml
# Applies to every check (phpcs, phpmd, phpcpd, ...)
filter:
  notPaths:
    - 'classes/external/legacy_bridge.php'
    - 'thirdparty'
  notNames:
    - '*_generated.php'

# Only applies to `moodle-plugin-ci phpmd` — replaces (not merges with)
# the generic `filter` above for this one command
filter-phpmd:
  notPaths:
    - 'classes/report/heavy_export.php'
  notNames:
    - '*_test_stub.php'
```

- `notPaths` — relative to the plugin root, matched against the file path.
- `notNames` — filename glob patterns, matched against the basename.
- A command-specific key (`filter-phpcs`, `filter-phpmd`, `filter-phpcpd`,
  ...) **overrides** the generic `filter` for that command rather than
  adding to it — repeat any shared entries if you need both.
- `.moodle-plugin-ci.yml` files also work in **subdirectories** (e.g. a
  subplugin folder) and get merged automatically, with `notPaths` prefixed
  by that subdirectory so they still resolve correctly from the plugin
  root.

## When to use this instead of `phpcs-exclusions.xml`

- A whole file is irrelevant to code-quality checks (generated code,
  vendored third-party code, a deliberately-messy legacy file) →
  `.moodle-plugin-ci.yml`.
- You want to keep checking a file but turn off/adjust one specific
  sniff or rule everywhere → `phpcs-exclusions.xml` (or the equivalent
  custom `phpmd.xml` ruleset for PHPMD).

As with the PHPCS ruleset, **add a short comment explaining why** next to
each entry.

# Whitelisting ESLint rules per subdirectory

ESLint's legacy `.eslintrc.*` config format cascades: for any JS file it
lints, ESLint walks up from that file's directory towards the project
root, picking up every `.eslintrc.*` it finds along the way and merging
them (closer directories win on conflicts), stopping once it hits a
config with `"root": true` (Moodle's own root config has this set). This
is standard ESLint behaviour — nothing moodle-plugin-ci specific — so it
works for both the `moodle-plugin-ci grunt` step and the manual
`eslint --fix` step in the CI workflow.

To relax or change rules for just one part of the plugin (e.g. an `amd/`
subtree with older code, or a `tests/` folder), drop an `.eslintrc.json`
into that subdirectory:

```json
{
  "rules": {
    "no-console": "off",
    "camelcase": ["warn", { "properties": "never" }]
  }
}
```

Notes:

- Only files **inside that subdirectory** (and its children) are
  affected — everything else keeps using Moodle's root config.
- You don't need `"extends"` or to redeclare the rest of the ruleset;
  this file is merged on top of every config found further up, so you
  only need to list what you're changing.
- Keep it as small and targeted as possible, and add a comment (JSON
  doesn't support comments — use a sibling `README.md` or a note in the
  PR) explaining why the subtree needs different rules.
- If a rule genuinely only needs relaxing for a single line rather than
  a whole directory, prefer an inline suppression instead:

  ```js
  // eslint-disable-next-line no-console
  console.log('debug output');
  ```

# Running checks locally with `moodle-plugin-ci.phar`

You don't have to push to GitHub just to see if `phpcs`/`phpmd`/etc. pass.
The tool ships a self-contained `.phar` you can run against your plugin
directly on your machine.

## 1. Download the phar

Grab the latest release from the
[releases page](https://github.com/moodlehq/moodle-plugin-ci/releases):

```bash
wget https://github.com/moodlehq/moodle-plugin-ci/releases/download/<version>/moodle-plugin-ci.phar
```

Replace `<version>` with the version shown on the releases page (e.g.
`4.5.3`). It's safe to drop this file straight into your local Moodle
install directory — it's already covered by Moodle core's `.gitignore`.

## 2. Run the static-analysis checks (no Moodle install required)

These commands only need your plugin's own files — no database, no full
Moodle codebase — so they run fast and are the closest local equivalent
to the `test` job's lint/style steps:

```bash
php moodle-plugin-ci.phar phplint    ./path/to/plugin
php moodle-plugin-ci.phar phpcpd     ./path/to/plugin
php moodle-plugin-ci.phar phpmd      ./path/to/plugin
php moodle-plugin-ci.phar mustache   ./path/to/plugin
php moodle-plugin-ci.phar validate   ./path/to/plugin
php moodle-plugin-ci.phar savepoints ./path/to/plugin

# Point at your plugin-specific overrides the same way the workflow does:
php moodle-plugin-ci.phar phpcs ./path/to/plugin \
  --standard=./path/to/plugin/.github/moodle-ci/phpcs-exclusions.xml \
  --max-warnings 0

php moodle-plugin-ci.phar phpmd ./path/to/plugin \
  --rules=./path/to/plugin/.github/moodle-ci/phpmd-exclusions.xml
```

`phpcbf` (the auto-fixer) works the same way and can save you a round
trip through CI's `fix-and-pr` job:

```bash
php moodle-plugin-ci.phar phpcbf ./path/to/plugin \
  --standard=./path/to/plugin/.github/moodle-ci/phpcs-exclusions.xml
```

## 3. Running PHPUnit / Behat locally (needs a full install)

`phpunit` and `behat` need an actual Moodle codebase and database to run
against, so they need the `install` command first — this is the same
step the `test` job runs in CI:

```bash
php moodle-plugin-ci.phar install --plugin ./path/to/plugin --db-host=127.0.0.1
```

Set `DB` and `MOODLE_BRANCH` as environment variables beforehand to
match what the workflow uses (e.g. `DB=mariadb`,
`MOODLE_BRANCH=MOODLE_502_STABLE`), and make sure a MariaDB/Postgres
instance is reachable at `--db-host`. After that, the usual commands
work locally:

```bash
php moodle-plugin-ci.phar phpunit ./path/to/plugin --fail-on-warning
php moodle-plugin-ci.phar behat   ./path/to/plugin --profile chrome
```

## Notes

- Keep `moodle-plugin-ci.phar` out of git (Moodle's own `.gitignore`
  already covers it if it's dropped inside a Moodle install directory;
  otherwise add it to your own `.gitignore`/`.git/info/exclude`).
- `php moodle-plugin-ci.phar selfupdate` updates the phar in place —
  handy since new Moodle branches/PHP versions land in new releases.
- If you'd rather install via Composer instead of the phar (e.g. to also
  get `vendor/bin/phpcs`/`phpcbf` for running against arbitrary files,
  not just full plugins), see the ["Using in a Local Dev Environment"](https://moodlehq.github.io/moodle-plugin-ci/#using-in-a-local-dev-environment)
  section of the upstream docs.
