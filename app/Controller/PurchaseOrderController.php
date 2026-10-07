<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderStatus;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\OrderSearchCriteria;
use App\Service\BusinessRuleException;
use App\Service\PurchaseOrderService;
use App\Service\ValidationException;
use DateTimeImmutable;

/**
 * Akses (§1.2) diatur di router: daftar/detail/buat/terima untuk Admin &
 * Warehouse Staff; "pesan" dan "batalkan" khusus Admin. Sales tidak punya akses.
 */
final class PurchaseOrderController
{
    public function __construct(
        private readonly PurchaseOrderService $orders,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $statuses = array_map(static fn (PurchaseOrderStatus $s): string => $s->value, PurchaseOrderStatus::cases());
        $criteria = OrderSearchCriteria::fromQuery($request->allQuery(), $statuses);

        return Response::html($this->view->render('purchase-orders/index', [
            'title' => 'Purchase Order',
            'result' => $this->orders->search($criteria),
            'criteria' => $criteria,
        ]));
    }

    public function create(): Response
    {
        return $this->form(['order_date' => (new DateTimeImmutable('today'))->format('Y-m-d')], [[], [], []]);
    }

    public function store(Request $request): Response
    {
        $lines = $request->inputRows('items');
        try {
            $id = $this->orders->create($request->allInput(), $lines);
        } catch (ValidationException $e) {
            return $this->form($request->allInput(), $lines === [] ? [[]] : $lines, $e->errors);
        }
        $this->session->flash('success', 'Purchase Order berhasil dibuat sebagai Draft.');

        return Response::redirect('/purchase-orders/' . $id);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, User $user, array $params): Response
    {
        return $this->detail($this->findOr404($params), $user);
    }

    /**
     * @param array<string, string> $params
     */
    public function markOrdered(Request $request, User $user, array $params): Response
    {
        $id = Router::intParam($params, 'id');

        return $this->runAction($id, fn (): PurchaseOrder => $this->orders->markOrdered($id), 'PO %s ditandai Ordered (dikirim ke supplier).');
    }

    /**
     * @param array<string, string> $params
     */
    public function cancel(Request $request, User $user, array $params): Response
    {
        $id = Router::intParam($params, 'id');

        return $this->runAction($id, fn (): PurchaseOrder => $this->orders->cancel($id), 'PO %s dibatalkan.');
    }

    /**
     * @param array<string, string> $params
     */
    public function receive(Request $request, User $user, array $params): Response
    {
        $order = $this->findOr404($params);
        try {
            $updated = $this->orders->receive($order->id, $request->inputMap('receive'), $user);
        } catch (ValidationException $e) {
            return $this->detail($order, $user, $request->inputMap('receive'), $e->errors);
        } catch (BusinessRuleException $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/purchase-orders/' . $order->id);
        }
        $this->session->flash('success', sprintf('Penerimaan barang %s tersimpan. Status: %s.', $updated->orderNo, $updated->status->label()));

        return Response::redirect('/purchase-orders/' . $order->id);
    }

    /**
     * @param callable(): PurchaseOrder $action
     */
    private function runAction(int $id, callable $action, string $successMessage): Response
    {
        try {
            $order = $action();
            $this->session->flash('success', sprintf($successMessage, $order->orderNo));
        } catch (BusinessRuleException $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return Response::redirect('/purchase-orders/' . $id);
    }

    /**
     * @param array<array-key, string> $receiveOld
     * @param array<string, string> $errors
     */
    private function detail(PurchaseOrder $order, User $user, array $receiveOld = [], array $errors = []): Response
    {
        $isAdmin = $user->role === Role::Admin;

        return Response::html($this->view->render('purchase-orders/show', [
            'title' => $order->orderNo,
            'order' => $order,
            'receipts' => $this->orders->receipts($order),
            'canOrder' => $isAdmin && $order->status->canBeOrdered(),
            'canCancel' => $isAdmin && $order->status->canBeCancelled(),
            'canReceive' => $user->hasRole(Role::Admin, Role::WarehouseStaff) && $order->status->canReceive(),
            'receiveOld' => $receiveOld,
            'errors' => $errors,
        ]), $errors === [] ? 200 : 422);
    }

    /**
     * @param array<string, string> $old
     * @param list<array<string, string>> $lines
     * @param array<string, string> $errors
     */
    private function form(array $old, array $lines, array $errors = []): Response
    {
        return Response::html($this->view->render('purchase-orders/form', [
            'title' => 'Buat Purchase Order',
            'old' => $old,
            'lines' => $lines,
            'errors' => $errors,
            'options' => $this->orders->formOptions(),
        ]), $errors === [] ? 200 : 422);
    }

    /**
     * @param array<string, string> $params
     */
    private function findOr404(array $params): PurchaseOrder
    {
        return $this->orders->find(Router::intParam($params, 'id')) ?? throw HttpException::notFound();
    }
}
