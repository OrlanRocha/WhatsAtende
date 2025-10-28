<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\GroupService;
use App\Services\ReportService;
use DateInterval;
use DateTimeImmutable;
use Throwable;

class ReportController
{
    public function __construct(
        private ReportService $reports,
        private GroupService $groups
    ) {
    }

    public function index(): void
    {
        require_role('admin', 'dev', 'supervisor');

        $groups = $this->groups->listGroups();

        $fromParam = $_GET['from'] ?? null;
        $toParam = $_GET['to'] ?? null;
        $groupParam = $_GET['group'] ?? null;

        $groupIds = [];
        if (is_array($groupParam)) {
            $groupIds = array_map('intval', $groupParam);
        } elseif ($groupParam !== null && $groupParam !== '') {
            $groupIds = [(int) $groupParam];
        }
        $groupIds = array_values(array_filter($groupIds, static fn (int $id): bool => $id > 0));

        try {
            $from = $fromParam ? new DateTimeImmutable((string) $fromParam) : (new DateTimeImmutable())->sub(new DateInterval('P7D'));
        } catch (Throwable) {
            $from = (new DateTimeImmutable())->sub(new DateInterval('P7D'));
        }

        try {
            $to = $toParam ? new DateTimeImmutable((string) $toParam) : new DateTimeImmutable();
        } catch (Throwable) {
            $to = new DateTimeImmutable();
        }

        $errorMessage = null;
        $diffDays = (int) $from->diff($to)->format('%a');
        if ($from > $to) {
            $errorMessage = 'A data inicial não pode ser maior que a data final.';
        } elseif ($diffDays > 365) {
            $errorMessage = 'Selecione um período de até 365 dias (12 meses) para gerar o relatório.';
        }

        if ($errorMessage !== null) {
            if (is_ajax()) {
                json_response(['message' => $errorMessage], 422);
                return;
            }

            $summary = [];
        } else {
            try {
                $summary = $this->reports->attendanceSummary($from, $to, $groupIds);
            } catch (Throwable $exception) {
                if (is_ajax()) {
                    json_response(['message' => 'Não foi possível carregar os relatórios.', 'error' => $exception->getMessage()], 500);
                    return;
                }

                $summary = [];
                $errorMessage = 'Não foi possível carregar os relatórios no momento. Tente novamente em instantes.';
            }
        }

        if (is_ajax()) {
            json_response([
                'summary' => $summary,
                'groups' => $groups,
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ]);
            return;
        }

        view('admin/reports/index', [
            'groups' => $groups,
            'summary' => $summary,
            'filters' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'group' => $groupIds,
            ],
            'errorMessage' => $errorMessage,
        ]);
    }

    public function export(): void
    {
        require_role('admin', 'dev', 'supervisor');

        $fromParam = $_GET['from'] ?? null;
        $toParam = $_GET['to'] ?? null;
        $groupParam = $_GET['group'] ?? null;

        try {
            $from = $fromParam ? new DateTimeImmutable((string) $fromParam) : (new DateTimeImmutable())->sub(new DateInterval('P7D'));
        } catch (Throwable) {
            $from = (new DateTimeImmutable())->sub(new DateInterval('P7D'));
        }
        try {
            $to = $toParam ? new DateTimeImmutable((string) $toParam) : new DateTimeImmutable();
        } catch (Throwable) {
            $to = new DateTimeImmutable();
        }

        $groups = [];
        if (is_array($groupParam)) {
            $groups = array_map('intval', $groupParam);
        } elseif ($groupParam !== null && $groupParam !== '') {
            $groups = [(int) $groupParam];
        }
        $groups = array_values(array_filter($groups, static fn (int $id): bool => $id > 0));

        $diffDays = (int) $from->diff($to)->format('%a');
        if ($from > $to) {
            http_response_code(422);
            echo 'A data inicial não pode ser maior que a data final.';
            return;
        }
        if ($diffDays > 365) {
            http_response_code(422);
            echo 'Selecione um período de até 365 dias (12 meses) para exportar o relatório.';
            return;
        }

        try {
            $rows = $this->reports->attendanceSummary($from, $to, $groups);
            $csv = $this->reports->summaryToCsv($rows);
        } catch (Throwable $exception) {
            http_response_code(500);
            echo 'Não foi possível gerar o relatório.';
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio-atendimentos.csv"');
        echo $csv;
    }
}
