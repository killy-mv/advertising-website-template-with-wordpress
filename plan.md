# Restaurant Advertising Website Template — Project Plan

A learning/portfolio project: a restaurant marketing website built on **WordPress**, served through **Nginx** (reverse proxy + load balancer), accelerated with **Redis** caching, and exposed to the internet via **Cloudflare**.

---

## 1. Goals

**Learning goals**
- Understand how a production-style WordPress stack fits together (Nginx → PHP-FPM → MySQL).
- Configure Nginx as a reverse proxy and load balancer.
- Design a multi-layer caching strategy (object cache, page cache, edge cache) and handle cache invalidation.
- Expose a locally hosted site to the internet securely with Cloudflare Tunnel.
- Measure performance and prove the impact of each caching layer.

**Portfolio deliverables**
- A public GitHub repo that anyone can run with `docker compose up`.
- A live demo site on a real domain.
- A README with an architecture diagram, setup guide, and benchmark results.
- A reusable restaurant theme (menu, gallery, hours, location, reservations/contact).

**Non-goals (for now)**
- Headless WordPress / JS frontend.
- Multi-tenant (WordPress Multisite) — possible stretch goal.
- Real payment or online ordering.

---

## 2. Architecture

```
                         ┌──────────────────────────┐
  Browser ──HTTPS──▶     │  Cloudflare (DNS + CDN)  │  ← edge cache (layer 3)
                         └────────────┬─────────────┘
                                      │ Cloudflare Tunnel (cloudflared)
                                      ▼
                         ┌──────────────────────────┐
                         │  Nginx                   │  ← full-page cache (layer 2)
                         │  reverse proxy + LB      │    static files
                         └──────┬───────────┬───────┘
                                │ FastCGI   │
                     ┌──────────▼──┐   ┌────▼────────┐
                     │ WordPress 1 │   │ WordPress 2 │   (PHP-FPM)
                     └──────┬──────┘   └──────┬──────┘
                            │                 │
              ┌─────────────┴────┐   ┌────────┴────────┐
              │ MySQL/MariaDB    │   │ Redis           │  ← object cache (layer 1)
              │ (content)        │   │ (shared state)  │
              └──────────────────┘   └─────────────────┘
```

**Caching layers**

| Layer | Where | What is cached | Benefits |
|-------|-------|----------------|----------|
| 1. Object cache | Redis | DB query results, transients, options | Every request, including wp-admin |
| 2. Page cache | Nginx `fastcgi_cache` (or Redis via `srcache`) | Full rendered HTML | Anonymous visitors, no PHP executed |
| 3. Edge cache | Cloudflare | HTML + static assets at the CDN | Global latency, origin offload |

---

## 3. Tech Stack

| Component | Choice | Notes |
|-----------|--------|-------|
| Host | Windows | Project on `E:\`, commands run in PowerShell |
| Containers | Docker Desktop (Windows) + Docker Compose | WSL2 backend; WP core in named volumes, only custom code bind-mounted |
| CMS | WordPress (`wordpress:php8.3-fpm`) | FPM image so Nginx sits in front |
| Database | MySQL 8 or MariaDB 11 | |
| Web server | Nginx (`nginx:alpine`) | Reverse proxy, LB, page cache |
| Cache | Redis 7 | + Redis Object Cache plugin |
| CLI | WP-CLI | Scripted setup, cache flushes, content seeding |
| Edge/DNS | Cloudflare + `cloudflared` Tunnel | Free plan is enough |
| Benchmarking | `k6` or `wrk` | Before/after comparisons |

---

## 4. Proposed Repo Structure

```
restaurant-wp/
├── docker-compose.yml
├── .env.example              # DB creds, domain, ports (real .env is gitignored)
├── nginx/
│   ├── nginx.conf
│   └── conf.d/
│       ├── site.conf         # server block, upstream, cache rules
│       └── cache.conf        # fastcgi_cache zone definitions
├── wordpress/
│   ├── wp-config-extra.php   # Redis, HTTPS-behind-proxy, site URL settings
│   └── wp-content/
│       ├── themes/restaurant-theme/
│       └── mu-plugins/       # cache purge hooks
├── redis/
│   └── redis.conf
├── scripts/
│   ├── setup.sh              # WP-CLI install + plugin activation + seed content
│   └── benchmark.sh
├── benchmarks/               # results (markdown/CSV)
├── docs/
│   └── architecture.png
├── plan.md
└── README.md
```

---

## 5. Phases

Each phase ends with a working, committed state. Add one layer at a time so problems are easy to isolate.

### Phase 0 — Environment setup
- [x] Docker Desktop installed on Windows (client 29.6.1, Compose v5.2.0); git 2.53 installed.
- [ ] Start Docker Desktop (engine must be running).
- [ ] **Volume strategy** (Windows-drive bind mounts are slow for PHP's many small file reads):
  - WordPress core + uploads → Docker **named volumes** (live inside Docker's VM, fast).
  - Only our own code (`themes/restaurant-theme`, `mu-plugins`, Nginx/Redis config) → **bind mounts** from `E:\`.
- [ ] `git init`, add `.gitignore` (`.env`, uploads, db data volumes).
- [ ] (Optional) Register a domain or add an existing one to Cloudflare.

**Done when:** `docker run hello-world` works from PowerShell.

### Phase 1 — Plain WordPress (baseline)
- [ ] `docker-compose.yml` with `wordpress:latest` (Apache built in) + MySQL.
- [ ] Use named volumes for DB data and `wp-content`.
- [ ] Credentials in `.env`, provide `.env.example`.
- [ ] Complete the WordPress installer at `http://localhost:8080`.

**Done when:** you can log in to wp-admin and publish a post.
**Learn:** how WordPress talks to the DB; container networking by service name.

### Phase 2 — Nginx + PHP-FPM
- [ ] Switch to the `wordpress:php8.3-fpm` image.
- [ ] Add an Nginx container; share the WordPress files volume with it (Nginx serves static files, forwards `.php` to FPM on port 9000).
- [ ] Write `site.conf`: `try_files` for pretty permalinks, FastCGI params, deny access to `wp-config.php`, `.ht*`, `xmlrpc.php`.
- [ ] Set gzip, sensible `client_max_body_size` for media uploads.

**Done when:** the site works through Nginx, permalinks work, image uploads work.
**Learn:** reverse proxying, FastCGI, the difference between Nginx and PHP-FPM responsibilities.

### Phase 3 — Restaurant theme & content
- [ ] Build a block theme (or child theme of Twenty Twenty-Five) named `restaurant-theme`.
- [ ] Sections / templates:
  - Hero (image, tagline, CTA "Book a table")
  - Menu (custom post type `menu_item` with category, price, dietary tags — or block patterns)
  - About / story
  - Gallery
  - Opening hours + location (embedded map)
  - Promotions / events (regular posts)
  - Contact / reservation form (e.g. Contact Form 7 or WPForms Lite)
- [ ] Mobile-first, responsive layout; basic SEO (titles, meta, Open Graph, schema.org `Restaurant` JSON-LD).
- [ ] `scripts/setup.sh`: WP-CLI to install WP, activate theme/plugins, seed demo menu items and pages — so a fresh clone gets a full demo site.

**Done when:** a fresh `docker compose up && ./scripts/setup.sh` gives a complete demo restaurant site.
**Learn:** WordPress theming, custom post types, WP-CLI automation.

### Phase 4 — Redis object cache (layer 1)
- [ ] Add the Redis container (with `maxmemory` + `allkeys-lru` eviction policy).
- [ ] Install the PHP Redis extension (custom WordPress Dockerfile if the image lacks it).
- [ ] Install/activate the **Redis Object Cache** plugin; configure `WP_REDIS_HOST`, `WP_REDIS_PREFIX` in `wp-config`.
- [ ] Verify with `redis-cli monitor` and the plugin's status page.
- [ ] **Benchmark:** record baseline (no cache) vs object cache — see Phase 8.

**Done when:** plugin shows "Connected" and DB queries per page drop noticeably (Query Monitor plugin).
**Learn:** what WordPress caches, key prefixes, eviction policies.

### Phase 5 — Full-page cache (layer 2)
- [ ] Configure Nginx `fastcgi_cache` (cache zone, key, TTL).
- [ ] **Bypass rules** — do NOT cache when:
  - request method is not GET/HEAD
  - URL is `/wp-admin/*`, `/wp-login.php`, `/wp-json/*`, feeds, `?s=` search, preview
  - cookies: `wordpress_logged_in_*`, `wp-postpass_*`, `comment_author_*`
- [ ] Add `X-Cache-Status` response header (HIT / MISS / BYPASS / EXPIRED).
- [ ] **Cache invalidation:** mu-plugin hooking `save_post`, `deleted_post`, `switch_theme`, menu/customizer changes → purge affected URLs (Nginx Helper plugin, or custom purge endpoint).
- [ ] (Stretch) Compare with a Redis-backed page cache (`srcache` + `redis2` modules via OpenResty) and document trade-offs.

**Done when:** anonymous page loads show `X-Cache-Status: HIT`, logged-in users always get BYPASS, and editing a menu item shows the change immediately.
**Learn:** cache keys, TTLs, bypass logic, invalidation — the hard part of caching.

### Phase 6 — Load balancing
- [ ] Run 2+ WordPress FPM containers (`deploy.replicas` or named `wordpress1`, `wordpress2`).
- [ ] Nginx `upstream` block; try round-robin, `least_conn`, and `ip_hash`, note the differences.
- [ ] Ensure shared state: same DB, same Redis, shared `wp-content/uploads` volume.
- [ ] Add an `X-Upstream` header (debug only) to see which backend served the request.
- [ ] Health checks: stop one WP container and confirm the site stays up (`max_fails`, `fail_timeout`).
- [ ] Verify page-cache purge still works across nodes (cache lives in Nginx, so it should).

**Done when:** requests alternate between backends, and killing one backend causes no downtime.
**Learn:** stateless app servers, why shared cache/sessions matter for horizontal scaling.

### Phase 7 — Cloudflare (layer 3 + public access)
- [ ] Add the domain to Cloudflare (change nameservers at the registrar).
- [ ] Install `cloudflared` (as a container in the compose file is cleanest), create a named Tunnel, route `restaurant.<domain>` → `http://nginx:80`.
- [ ] WordPress behind HTTPS proxy: set `WP_HOME` / `WP_SITEURL` to the public URL and trust `X-Forwarded-Proto` in `wp-config` (avoids redirect loops / mixed content).
- [ ] Restore real visitor IPs in Nginx (`real_ip_header CF-Connecting-IP`).
- [ ] Cloudflare Cache Rules: cache HTML for anonymous users, bypass on WP cookies and `/wp-admin`.
- [ ] Extend purge mu-plugin to call the Cloudflare purge API (API token stored in `.env`).
- [ ] SSL mode "Full", "Always Use HTTPS", Brotli.
- [ ] Protect `/wp-admin` & `/wp-login.php` with Cloudflare Access or a WAF rule (optional but good practice).
- [ ] (Stretch) Cloudflare **Pages** for a static landing/status page or docs site; a **Worker** that adds edge logic (e.g. A/B hero banner, geo-based greeting, custom cache key).

**Done when:** the site is reachable at `https://restaurant.<domain>`, `cf-cache-status: HIT` appears for anonymous visitors, and edits purge all three layers.
**Learn:** DNS, tunnels, CDN cache behavior, proxy headers.

### Phase 8 — Benchmarking & observability
- [ ] Write `scripts/benchmark.sh` using `k6` or `wrk` (fixed concurrency, duration, same URLs).
- [ ] Measure each configuration:
  1. No cache
  2. + Redis object cache
  3. + Nginx page cache
  4. + load balancing (2 backends)
  5. Through Cloudflare (from the public URL)
- [ ] Record: requests/sec, p50/p95/p99 latency, error rate, TTFB; store results in `benchmarks/`.
- [ ] (Optional) Redis stats (`INFO stats` hit ratio), Nginx access log with `$upstream_cache_status` and `$request_time`.
- [ ] (Stretch) Grafana + Prometheus dashboard (nginx-exporter, redis-exporter).

**Done when:** a results table + chart is in the README.

### Phase 9 — Hardening & polish
- [ ] Security: disable file editing in wp-admin (`DISALLOW_FILE_EDIT`), strong salts, rate limit `wp-login.php` in Nginx, security headers (CSP basics, `X-Frame-Options`, `Referrer-Policy`).
- [ ] Backups: script for DB dump + uploads archive.
- [ ] Image optimization (WebP), lazy loading, long cache headers for static assets.
- [ ] Lighthouse audit — aim for 90+ in Performance/SEO/Accessibility.
- [ ] README: overview, architecture diagram, quick start, how each cache layer works, benchmark results, lessons learned, screenshots.
- [ ] (Stretch) GitHub Actions: lint Nginx config (`nginx -t`), PHP lint, compose build check.

---

## 6. Testing Checklist

| Test | Expected |
|------|----------|
| Anonymous home page, second request | `X-Cache-Status: HIT` |
| Logged-in user | `BYPASS`, sees admin bar |
| Submit contact form (POST) | Never cached, works |
| Edit menu item price, reload as visitor | New price visible immediately (all layers purged) |
| Stop one WordPress container | Site stays up |
| Stop Redis | Site still works (slower), no fatal errors |
| Access `/wp-config.php` | 403/404 |
| HTTP → HTTPS on public domain | Redirects, no mixed content |

---

## 7. Risks & Gotchas

- **Bind-mount performance on Windows** — don't bind-mount the whole WordPress install from `E:\`; use named volumes for core and mount only custom code.
- **Line endings** — shell scripts and configs used inside containers must be LF, not CRLF (add a `.gitattributes` with `* text=auto eol=lf`).
- **Shell scripts** — `setup.sh` / `benchmark.sh` run inside containers (e.g. `docker compose run wpcli ...`), or provide PowerShell equivalents.
- **Redirect loops behind Cloudflare** — `WP_HOME`/`WP_SITEURL` + `X-Forwarded-Proto` handling.
- **Stale content** — every cache layer needs a purge path; test invalidation, not just hits.
- **Caching personalized pages** — cookie-based bypass must be correct or users see others' sessions/nonces.
- **Nonces in cached forms** — contact form nonces can expire in long-cached pages; keep HTML TTL modest or load forms via AJAX.
- **Uploads with multiple backends** — must be a shared volume.
- **Home machine uptime** — the demo is only live while your PC + tunnel run; consider a cheap VPS later for a permanent demo.
- **Secrets** — never commit `.env`, Cloudflare API tokens, or DB passwords.

---

## 8. Stretch Ideas

- WordPress Multisite: one install, multiple restaurant demo sites (true "template" story).
- Redis-backed page cache vs Nginx disk cache comparison write-up.
- Online menu with QR code per table.
- Headless variant: WP REST API + Astro/Next.js on Cloudflare Pages.
- Deploy the same compose stack to a VPS (Hetzner/DigitalOcean) with the same Tunnel.

---

## 9. Suggested Order / Milestones

| Milestone | Phases | Result |
|-----------|--------|--------|
| M1 — It runs | 0, 1, 2 | WordPress behind Nginx locally |
| M2 — It looks good | 3 | Restaurant theme + seeded demo content |
| M3 — It's fast | 4, 5, 8 (partial) | Object + page cache with benchmarks |
| M4 — It scales | 6 | Load balanced backends |
| M5 — It's live | 7 | Public domain via Cloudflare |
| M6 — It's portfolio-ready | 8, 9 | Benchmarks, hardening, README |

---

## 10. Open Decisions

- [ ] MySQL vs MariaDB (either is fine; MariaDB is lighter).
- [ ] Block theme from scratch vs child theme of Twenty Twenty-Five.
- [ ] Menu as custom post type vs block patterns (CPT is better practice + better demo).
- [ ] Page cache: Nginx `fastcgi_cache` (simpler, recommended first) vs Redis-backed (more aligned with the project title — do as stretch comparison).
- [ ] Domain name / whether to buy one.
