<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Entity\User;
use App\Service\AuthorizationException;
use App\Service\NotFoundException;
use App\Service\ReportService;
use App\Service\ValidationException;

final class ReportController
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly View $view,
    ) {
    }

    public function index(Request $request, User $user): Response
    {
        $errors = [];
        try {
            $range = $this->reports->range($request->allQuery());
        } catch (ValidationException $e) {
            $errors = $e->errors;
            $range = $this->reports->range([]);
        }

        return Response::html($this->view->render('reports/index', [
            'title' => 'Laporan',
            'range' => $range,
            'old' => ['from' => $request->query('from', $range->fromDate()), 'to' => $request->query('to', $range->toDate())],
            'errors' => $errors,
            'available' => $this->reports->availableReports($user),
            'preview' => $this->reports->preview($user, $range),
        ]), $errors === [] ? 200 : 422);
    }

    /**
     * @param array<string, string> $params
     */
    public function export(Request $request, User $user, array $params): Response
    {
        try {
            $range = $this->reports->range($request->allQuery());
            $csv = $this->reports->export($params['type'], $user, $range);
        } catch (ValidationException $e) {
            throw new HttpException(422, implode(' ', $e->errors));
        } catch (AuthorizationException $e) {
            throw new HttpException(403, $e->getMessage());
        } catch (NotFoundException) {
            throw HttpException::notFound();
        }

        return Response::csv($csv->filename, $csv->rows);
    }
}
