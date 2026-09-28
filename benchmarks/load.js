// k6 load test: random visitor browsing the public pages.
// Run via scripts/benchmark.ps1 (or .sh), which passes BASE_URL, VUS and DURATION.
import http from 'k6/http';
import { check } from 'k6';

const BASE = __ENV.BASE_URL || 'http://nginx';
const HOST = __ENV.HOST_HEADER || '';
const PATHS = ['/', '/menu/', '/about/', '/news/', '/visit/', '/dish/pho-ga/', '/pho-happy-hour/'];

export const options = {
  vus: Number(__ENV.VUS || 20),
  duration: __ENV.DURATION || '30s',
  discardResponseBodies: true,
  summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
};

export default function () {
  const path = PATHS[Math.floor(Math.random() * PATHS.length)];
  const res = http.get(BASE + path, {
    headers: HOST ? { Host: HOST } : {},
    redirects: 0,
  });
  check(res, { 'status 200': (r) => r.status === 200 });
}
