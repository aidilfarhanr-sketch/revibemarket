# Docker Production

```bash
cp .env.example .env
# isi .env production, terutama DB eksternal, Redis, storage remote
COMPOSE_FILE=docker-compose.production.yml docker compose up -d --build
```

Gunakan `db-demo` hanya untuk demo:

```bash
docker compose -f docker-compose.production.yml --profile demo-db up -d
```
