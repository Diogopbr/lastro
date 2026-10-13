import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const liveURL = process.env.E2E_BASE_URL;

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60000,
  expect: { timeout: 10000 },
  reporter: [['list']],
  use: {
    baseURL: liveURL || 'http://127.0.0.1:5083',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    ...devices['Desktop Chrome'],
  },
  webServer: liveURL ? undefined : {
    command: 'php -S 127.0.0.1:5083 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
    cwd: path.resolve('public'),
    url: 'http://127.0.0.1:5083/health/ready',
    reuseExistingServer: false,
    timeout: 30000,
    stdout: 'ignore',
    stderr: 'ignore',
    env: {
      APP_ENV: 'local', APP_DEBUG: 'false',
      LASTRO_DEMO_ENABLED: 'true', LASTRO_DEMO_EXAMPLES: 'true',
      SESSION_DRIVER: 'database', CACHE_STORE: 'database',
    },
  },
});
