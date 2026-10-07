<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Role;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderStatus;
use App\Entity\User;

/**
 * Aturan otorisasi Sales Order (§1.2, SO-01) di satu tempat. Ditegakkan di
 * server oleh SalesOrderService (bukan hanya menyembunyikan tombol di UI);
 * view memakai method yang sama hanya untuk menentukan tombol yang tampil.
 *
 * Segregation of duties: pembuat order TIDAK PERNAH boleh menyetujui/menolak
 * order yang sama — berlaku untuk semua user, termasuk Admin (lihat K-05).
 */
final class SalesOrderPolicy
{
    public function canCreate(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Sales);
    }

    /**
     * Admin: semua. Sales: hanya order miliknya. Warehouse Staff: semua kecuali
     * Draft (Draft masih urusan internal pembuatnya).
     */
    public function canView(User $user, SalesOrder $order): bool
    {
        return match ($user->role) {
            Role::Admin => true,
            Role::Sales => $order->isCreatedBy($user),
            Role::WarehouseStaff => $order->status !== SalesOrderStatus::Draft,
        };
    }

    public function canSubmit(User $user, SalesOrder $order): bool
    {
        return $order->status === SalesOrderStatus::Draft
            && ($order->isCreatedBy($user) || $user->role === Role::Admin);
    }

    /**
     * Approve dan reject memakai aturan yang sama: hanya Admin, dan bukan pembuat order.
     */
    public function canReview(User $user, SalesOrder $order): bool
    {
        return $order->status === SalesOrderStatus::PendingApproval
            && $user->role === Role::Admin
            && !$order->isCreatedBy($user);
    }

    public function canFulfill(User $user, SalesOrder $order): bool
    {
        return $order->status === SalesOrderStatus::Approved
            && $user->hasRole(Role::Admin, Role::WarehouseStaff);
    }

    /**
     * Admin boleh membatalkan kapan pun sebelum Fulfilled. Sales hanya order
     * miliknya yang belum disetujui (Draft / PendingApproval).
     */
    public function canCancel(User $user, SalesOrder $order): bool
    {
        if ($order->status->isFinal()) {
            return false;
        }

        return $user->role === Role::Admin
            || ($order->isCreatedBy($user)
                && in_array($order->status, [SalesOrderStatus::Draft, SalesOrderStatus::PendingApproval], true));
    }
}
