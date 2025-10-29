<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\FeedbackService;
use App\Services\LoggerService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class FeedbackController
{
    public function __construct(
        private FeedbackService $feedback,
        private LoggerService $logger
    ) {
    }

    public function show(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            view('feedback/form', [
                'status' => 'invalid',
                'token' => $token,
                'feedback' => null,
                'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
            ]);
            return;
        }

        $details = $this->feedback->getByToken($token);
        if ($details === null) {
            view('feedback/form', [
                'status' => 'invalid',
                'token' => $token,
                'feedback' => null,
                'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
            ]);
            return;
        }

        $status = 'available';
        if (!empty($details['is_submitted'])) {
            $status = 'submitted';
        } elseif (!empty($details['is_expired'])) {
            $status = 'expired';
        }

        view('feedback/form', [
            'status' => $status,
            'feedback' => $details,
            'token' => $token,
            'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
        ]);
    }

    public function submit(string $token): void
    {
        $token = trim($token);
        $details = $this->feedback->getByToken($token);

        if ($details === null) {
            view('feedback/form', [
                'status' => 'invalid',
                'token' => $token,
                'feedback' => null,
                'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
            ]);
            return;
        }

        if (!empty($details['is_submitted'])) {
            view('feedback/form', [
                'status' => 'submitted',
                'feedback' => $details,
                'token' => $token,
                'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
            ]);
            return;
        }

        if (!empty($details['is_expired'])) {
            view('feedback/form', [
                'status' => 'expired',
                'feedback' => $details,
                'token' => $token,
                'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
            ]);
            return;
        }

        $ratingInput = $_POST['rating'] ?? null;
        $rating = null;
        if ($ratingInput !== null && $ratingInput !== '') {
            $rating = (int) $ratingInput;
        }
        $note = isset($_POST['note']) ? (string) $_POST['note'] : null;

        if ($rating === null) {
            view('feedback/form', [
                'status' => 'available',
                'feedback' => $details,
                'token' => $token,
                'errorMessage' => 'Selecione uma nota para concluir sua avaliação.',
                'submittedNote' => $note,
                'selectedRating' => $rating,
                'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
            ]);
            return;
        }

        try {
            $result = $this->feedback->submitFeedback($token, $rating, $note);
        } catch (InvalidArgumentException $exception) {
            view('feedback/form', [
                'status' => 'available',
                'feedback' => $details,
                'token' => $token,
                'errorMessage' => $exception->getMessage(),
                'submittedNote' => $note,
                'selectedRating' => $rating,
                'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
            ]);
            return;
        } catch (RuntimeException $exception) {
            view('feedback/form', [
                'status' => 'error',
                'feedback' => $details,
                'token' => $token,
                'errorMessage' => $exception->getMessage(),
                'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
            ]);
            return;
        } catch (Throwable $exception) {
            $this->logger->error('feedback.submit_failed', [
                'token' => $token,
                'error' => $exception->getMessage(),
            ]);

            view('feedback/form', [
                'status' => 'error',
                'feedback' => $details,
                'token' => $token,
                'errorMessage' => 'Não foi possível registrar sua avaliação no momento. Tente novamente em instantes.',
                'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
            ]);
            return;
        }

        view('feedback/form', [
            'status' => 'success',
            'feedback' => $result,
            'token' => $token,
            'pageTitle' => 'Avaliação do atendimento · WhatsAtende',
        ]);
    }
}
