<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Status PO (§1.3) beserta aturan transisinya (PO-01):
 * Draft -> Ordered -> PartiallyReceived -> Received, atau Cancelled
 * selama belum ada barang yang diterima.
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'Draft';
    case Ordered = 'Ordered';
    case PartiallyReceived = 'PartiallyReceived';
    case Received = 'Received';
    case Cancelled = 'Cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ordered => 'Ordered',
            self::PartiallyReceived => 'Partially Received',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Hanya Draft yang bisa diajukan ke supplier.
     */
    public function canBeOrdered(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Barang hanya bisa diterima untuk PO yang sudah dipesan dan belum lengkap.
     */
    public function canReceive(): bool
    {
        return $this === self::Ordered || $this === self::PartiallyReceived;
    }

    /**
     * Pembatalan hanya sebelum ada barang yang diterima. PO PartiallyReceived
     * tidak bisa dibatalkan karena stok yang sudah masuk sudah tercatat di ledger.
     */
    public function canBeCancelled(): bool
    {
        return $this === self::Draft || $this === self::Ordered;
    }
}
