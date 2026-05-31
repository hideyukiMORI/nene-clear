<?php

declare(strict_types=1);

namespace NeneClear\Tests\Dunning;

use NeneClear\Dunning\PauseDunningUseCase;
use NeneClear\Dunning\ResumeDunningUseCase;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class PauseDunningUseCaseTest extends TestCase
{
    private InMemoryDunningPauseRepository $pauses;
    private InMemoryAuditEventRepository $audit;
    private FixedClock $clock;
    private PauseDunningUseCase $pauseUseCase;
    private ResumeDunningUseCase $resumeUseCase;

    protected function setUp(): void
    {
        $this->pauses = new InMemoryDunningPauseRepository();
        $this->audit = new InMemoryAuditEventRepository();
        $this->clock = new FixedClock('2026-06-01T10:00:00+00:00');
        $this->pauseUseCase = new PauseDunningUseCase($this->pauses, $this->audit, $this->clock);
        $this->resumeUseCase = new ResumeDunningUseCase($this->pauses, $this->audit, $this->clock);
    }

    public function test_pause_creates_record_and_audit_event(): void
    {
        $pause = $this->pauseUseCase->execute(7, 1, 42, 'pending dispute resolution');

        self::assertSame(1, $pause->invoiceId);
        self::assertSame('pending dispute resolution', $pause->pausedReason);
        self::assertSame(42, $pause->pausedBy);
        self::assertNull($pause->unpausedAt);
        self::assertTrue($pause->isActive());

        self::assertCount(1, $this->audit->events);
        self::assertSame('dunning_paused', $this->audit->events[0]->eventType);
    }

    public function test_pause_is_idempotent_when_already_active(): void
    {
        $first = $this->pauseUseCase->execute(7, 1, 42, 'reason A');
        $second = $this->pauseUseCase->execute(7, 1, 99, 'reason B');

        self::assertSame($first->id, $second->id);
        self::assertSame('reason A', $second->pausedReason);
        self::assertCount(1, $this->audit->events);
    }

    public function test_findActiveByInvoice_returns_active_pause(): void
    {
        $this->pauseUseCase->execute(7, 5, 42, 'disputed');

        $active = $this->pauses->findActiveByInvoice(7, 5);
        self::assertNotNull($active);
        self::assertTrue($active->isActive());
    }

    public function test_resume_clears_active_pause_and_records_audit(): void
    {
        $this->pauseUseCase->execute(7, 1, 42, 'reason');

        $this->resumeUseCase->execute(7, 1, 99);

        $active = $this->pauses->findActiveByInvoice(7, 1);
        self::assertNull($active);

        self::assertCount(2, $this->audit->events);
        self::assertSame('dunning_resumed', $this->audit->events[1]->eventType);
    }

    public function test_resume_different_org_does_not_affect(): void
    {
        $this->pauseUseCase->execute(7, 1, 42, 'reason');

        $this->resumeUseCase->execute(99, 1, 42);

        $active = $this->pauses->findActiveByInvoice(7, 1);
        self::assertNotNull($active);
    }

    public function test_send_dunning_blocked_when_paused(): void
    {
        $this->pauseUseCase->execute(7, 1, 42, 'blocked reason');

        $active = $this->pauses->findActiveByInvoice(7, 1);
        self::assertNotNull($active);
        self::assertSame('blocked reason', $active->pausedReason);
    }

    public function test_after_resume_no_active_pause(): void
    {
        $this->pauseUseCase->execute(7, 1, 42, 'temp hold');
        $this->resumeUseCase->execute(7, 1, 42);

        self::assertNull($this->pauses->findActiveByInvoice(7, 1));
    }

    public function test_list_by_organization_active_only(): void
    {
        $this->pauseUseCase->execute(7, 1, 42, 'reason A');
        $this->pauseUseCase->execute(7, 2, 42, 'reason B');
        $this->resumeUseCase->execute(7, 1, 42);

        $active = $this->pauses->findByOrganization(7, true, 50, 0);
        self::assertCount(1, $active);
        self::assertSame(2, $active[0]->invoiceId);
    }
}
