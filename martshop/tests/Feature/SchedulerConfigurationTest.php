<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerConfigurationTest extends TestCase
{
    public function test_critical_marketplace_jobs_have_bounded_overlap_locks(): void
    {
        $events = collect(app(Schedule::class)->events());

        $reservation = $this->eventFor($events, 'reservations:release-expired');
        $this->assertSame('* * * * *', $reservation->expression);
        $this->assertTrue($reservation->withoutOverlapping);
        $this->assertSame(5, $reservation->expiresAt);

        $settlement = $this->eventFor($events, 'settlements:release-due');
        $this->assertSame('*/5 * * * *', $settlement->expression);
        $this->assertTrue($settlement->withoutOverlapping);
        $this->assertSame(15, $settlement->expiresAt);

        $resetCleanup = $this->eventFor($events, 'auth:clear-resets');
        $this->assertSame('20 3 * * *', $resetCleanup->expression);
        $this->assertTrue($resetCleanup->withoutOverlapping);
        $this->assertSame(10, $resetCleanup->expiresAt);
    }

    public function test_async_queue_connections_dispatch_only_after_database_commit_by_default(): void
    {
        foreach (['database', 'beanstalkd', 'sqs', 'redis'] as $connection) {
            $this->assertTrue(config("queue.connections.{$connection}.after_commit"));
        }
    }

    private function eventFor($events, string $command): Event
    {
        $event = $events->first(fn (Event $event) => str_contains((string) $event->command, $command));

        $this->assertInstanceOf(Event::class, $event, "Missing scheduled command: {$command}");

        return $event;
    }
}
