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

# Install dependencies
RUN pnpm install --frozen-lockfile

# Build web app
RUN pnpm --filter web build

EXPOSE 7777

ENV PORT=7777

CMD ["pnpm", "--filter", "web", "start"]
