# Dynamic Solutions

Production website and admin console for a diesel supply business at
31 Dassie Road, Zesfontein, Benoni, Gauteng.

The business sells bulk diesel (50ppm and 500ppm), runs a pump at its yard,
rents secure truck parking bays, and delivers around Gauteng. The admin console
is how the owner sets the daily diesel price and issues quotes and invoices.

---

## ⚠️ Before this goes in front of a customer

Several business facts are still placeholders and **print on quotes and invoices
exactly as written**. Search for `TODO` in [.env.example](.env.example) and fill in:

| Setting | What it is |
|---|---|
| `BUSINESS_REGISTERED_NAME` | Registered company name, e.g. `Something (Pty) Ltd` |
| `BUSINESS_REG_NUMBER` | CIPC registration number |
| `BUSINESS_VAT_NUMBER` | **Leave blank if not VAT registered** — see below |
| `BUSINESS_EMAIL` | Where website enquiries land |
| `BUSINESS_HOURS_WEEKDAY` / `_SATURDAY` | Gate times |
| `BUSINESS_BANK_*` | Bank, account name, number, branch code, account type |
| `BUSINESS_YARD_BAYS` | How many parking bays the yard actually has |
| `OWNER_EMAIL` / `OWNER_PASSWORD` | The seeded owner login |

These seed the `settings` table on first run; after that the owner edits them at
`/admin/settings`, which shows a banner counting anything still reading `TODO`.

**On VAT:** leaving `BUSINESS_VAT_NUMBER` blank forces every document to VAT
mode `none` and hides VAT rows entirely. That is deliberate — showing a VAT line
without a VAT number is not compliant with SARS requirements.

---

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13, PHP 8.3+ |
| Public pages | Blade + Alpine.js 3 |
| Admin console | Inertia + React 19 + TypeScript |
| Styling | Tailwind CSS v4 (shared across Blade and React) |
| Build | Vite |
| Database | PostgreSQL 16 |
| PDF | `spatie/laravel-pdf` (Browsershot + headless Chromium) |
| Email | AWS SES via Laravel's `ses` mail driver |
| File storage | AWS S3 for issued PDFs |
| Queue | `database` driver, worker under Supervisor |
| Tests | Pest (PHP) + Vitest (TypeScript) |
| Server | AWS Lightsail 2GB, provisioned by Laravel Forge |

> The build brief specified Laravel 12 / Inertia 2 / Vite 6 / Pest 3. The
> official React starter kit has since moved to the versions above and
> `laravel new --react` produces them. Everything else is as specified. All the
> starter-kit packages still support Laravel 12 if you want to pin back.

---

## Architecture — the split matters

Two rendering strategies in one application, divided by what each is good at.

**Public pages — Blade + Alpine.** Marketing pages need to rank on Google and
load instantly for someone standing next to a truck on mobile data. Plain
server-rendered HTML, no hydration cost. The pump-counter animation is about
thirty lines of Alpine in [resources/js/site.ts](resources/js/site.ts).

**Admin console — Inertia + React.** Stateful, component-heavy, and the document
builder needs a live A4 preview that updates as you type.

```
routes/web.php
├── Public      → Controller → view('pages.home')            [Blade]
└── /admin/*    → Controller → Inertia::render('admin/...')  [React]
```

Both share [resources/css/app.css](resources/css/app.css), so the brand tokens
are defined once. One repo, one deploy.

### One deliberate departure from the mockup

[design/mockup.html](design/mockup.html) wraps everything in
`.frame { max-width: 1180px; box-shadow: … }`. That is the mockup viewer's
simulated browser window, not the design — taken literally it boxes the real
site, and the dark hero and red trust bar end up stranded in white gutters on
any screen wider than 1180px.

So the sections run **full bleed** and their horizontal padding grows to hold
the content itself to the same column the mockup has. Two custom properties on
`.ds-shell` do it (`--ds-bleed`, `--ds-edge`), both resolved against the shell's
own width rather than `100vw`, so a desktop scrollbar cannot tip the page into a
horizontal scroll. Every heading lines up on the same left edge at every width;
`.ds-split` paints its mist/ink seam as a gradient on the container for the same
reason. Verified for zero horizontal overflow across all five public pages at
390 / 768 / 1180 / 1470 / 1920px.

---

## Local setup

Requires PHP 8.3+, Composer, Node 20+, and PostgreSQL 16.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Create the databases (the test suite needs its own).
createdb dynamic_solutions
createdb dynamic_solutions_test

php artisan migrate --seed          # grades, settings, owner account
php artisan db:seed --class=DemoSeeder   # sample prices, customers, documents

npm run build
composer dev                        # serve + queue + vite
```

`DemoSeeder` is development-only and refuses to run in production — it publishes
a diesel price, which is the owner's decision, not a fixture's.

### PDFs locally

Browsershot needs Chromium:

```bash
npm install puppeteer
npx puppeteer browsers install chrome
```

Then point `LARAVEL_PDF_CHROME_PATH` at the binary, or leave it blank to let
Browsershot find one. On macOS the system Chrome works:

```
LARAVEL_PDF_CHROME_PATH="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
```

---

## Money — read this before touching any arithmetic

Diesel is sold in thousands of litres. A half-cent rounding error on a 15,000
litre order becomes a customer query on an invoice. **Nothing in the money path
uses a float.**

- `price_per_litre` — `decimal(10,4)`; wholesale prices carry more than two decimals
- all monetary amounts — `bigint` **cents** (`subtotal_cents`, `vat_cents`, …)
- `litres` — `decimal(12,2)`

Each line is rounded to a whole cent **first**, then the rounded amounts are
summed. Never sum unrounded values and round at the end — line amounts are
printed individually on the document and must add up to the total exactly.

### The three implementations, and the one fixture

| File | Role |
|---|---|
| [app/Support/Totals.php](app/Support/Totals.php) | **Authoritative.** Nothing else writes totals to the database. |
| [resources/js/lib/totals.ts](resources/js/lib/totals.ts) | Line-for-line port. Drives the builder's live preview only. Never posts a total. |
| [tests/fixtures/totals.json](tests/fixtures/totals.json) | 25 cases, read by **both** suites. Divergence fails CI. |

The fixture's expectations were generated by an independent third implementation
([tests/fixtures/generate.py](tests/fixtures/generate.py), Python `Decimal`), so
they are not just a recording of what the PHP happens to do.

One case earns its keep on its own:

> `8.70 L × R1.1500` is exactly `1000.5` cents, so the correct answer is `1001`.
> In IEEE-754 the product is `1000.4999999999999`, and **both** `round()` in PHP
> and `Math.round()` in JavaScript return `1000` — one cent short, on every line,
> silently. Both implementations use exact integer arithmetic (bcmath / BigInt)
> and return `1001`.

### VAT

```
none         subtotal = sum   vat = 0                                total = subtotal
exclusive    subtotal = sum   vat = round(subtotal × rate)           total = subtotal + vat
inclusive    total    = sum   vat = total − round(total ÷ (1+rate))  subtotal = total − vat
```

Default is **inclusive** — the owner's existing quotes are priced VAT-inclusive.

---

## The rules that break quietly and cost money

**1. Issued documents snapshot everything.** The price is copied onto
`document_lines.price_per_litre`, the customer into `documents.bill_to` as JSON,
and the terms and banking text into their own columns. Publishing Wednesday's
price must never retroactively change a quote sent last week — including one the
customer has already accepted.

> **Never join to `price_history`, `customers` or `settings` when rendering a
> document.** It looks like a reasonable optimisation. It is a silent repricing
> of documents that have already left the building. See the comment above
> `Document::forRender()`.

**2. Quote validity is enforced, not decorative.** `valid_until` defaults to
issue + `quote_valid_hours` (48). `documents:expire` runs hourly and flips
passed quotes to `expired`. Expired quotes show a banner on their public link
and cannot be converted. **Re-price** clones at today's price with a fresh
number, leaving the original intact.

**3. Quote → invoice copies, never mutates.** A new row, a fresh number,
`converted_from` set, lines duplicated with their snapshot prices. The quote is
marked `accepted` and stays readable forever.

**4. The server recomputes totals on every save.** The React preview posts line
data, not totals. Any total arriving from a client is not even read.

---

## Roles

| Role | Can |
|---|---|
| `owner` | Everything, including publishing prices and changing settings |
| `admin` | Issue and edit documents, manage customers, check trucks in — **cannot** publish prices or change settings |
| `attendant` | Yard bookings only. Cannot see pricing, documents or customers |

Gated in the React sidebar off `auth.can` in Inertia's shared props **and**
enforced again in every controller. A hidden nav item is not authorisation.

There is no public registration; the first owner is seeded from env.

---

## Testing

```bash
composer test          # Pest + Vitest, both reading tests/fixtures/totals.json
composer test:js       # Vitest alone
./vendor/bin/pest      # Pest alone
```

Pest runs against **PostgreSQL**, not SQLite — the schema uses `jsonb` and the
document numberer relies on `SELECT … FOR UPDATE` row locks. Testing that
against SQLite would prove nothing.

`tests/Unit/DocumentNumbererTest.php` launches **20 genuinely concurrent OS
processes** (via `tests/Support/next-number.php`) and asserts 20 distinct,
consecutive numbers. Running the numberer 20 times in a loop would prove
nothing; with the row lock removed, the same test returns 6 distinct numbers.

---

## Layout of the interesting bits

```
app/
  Enums/            DocType, DocStatus, VatMode, UserRole
  Support/
    Dec.php         exact decimal helpers (bcmath)
    Totals.php      ← authoritative money
    Money.php       rand formatting
    PageCache.php   60s marketing cache, version-stamped
    InlineAssets.php CSS/font inlining for the PDF path
  Services/
    PriceBook.php       today's price; publishing appends, never updates
    DocumentWriter.php  creates/updates documents, snapshots everything
    DocumentNumberer.php row-locked sequential numbering
    Settings.php        the settings table, cached
resources/
  css/ds.css        the mockup, ported by hand
  js/lib/totals.ts  ← the port
  js/site.ts        Alpine: pump counter, mobile nav, quote calculator
  views/documents/paper.blade.php  the document — print, PDF and public link
design/mockup.html  the visual spec
```

---

## Deployment

See [DEPLOY.md](DEPLOY.md).
