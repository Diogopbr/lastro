<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class VercelIngressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/test/ingress', fn (Request $request) => response()->json(['ip' => $request->ip(), 'secure' => $request->isSecure(), 'host' => $request->host()]));
    }

    public function test_gateway_mode_uses_the_platform_ip_and_https_but_not_forwarded_host(): void
    {
        config(['lastro.vercel_ingress' => true, 'lastro.vercel_runtime' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->getJson('http://lastro.example/test/ingress', [
            'X-Vercel-Forwarded-For' => '203.0.113.19', 'X-Forwarded-For' => '198.51.100.1',
            'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example',
        ])->assertOk()->assertJsonPath('ip', '203.0.113.19')->assertJsonPath('secure', true)->assertJsonPath('host', 'lastro.example');
    }

    public function test_direct_server_ignores_headers_even_after_a_gateway_request(): void
    {
        config(['lastro.vercel_ingress' => true, 'lastro.vercel_runtime' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->getJson('http://localhost/test/ingress', [
            'X-Vercel-Forwarded-For' => '203.0.113.19', 'X-Forwarded-Proto' => 'https',
        ])->assertOk()->assertJsonPath('secure', true);
        config(['lastro.vercel_ingress' => false, 'lastro.vercel_runtime' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->getJson('http://localhost/test/ingress', [
            'X-Vercel-Forwarded-For' => '203.0.113.19', 'X-Forwarded-For' => '198.51.100.1', 'X-Forwarded-Proto' => 'https',
        ])->assertOk()->assertJsonPath('ip', '10.0.0.3')->assertJsonPath('secure', false);
    }

    public function test_gateway_mode_is_unavailable_without_the_runtime_marker(): void
    {
        config(['lastro.vercel_ingress' => true, 'lastro.vercel_runtime' => false]);
        $this->getJson('/test/ingress')->assertStatus(503);
    }

    public function test_malformed_platform_ip_cannot_become_a_rate_limit_identity(): void
    {
        config(['lastro.vercel_ingress' => true, 'lastro.vercel_runtime' => true]);
        $this->getJson('/test/ingress', ['X-Vercel-Forwarded-For' => '203.0.113.1, 198.51.100.1', 'X-Forwarded-Proto' => 'https'])->assertBadRequest();
    }
}
