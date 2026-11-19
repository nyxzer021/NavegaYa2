<?php

namespace Tests\Feature;

use App\Mail\QueueProbeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QueueMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_worker_sends_a_queued_message(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);

        Mail::to('operador@navegaya.test')->queue(new QueueProbeMail);

        $this->assertDatabaseCount('jobs', 1);

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());
        $this->assertStringContainsString('Prueba de la cola de correo', $transport->messages()->first()->getOriginalMessage()->toString());
    }
}
