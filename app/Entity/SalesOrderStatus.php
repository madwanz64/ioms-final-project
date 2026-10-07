<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Status SO (§1.3): Draft -> PendingApproval -> Approved -> Fulfilled,
 * atau Cancelled pada tahap mana pun sebelum Fulfilled.
 */
enum SalesOrderStatus: string
{
    case Draft = 'Draft';
    case PendingApproval = 'PendingApproval';
    case Approved = 'Approved';
    case Fulfilled = 'Fulfilled';
    case Cancelled = 'Cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending Approval',
            default => $this->value,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Fulfilled || $this === self::Cancelled;
    }
}
