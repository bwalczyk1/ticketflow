<?php

use App\Events\Orders\OrderPaid;
use App\Jobs\Payments\ProcessRefund;
use App\Listeners\Notifications\NotifyOrganizer;
use App\Services\Payments\SettleRefund;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * @param  array<string, int>  $ids
 */
function assertFailedJobLogged(string $job, array $ids): void
{
    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $message === 'Queued job failed'
            && $context['job'] === $job
            && array_intersect_assoc($context, $ids) === $ids
            && $context['error'] === RuntimeException::class
            && ! str_contains(json_encode($context), 'jan@example.com'));
}

it('logs a failed job with its class, ids and exception class only', function () {
    Log::spy();
    $this->mock(SettleRefund::class)->shouldReceive('handle')->andThrow(new RuntimeException('Refund for jan@example.com refused'));

    expect(fn () => Bus::dispatch(new ProcessRefund(5)))->toThrow(RuntimeException::class);

    assertFailedJobLogged(ProcessRefund::class, ['refundId' => 5]);
});

it('logs a failed queued listener with the ids of its event', function () {
    Log::spy();
    $this->mock(NotifyOrganizer::class)->shouldReceive('handle')->andThrow(new RuntimeException('Mailbox jan@example.com is full'));

    expect(fn () => Bus::dispatch(new CallQueuedListener(NotifyOrganizer::class, 'handle', [new OrderPaid(7)])))
        ->toThrow(RuntimeException::class);

    assertFailedJobLogged(NotifyOrganizer::class, ['orderId' => 7]);
});
