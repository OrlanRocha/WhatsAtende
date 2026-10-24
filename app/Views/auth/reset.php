<?php
/** @var string $token */
/** @var string $email */
/** @var string|null $status */
/** @var string|null $error */
$pageTitle = 'Definir nova senha · WhatsAtende';
include base_path('app/Views/partials/layout-auth-start.php');
?>
<div class="min-h-screen w-full flex items-center justify-center p-6 bg-slate-50 relative overflow-hidden font-['Inter',sans-serif]">
    <!-- Background Accents -->
    <div class="absolute inset-0 z-0 opacity-40 select-none pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-indigo-100 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[30%] h-[30%] bg-blue-50 rounded-full blur-[100px]"></div>
    </div>
    <!-- Reset Password Card -->
    <div class="relative z-10 w-full animate-in fade-in slide-in-from-bottom-4 duration-700" style="max-width: 400px; margin: 0 auto;">
        <div class="bg-white rounded-[2rem] shadow-[0_32px_64px_-16px_rgba(0,0,0,0.12)] border border-slate-100 p-8 lg:p-10">
            
            <!-- Branding & Header -->
            <div class="mb-8 text-center">
                <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-indigo-100 mx-auto mb-4" style="background-color: #4f46e5 !important;">
                    <i class="bi bi-shield-check-fill text-2xl"></i>
                </div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Nova Senha</h1>
                <p class="text-[13px] font-medium text-slate-400 mt-1">Defina sua nova credencial de acesso</p>
                <div class="mt-3 px-3 py-1 bg-indigo-50 rounded-full inline-block">
                    <span class="text-[9px] font-black text-indigo-600 uppercase tracking-widest"><?= htmlspecialchars($email) ?></span>
                </div>
            </div>

            <div class="alert-stack space-y-4 mb-6">
                <?php if (!empty($status)): ?>
                    <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-100/50 text-emerald-700 text-xs font-bold flex items-center gap-3">
                        <i class="bi bi-check-circle-fill"></i>
                        <?= htmlspecialchars($status) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-100/50 text-rose-700 text-xs font-bold flex items-center gap-3">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="<?= htmlspecialchars(route_path('/reset-password'), ENT_QUOTES) ?>" class="space-y-5" data-ajax data-success-message="Senha redefinida com sucesso." data-success-redirect="<?= htmlspecialchars(route_path('/login'), ENT_QUOTES) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="space-y-1">
                    <label for="password" class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nova Senha</label>
                    <input type="password" id="password" name="password" 
                        class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-800 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white outline-none transition-all text-sm font-medium placeholder:text-slate-300" 
                        minlength="8" required autocomplete="new-password" autofocus placeholder="••••••••">
                </div>

                <div class="space-y-1">
                    <label for="password_confirmation" class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Confirmar Senha</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" 
                        class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-800 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white outline-none transition-all text-sm font-medium placeholder:text-slate-300" 
                        minlength="8" required autocomplete="new-password" placeholder="••••••••">
                </div>

                <div style="margin-top: 32px;">
                    <button type="submit" 
                        style="background-color: #4f46e5 !important; color: white !important; padding: 22px 0 !important;"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-[1.25rem] font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-indigo-100 transition-all active:scale-[0.98] flex items-center justify-center gap-3 border-none ring-0">
                        ATUALIZAR SENHA
                        <i class="bi bi-arrow-repeat text-lg"></i>
                    </button>
                </div>
            </form>

            <div class="mt-8 text-center border-t border-slate-100 pt-6">
                <a href="<?= htmlspecialchars(route_path('/login'), ENT_QUOTES) ?>" class="text-[10px] font-black text-slate-400 hover:text-indigo-600 uppercase tracking-widest transition-colors">Cancelar e voltar</a>
            </div>
        </div>

        <div class="mt-10 text-center">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                &copy; <?= date('Y') ?> WhatsAtende. Proteção avançada.
            </p>
        </div>
    </div>
</div>
<?php include base_path('app/Views/partials/layout-auth-end.php'); ?>
