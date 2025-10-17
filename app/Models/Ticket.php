<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

class Ticket
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public function __construct(
        public ?int $id = null,
        public int $contactId = 0,
        public ?string $subject = null,
        public string $status = self::STATUS_OPEN,
        public string $priority = self::PRIORITY_NORMAL,
        public ?int $assignedUserId = null,
        public ?DateTimeImmutable $openedAt = null,
        public ?DateTimeImmutable $closedAt = null,
        public ?DateTimeImmutable $slaDueAt = null,
        public string $channel = 'whatsapp'
    ) {
        $this->openedAt ??= new DateTimeImmutable();
    }
}
