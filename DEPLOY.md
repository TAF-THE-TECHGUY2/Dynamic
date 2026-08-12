# Deploying Dynamic Solutions to AWS EC2

Ubuntu 24.04, PostgreSQL 16 on the same instance, nginx + PHP-FPM, queue worker
under systemd. No RDS is needed at this volume.

Everything here is scripted in [deploy/](deploy/):

| File | What it is |
|---|---|
| `provision.sh` | One-time server setup. Idempotent — safe to re-run. |
| `deploy.sh` | Pull, build, migrate, restart. Run on every deploy. |
| `nginx.conf` | vhost, with gzip and asset caching |
| `dynamic-queue.service` | systemd unit for the queue worker |

---

## Day one, before anything else

**Request AWS SES production access.** The sandbox only delivers to verified
addresses and approval takes a day or two. Quotes-by-email do not work until it
clears, and it is the one thing you cannot hurry on the day you go live.

While waiting, verify the sending domain and turn on DKIM.

---

## 1. Launch the instance

| Setting | Value | Why |
|---|---|---|
| AMI | Ubuntu Server 24.04 LTS | PHP 8.3 is in main, no third-party PPA |
| Type | **t3.small** (2 vCPU, 2GB) | 2GB is the floor. Chromium peaks over 500MB rendering a PDF. |
| Storage | 20GB gp3 | |
| Security group | 22 from **your IP only**, 80 and 443 from anywhere | |
| Elastic IP | Allocate and associate | Otherwise the public IP changes on stop/start and DNS breaks |

`provision.sh` adds a 2GB swap file. On a 2GB box that is not optional — without
it the kernel OOM-kills the queue worker mid-render, and the only symptom is
invoices that never arrive.

> **t3.micro (1GB) will not work.** Chromium plus PHP-FPM plus Postgres will
> thrash even with swap. If cost is the constraint, use t4g.small (ARM) and let
> puppeteer install the ARM build — the scripts are architecture-agnostic.

### IAM role instead of access keys

Attach an instance profile granting `ses:SendRawEmail` and `s3:PutObject`,
`s3:GetObject`, `s3:DeleteObject` on the bucket. Then leave
`AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` **blank** in `.env` — the AWS
SDK picks up instance credentials automatically. Long-lived keys on a web
server are the thing you least want leaked.

---

## 2. Provision

```bash
scp -i your-key.pem deploy/provision.sh ubuntu@<elastic-ip>:~
ssh -i your-key.pem ubuntu@<elastic-ip> 'sudo bash provision.sh'
```

It installs PHP 8.3 (with **bcmath** — the money arithmetic depends on it),
PostgreSQL 16, nginx, Node 22, Composer, headless Chromium and its libraries;
creates the database with a generated password; clones the repo to
`/var/www/dynamic`; installs the nginx vhost, the queue worker unit and the
scheduler cron; and enables ufw.

It prints the database password and the Chrome path at the end. You need both
for the next step.

---

## 3. Environment

```bash
ssh -i your-key.pem ubuntu@<elastic-ip>
sudo -u www-data cp /var/www/dynamic/.env.example /var/www/dynamic/.env
sudo -u www-data nano /var/www/dynamic/.env
```

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.co.za
APP_TIMEZONE=Africa/Johannesburg

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=dynamic_solutions
DB_USERNAME=dynamic
DB_PASSWORD=<printed by provision.sh, also in /etc/dynamic-db-password>

MAIL_MAILER=ses
MAIL_FROM_ADDRESS=accounts@your-domain.co.za

# Blank on purpose — the instance role supplies them.
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=dynamic-solutions-documents

FILESYSTEM_DISK=s3
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

LARAVEL_PDF_DRIVER=browsershot
LARAVEL_PDF_CHROME_PATH=<printed by provision.sh, also in /etc/dynamic-chrome-path>
```

**Every value reading `TODO` is printed on quotes and invoices customers
receive.** Registered name, reg number, VAT number, banking details, hours, yard
bay count. See the table at the top of [README.md](README.md).

Leaving `BUSINESS_VAT_NUMBER` blank is a valid choice — it forces every document
to VAT mode `none` and hides VAT rows entirely, which is correct when you are
not VAT registered. What is not valid is leaving it as `TODO`.

**Do not make the S3 bucket public.** Issued PDFs are served through temporary
signed URLs; the app never hands out a bucket URL.

---

## 4. First deploy

```bash
cd /var/www/dynamic
sudo -u www-data bash deploy/deploy.sh --first-run
sudo systemctl start dynamic-queue
```

`--first-run` also generates the app key and seeds the grades, settings and
owner account. It never seeds demo data — `DemoSeeder` refuses to run in
production, because it publishes a diesel price the owner never set.

Every deploy after that is just:

```bash
cd /var/www/dynamic && sudo -u www-data bash deploy/deploy.sh
```

---

## 5. TLS

Point an A record at the Elastic IP, wait for it to resolve, then:

```bash
sudo certbot --nginx -d your-domain.co.za -d www.your-domain.co.za
```

Certbot rewrites the vhost and installs a renewal timer. Confirm with
`sudo certbot renew --dry-run`.

---

## 6. Backups

```bash
sudo tee /etc/cron.daily/dynamic-backup >/dev/null <<'SH'
#!/bin/sh
STAMP=$(date +%F)
sudo -u postgres pg_dump dynamic_solutions | gzip > /tmp/db-$STAMP.sql.gz
aws s3 cp /tmp/db-$STAMP.sql.gz s3://dynamic-solutions-backups/ --storage-class STANDARD_IA
rm -f /tmp/db-$STAMP.sql.gz
SH
sudo chmod +x /etc/cron.daily/dynamic-backup
```

Needs `awscli` installed and the instance role extended to the backup bucket.
Also take EBS snapshots on a schedule via AWS Backup — the database dump alone
does not get you a working server back.

---

## Going-live checklist

- [ ] SES out of the sandbox, domain verified, DKIM signing
- [ ] Every `TODO` in `.env` replaced with a real value
- [ ] `BUSINESS_VAT_NUMBER` set **or** deliberately blank
- [ ] Owner logged in and **password changed** from the seeded one
- [ ] Opening diesel price published at `/admin/pricing`
- [ ] `yard.bays` set to the real bay count
- [ ] Banking details checked character by character against a statement
- [ ] Test quote issued end to end: PDF renders, email arrives, `/d/{token}` opens logged out
- [ ] `sudo systemctl status dynamic-queue` → active (running)
- [ ] `php artisan schedule:list` shows `documents:expire` hourly
- [ ] Homepage price correct, with timestamp, in view-source with JS disabled
- [ ] S3 bucket **not** public
- [ ] Security group port 22 restricted to your IP

---

## Verifying it works

```bash
# The whole stack
curl -sI https://your-domain.co.za | head -1
sudo systemctl status dynamic-queue nginx postgresql php8.3-fpm --no-pager

# The PDF renderer, which is what breaks
cd /var/www/dynamic && sudo -u www-data php artisan tinker --execute='
    $d = App\Models\Document::query()->with("lines")->first();
    file_put_contents("/tmp/check.pdf", App\Jobs\RenderDocumentPdf::render($d));
    echo filesize("/tmp/check.pdf"), " bytes", PHP_EOL;'
```

A few tens of kilobytes starting with `%PDF-` means it works.

---

## Things that will bite

**Chromium.** `LARAVEL_PDF_CHROME_PATH` contains a version number, so
reinstalling Chrome moves it and PDFs start failing on the queue while the site
looks perfectly healthy. Check `failed_jobs` and `/var/log/dynamic-queue.log`.
`MODULE_NOT_FOUND` means puppeteer is missing; a shared-library error means the
`apt-get` line in `provision.sh` did not run.

**Skipping `npm run build`.** The PDF renderer reads the compiled stylesheet off
disk and inlines it, because Browsershot renders from a temp file and cannot
fetch assets over HTTP. Without a build, invoices come out as unstyled HTML —
which looks like a CSS bug and is not one. `deploy.sh` aborts if no manifest is
produced.

**A stale `public/hot` file.** Makes Blade point at a Vite dev server that is not
running: unbundled TypeScript over a dead port, and a site that looks broken for
no visible reason. `deploy.sh` deletes it every time.

**`config:cache` and `.env`.** Editing `.env` does nothing until the config cache
is rebuilt. `deploy.sh` does it; a manual edit needs
`php artisan config:cache` afterwards.

**Clocks.** The app runs in `Africa/Johannesburg`; a default EC2 instance runs
UTC. Anything comparing a stored timestamp against "now" must bind PHP's clock
rather than call the database's `now()`, or the two read two hours apart —
`PriceBook::current()` carries a comment explaining exactly this, because
getting it wrong makes a freshly published price invisible for two hours with
nothing in the logs. Postgres `now()` is also *transaction* time, frozen at the
moment the transaction opened.

**Memory.** If PDFs fail only under load, check `dmesg | grep -i oom`. The swap
file from `provision.sh` should prevent it; a busier yard wants t3.medium.

**The page cache is version-stamped, not tagged.** The `database` cache store
has no tag support and there is no Redis on a 2GB box, so `App\Support\PageCache`
folds a version number into every key and publishing a price bumps it.
`php artisan cache:clear` is the blunt fix, but should never be needed.
