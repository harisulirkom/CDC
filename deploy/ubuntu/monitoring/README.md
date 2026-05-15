# CDC Monitoring & Alerting (SEC-24)

## 1) Exporter minimum

1. `node_exporter` (CPU, RAM, disk)
2. `nginx-prometheus-exporter` (request, 5xx)
3. `mysqld_exporter` (connection, slow query indicator)
4. `redis_exporter` (memory, evictions)

## 2) Alert Rules

Import file:

```bash
deploy/ubuntu/monitoring/prometheus-alert-rules.yml
```

## 3) Kriteria Go-Live Monitoring

1. Alert channel aktif (Telegram/Slack/Email).
2. Uji kirim alert minimal:
   - simulasi 5xx (backend dimatikan sementara)
   - simulasi disk space rendah (filesystem test)
3. Ada bukti notifikasi diterima + acknowledged.
