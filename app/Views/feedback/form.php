<?php
/** @var array<string, mixed>|null $feedback */
/** @var string $status */
/** @var string|null $errorMessage */
/** @var string|null $submittedNote */
/** @var int|null $selectedRating */
/** @var string|null $token */

$feedback = $feedback ?? null;
$status = $status ?? 'invalid';
$errorMessage = $errorMessage ?? null;
$submittedNote = $submittedNote ?? ($feedback['note'] ?? '');
$selectedRating = $selectedRating ?? ($feedback['rating'] ?? null);
$token = $token ?? ($feedback['token'] ?? '');
$pageTitle = $pageTitle ?? 'Avaliação do atendimento · WhatsAtende';

include base_path('app/Views/partials/layout-auth-start.php');
?>
<div class="auth-wrapper feedback-shell">
    <section class="auth-showcase">
        <span class="auth-badge auth-badge--info">
            <i class="bi bi-stars" aria-hidden="true"></i>
            Avaliação
        </span>
        <h1 class="auth-title">Sua opinião importa</h1>
        <p class="auth-subtitle">Conte para a nossa equipe como foi a experiência de atendimento. Sua resposta ajuda a melhorar o serviço.</p>
        <ul class="auth-feature-list">
            <li><i class="bi bi-check-circle" aria-hidden="true"></i> Nota de 1 a 5 estrelas</li>
            <li><i class="bi bi-check-circle" aria-hidden="true"></i> Comentário opcional</li>
            <li><i class="bi bi-check-circle" aria-hidden="true"></i> Link válido por 24 horas</li>
        </ul>
    </section>
    <section class="auth-card feedback-card">
        <?php if ($status === 'available'): ?>
            <header class="feedback-header">
                <h2>Como avalia o atendimento?</h2>
                <p>Selecione uma nota e, se desejar, deixe um comentário. Obrigado por dedicar alguns segundos!</p>
            </header>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
            <form method="POST" action="<?= htmlspecialchars(route_path('/avaliacao/' . $token), ENT_QUOTES) ?>" class="feedback-form" novalidate>
                <fieldset class="rating-fieldset">
                    <legend>Nota do atendimento</legend>
                    <div class="rating-control" role="radiogroup" aria-label="Avaliação em estrelas">
                        <?php for ($star = 5; $star >= 1; $star--): ?>
                            <?php
                                $inputId = 'rating-' . $star;
                                $isChecked = (int) $selectedRating === $star;
                            ?>
                            <input type="radio" name="rating" value="<?= $star ?>" id="<?= $inputId ?>" <?= $isChecked ? 'checked' : '' ?> required>
                            <label for="<?= $inputId ?>" aria-label="<?= $star ?> estrela<?= $star > 1 ? 's' : '' ?>">
                                <i class="bi bi-star-fill" aria-hidden="true"></i>
                            </label>
                        <?php endfor; ?>
                    </div>
                </fieldset>
                <div class="mb-4">
                    <label class="form-label" for="feedback-note">Conte-nos mais (opcional)</label>
                    <textarea id="feedback-note" name="note" class="form-control" rows="4" maxlength="1000" placeholder="Compartilhe como podemos melhorar."><?= htmlspecialchars((string) $submittedNote) ?></textarea>
                    <small class="text-muted">Sua resposta é confidencial e ajuda a aperfeiçoar nosso atendimento.</small>
                </div>
                <button type="submit" class="btn btn-primary w-100">Enviar avaliação</button>
            </form>
        <?php elseif ($status === 'success'): ?>
            <div class="feedback-state feedback-state--success">
                <i class="bi bi-emoji-smile" aria-hidden="true"></i>
                <h2>Obrigado pelo retorno!</h2>
                <p>Sua avaliação foi registrada com sucesso. Nossa equipe agradece o tempo dedicado e usará o feedback para evoluir o atendimento.</p>
                <?php if (!empty($feedback['rating'])): ?>
                    <div class="feedback-rating" aria-label="Nota enviada: <?= (int) $feedback['rating'] ?> estrelas">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi <?= $i <= (int) $feedback['rating'] ? 'bi-star-fill' : 'bi-star' ?>" aria-hidden="true"></i>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($feedback['note'])): ?>
                    <blockquote class="feedback-note">
                        <?= nl2br(htmlspecialchars((string) $feedback['note'])) ?>
                    </blockquote>
                <?php endif; ?>
            </div>
        <?php elseif ($status === 'submitted'): ?>
            <div class="feedback-state feedback-state--info">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <h2>Avaliação já recebida</h2>
                <p>Registramos sua avaliação anteriormente. Caso precise atualizar o feedback, entre em contato com a nossa equipe.</p>
            </div>
        <?php elseif ($status === 'expired'): ?>
            <div class="feedback-state feedback-state--warning">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <h2>Link expirado</h2>
                <p>O período de 24 horas para avaliação terminou. Caso queira deixar um novo feedback, peça um novo link ao atendente.</p>
            </div>
        <?php elseif ($status === 'error'): ?>
            <div class="feedback-state feedback-state--error">
                <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                <h2>Não foi possível registrar a avaliação</h2>
                <p><?= htmlspecialchars($errorMessage ?? 'Ocorreu um erro inesperado ao processar sua avaliação.') ?></p>
            </div>
        <?php else: ?>
            <div class="feedback-state feedback-state--error">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <h2>Link inválido</h2>
                <p>Não encontramos a avaliação solicitada. Verifique se o link está correto ou solicite um novo convite.</p>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php include base_path('app/Views/partials/layout-auth-end.php'); ?>
