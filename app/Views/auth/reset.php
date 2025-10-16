<?php
/** @var string $token */
/** @var string $email */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Definir nova senha · WhatsAtende</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3 text-center">Definir nova senha</h1>
                    <p class="text-muted small text-center">Redefinindo acesso para <strong><?= htmlspecialchars($email) ?></strong></p>
                    <form method="POST" action="/reset-password" class="vstack gap-3">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <div>
                            <label for="password" class="form-label">Nova senha</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" required autofocus>
                        </div>
                        <div>
                            <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Salvar nova senha</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="/login" class="small">Voltar ao login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
