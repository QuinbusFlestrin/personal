# Deploying to Infomaniak Shared Web Hosting

This describes the one-time setup to connect this repo's `main` branch to an
Infomaniak Shared Web Hosting plan via `.github/workflows/deploy.yml`. After
this is done once, every push to `main` that passes tests deploys
automatically.

## 1. Prepare the Infomaniak side

1. In the Infomaniak Manager, create (or pick) the **Web Hosting** product and
   the domain/subdomain this site should live on. Set the site's **PHP
   version to 8.4** (Manager → the site's settings) — that's what this app's
   `composer.lock` and the CI/deploy workflows are pinned to.
2. Create a **MySQL/MariaDB database** for the app (Manager → Databases) and
   note the host, database name, username, and password.
3. Enable **SSH access** for the hosting (Manager → Advanced parameters →
   SSH access) and note the SSH host and port Infomaniak gives you.
4. Generate a dedicated deploy SSH keypair locally (do not reuse a personal
   key):
   ```
   ssh-keygen -t ed25519 -f infomaniak_deploy_key -C "github-actions-deploy"
   ```
   Add the **public** key (`infomaniak_deploy_key.pub`) to the SSH keys
   allowed for that hosting user in the Infomaniak Manager.
5. Note the absolute path to the web root for the domain (Manager shows this,
   typically something like `/home/clients/<id>/sites/<domain>/live` — the
   exact layout depends on the plan).

## 2. Configure GitHub repository secrets

In the GitHub repo → Settings → Environments, create a `production`
environment (matches `environment: production` in `deploy.yml`), and add
these secrets:

| Secret | Value |
|---|---|
| `SSH_HOST` | The SSH hostname Infomaniak gave you |
| `SSH_PORT` | The SSH port Infomaniak gave you |
| `SSH_USER` | Your hosting SSH username |
| `SSH_PRIVATE_KEY` | The **private** half of the keypair from step 1.4 (full contents of `infomaniak_deploy_key`) |
| `DEPLOY_PATH` | The absolute web root path from step 1.5 |

## 3. One-time manual bootstrap on the server

`rsync` only ships application files — it deliberately never touches
`storage/` or `.env` (see `rsync-exclude.txt`), and the very first deploy
needs a few things to exist before `deploy.yml`'s post-deploy step can run
successfully. Over SSH, once, before the first automated deploy:

```
cd <DEPLOY_PATH>
mkdir -p storage/framework/{sessions,views,cache} storage/logs storage/app/public
chmod -R 775 storage bootstrap/cache
cp .env.example .env   # then edit .env with real production values (see below)
php artisan key:generate --force
```

Production `.env` values that matter beyond the Laravel defaults:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain.ch`
- `DB_CONNECTION=mysql` plus the host/database/username/password from step 1.2
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`
  (there's no persistent worker on shared hosting — see the main plan)
- `MAIL_*` — a real SMTP/API provider, needed once Phase 2 digest emails ship
- `CRON_SECRET` — a long random value, e.g.
  `php artisan tinker --execute="echo bin2hex(random_bytes(32));"`

After that, run the first deploy (push to `main`, or re-run the workflow),
then seed the reference data once:

```
cd <DEPLOY_PATH>
php artisan db:seed --force
```

## 4. Point Infomaniak's task scheduler at the app

Infomaniak's shared hosting has no shell crontab — its **task scheduler**
instead hits a URL on a timer. In the Manager, add a scheduled task that
does a GET request to:

```
https://your-domain.ch/cron/run?token=<the CRON_SECRET value from your .env>
```

Set the interval to the finest one the plan allows (Laravel's own scheduler,
defined in `routes/console.php`, owns the real cadence — e.g. daily import —
so hitting this URL more often than needed is harmless, just a no-op most of
the time).

## 5. Ongoing deploys

From here on, every push to `main` that passes the `test` job in
`deploy.yml` automatically: builds production `vendor/` and compiled assets,
rsyncs them to Infomaniak, then runs `migrate --force` and refreshes the
config/route/view caches over SSH.

## Fallback if SSH command execution from Actions is restricted

Some hosting plans restrict what a scripted SSH session can run. If the
`appleboy/ssh-action` post-deploy step in `deploy.yml` fails for that reason,
the documented fallback (see the project plan) is a second token-protected
HTTP endpoint — mirroring `CronController` — that runs `migrate --force` and
the cache-clear commands when hit after a successful rsync, instead of over
SSH. This isn't implemented by default since the SSH path is expected to
work on a plan with SSH access enabled (step 1.3); add it only if you hit
that restriction in practice.
