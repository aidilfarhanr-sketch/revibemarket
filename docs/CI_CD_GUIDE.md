# CI_CD_GUIDE.md

GitHub Actions menjalankan:

- PHP syntax check.
- Secret/sensitive file scan.
- Migration smoke dan migration run dua kali.
- Business flow smoke tests.
- Storage/cache/error masking/admin 2FA/ledger smoke test.
- Health/readiness check.
- Release ZIP bersih tanpa `.env`, log, backup, dan private upload.

Artifact release dibuat sebagai `revibe-market-hosting-100-release.zip`.
