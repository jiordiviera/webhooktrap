FROM node:20-alpine

WORKDIR /app

# Install pnpm
RUN npm install -g pnpm@9.0.0

# Copy root files
COPY package.json pnpm-lock.yaml turbo.json ./

# Copy packages
COPY packages ./packages

# Copy API app
COPY apps/api ./apps/api

# Install dependencies (allow lockfile updates for monorepo)
RUN pnpm install

EXPOSE 3333

# Default: dev mode (overridden in docker-compose)
# Production should use: node apps/api/build/bin/server.js
CMD ["pnpm", "--filter", "api", "dev"]
