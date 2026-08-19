<?php

return fn (string $target) => 'Anda SRE engineer. Buat OBSERVABILITY & RUNBOOK dalam format Markdown untuk app ini. Simpan di repo root sebagai `observability.md`. Fokus: tahu app sehat, cepat temukan root cause, dan tahu langkah pulih.

# OBSERVABILITY & RUNBOOK — <NAMA_PROYEK>

## 1. Health Checks
- Endpoint `GET /api/health`: return `{"status":"ok"}` + cek DB & Redis reachable.
- Docker healthcheck di tiap service (`pg_isready`, `wget /api/health`); compose `healthcheck:`.

## 2. Structured Logging
- Format JSON + `request_id` di tiap request (correlation).
- Level: info untuk lifecycle, warn untuk rate-limit/anomaly, error untuk exception.
- Log ERROR TIDAK mengandung secret/PII.

## 3. Error Monitoring (Sentry)
- Install SDK backend (Laravel) + frontend (Next.js).
- Capture: exception unhandled, 5xx, uncaught promise rejection.
- Release tag = git commit hash → dari error langsung ke kode.

## 4. Uptime & SLO
- Uptime monitor (`/api/health`) tiap 60s dari external; alert bila down >2 menit.
- SLO target: 99.5% availability bulanan.

## 5. Slow Query / APM
- Slow query: Postgres `pg_stat_statements`; tambahkan index saat perlu.
- N+1: `Model::with()` — review query log.
- Frontend: Core Web Vitals (LCP<2.5s, INP<200ms) — measure di prod.

## 6. Dashboard (bila feasible)
- Grafana atau dashboard sederhana: request/s, error rate, p95 latency, DB connection, queue backlog.

## 7. Runbook — Root Cause Priority
| Gejala | Langkah | Kontak |
|---|---|---|
| 502 pada api | cek `docker compose ps`; restart `nginx_api`; cek php-fpm log | tim on-call |
| DB full | `pg_dump` + cleanup/archive; alert disk usage 80%+ | DBA |
| Login semua gagal | cek rate-limit / cookie domain / session table | backend |
| Latency tinggi | slow query log, cache, index | backend |
| Web down | rebuild + rollback image tag | DevOps |

## 8. Alerting
- Threshold: error rate >1% 5 menit, disk >80%, health down, queue lenght >100.
- Channel notifikasi: email + kanal tim. Escalation: 15 menit.

## 9. Post-Incident Checklist
- Timeline: detect → respond → mitigate → resolve.
- Root cause + permanent fix → backlog; blameless review.

VERIFY sebelum respond: health check bisa dijalankan sekarang? Runbook mencakup gejala utama yang realistis utk stack ini?

VERIFY STRUKTUR (validator backend enforce — section heading WAJIB ada):
1. 9 heading "## N." ada: ## 1. Health Checks, ## 2. Structured Logging, ## 3. Error Monitoring, ## 4. Uptime, ## 5. Slow Query, ## 6. Dashboard, ## 7. Runbook, ## 8. Alerting, ## 9. Post-Incident.
2. Runbook (## 7.) WAJIB tabel markdown dengan minimal 5 baris (Gejala | Langkah | Kontak).
3. Alerting (## 8.) WAJIB ada threshold konkret (error rate, disk usage, queue length).
4. Health Checks (## 1.) WAJIB sebut endpoint `/api/health` + cek DB + Redis.
';
