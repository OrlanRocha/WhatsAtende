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
$ratingLabels = [
    5 => 'Excelente — superou minhas expectativas',
    4 => 'Muito bom — atendeu ao que eu precisava',
    3 => 'Bom — resolveu, mas pode melhorar',
    2 => 'Regular — tive algumas dificuldades',
    1 => 'Precisa melhorar — não fiquei satisfeito',
];
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
                <div class="feedback-header__title">
                    <h2>Como avalia o atendimento?</h2>
                    <p>Reserve um instante para registrar sua percepção. Levamos cada avaliação em conta para evoluir a experiência.</p>
                </div>
                <div class="feedback-highlights" role="list">
                    <div class="feedback-highlights__item" role="listitem">
                        <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                        <span>Leva menos de 30 segundos</span>
                    </div>
                    <div class="feedback-highlights__item" role="listitem">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span>Resposta segura e confidencial</span>
                    </div>
                    <div class="feedback-highlights__item" role="listitem">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        <span>Link válido por 24&nbsp;horas</span>
                    </div>
                </div>
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
                                $ariaLabel = $star . ' estrela' . ($star > 1 ? 's' : '');
                            ?>
                            <input type="radio" name="rating" value="<?= $star ?>" id="<?= $inputId ?>" <?= $isChecked ? 'checked' : '' ?> required>
                            <label for="<?= $inputId ?>" aria-label="<?= $ariaLabel ?>" data-rating-text="<?= htmlspecialchars($ratingLabels[$star]) ?>">
                                <i class="bi bi-star-fill" aria-hidden="true"></i>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <p class="feedback-rating-hint" data-default-text="Escolha uma nota para liberar o comentário.">
                        <?= htmlspecialchars($selectedRating ? $ratingLabels[(int) $selectedRating] : 'Escolha uma nota para liberar o comentário.') ?>
                    </p>
                    <dl class="feedback-scale" aria-hidden="true">
                        <?php foreach ($ratingLabels as $score => $label): ?>
                            <div class="feedback-scale__item">
                                <dt><?= $score ?> estrela<?= $score > 1 ? 's' : '' ?></dt>
                                <dd><?= htmlspecialchars($label) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </fieldset>
                <div class="mb-4">
                    <label class="form-label" for="feedback-note">Conte-nos mais (opcional)</label>
                    <textarea id="feedback-note" name="note" class="form-control" rows="4" maxlength="1000" placeholder="Compartilhe como podemos melhorar."><?= htmlspecialchars((string) $submittedNote) ?></textarea>
                    <small class="text-muted">Sua resposta é confidencial e ajuda a aperfeiçoar nosso atendimento.</small>
                </div>
                <div class="feedback-actions">
                    <button type="submit" class="btn btn-primary btn-lg w-100">Enviar avaliação</button>
                    <p class="feedback-privacy">
                        Ao enviar, você concorda com o uso das informações para aprimorar nossos processos internos.
                    </p>
                </div>
            </form>
            <section class="feedback-extra" aria-label="Como usamos seu feedback">
                <h3>O que fazemos com a sua resposta?</h3>
                <ul>
                    <li><i class="bi bi-graph-up" aria-hidden="true"></i> Monitoramos indicadores de satisfação diariamente.</li>
                    <li><i class="bi bi-people" aria-hidden="true"></i> Compartilhamos boas práticas com toda a equipe.</li>
                    <li><i class="bi bi-tools" aria-hidden="true"></i> Priorizamos melhorias a partir das sugestões recebidas.</li>
                </ul>
            </section>
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
<script type="module">
    const ratingControls = document.querySelectorAll('.rating-control input');
    const hint = document.querySelector('.feedback-rating-hint');

    if (ratingControls.length && hint) {
        const defaultText = hint.dataset.defaultText ?? hint.textContent ?? '';
        const updateHint = (input) => {
            if (!input) {
                hint.textContent = defaultText;
                return;
            }
            const label = input.nextElementSibling;
            const labelText = label?.dataset.ratingText ?? defaultText;
            hint.textContent = labelText;
        };

        ratingControls.forEach((input) => {
            input.addEventListener('change', () => updateHint(input));
            input.addEventListener('focus', () => updateHint(input));
        });

        const checked = document.querySelector('.rating-control input:checked');
        updateHint(checked ?? null);
    }
</script>
<?php include base_path('app/Views/partials/layout-auth-end.php'); ?>
