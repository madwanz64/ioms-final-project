<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderStatus;
use App\Entity\User;
use App\Repository\OrderSearchCriteria;
use App\Service\AuthorizationException;
use App\Service\BusinessRuleException;
use App\Service\NotFoundException;
use App\Service\SalesOrderPolicy;
use App\Service\SalesOrderService;
use App\Service\ValidationException;
use DateTimeImmutable;

/**
 * Router hanya menyaring role kasar (siapa yang boleh membuka modul SO).
 * Aturan per-order (pemilik, segregation of duties, status) ditegakkan di
 * SalesOrderService; pelanggarannya dijawab 403 di sini.
 */
final class SalesOrderController
{
    public function __construct(
        private readonly SalesOrderService $orders,
        private readonly SalesOrderPolicy $policy,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request, User $user): Response
    {
        $statuses = array_map(static fn (SalesOrderStatus $s): string => $s->value, SalesOrderStatus::cases());
        $criteria = OrderSearchCriteria::fromQuery($request->allQuery(), $statuses);

        return Response::html($this->view->render('sales-orders/index', [
            'title' => 'Sales Order',
            'result' => $this->orders->search($criteria, $user),
            'criteria' => $criteria,
            'canCreate' => $this->policy->canCreate($user),
        ]));
    }

    public function create(Request $request, User $user): Response
    {
        return $this->form(['order_date' => (new DateTimeImmutable('today'))->format('Y-m-d')], [[], [], []]);
    }

    public function store(Request $request, User $user): Response
    {
        $lines = $request->inputRows('items');
        try {
            $id = $this->orders->create($request->allInput(), $lines, $user);
        } catch (ValidationException $e) {
            return $this->form($request->allInput(), $lines === [] ? [[]] : $lines, $e->errors);
        } catch (AuthorizationException $e) {
            throw new HttpException(403, $e->getMessage());
        }
        $this->session->flash('success', 'Sales Order berhasil dibuat sebagai Draft. Ajukan agar dapat disetujui Admin.');

        return Response::redirect('/sales-orders/' . $id);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, User $user, array $params): Response
    {
        $order = $this->orders->findVisible(Router::intParam($params, 'id'), $user) ?? throw HttpException::notFound();

        return Response::html($this->view->render('sales-orders/show', [
            'title' => $order->orderNo,
            'order' => $order,
            'issues' => $this->orders->issues($order),
            'policy' => $this->policy,
        ]));
    }

    /**
     * @param array<string, string> $params
     */
    public function submit(Request $request, User $user, array $params): Response
    {
        return $this->act($params, fn (int $id): SalesOrder => $this->orders->submit($id, $user), '%s diajukan dan menunggu persetujuan Admin.');
    }

    /**
     * @param array<string, string> $params
     */
    public function approve(Request $request, User $user, array $params): Response
    {
        return $this->act($params, fn (int $id): SalesOrder => $this->orders->approve($id, $user), '%s disetujui dan masuk antrean goods issue.');
    }

    /**
     * @param array<string, string> $params
     */
    public function reject(Request $request, User $user, array $params): Response
    {
        return $this->act($params, fn (int $id): SalesOrder => $this->orders->reject($id, $user), '%s ditolak dan dibatalkan.');
    }

    /**
     * @param array<string, string> $params
     */
    public function cancel(Request $request, User $user, array $params): Response
    {
        return $this->act($params, fn (int $id): SalesOrder => $this->orders->cancel($id, $user), '%s dibatalkan.');
    }

    /**
     * @param array<string, string> $params
     */
    public function fulfill(Request $request, User $user, array $params): Response
    {
        return $this->act($params, fn (int $id): SalesOrder => $this->orders->fulfill($id, $user), 'Goods issue %s berhasil: stok berkurang dan order Fulfilled.');
    }

    /**
     * @param array<string, string> $params
     * @param callable(int): SalesOrder $action
     */
    private function act(array $params, callable $action, string $successMessage): Response
    {
        $id = Router::intParam($params, 'id');
        try {
            $order = $action($id);
            $this->session->flash('success', sprintf($successMessage, $order->orderNo));
        } catch (AuthorizationException $e) {
            // Otorisasi ditegakkan server: tetap 403 walau tombolnya tidak tampil di UI.
            throw new HttpException(403, $e->getMessage());
        } catch (NotFoundException) {
            throw HttpException::notFound();
        } catch (BusinessRuleException $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return Response::redirect('/sales-orders/' . $id);
    }

    /**
     * @param array<string, string> $old
     * @param list<array<string, string>> $lines
     * @param array<string, string> $errors
     */
    private function form(array $old, array $lines, array $errors = []): Response
    {
        return Response::html($this->view->render('sales-orders/form', [
            'title' => 'Buat Sales Order',
            'old' => $old,
            'lines' => $lines,
            'errors' => $errors,
            'options' => $this->orders->formOptions(),
        ]), $errors === [] ? 200 : 422);
    }
}
