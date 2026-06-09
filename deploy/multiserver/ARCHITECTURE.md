# Multi-Server Architecture

Cloudflare DNS/CDN/WAF -> Load Balancer -> App Server 1/2 -> Managed MySQL -> Managed Redis -> Object Storage S3/R2/Spaces -> Worker -> Scheduler -> Backup Offsite -> Monitoring.

App server stateless. Session/cache/rate limit/queue di Redis. Upload di object storage. Database managed. Backup offsite.
