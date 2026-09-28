// k6 load test: random visitor browsing the public pages.
// Run via scripts/benchmark.ps1 (or .sh), which passes BASE_URL, VUS and DURATION.
import http from 'k6/http';
import { check } from 'k6';

const BASE = __ENV.BASE_URL || 'http://nginx';
const HOST = __ENV.HOST_HEADER || '';
// BYPASS=1 sends a cookie that skips the page cache: measures PHP capacity, not cache speed.
const BYPASS = __ENV.BYPASS === '1';
const PATHS = ['/', '/menu/', '/about/', '/news/', '/visit/', '/dish/pho-ga/', '/pho-happy-hour/'];

export const options = {
  vus: Number(__ENV.VUS || 20),
  duration: __ENV.DURATION || '30s',
  discardResponseBodies: true,
  summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
};

export default function () {
  const path = PATHS[Math.floor(Math.random() * PATHS.length)];
  const headers = {};
  if (HOST) headers.Host = HOST;
  if (BYPASS) headers.Cookie = 'wordpress_no_cache=1';
  const res = http.get(BASE + path, {
    headers,
    redirects: 0,
  });
  check(res, { 'status 200': (r) => r.status === 200 });
}
