FROM node:20-alpine

WORKDIR /app

# Install pnpm
RUN npm install -g pnpm@9.0.0

# Copy root files
COPY package.json pnpm-lock.yaml turbo.json ./

# Copy packages
COPY packages ./packages

# Copy web app
COPY apps/web ./apps/web

# Install dependencies (allow lockfile updates for monorepo)
RUN pnpm install

EXPOSE 7777

ENV PORT=7777

# Default: dev mode (overridden in docker-compose)
# Production should use: pnpm --filter web build && pnpm --filter web start
CMD ["pnpm", "--filter", "web", "dev"]
