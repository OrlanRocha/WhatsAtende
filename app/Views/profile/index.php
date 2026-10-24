<?php
$pageTitle = 'Meu Perfil';
$breadcrumbs = [['label' => 'Meu Perfil']];
view('partials/layout-start', ['pageTitle' => $pageTitle]);
view('partials/topbar', ['pageTitle' => $pageTitle, 'breadcrumbs' => $breadcrumbs]);
?>

<div class="max-w-4xl mx-auto space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <!-- Profile Card -->
    <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-2xl border border-white dark:border-slate-800 rounded-[2.5rem] shadow-2xl overflow-hidden group hover:shadow-primary/5 transition-all duration-500">
        <div class="p-8 sm:p-12">
            <div class="flex flex-col sm:flex-row items-center gap-8 mb-12">
                <div class="relative group/avatar">
                    <div class="w-32 h-32 rounded-[2.5rem] bg-gradient-to-tr from-primary to-blue-400 flex items-center justify-center text-white text-5xl font-black shadow-2xl shadow-primary/20 transition-transform duration-500 group-hover/avatar:rotate-6 group-hover/avatar:scale-105">
                        <?= mb_strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
                    </div>
                </div>
                <div class="text-center sm:text-left flex-1">
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight mb-2"><?= htmlspecialchars($user['full_name']) ?></h2>
                    <p class="text-slate-500 dark:text-slate-400 font-bold uppercase tracking-widest text-xs flex items-center justify-center sm:justify-start gap-2">
                        <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                        <?= htmlspecialchars(match($user['role'] ?? '') { 
                            'admin' => 'Administrador',
                            'agent' => 'Atendente',
                            'dev' => 'Desenvolvedor',
                            'supervisor' => 'Supervisor',
                            default => ucfirst($user['role'] ?? 'Membro')
                        }) ?>
                    </p>
                </div>
            </div>

            <form action="<?= route_path('/profile') ?>" method="POST" class="space-y-8">
                <?php if ($status): ?>
                    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-sm font-bold flex items-center gap-3">
                        <i class="bi bi-check-circle-fill"></i>
                        <?= htmlspecialchars((string)$status) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-sm font-bold flex items-center gap-3">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?= htmlspecialchars((string)$error) ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                    <!-- Name -->
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nome Completo</label>
                        <div class="relative group/input">
                            <i class="bi bi-person absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within/input:text-primary transition-colors"></i>
                            <input type="text" name="full_name" required value="<?= htmlspecialchars((string)$user['full_name']) ?>"
                                   class="w-full pl-12 pr-4 py-4 bg-slate-50 dark:bg-slate-950 border-2 border-transparent focus:border-primary/20 focus:bg-white dark:focus:bg-slate-900 rounded-2xl text-sm font-bold transition-all outline-none text-slate-700 dark:text-slate-200"
                                   placeholder="Seu nome completo">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">E-mail</label>
                        <div class="relative group/input">
                            <i class="bi bi-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within/input:text-primary transition-colors"></i>
                            <input type="email" name="email" required value="<?= htmlspecialchars((string)$user['email']) ?>"
                                   class="w-full pl-12 pr-4 py-4 bg-slate-50 dark:bg-slate-950 border-2 border-transparent focus:border-primary/20 focus:bg-white dark:focus:bg-slate-900 rounded-2xl text-sm font-bold transition-all outline-none text-slate-700 dark:text-slate-200"
                                   placeholder="seu@email.com">
                        </div>
                    </div>

                    <!-- CPF (Readonly) -->
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">CPF (Somente Leitura)</label>
                        <div class="relative group/input opacity-60">
                            <i class="bi bi-card-text absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" readonly value="<?= htmlspecialchars((string)$user['cpf']) ?>"
                                   class="w-full pl-12 pr-4 py-4 bg-slate-100 dark:bg-slate-800 border-2 border-transparent rounded-2xl text-sm font-bold outline-none text-slate-500 cursor-not-allowed">
                        </div>
                    </div>
                </div>

                <div class="pt-8 border-t border-slate-100 dark:border-slate-800/50">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider mb-6 flex items-center gap-3">
                        <i class="bi bi-shield-lock text-primary"></i>
                        Alterar Senha
                        <span class="text-[10px] font-bold text-slate-400 normal-case">(Deixe em branco para não alterar)</span>
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nova Senha</label>
                            <div class="relative group/input">
                                <i class="bi bi-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within/input:text-primary transition-colors"></i>
                                <input type="password" name="password" minlength="8"
                                       class="w-full pl-12 pr-4 py-4 bg-slate-50 dark:bg-slate-950 border-2 border-transparent focus:border-primary/20 focus:bg-white dark:focus:bg-slate-900 rounded-2xl text-sm font-bold transition-all outline-none text-slate-700 dark:text-slate-200"
                                       placeholder="Mínimo 8 caracteres">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Confirmar Nova Senha</label>
                            <div class="relative group/input">
                                <i class="bi bi-lock-fill absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within/input:text-primary transition-colors"></i>
                                <input type="password" name="password_confirmation"
                                       class="w-full pl-12 pr-4 py-4 bg-slate-50 dark:bg-slate-950 border-2 border-transparent focus:border-primary/20 focus:bg-white dark:focus:bg-slate-900 rounded-2xl text-sm font-bold transition-all outline-none text-slate-700 dark:text-slate-200"
                                       placeholder="Repita a nova senha">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-8 flex justify-end">
                    <button type="submit" 
                            class="px-12 py-4 bg-primary text-white rounded-2xl font-black text-sm shadow-2xl shadow-primary/20 hover:shadow-primary/40 hover:-translate-y-1 active:scale-95 transition-all duration-300 flex items-center gap-3">
                        <span>Salvar Alterações</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php view('partials/layout-end'); ?>
