<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Entity\User;
use App\Repository\OrderSearchCriteria;
use App\Service\BusinessRuleException;
use App\Service\StockTransferService;
use App\Service\ValidationException;

/**
 * Transfer stok antar-gudang (K-08). Akses Admin & Warehouse Staff diatur router.
 */
final class StockTransferController
{
    public function __construct(
        private readonly StockTransferService $transfers,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $criteria = OrderSearchCriteria::fromQuery($request->allQuery(), []);

        return Response::html($this->view->render('stock-transfers/index', [
            'title' => 'Transfer Stok',
            'result' => $this->transfers->search($criteria),
            'criteria' => $criteria,
        ]));
    }

    public function create(): Response
    {
        return $this->form([], [[], [], []]);
    }

    public function store(Request $request, User $user): Response
    {
        $lines = $request->inputRows('items');
        try {
            $id = $this->transfers->create($request->allInput(), $lines, $user);
        } catch (ValidationException $e) {
            return $this->form($request->allInput(), $lines === [] ? [[]] : $lines, $e->errors);
        } catch (BusinessRuleException $e) {
            // Stok gudang asal tidak cukup: seluruh transfer dibatalkan, input dipertahankan.
            return $this->form($request->allInput(), $lines === [] ? [[]] : $lines, ['items' => $e->getMessage()]);
        }
        $this->session->flash('success', 'Transfer stok berhasil: stok gudang asal berkurang dan gudang tujuan bertambah.');

        return Response::redirect('/stock-transfers/' . $id);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, User $user, array $params): Response
    {
        $transfer = $this->transfers->find(Router::intParam($params, 'id')) ?? throw HttpException::notFound();

        return Response::html($this->view->render('stock-transfers/show', [
            'title' => $transfer->transferNo,
            'transfer' => $transfer,
            'movements' => $this->transfers->movements($transfer),
        ]));
    }

    /**
     * @param array<string, string> $old
     * @param list<array<string, string>> $lines
     * @param array<string, string> $errors
     */
    private function form(array $old, array $lines, array $errors = []): Response
    {
        return Response::html($this->view->render('stock-transfers/form', [
            'title' => 'Transfer Stok Baru',
            'old' => $old,
            'lines' => $lines,
            'errors' => $errors,
            'options' => $this->transfers->formOptions(),
        ]), $errors === [] ? 200 : 422);
    }
}
