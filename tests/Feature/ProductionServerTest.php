<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductionServerTest extends TestCase
{
    public function test_the_health_route_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_https_behind_the_railway_proxy_is_trusted(): void
    {
        $request = Request::create('/up', 'GET', server: [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'navegaya.example',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.8',
            'REMOTE_ADDR' => '10.0.0.5',
        ]);

        $response = $this->app->make(Kernel::class)->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($request->isSecure());
        $this->assertSame('203.0.113.8', $request->ip());
    }
}
