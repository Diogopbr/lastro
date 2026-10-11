<?php

namespace Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SharedSessionTest extends TestCase
{
    public function test_two_server_processes_share_session_and_reset_invalidates_old_cookies(): void
    {
        $original = config('database.connections.pgsql');
        $schema = 'session_test_'.bin2hex(random_bytes(6));
        $servers = [];
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        try {
            config(['database.connections.pgsql.search_path' => $schema]);
            DB::purge('pgsql');
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
            $environment = [
                'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'APP_DEBUG' => 'false',
                'LASTRO_DEMO_ENABLED' => 'true', 'SESSION_DRIVER' => 'database', 'SESSION_SECURE_COOKIE' => 'false',
                'CACHE_STORE' => 'database', 'DB_SEARCH_PATH' => $schema,
                'DB_CONNECTION' => 'pgsql', 'DB_HOST' => $original['host'], 'DB_PORT' => (string) $original['port'],
                'DB_DATABASE' => $original['database'], 'DB_USERNAME' => $original['username'], 'DB_PASSWORD' => $original['password'],
            ];
            $urls = [];
            for ($index = 0; $index < 2; $index++) {
                $port = $this->availablePort();
                $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], public_path(), $environment);
                $server->setTimeout(30);
                $server->start();
                $servers[] = $server;
                $urls[] = 'http://127.0.0.1:'.$port;
                $this->waitReady($urls[$index], $server);
            }
            $cookies = new CookieJar;
            $client = new Client(['cookies' => $cookies, 'http_errors' => false, 'timeout' => 5, 'headers' => ['Accept' => 'application/json']]);
            $token = json_decode($client->get($urls[0].'/csrf')->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR)['token'];
            $this->assertSame(419, $client->post($urls[0].'/demo/start')->getStatusCode());
            $this->assertSame(201, $client->post($urls[0].'/demo/start', ['headers' => ['X-CSRF-TOKEN' => $token]])->getStatusCode());
            $oldCookies = new CookieJar(false, $cookies->toArray());
            $response = $client->get($urls[1].'/conferencias');
            $this->assertSame(200, $response->getStatusCode());
            $this->assertCount(3, json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR)['conferences']);
            $this->assertSame(1, DB::table('workspaces')->count());
            $oldWorkspace = DB::table('workspaces')->value('id');
            $token = json_decode($client->get($urls[1].'/csrf')->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR)['token'];
            $this->assertSame(200, $client->post($urls[1].'/demo/reset', ['headers' => ['X-CSRF-TOKEN' => $token]])->getStatusCode());
            $oldClient = new Client(['cookies' => $oldCookies, 'http_errors' => false, 'timeout' => 5, 'headers' => ['Accept' => 'application/json']]);
            $this->assertSame(401, $oldClient->get($urls[0].'/conferencias')->getStatusCode());
            $this->assertSame(200, $client->get($urls[0].'/conferencias')->getStatusCode());
            $this->assertFalse(DB::table('workspaces')->where('id', $oldWorkspace)->whereNull('revoked_at')->exists());
            $token = json_decode($client->get($urls[0].'/csrf')->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR)['token'];
            $limited = $client->post($urls[0].'/demo/reset', ['headers' => ['X-CSRF-TOKEN' => $token]]);
            $this->assertSame(429, $limited->getStatusCode());
            $this->assertGreaterThan(0, (int) $limited->getHeaderLine('Retry-After'));
        } finally {
            foreach ($servers as $server) {
                $server->stop(1);
            }
            config(['database.connections.pgsql' => $original]);
            DB::purge('pgsql');
            DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
        }
    }

    private function availablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket);
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }

    private function waitReady(string $url, Process $server): void
    {
        $client = new Client(['http_errors' => false, 'timeout' => 1]);
        $deadline = microtime(true) + 10;
        while ($server->isRunning() && microtime(true) < $deadline) {
            try {
                if ($client->get($url.'/health/ready')->getStatusCode() === 200) {
                    return;
                }
            } catch (ConnectException) {
            }
            usleep(10000);
        }
        $this->fail('Server did not become ready: '.$server->getErrorOutput());
    }
}
