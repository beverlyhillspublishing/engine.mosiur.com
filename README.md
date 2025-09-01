# Engine OS for Mosiur — Monorepo

Private repository for engine.mosiur.com. This repo is a scaffolded monorepo with placeholders for backend, frontend, services, infrastructure, and Kubernetes overlays.

The current state intentionally keeps most implementation files empty. A minimal Dockerfile has been added so DigitalOcean App Platform can build and run a simple health endpoint until real services are wired in.

## What’s Included

- Monorepo workspace (`package.json`) with scripts and targets
- Dev Docker Compose with Postgres/Redis and service stubs (`docker-compose.yml`)
- Kubernetes overlays (placeholders) under `kubernetes/overlays/*`
- CI workflow for DOKS deployments: `.github/workflows/deploy-do.yml`
- Minimal root `Dockerfile` serving a health endpoint for App Platform

## Quick Start

Prerequisites
```
- Node.js 20+
- npm 10+
- Docker Desktop
- kubectl (for k8s workflows)
- doctl (for DigitalOcean)
```

Local Development (scaffold)
```
npm install
docker compose up -d postgres redis
# Service Dockerfiles are placeholders; implement before using 'backend'/'frontend' containers
```

DigitalOcean — App Platform (temporary)
- Uses the root `Dockerfile` (minimal health server). The service listens on `$PORT` (default 8080) and responds to `/` and `/health`.
- Replace with a real app image when backend/frontend Dockerfiles are ready.

DigitalOcean — DOKS via GitHub Actions
- Workflow: `.github/workflows/deploy-do.yml` (manual dispatch or push to main)
- Secret: `DIGITALOCEAN_TOKEN` (already configured)
- Kustomize overlays live at `kubernetes/overlays/digitalocean/*` and are currently empty.

## Repo Layout
```
.
├── backend/              # Backend service (Bun/Node) — placeholder
├── frontend/             # Frontend (Next.js) — placeholder
├── services/             # Additional services — placeholder
├── packages/             # Shared UI/types/utils — placeholder
├── infrastructure/       # Terraform, etc. — placeholder
├── kubernetes/           # Base + overlays (empty stubs)
├── docker/               # Service Dockerfiles (placeholders)
├── scripts/              # Automation scripts
├── .github/workflows/    # CI/CD
├── Dockerfile            # Minimal health server for App Platform
└── docker-compose.yml    # Dev databases and stubs
```

## Deployment

DigitalOcean App Platform
- Uses the root `Dockerfile` (minimal health server). The service listens on `$PORT` (default 8080).
- When your backend is ready, point App Platform to a real `backend/Dockerfile` and set the internal port accordingly.

DigitalOcean Kubernetes (DOKS)
- Trigger the workflow: Actions → “Deploy to DigitalOcean (DOKS)” → select environment.
- Ensure `${{ secrets.DIGITALOCEAN_TOKEN }}` is set (already done) and your overlays contain valid manifests.

## Secrets

- GitHub: `DIGITALOCEAN_TOKEN` is configured as a repo secret. Reference as `${{ secrets.DIGITALOCEAN_TOKEN }}` in workflows.
- Do NOT commit real cloud tokens. `.env.example` contains placeholders only.

## Next Steps

- Implement production `backend/Dockerfile` and `frontend/Dockerfile`.
- Populate Kubernetes overlays under `kubernetes/overlays/digitalocean/*` with `kustomization.yaml` + manifests.
- Add lock files for deterministic builds (`package-lock.json`/`bun.lockb` per workspace).
- Replace the root `Dockerfile` with a real service image or keep it only for App Platform placeholder deployments.

