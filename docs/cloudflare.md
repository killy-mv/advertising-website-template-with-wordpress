# Going public with Cloudflare

This connects your local stack to a real domain through **Cloudflare Tunnel**. You don't need to open router ports or know your public IP: `cloudflared` makes an outbound connection to Cloudflare, and Cloudflare sends visitors down it to Nginx.

```
Visitor ──HTTPS──▶ Cloudflare edge (cache, TLS, WAF) ──tunnel──▶ cloudflared ──HTTP──▶ nginx:80
```

Already done in the repo:

| Piece | Where |
|---|---|
| `cloudflared` container (profile `tunnel`) | `docker-compose.yml` |
| Real visitor IP from `CF-Connecting-IP` | `nginx/nginx.conf` (`real_ip_header`) |
| HTTPS detection via `X-Forwarded-Proto` | `wordpress/wp-config-extra.php` |
| `Cloudflare-CDN-Cache-Control` header (1800 s for cacheable pages, `no-store` for personalized ones) | `nginx/conf.d/cache.conf` |
| Purge Cloudflare when WordPress purges Nginx | `wordpress/mu-plugins/cloudflare-purge.php` |

You still need to do the steps below in the Cloudflare dashboard.

---

## 1. Add your domain to Cloudflare

1. Sign up at <https://dash.cloudflare.com> (Free plan is enough).
2. **Add a domain** → enter your domain → pick **Free**.
3. At your registrar, replace the nameservers with the two Cloudflare gives you.
4. Wait until the domain shows **Active**. This usually takes minutes but can take up to 24 h.

## 2. Create the tunnel

1. Dashboard → **Zero Trust** → **Networks** → **Tunnels** → **Create a tunnel** → **Cloudflared**.
2. Name it `restaurant-wp`.
3. On the install screen, choose **Docker**, then copy only the **token**: the long string after `--token`.
4. Next, add a **Public hostname**:
   - Subdomain: `restaurant` (or leave empty for the root domain)
   - Domain: your domain
   - Service: **HTTP** → `nginx:80`

   `nginx` is the service name on the Docker network, not `localhost`.

## 3. Point the site at the new URL

Edit `.env`:

```ini
SITE_URL=https://restaurant.your-domain.com
CF_TUNNEL_TOKEN=eyJhIjoi...        # from step 2
```

Start everything, including the tunnel:

```powershell
docker compose --profile tunnel up -d
docker compose run --rm wpcli page-cache flush
```

WordPress reads `SITE_URL` from the environment (`WP_HOME` in `wp-config-extra.php`), so you don't need to edit the database. If you uploaded images while on localhost, rewrite their URLs once:

```powershell
docker compose run --rm wpcli search-replace http://localhost:8080 https://restaurant.your-domain.com --skip-columns=guid
```

Open `https://restaurant.your-domain.com`. You should see the site with a valid certificate.

## 4. SSL/TLS settings

Dashboard → your domain:

- **SSL/TLS → Overview**: **Full**. The tunnel is already encrypted end to end.
- **SSL/TLS → Edge Certificates**: turn on **Always Use HTTPS**.
- **Speed → Optimization**: Brotli is on by default.

## 5. Cache Rule: let Cloudflare cache HTML

By default Cloudflare only caches static files (images, CSS, JS). This rule makes it cache HTML too, but only for anonymous visitors.

Dashboard → **Caching** → **Cache Rules** → **Create rule** → name it `WordPress HTML` → **Edit expression**:

```
(http.host eq "restaurant.your-domain.com"
 and not starts_with(http.request.uri.path, "/wp-admin")
 and not starts_with(http.request.uri.path, "/wp-login.php")
 and not starts_with(http.request.uri.path, "/wp-json")
 and not http.cookie contains "wordpress_logged_in_"
 and not http.cookie contains "wp-postpass_"
 and not http.cookie contains "comment_author_"
 and http.request.uri.query eq "")
```

Then set:

- **Cache eligibility**: *Eligible for cache*
- **Edge TTL**: *Use cache-control header if present, bypass cache if not*
- **Browser TTL**: *Respect origin*

Nginx sends `Cloudflare-CDN-Cache-Control: max-age=1800` only on pages that are safe to share. Everything else gets `private, no-store`. So even if the rule matched a personalized page by mistake, Cloudflare would still refuse to cache it. The rule and the header protect you independently of each other.

## 6. API token for automatic purging

So that editing a dish in wp-admin also clears Cloudflare's copy:

1. Dashboard → **My Profile** → **API Tokens** → **Create Token** → **Custom token**.
2. Permissions: **Zone** → **Cache Purge** → **Purge**.
3. Zone Resources: **Include** → **Specific zone** → your domain.
4. Copy the token. Copy the **Zone ID** from your domain's **Overview** page (right sidebar).

Add both to `.env`:

```ini
CF_ZONE_ID=0123456789abcdef...
CF_API_TOKEN=...
```

Apply the change: `docker compose --profile tunnel up -d`

Test it without touching the network (it prints the API calls instead of sending them):

```powershell
docker compose run --rm -e CF_ZONE_ID=x -e CF_API_TOKEN=x wpcli eval-file /scripts/cf-purge-test.php
```

## 7. Protect wp-admin (recommended)

**Zero Trust** → **Access** → **Applications** → **Add** → **Self-hosted**:

- Domain: `restaurant.your-domain.com`, paths `wp-admin` and `wp-login.php`
- Policy: **Allow** → **Emails** → your email

Visitors never see the login page. You sign in with a one-time code sent to your email before WordPress even loads.

## 8. Verify

```powershell
# First request MISS, second HIT at the edge
curl.exe -sI https://restaurant.your-domain.com/menu/ | findstr /i "cf-cache-status x-cache-status"
curl.exe -sI https://restaurant.your-domain.com/menu/ | findstr /i "cf-cache-status x-cache-status"

# Logged-in cookie must never be cached
curl.exe -sI -b "wordpress_logged_in_x=1" https://restaurant.your-domain.com/menu/ | findstr /i "cf-cache-status"
```

| Header | Meaning |
|---|---|
| `cf-cache-status: HIT` | Served by Cloudflare. Your PC wasn't even contacted. |
| `cf-cache-status: MISS` + `x-cache-status: HIT` | Cloudflare didn't have it; Nginx's page cache did. |
| `cf-cache-status: DYNAMIC` / `BYPASS` | Not cacheable (logged in, wp-admin, query string). |

Then edit a menu price in wp-admin and reload the public page: the new price should show immediately.

Benchmark through the edge (from your PC, so this includes your internet latency):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\benchmark.ps1 -Label "Through Cloudflare" -Url https://restaurant.your-domain.com
```

## Troubleshooting

| Symptom | Fix |
|---|---|
| **Too many redirects** | SSL mode must be **Full**, not Flexible. `SITE_URL` must start with `https://`. |
| **Mixed content** warnings | Run the `search-replace` from step 3. |
| **502 / tunnel down** in Cloudflare | `docker compose --profile tunnel logs cloudflared`. Check the token, and that the service is `http://nginx:80`. |
| Still `cf-cache-status: DYNAMIC` on anonymous pages | Check the Cache Rule's hostname, and that you aren't sending a `wordpress_logged_in_` cookie (use a private window). |
| Site offline when the PC sleeps | Expected: your PC is the server. For a 24/7 demo, run the same compose file on a small VPS with the same tunnel token. |
