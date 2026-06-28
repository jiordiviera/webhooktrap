interface AppConfig {
  env: string;
  url: string;
  isProduction: boolean;
}

interface ApiConfig {
  url: string;
}

interface EnvConfig {
  app: AppConfig;
  api: ApiConfig;
}

export const env: EnvConfig = {
  app: {
    env: process.env.NODE_ENV || "development",
    url: process.env.NEXT_PUBLIC_APP_URL!,
    isProduction: process.env.NODE_ENV === "production",
  },
  api: {
    url: process.env.NEXT_PUBLIC_API_URL!,
  },
};

function validateEnv(): void {
  const required = {
    NEXT_PUBLIC_APP_URL: env.app.url,
    NEXT_PUBLIC_API_URL: env.api.url,
  };

  const missing = Object.entries(required).flatMap(([key, value]) =>
    value ? [] : [key],
  );

  if (missing.length > 0) {
    throw new Error(
      `Missing environment variables: ${missing.join(", ")}\n` +
        "Please check your .env file.",
    );
  }
}

validateEnv();
