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

# Install dependencies
RUN pnpm install --frozen-lockfile

# Build API
RUN pnpm --filter api build

EXPOSE 3333

CMD ["node", "apps/api/build/bin/server.js"]
