# Lighthouse results

Lighthouse 12 run locally against `http://localhost:8080` (page cache warm).

| Page | Device | Performance | Accessibility | Best practices | SEO | LCP | CLS | TBT |
|---|---|---|---|---|---|---|---|---|
| Home | Mobile | 99 | 100 | 100 | 100 | 1.6 s | 0 | 0 ms |
| Menu | Mobile | 100 | 100 | 100 | 100 | 1.1 s | 0 | 0 ms |
| Reservations | Mobile | 100 | 100 | 100 | 100 | 1.1 s | 0 | 0 ms |
| Home | Desktop | 100 | 100 | 100 | 100 | 0.6 s | 0 | 0 ms |

Fixes made after the first audit (Home, mobile: 96 / 96 / 96 / 100):

- **Color contrast**: turmeric text on the green band was 3.7:1, now uses a lighter tint (5:1).
- **Layout shift (CLS 0.108 to 0)**: the hero illustration reserves its space with `aspect-ratio`.
- **Console error**: added an SVG favicon (the browser was requesting a missing `/favicon.ico`).
