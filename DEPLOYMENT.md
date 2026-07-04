# Deployment Guide

> Webhook Trap can run anywhere: local Docker, Railway, Fly.io, or a VPS.

## Local Development with Docker

### Prerequisites
- Docker & Docker Compose
- 2GB available RAM

### Quick Start

```bash
git clone https://github.com/jiordiviera/webhooktrap.git
cd webhooktrap

# Copy env template
cp .env.example .env

# Start services (postgres, api, web)
docker-compose up
```

Visit:
- **Web:** http://localhost:7777
- **API:** http://localhost:3333

The database is seeded with a test user (see logs after first run).

### Customize Environment

Edit `.env` before running `docker-compose up`:

```bash
# OAuth (optional — leave empty to skip)
GITHUB_CLIENT_ID=your-id
GITHUB_CLIENT_SECRET=your-secret

# Media storage (R2/S3 or local)
DRIVE_DISK=r2
R2_KEY=your-key
R2_SECRET=your-secret
R2_BUCKET=webhooktrap
R2_ENDPOINT=https://xxx.r2.cloudflarestorage.com
```

### Stop Services

```bash
docker-compose down
```

To also remove the database:

```bash
docker-compose down -v
```

---

## Production Deployment

### Option 1: Railway (Recommended for small scale)

Railway auto-deploys from GitHub and handles SSL/domains.

1. **Create account** at [railway.app](https://railway.app)
2. **Connect your GitHub repo**
3. **Set environment variables** in Railway dashboard:
   - `DATABASE_URL` (Postgres plugin)
   - `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`
   - `R2_KEY`, `R2_SECRET`, `R2_BUCKET`, `R2_ENDPOINT`
   - `MAIL_MAILER`, `MAIL_FROM_ADDRESS`

4. **Point your domain** via CNAME to Railway

**Cost:** ~$5–20/month depending on traffic.

### Option 2: Fly.io (App + Postgres)

Fly.io manages global deployments with edge caching.

1. **Install flyctl** and login

```bash
brew install flyctl
flyctl auth login
```

2. **Create app**

```bash
flyctl launch
```

3. **Set secrets**

```bash
flyctl secrets set DATABASE_URL="postgresql://..."
flyctl secrets set GITHUB_CLIENT_ID="xxx"
flyctl secrets set GITHUB_CLIENT_SECRET="xxx"
# ... etc
```

4. **Deploy**

```bash
flyctl deploy
```

**Cost:** ~$7–15/month (includes Postgres).

### Option 3: VPS (Full Control, Higher Maintenance)

For AWS EC2, DigitalOcean, Hetzner, etc.

1. **SSH into VPS** (Ubuntu 22.04+)

```bash
ssh root@your-vps-ip
```

2. **Install Docker + Docker Compose**

```bash
curl -fsSL https://get.docker.com | sh
sudo apt-get install -y docker-compose
```

3. **Clone repo and configure**

```bash
git clone https://github.com/jiordiviera/webhooktrap.git
cd webhooktrap

cp .env.example .env
# Edit .env with production values
```

4. **Setup SSL with Caddy** (reverse proxy + auto HTTPS)

Create `Caddyfile`:

```caddy
webhooktrap.dev {
  reverse_proxy localhost:7777
}

api.webhooktrap.dev {
  reverse_proxy localhost:3333
}
```

5. **Start services with systemd**

Create `/etc/systemd/system/webhooktrap.service`:

```ini
[Unit]
Description=Webhook Trap
After=docker.service
Requires=docker.service

[Service]
Type=simple
WorkingDirectory=/root/webhooktrap
ExecStart=/usr/bin/docker-compose up
ExecStop=/usr/bin/docker-compose down
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
systemctl daemon-reload
systemctl enable webhooktrap
systemctl start webhooktrap
```

**Cost:** $5–50/month depending on VPS provider.

---

## Database Backup & Migration

### Backup Postgres (Docker)

```bash
docker-compose exec postgres pg_dump -U local webhooktrap > backup.sql
```

### Restore from Backup

```bash
cat backup.sql | docker-compose exec -T postgres psql -U local webhooktrap
```

### Migrate to Neon (Managed Postgres)

1. Create account at [neon.tech](https://neon.tech)
2. Copy `DATABASE_URL` from Neon dashboard
3. Update `.env` and redeploy

---

## Monitoring & Logs

### Local Docker Logs

```bash
docker-compose logs -f api   # API logs
docker-compose logs -f web   # Web logs
docker-compose logs -f postgres  # Database logs
```

### Production (Railway/Fly)

Use their built-in dashboards:
- **Railway:** Dashboard → Logs tab
- **Fly.io:** `flyctl logs -a your-app-name`

### Error Tracking (Optional)

Add Sentry for error monitoring:

```bash
# Set in environment
SENTRY_DSN=https://xxx@sentry.io/xxx
```

Errors will be sent to Sentry automatically.

---

## Performance Tuning

### Database Connection Pool

If seeing "too many connections" errors, adjust in `.env`:

```bash
DATABASE_URL="postgresql://user:pass@host/db?sslmode=require&max_pool_size=20"
```

### API Worker Threads

For high webhook volume:

```bash
# Set in docker-compose or systemd
NODE_OPTIONS="--max-old-space-size=2048"
```

### CDN for Media

Point `MEDIA_CDN_BASE_URL` to a CDN (Cloudflare, Bunny) for faster downloads.

---

## Troubleshooting

### Port Already in Use

```bash
# Local dev: change ports in docker-compose.yml
# Or kill the process:
lsof -ti :3333 | xargs kill -9
```

### Database Connection Failed

Check:
- Postgres is running: `docker-compose ps`
- `DATABASE_URL` is correct
- Network: `docker-compose logs postgres`

### Webhooks Not Being Received

- Verify firewall allows inbound traffic on port 3333
- Check API logs: `docker-compose logs -f api`
- Confirm webhook provider is sending to correct URL

---

## Self-Hosting Checklist

- [ ] Database backed up daily
- [ ] `.env` secrets are never committed
- [ ] SSL certificate auto-renews (Caddy/Let's Encrypt)
- [ ] Logs retained for 30 days
- [ ] Monitoring set up (Sentry or equivalent)
- [ ] Rate limiting configured for abuse prevention
- [ ] CORS origins whitelisted
