# Team Structure Guide

## Repository Layout

- `services/user-service`: User microservice (auth, profile, user endpoints)
- `services/product-service`: Product microservice (catalog, stock)
- `services/order-service`: Order microservice (order CRUD, history)
- `web`: Frontend static files (home, login, orders page, assets)
- `infra/gateway`: Nginx API gateway config
- `infra/php-apache`: Shared PHP Apache Docker image config
- `databases`: SQL seed files for each database

## Suggested Ownership

- Member A: `services/user-service` + `databases/user_db.sql`
- Member B: `services/product-service` + `databases/product_db.sql`
- Member C: `services/order-service` + `databases/order_db.sql`
- Member D (optional): `web` + `infra/gateway`

## Git Workflow (Recommended)

1. Create branch by scope, for example:
   - `feat/user-service-login`
   - `feat/product-stock`
   - `feat/order-crud`
2. Only modify files inside your assigned scope.
3. Open Pull Request into `develop` (or `main` if your team uses trunk).
4. Resolve conflicts early by rebasing frequently.

## Run Project

```bash
docker compose up -d
```

If schema files change and you need a fresh database, recreate DB containers/volumes before running again.
