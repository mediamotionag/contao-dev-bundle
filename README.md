# Contao Dev Bundle

## About
Use this bundle while building a new website.
It let's you overwrite the robots tag and the "no-cache" for all pages based on the current url (dev-domains).
So no development-domain get indexed ever again :)

## Installation
Install [composer](https://getcomposer.org) if you haven't already.
Afterwords, you can add the bundle like this:
```sh
composer require mediamotionag/contao-dev-bundle
```

## Usage
1. Install Bundle
2. Go to Contao Settings to see the options under "Entwickler Einstellungen"
3. Define your development-domains - like: 127.0.0.1,localhost etc.
2. Enjoy

## Version switch
Admins find the legend **Version** in **System → Einstellungen**: a select with the 20 newest tags
(the current one excluded) and three buttons. Saving the settings never switches.

- **Tags abrufen** runs `git fetch --tags --prune --prune-tags --force origin`.
- **Wechseln** starts `memo:dev:switch-tag <tag>` as a detached background process and opens a modal
  that polls the log every 2 seconds.
- **Protokoll** opens the same modal for the last 10 runs.

The modal uses Contao's dialog component and shows a progress bar, the steps (Prüfen, Code auschecken,
Composer install, Backup & Migration, Cache & Assets), the elapsed time and the log. It refreshes every
second while a job runs, through `/_memo-dev/version-poll/{run}`: outside `/contao` and without a session
(composer and the job clear the cache, and a backend request crashing in that moment would end the login),
secured by a per-run HMAC token that only the logged-in status route hands out, and incremental (only new
log bytes). It offers:
- **Rollback auf …**: switches the code back to the ref the last switch started from. The database is not touched.
- **Datenbank wiederherstellen**: restores the backup `contao:migrate` created during the last switch
  (`memo:dev:restore-backup`). Everything changed since then is lost.

Only one job runs at a time (`var/memo-dev/version-switch/switch.lock`). A job that dies is shown as
«Abgebrochen» automatically.

A switch runs `git checkout`, `composer install` (keeps `--no-dev` if the current installation has no
dev packages), `contao:migrate --no-interaction` (creates a backup first, never runs DROPs),
`cache:clear`, `cache:warmup` and `assets:install`. There is no maintenance mode: like a manual deploy,
single requests can fail while `vendor/` is replaced. If a step fails after the checkout, the previous
ref is checked out again and `composer install` re-runs; the database is not restored.

The jobs also run on the CLI: `memo:dev:switch-tag v1.2.3`, `memo:dev:switch-tag --rollback`,
`memo:dev:restore-backup <file>`. Status and logs are stored in `var/memo-dev/version-switch/`.

### Requirements
- The project root is a git checkout without modified tracked files, and the web server user can fetch from `origin` (deploy key).
- `proc_open()` is enabled.
- The PHP CLI binary has the same version as the web PHP. Under PHP-FPM the detected binary may differ; set it explicitly:
  ```dotenv
  MEMO_DEV_PHP_BINARY=/usr/bin/php8.4
  MEMO_DEV_COMPOSER_BINARY=/home/user/bin/composer
  ```

### Risks
- After a switch the checkout is in detached HEAD. Run `git checkout master` (or use Rollback) before the next manual `git pull`.
- `composer install` installs exactly the lock file of the target tag. A `vendor/` that was behind its lock gets updated as a side effect.
- The schema diff skips DROPs but can still alter columns. Check `contao:migrate --dry-run` before switching a live site.
- Each backup can delete older dumps in `var/backups/` through Contao's retention rules.
- If the target tag contains an older version of this bundle without the version switch, switching back needs SSH.
- Every admin can deploy any tag that exists on `origin`.

## Contribution
Bug reports and pull requests are welcome
