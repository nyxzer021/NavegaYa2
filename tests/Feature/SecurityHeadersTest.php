<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Alpine evalúa sus expresiones (x-data, x-show, @click) y necesita 'unsafe-eval'.
     * Sin él, el menú del panel, el asistente de flota y el diseñador del plano quedan inertes en producción.
     */
    public function test_production_policy_lets_alpine_run_and_keeps_the_rest_locked(): void
    {
        $this->app['env'] = 'production';

        $policy = (string) $this->get(route('home'))->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
    }
}
