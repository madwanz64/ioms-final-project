<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Role;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderStatus;
use App\Entity\User;
use App\Service\SalesOrderPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Area logic: ownership & authorization approve (SO-01, segregation of duties §1.2).
 */
final class SalesOrderPolicyTest extends TestCase
{
    private const ADMIN = 1;
    private const ADMIN_2 = 9;
    private const SINTA = 2;
    private const DONI = 3;
    private const RUDI = 4;

    /**
     * @return array<string, array{int, Role, int, bool}>
     */
    public static function reviewCases(): array
    {
        return [
            'Admin menyetujui order Sales' => [self::ADMIN, Role::Admin, self::SINTA, true],
            'Sales menyetujui order MILIKNYA SENDIRI' => [self::SINTA, Role::Sales, self::SINTA, false],
            'Sales menyetujui order Sales lain' => [self::DONI, Role::Sales, self::SINTA, false],
            'Warehouse Staff menyetujui' => [self::RUDI, Role::WarehouseStaff, self::SINTA, false],
            // K-05 (dikonfirmasi): tabel peran §1.2 membolehkan Admin membuat & menyetujui SO.
            'Admin menyetujui order buatannya sendiri' => [self::ADMIN, Role::Admin, self::ADMIN, true],
            'Admin lain menyetujui order Admin' => [self::ADMIN_2, Role::Admin, self::ADMIN, true],
        ];
    }

    #[DataProvider('reviewCases')]
    public function testOnlyAdminCanApproveOrRejectAndSalesNeverCan(int $actorId, Role $role, int $creatorId, bool $expected): void
    {
        $order = $this->order($creatorId, SalesOrderStatus::PendingApproval);

        self::assertSame($expected, (new SalesOrderPolicy())->canReview($this->user($actorId, $role), $order));
    }

    public function testApprovalRequiresPendingApprovalStatus(): void
    {
        $policy = new SalesOrderPolicy();
        $admin = $this->user(self::ADMIN, Role::Admin);

        foreach ([SalesOrderStatus::Draft, SalesOrderStatus::Approved, SalesOrderStatus::Fulfilled, SalesOrderStatus::Cancelled] as $status) {
            self::assertFalse($policy->canReview($admin, $this->order(self::SINTA, $status)), $status->value);
        }
    }

    public function testVisibility(): void
    {
        $policy = new SalesOrderPolicy();
        $sintaDraft = $this->order(self::SINTA, SalesOrderStatus::Draft);
        $sintaPending = $this->order(self::SINTA, SalesOrderStatus::PendingApproval);

        self::assertTrue($policy->canView($this->user(self::SINTA, Role::Sales), $sintaDraft));
        self::assertFalse($policy->canView($this->user(self::DONI, Role::Sales), $sintaPending), 'Sales tidak melihat order Sales lain');
        self::assertFalse($policy->canView($this->user(self::RUDI, Role::WarehouseStaff), $sintaDraft), 'Warehouse tidak melihat Draft');
        self::assertTrue($policy->canView($this->user(self::RUDI, Role::WarehouseStaff), $sintaPending));
        self::assertTrue($policy->canView($this->user(self::ADMIN, Role::Admin), $sintaDraft));
    }

    public function testCancelRules(): void
    {
        $policy = new SalesOrderPolicy();
        $sinta = $this->user(self::SINTA, Role::Sales);
        $admin = $this->user(self::ADMIN, Role::Admin);

        self::assertTrue($policy->canCancel($sinta, $this->order(self::SINTA, SalesOrderStatus::PendingApproval)));
        self::assertFalse($policy->canCancel($sinta, $this->order(self::SINTA, SalesOrderStatus::Approved)), 'Sales tidak membatalkan order yang sudah disetujui');
        self::assertTrue($policy->canCancel($admin, $this->order(self::SINTA, SalesOrderStatus::Approved)));
        self::assertFalse($policy->canCancel($admin, $this->order(self::SINTA, SalesOrderStatus::Fulfilled)), 'Tidak ada pembatalan setelah Fulfilled');
        self::assertFalse($policy->canCancel($this->user(self::RUDI, Role::WarehouseStaff), $this->order(self::SINTA, SalesOrderStatus::Approved)));
    }

    public function testFulfillmentIsForAdminOrWarehouseOnApprovedOrders(): void
    {
        $policy = new SalesOrderPolicy();
        $approved = $this->order(self::SINTA, SalesOrderStatus::Approved);

        self::assertTrue($policy->canFulfill($this->user(self::RUDI, Role::WarehouseStaff), $approved));
        self::assertTrue($policy->canFulfill($this->user(self::ADMIN, Role::Admin), $approved));
        self::assertFalse($policy->canFulfill($this->user(self::SINTA, Role::Sales), $approved));
        self::assertFalse($policy->canFulfill($this->user(self::RUDI, Role::WarehouseStaff), $this->order(self::SINTA, SalesOrderStatus::PendingApproval)));
    }

    private function user(int $id, Role $role): User
    {
        return new User($id, 'User ' . $id, $id . '@ioms.test', 'x', $role, true);
    }

    private function order(int $creatorId, SalesOrderStatus $status): SalesOrder
    {
        return new SalesOrder(1, 'SO-TEST-0001', 1, 'Customer', 1, 'Gudang', $creatorId, 'Pembuat', null, null, $status, '2026-10-01', []);
    }
}
