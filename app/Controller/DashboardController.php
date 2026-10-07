<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Entity\Role;
use App\Entity\User;
use App\Service\DashboardService;

/**
 * Satu URL /dashboard; isi berbeda per role sesuai §1.2 (DASH-01).
 */
final class DashboardController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly View $view,
    ) {
    }

    public function index(Request $request, User $user): Response
    {
        [$template, $data] = match ($user->role) {
            Role::Admin => ['dashboard/admin', $this->dashboard->forAdmin($user)],
            Role::Sales => ['dashboard/sales', $this->dashboard->forSales($user)],
            Role::WarehouseStaff => ['dashboard/warehouse', $this->dashboard->forWarehouse()],
        };

        return Response::html($this->view->render($template, ['title' => 'Dashboard'] + $data));
    }
}
