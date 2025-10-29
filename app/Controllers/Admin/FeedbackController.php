<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\FeedbackService;
use App\Services\GroupService;
use DateInterval;
use DateTimeImmutable;
use Throwable;

class FeedbackController
{
    public function __construct(
        private FeedbackService $feedback,
        private GroupService $groups
    ) {
    }

    public function index(): void
    {
        require_permission('feedback.view');

        $groups = $this->groups->listGroups();

        $fromParam = $_GET['from'] ?? null;
        $toParam = $_GET['to'] ?? null;
        $groupParam = $_GET['group'] ?? [];
        $ratingParam = $_GET['rating'] ?? null;

        try {
            $from = $fromParam ? new DateTimeImmutable((string) $fromParam) : (new DateTimeImmutable())->sub(new DateInterval('P30D'));
        } catch (Throwable) {
            $from = (new DateTimeImmutable())->sub(new DateInterval('P30D'));
        }

        try {
            $to = $toParam ? new DateTimeImmutable((string) $toParam) : new DateTimeImmutable();
        } catch (Throwable) {
            $to = new DateTimeImmutable();
        }

        $selectedGroups = [];
        if (is_array($groupParam)) {
            $selectedGroups = array_map('intval', $groupParam);
        } elseif ($groupParam !== null && $groupParam !== '') {
            $selectedGroups = [(int) $groupParam];
        }
        $selectedGroups = array_values(array_filter($selectedGroups, static fn (int $id): bool => $id > 0));

        $errorMessage = null;
        if ($from > $to) {
            $errorMessage = 'A data inicial não pode ser maior que a data final.';
        }

        $entries = [];
        if ($errorMessage === null) {
            $entries = $this->feedback->listFeedback($from, $to, $selectedGroups);
        }

        $ratingFilter = null;
        if ($ratingParam !== null && $ratingParam !== '') {
            $ratingValue = (int) $ratingParam;
            if ($ratingValue >= 1 && $ratingValue <= 5) {
                $ratingFilter = $ratingValue;
                $entries = array_values(array_filter($entries, static function (array $row) use ($ratingValue): bool {
                    return isset($row['rating']) && (int) $row['rating'] === $ratingValue;
                }));
            }
        }

        $total = count($entries);
        $average = null;
        if ($total > 0) {
            $sum = 0;
            $count = 0;
            foreach ($entries as $entry) {
                if (isset($entry['rating'])) {
                    $sum += (int) $entry['rating'];
                    $count++;
                }
            }
            if ($count > 0) {
                $average = round($sum / $count, 2);
            }
        }

        view('admin/feedback/index', [
            'entries' => $entries,
            'groups' => $groups,
            'filters' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'group' => $selectedGroups,
                'rating' => $ratingFilter,
            ],
            'summary' => [
                'total' => $total,
                'average' => $average,
            ],
            'errorMessage' => $errorMessage,
        ]);
    }
}
