<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneEphemeralTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_sessions_and_cache_rows_are_removed(): void
    {
        $now = now()->getTimestamp();
        DB::table('sessions')->insert([
            ['id' => 'sesion-vieja', 'payload' => 'a', 'last_activity' => now()->subDays(10)->getTimestamp()],
            ['id' => 'sesion-viva', 'payload' => 'b', 'last_activity' => $now],
        ]);
        DB::table('cache')->insert([
            ['key' => 'cache-vieja', 'value' => 'a', 'expiration' => $now - 60],
            ['key' => 'cache-viva', 'value' => 'b', 'expiration' => $now + 3600],
        ]);

        $this->artisan('navegaya:prune-ephemeral')->assertSuccessful();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-vieja']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesion-viva']);
        $this->assertDatabaseMissing('cache', ['key' => 'cache-vieja']);
        $this->assertDatabaseHas('cache', ['key' => 'cache-viva']);
    }
}
