<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Entity\Role;
use App\Entity\User;
use App\Service\DashboardService;

final class DashboardController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly View $view,
    ) {
    }

    public function index(Request $request, User $user): Response
    {
        // Sales tidak melihat ringkasan stok (§1.2: dashboard Sales = order miliknya,
        // menyusul bersama modul Sales Order).
        $summary = $user->hasRole(Role::Admin, Role::WarehouseStaff) ? $this->dashboard->stockSummary() : null;

        return Response::html($this->view->render('dashboard/index', ['title' => 'Dashboard', 'summary' => $summary]));
    }
}
