# Laravel Cloud deployment

Tracks taking Ignition Index live on [Laravel Cloud](https://cloud.laravel.com) with CI on
GitHub Actions and a protected `main`. Update the checkboxes as steps land.

## Status

| Phase | State |
|---|---|
| A. Repository changes (`live_with_cicd`) | ✅ Done — merged into `main` (#6) and `development` (#7), CI green on both |
| B. Branch protection on `main` | ✅ Done — ruleset active, direct push verified rejected |
| C. Laravel Cloud setup | ✅ Done — live on `ignitionindex.com`, Resend verified and mail confirmed end to end |
| D. First release (`development` → `main`) | ✅ Done — releases deploy on merge, proven several times |

## How releases work

```
feature branch ──PR──▶ development ──PR──▶ main ──push──▶ Laravel Cloud deploys
                  │                  │
                  └─ CI runs         └─ CI runs, required to pass
```

1. **Every PR into `development` or `main`** runs `.github/workflows/ci.yml`: the full
   project gate against a real MySQL service.
2. **`main` is protected.** Direct pushes are rejected; code only arrives via a PR whose
   `Run Test Suite & Build` check passed.
3. **Merging into `main` is the deploy.** Laravel Cloud's push-to-deploy watches `main`.
   There are no deploy hooks and no deployment steps in GitHub Actions.

## Decisions

| Area | Choice | Why |
|---|---|---|
| Host | Laravel Cloud | Managed PHP runtime, database, storage, queue and scheduler; native push-to-deploy |
| CI checks | Full `composer ci:check` | Pest, PHPStan L7, Pint, `vp check`, `vue-tsc` — identical to local |
| CI scope | PRs into `main` and `development` | Feature work is tested when it lands, not only at release |
| Database | Laravel MySQL **8.4** | Matches local development; CI tests against the same version |
| Photos | Private object storage bucket, streamed through the app | Keeps the owner check on every request; no shareable links |
| Queue | Managed queue | Cloud's recommended option; scales to zero; failed-jobs dashboard |
| Mail (outbound) | Resend | First-party Laravel driver, one API key. Sends from the `send` subdomain |
| Mail (inbound) | Namecheap Private Email | Real `support@ignitionindex.com` mailbox, so app mail has a reply-to that reaches a person. Owns the apex MX/SPF |
| Domain | Custom domain | `TODO: domain name` |
| Environments | Production only, on `main` | Preview environments can be added later |
| PHP | 8.3 | Matches `composer.json` (`^8.3`) and CI. Cloud defaults new environments to 8.5 — set it explicitly |
| Node | 22 (`.nvmrc`) | vite-plus supports `^20.19 \|\| ^22.18 \|\| >=24.11` |

## Phase A — Repository changes

Branch `live_with_cicd`, off `development`.

- [x] Replace the Hostinger-era `.github/workflows/tests.yml` with `.github/workflows/ci.yml`
- [x] Pin Node 22 in `.nvmrc`
- [x] `composer require league/flysystem-aws-s3-v3 aws/aws-sdk-php resend/resend-php`
- [x] Serve photos from any disk: `VehiclePhotoController` streams from the disk instead of
      `response()->file($disk->path())`, which only works on a local disk
- [x] `->onOneServer()` on both scheduled tasks
- [x] Production hints in `.env.example` (comments only — CI copies this file)
- [x] Suite verified against MySQL locally: all migrations run, 189/189 tests pass, and the
      tests were confirmed to hit MySQL rather than `phpunit.xml`'s sqlite
- [x] Laravel Cloud's build command verified on a fresh clone: `composer install --no-dev`,
      `npm ci`, `npm run build` and `php artisan optimize` all succeed; dev packages are
      stripped, config and routes cache (including the closure route), and the app boots
      as `production` with debug off
- [x] Open PR `live_with_cicd` → `development`; **CI runs for the first time** — green
- [x] Merge once green

> **What actually happened.** Two PRs were open from `live_with_cicd`: #6 into `main` and #7
> into `development`. #6 was merged first, by accident. Because the branch was cut from
> `development`, that carried the whole rebrand plus CI into `main` — early, but only after
> CI had passed on that exact code. #7 was then merged, so `main` and `development` hold
> identical files. Nothing deployed, since Laravel Cloud was not yet connected.
>
> The two branches now differ only by PR merge commits with no file changes. That is normal
> with GitHub's "Create a merge commit" setting and will recur on every release.
>
> **Lesson:** GitHub's "Compare & pull request" banner defaults the base to `main`. Check the
> base branch before creating a PR from a feature branch.

## Phase B — Branch protection on `main`

Do this **after CI has run at least once**, so the check name is selectable. The GitHub
CLI isn't installed, so use the web UI.

GitHub → **Settings → Rules → Rulesets → New branch ruleset**:

- [x] Name `Protect Main` (ruleset id `23576332`), enforcement **Active**, target
      `refs/heads/main`
- [x] **Require a pull request before merging** (0 approvals — solo repository)
- [x] **Require status checks to pass** → `Run Test Suite & Build`; require branches to be
      up to date
- [x] **Block force pushes**
- [x] **Restrict deletions**
- [x] **Leave the bypass list empty.** Otherwise the repository owner can still push to
      `main` directly, which defeats the point.
- [x] Verify: a direct push to `main` is rejected

**How it was verified.** GitHub's rule evaluation for `main`
(`GET /repos/adevenuto/IgnitionIndex/rules/branches/main`, public, no auth) returns
`deletion`, `non_fast_forward`, `pull_request` (0 approvals) and `required_status_checks`
(`Run Test Suite & Build`, strict). The bypass list is only visible to admins, so it was
proven with a real push instead: an empty commit built on `main` and pushed by the
repository owner was refused.

```
remote: error: GH013: Repository rule violations found for refs/heads/main.
- Changes must be made through a pull request.
- Required status check "Run Test Suite & Build" is expected.
! [remote rejected] … -> main (push declined due to repository rule violations)
```

Because it was the owner's own push that was declined, nobody is on the bypass list.

> **In zsh, brace the variable in a refspec** — `"${sha}:refs/heads/main"`, not
> `"$sha:refs/heads/main"`. zsh reads `$sha:r` as its `:r` modifier and mangles the refspec,
> so git fails locally without ever contacting GitHub. The first attempt at this test did
> exactly that, and it looked like a pass.

> The job's `name:` in `ci.yml` *is* the required check. Renaming the job silently detaches
> the rule — change both together.

## Phase C — Laravel Cloud setup

- [x] **Application:** New application → GitHub `adevenuto/IgnitionIndex` → environment
      `production` on branch `main`, region closest to users, **push-to-deploy on**
- [x] **PHP version:** General settings → **8.3** (confirmed 8.3.33 at runtime). Cloud
      defaults new environments to 8.5, and a PHP change only applies on the **next deploy**
- [x] **App compute:** Flex size; **Scheduler** toggle **on** (Cloud then runs
      `schedule:run` every minute). Scale-to-zero is optional — Cloud wakes the environment
      for scheduled tasks and queued jobs
- [x] **Database:** Add database → new **Laravel MySQL** cluster (Flex, 5 GB), database
      `ignitionindex`. Backups: daily, 7-day retention. **Note the MySQL version** and align
      `mysql:8.0` in `ci.yml` if it differs
- [x] **Object storage:** Add bucket → Laravel Object Storage, visibility **Private**, disk
      name `photos`, **set as default disk**
- [x] **Managed queue:** Add compute → Managed queue, Flex, 256 MiB, default queue
- [x] **Build commands:**
      ```
      composer install --no-dev --optimize-autoloader && npm ci && npm run build && php artisan optimize
      ```
- [x] **Deploy commands:**
      ```
      php artisan migrate --force
      ```
      Don't add `queue:restart`, `storage:link` or `optimize:clear` — Cloud restarts workers
      itself, and neither of the others belongs in a deploy on Cloud.
- [x] **Environment variables:** see the table below. Verified at runtime with
      `php artisan about`: `production`, debug off, `mysql`, `database` cache and session,
      and queue `cloud`
- [x] **Resend:** sending domain verified and a production API key created — see
      "Picking up: Resend" below for the DNS layout and the traps met along the way
- [x] **Custom domain:** `ignitionindex.com` connected, Google Trust Services certificate,
      `www` redirects to the apex, `http` redirects to `https`. DNS is Namecheap BasicDNS:
      two A records (apex and `www`) to `103.133.1.1`

### Production environment variables

Cloud injects the database, object storage and queue settings when those resources are
attached. Set the rest manually.

| Variable | Value | Source |
|---|---|---|
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | — | Injected (database) |
| `FILESYSTEM_DISK`, bucket credentials | `photos` | Injected (object storage) |
| `QUEUE_CONNECTION` | `cloud` | Injected (managed queue) |
| `APP_NAME` | `Ignition Index` | Manual |
| `APP_ENV` | `production` | Manual |
| `APP_DEBUG` | `false` | Manual |
| `APP_KEY` | — | **Injected by Cloud.** Do not set a second one |
| `APP_URL` | `https://TODO-domain` | Manual |
| `LOG_LEVEL` | `warning` | Manual |
| `APP_MAINTENANCE_DRIVER` | `cache` | Manual |
| `APP_MAINTENANCE_STORE` | `database` | Manual |
| `SESSION_DRIVER` | `database` | Manual |
| `CACHE_STORE` | `database` | Manual |
| `MAIL_MAILER` | `log` → `resend` | Manual. `log` until the Resend domain is verified |
| `RESEND_API_KEY` | from Resend | Manual — keep secret |
| `MAIL_FROM_ADDRESS` | `support@ignitionindex.com` | Manual — a real Private Email mailbox, so replies land somewhere |
| `MAIL_FROM_NAME` | `Ignition Index` | Manual |
| `VITE_APP_NAME` | `Ignition Index` | Manual — **read at build time**, so it must exist before the build |

Only if the database connection is refused for lack of TLS:
`MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt`.

## Phase D — First release

- [x] ~~Open PR `development` → `main`~~ — superseded: the rebrand and CI reached `main`
      through #6 (see Phase A). CI passed on that code before the merge
- [x] CI passes → merge
- [ ] **Connect Laravel Cloud to `main` (Phase C).** Because `main` already holds the
      release, the first deployment happens as soon as Cloud is connected — there is no
      separate merge to trigger it. Finish the environment variables *before* the first
      deploy, or it boots without an `APP_KEY` or mail settings
- [ ] Laravel Cloud deploys — watch the build and deploy logs

## Go-live verification

- [ ] **CI enforces:** a deliberately broken test on a throwaway branch shows red on its PR
      and merging is blocked *(not yet exercised deliberately; CI has only ever passed)*
- [x] **Protection:** a direct push to `main` is rejected — verified with a real push, refused
      with `GH013`
- [x] **Deploy:** merging to `main` starts a deployment with no manual step — confirmed by
      shipping robots.txt, the footer, the hero figure and the favicon set, each visible on
      the live site afterwards
- [x] **Assets:** pages load with styles and fonts; no 404s for `/build/*`
- [x] **Auth:** register → verification email arrives via Resend → the link verifies.
      Confirmed 2026-09-18, twice: once on the original account and again on a fresh
      register-delete-reregister pass. **No "Invalid signature" 403** — Cloud's proxy
      already presents requests as `https`, so `trustProxies` is *not* needed here
- [x] **Photos:** upload a photo, **redeploy**, confirm it still loads — proves it lives in
      the bucket, not on the ephemeral filesystem. Confirmed 2026-09-18: a photo uploaded
      to a real vehicle survived a redeploy
- [ ] **Queue:** from the Commands tab, `php artisan reminders:send --force`; the job shows
      in the managed queue dashboard and the email arrives
- [ ] **Scheduler:** `php artisan schedule:list` shows `reminders:send` and `recalls:check`

## Things to know

- **The filesystem on Cloud is ephemeral and per-replica.** Anything written to local disk
  disappears on the next deploy. Persistent files belong in object storage.
- **CI tests on MySQL; local tests on sqlite.** CI sets `DB_*` as real environment
  variables, which take precedence over `phpunit.xml`'s sqlite `<env>` entries (PHPUnit
  only applies an `<env>` when the variable is unset and `force="true"` is absent).
  `composer ci:check` locally keeps using in-memory sqlite.
- **Decimal columns** (`gallons`, `mpg`, `avg_miles_per_day`) have their precision enforced
  by MySQL but not sqlite. A test that passes locally can fail in CI on an out-of-range
  value.
- **Reminders run at 08:00 UTC** (~4am US Eastern), because the app timezone is UTC.
  Worth revisiting before real users arrive.
- **`MAIL_MAILER=log` swallows mail entirely here.** Laravel's log mailer writes with
  `$this->logger->debug(...)`, and `LOG_LEVEL=warning` discards that — so while mail is set
  to `log`, a verification email is neither sent nor recorded, and a new account cannot
  verify itself. Mark a test account verified from the Commands tab if you need one before
  Resend is live.
- **`APP_NAME` is baked into the JS bundle at build time** through `VITE_APP_NAME`, which
  Vite resolves during `npm run build`. Changing the name means a **redeploy**, not a
  restart, and the variable must exist before the build. It also decides the session cookie
  name, so renaming logs everyone out.
- **`main` collects empty merge commits, and GitHub will suggest reconciling them.** Each
  `development` → `main` PR adds a merge commit that `development` never sees, so GitHub
  offers a `main` → `development` pull request. The two branches' *files* are identical —
  check with `git diff origin/main origin/development` before believing the banner. Ignore
  or close that PR; merging it only adds another empty commit. Squash or rebase merges
  would avoid it entirely.
- **Scale to Zero is safe here but was left off for the first deploy.** The documented risk
  is app-cluster `queue:work` processes being cut off mid-job; this environment uses a
  managed queue, whose workers scale independently. Cloud wakes the environment for
  scheduled tasks, and the schedule is daily/weekly, far less frequent than any sleep
  timeout. Turning it on is an App compute toggle plus a deploy.
- **The DNS for `ignitionindex.com` is Namecheap BasicDNS, and the apex mail records now
  belong to Namecheap Private Email** (`mx1`/`mx2.privateemail.com`, SPF
  `include:spf.privateemail.com`), which receives `support@ignitionindex.com`. Resend's
  records are CNAMEs on the `send` and `rsend` subdomains and must not disturb the apex.
  Mail Settings is on **Custom MX** with Private Email's two apex rows entered by hand;
  switching that dropdown re-provisions apex records and has twice wiped the apex SPF.
- **`schedule:list` needs the database.** Cloud runs it at deploy time to work out when to
  wake a sleeping environment. Because both tasks use `withoutOverlapping()` and
  `onOneServer()`, it probes the lock in the `cache_locks` table — so it errors on a
  machine without a database. That is harmless: the probe is `Lock::get()` with a
  callback, which acquires and then releases in a `finally`, so listing the schedule can
  never hold a lock that blocks a real run.

## Picking up: Resend

Everything else is live. Mail is the last piece, and until it works a new account can
register but can **never verify**, so nobody can reach the garage. Password resets are
blocked the same way — Fortify has `resetPasswords()` and `emailVerification()` enabled,
and both go out over the mailer.

**The application side is already done** (verified 2026-09-18): `resend/resend-php` is in
`composer.json`, `config/mail.php` has the `resend` transport, and `config/services.php`
reads `RESEND_API_KEY`. Nothing needs to be written or deployed from this repo — the
remaining work is entirely in Namecheap, Resend and the Cloud dashboard.

### Receiving mail: Namecheap Private Email

The domain no longer uses Namecheap's `eforward*` email forwarding. A **Private Email**
subscription replaced it, with `support@ignitionindex.com` as the mailbox, and its DNS is
already live (confirmed 2026-09-18):

```
dig +short MX ignitionindex.com          → 10 mx1.privateemail.com. / 10 mx2.privateemail.com.
dig +short TXT ignitionindex.com         → "v=spf1 include:spf.privateemail.com ~all"
dig +short CNAME autodiscover…/autoconfig… → privateemail.com.
```

**Mail Settings is now Custom MX**, holding exactly two rows — `@ → mx1.privateemail.com`
and `@ → mx2.privateemail.com`, priority 10 each. Do **not** switch it back to *Private
Email* mode: that mode re-injects its own apex SPF on top of the manual one, and two SPF
records at the same name is a permerror that breaks SPF outright.

**⚠️ The apex SPF keeps disappearing.** `TXT @ = v=spf1 include:spf.privateemail.com ~all`
was auto-managed under Private Email mode and vanished from the zone when host records
were edited. As of 2026-09-18 it has failed to save three times — the Namecheap panel
lists the row while both authoritative nameservers return nothing for it. This affects
only mail sent *from* `support@`; app mail through Resend is unaffected, as it
authenticates via `send.ignitionindex.com`. Namecheap's "Save All Changes" is a batch
commit, so one rejected row silently discards the rest — try saving that record alone,
and suspect a phantom Private Email SPF still held internally after the Custom MX switch.

**Receiving and sending stay separate, and do not collide:**

| | Host | Handles |
|---|---|---|
| Private Email | apex (`@`) | *Incoming* mail to `support@ignitionindex.com` |
| Resend | `send` subdomain | *Outgoing* app mail, bounces and DKIM |

Resend's MX and SPF live on `send`, not the apex, so they never touch Private Email's
records. Only one SPF TXT may exist per host — the apex keeps `spf.privateemail.com`,
and Resend's SPF is a separate record on `send`.

### Sending mail: Resend

**Resend no longer uses the SES-style MX/SPF records.** Older guides (and earlier drafts of
this doc) said to add `MX send → feedback-smtp.<region>.amazonses.com` and
`TXT send → v=spf1 include:amazonses.com ~all` by hand. Resend now issues **CNAMEs** that
carry those records for it, so Resend can rotate infrastructure without a DNS change:

```
send.forge.rmta.net    MX  → feedback.forge.rmta.net
                       TXT → v=spf1 ip4:52.3.252.119 ip4:44.222.39.36 ip4:199.249.231.0/24 ~all
rsend.forge.rmta.net   MX  → feedback-smtp.us-east-1.amazonses.com   (legacy SES path)
                       TXT → v=spf1 include:amazonses.com ~all
```

**No MX record is added for Resend at all.** Mail Settings stays on Custom MX holding only
Private Email's two apex rows.

**The records, as added on 2026-09-18:**

| Host | Type | Value |
|---|---|---|
| `resend._domainkey` | TXT | `p=MIGf…` (Resend's DKIM; no `v=DKIM1;` prefix — that is how Resend issues it, and `v=` defaults to `DKIM1`) |
| `send` | CNAME | `send.forge.rmta.net.` |
| `rsend` | CNAME | `rsend.forge.rmta.net.` |
| `_dmarc` | TXT | `v=DMARC1; p=none;` (optional, monitoring only) |

**Two traps met while doing this, both worth remembering:**

- **A CNAME cannot share a name with any other record** (RFC 1034). The old
  `TXT send = v=spf1 include:amazonses.com ~all` had to be deleted before the `send`
  CNAME would work.
- **The host is `rsend`, not `resend`.** Beyond simply not resolving, a CNAME at `resend`
  sits directly above the DKIM record at `resend._domainkey`, and no data may exist below
  a CNAME. Namecheap served it anyway, but some resolvers refuse to — an intermittent
  DKIM failure waiting to happen.

**Namecheap's nameservers update asynchronously.** A record can read empty on `dns1`
seconds after a save and be correct moments later. Re-query before concluding a save
failed.

**Then, in order:**

- [x] Add Resend's records in Namecheap (DKIM TXT + the two CNAMEs). **Leave the apex MX
      and apex SPF alone** — those belong to Private Email
- [x] Verify the domain in Resend — **Verified 2026-09-18**. Confirm with
      `dig +short TXT resend._domainkey.ignitionindex.com` and
      `dig +short CNAME send.ignitionindex.com`
- [ ] Re-check that receiving still works: `dig +short MX ignitionindex.com` must still
      return both `privateemail.com` hosts, and a test message to
      `support@ignitionindex.com` should still arrive
- [x] Create a production API key

**Verification stalled for about an hour, and that was expected.** The `rsend` host was
first created as `resend`. While it was missing, resolvers cached the negative answer for
the zone's SOA negative-cache TTL — `3601` seconds, the last SOA field. Until that
expired, Resend kept reading the record as absent no matter how correct the zone was. Any
future record fix here carries the same ~1 hour floor before a re-check can succeed.
- [x] Set in Cloud: `MAIL_MAILER=resend`, `RESEND_API_KEY`,
      `MAIL_FROM_ADDRESS=support@ignitionindex.com`, `MAIL_FROM_NAME="${APP_NAME}"`.
      **Cloud's env editor does resolve `${...}` interpolation** — verified in production,
      where `config('mail.from')` returns `name => "Ignition Index"`, not the literal
      string. (`config/mail.php` would also fall back to `APP_NAME` if the variable were
      omitted entirely.) No SMTP variables (`MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`) —
      the Resend transport is an HTTP API and reads none of them; `MAIL_ENCRYPTION` is not
      read by Laravel 11+ at all
- [x] **Deploy** (config is cached at build; a restart will not pick it up). Note that
      `php artisan optimize` from the **Commands tab does not help** — that runs in a
      separate, ephemeral container, so the config cache it writes is discarded while the
      web replicas keep serving the cache baked at their last build. Only a redeploy
      rebuilds what serves traffic
- [x] Register on `https://ignitionindex.com` and confirm the verification email arrives
      and its link verifies. **An "Invalid signature" 403 means Cloud's proxy is not
      trusted** — add `$middleware->trustProxies(at: '*')` in `bootstrap/app.php`.
      *Probably not needed:* probing the live site on 2026-09-18, an unauthenticated
      `GET /settings/profile` redirected to `https://ignitionindex.com/login` — that
      `Location` is built by `route('login')` from the incoming request, so the request
      is resolving as `https`, which is what signed-URL validation compares against. The
      rendered `/login` body likewise contains no `http://ignitionindex.com` URL at all.
      Strong evidence, not proof: the load balancer could in principle rewrite a
      `Location` header, and `asset()` URLs can come from `APP_URL` rather than the
      request. The verification link itself is still the decisive test
- [ ] Then the remaining go-live checks that need a verified account: upload a photo,
      redeploy, confirm it still loads; and `php artisan reminders:send --force` from the
      Commands tab — **still outstanding**

**Careful with `MAIL_MAILER=log`:** Laravel's log mailer writes at `debug` level and
`LOG_LEVEL` is `warning`, so while mail is set to `log` a verification email is neither
sent nor recorded. To unblock testing before Resend is live, mark an account verified
from the Commands tab:

```
php artisan tinker --execute="App\Models\User::where('email','YOU@example.com')->update(['email_verified_at' => now()]);"
```

## Launch checklist

The site is deliberately hidden from search engines until launch. Undoing it means
**two** changes, in one PR:

- [ ] `public/robots.txt` — change `Disallow: /` back to `Disallow:`
- [ ] `resources/views/app.blade.php` — remove the `noindex, nofollow` meta tag
- [ ] `tests/Feature/SearchVisibilityTest.php` — delete it; it exists to make both of
      the above fail loudly if only one is done
- [ ] Deploy, then confirm with `curl -s https://ignitionindex.com/robots.txt` and by
      checking the meta tag is gone

Both are needed. robots.txt stops well-behaved crawlers fetching pages; the meta tag
stops indexing of URLs discovered some other way, such as a link from elsewhere.
Laravel Cloud's own `X-Robots-Tag: noindex` applies only to `*.laravel.cloud` domains,
never a custom one.

## Follow-ups

- [x] Fill in the custom domain: `ignitionindex.com`, connected with a Google Trust
      Services certificate; `www` redirects to the apex
- [x] Confirm Cloud's MySQL version against the CI service image — both 8.4
- [x] Decide whether trusted proxies are needed — **not needed.** The go-live auth check
      passed with no "Invalid signature" 403, confirming what live probing suggested
- [ ] Consider a user-local reminder time
