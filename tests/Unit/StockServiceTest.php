<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\StockChange;
use App\Service\BusinessRuleException;
use App\Service\StockService;
use PHPUnit\Framework\TestCase;
use Tests\Fake\InMemoryStockRepository;

/**
 * Area logic: perhitungan stok & aturan anti-oversell (ADR-001), tanpa database.
 */
final class StockServiceTest extends TestCase
{
    private InMemoryStockRepository $repo;
    private StockService $service;

    protected function setUp(): void
    {
        $this->repo = new InMemoryStockRepository();
        $this->repo->set('SKU-A', 1, 5);
        $this->repo->set('SKU-B', 1, 2);
        $this->service = new StockService($this->repo);
    }

    public function testReceiptIncreasesStockAndWritesOneLedgerRowPerChange(): void
    {
        $this->service->apply([$this->change('SKU-A', 3), $this->change('SKU-B', 4)]);

        self::assertSame(8, $this->repo->quantity('SKU-A', 1));
        self::assertSame(6, $this->repo->quantity('SKU-B', 1));
        self::assertCount(2, $this->repo->ledger);
    }

    public function testIssueThatWouldMakeStockNegativeIsRejectedBeforeAnythingIsWritten(): void
    {
        try {
            // SKU-A cukup, SKU-B tidak: tidak boleh ada perubahan parsial.
            $this->service->apply([$this->change('SKU-A', -1), $this->change('SKU-B', -3)]);
            self::fail('BusinessRuleException seharusnya dilempar.');
        } catch (BusinessRuleException $e) {
            self::assertSame('Stok SKU-B tidak mencukupi: tersedia 2, dibutuhkan 3.', $e->getMessage());
        }

        self::assertSame(5, $this->repo->quantity('SKU-A', 1));
        self::assertSame([], $this->repo->ledger);
    }

    public function testIssueOfExactlyAllStockIsAllowedAndSecondIssueIsRejected(): void
    {
        $this->service->apply([$this->change('SKU-B', -2)]);
        self::assertSame(0, $this->repo->quantity('SKU-B', 1));

        $this->expectException(BusinessRuleException::class);
        $this->service->apply([$this->change('SKU-B', -1)]);
    }

    public function testMultipleChangesOnSameRowAreCheckedAsOneNetAmount(): void
    {
        $this->expectException(BusinessRuleException::class);

        // Masing-masing -3 lolos terhadap stok 5, tetapi totalnya -6.
        $this->service->apply([$this->change('SKU-A', -3), $this->change('SKU-A', -3)]);
    }

    public function testRowsAreLockedInSkuOrderToPreventDeadlock(): void
    {
        $this->repo->set('SKU-C', 1, 1);

        $this->service->apply([$this->change('SKU-C', 1), $this->change('SKU-A', 1), $this->change('SKU-B', 1)]);

        self::assertSame(['SKU-A@1', 'SKU-B@1', 'SKU-C@1'], $this->repo->lockOrder);
    }

    private function change(string $sku, int $delta): StockChange
    {
        return new StockChange($sku, 1, $delta, $delta > 0 ? StockChange::TYPE_RECEIPT : StockChange::TYPE_ISSUE, 'PO', 'PO-TEST', 1);
    }
}
