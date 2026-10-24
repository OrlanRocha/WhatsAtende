<?php
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
$pageTitle = 'Cadastro · WhatsAtende';
include base_path('app/Views/partials/layout-auth-start.php');
?>
<div class="min-h-screen w-full flex items-center justify-center p-6 bg-slate-50 relative overflow-hidden font-['Inter',sans-serif]">
    <!-- Background Accents -->
    <div class="absolute inset-0 z-0 opacity-40 select-none pointer-events-none">
        <div class="absolute top-[-10%] right-[-10%] w-[40%] h-[40%] bg-indigo-100 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[30%] h-[30%] bg-blue-50 rounded-full blur-[100px]"></div>
    </div>

    <!-- Register Card -->
    <div class="relative z-10 w-full animate-in fade-in slide-in-from-bottom-4 duration-700" style="max-width: 600px; margin: 0 auto;">
        <div class="bg-white rounded-[2rem] shadow-[0_32px_64px_-16px_rgba(0,0,0,0.12)] border border-slate-100 p-8 lg:p-10">
            
            <!-- Branding & Header -->
            <div class="mb-8 text-center">
                <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-indigo-100 mx-auto mb-4" style="background-color: #4f46e5 !important;">
                    <i class="bi bi-person-plus-fill text-2xl"></i>
                </div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Crie sua conta</h1>
                <p class="text-[13px] font-medium text-slate-400 mt-1">Sua jornada omnichannel começa agora</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-3.5 rounded-xl bg-rose-50 border border-rose-100/50 text-rose-700 text-[10px] font-black uppercase tracking-widest">
                    <ul class="space-y-1">
                        <?php foreach ($errors as $message): ?>
                            <li class="flex items-center gap-2 px-2 py-0.5">
                                <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($message) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars(route_path('/register'), ENT_QUOTES) ?>" class="space-y-5" data-ajax data-success-redirect="<?= htmlspecialchars(route_path('/tickets'), ENT_QUOTES) ?>" data-success-message="Bem-vindo ao WhatsAtende!">
                
                <div class="space-y-1">
                    <label for="full_name" class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nome Completo</label>
                    <input type="text" id="full_name" name="full_name" required value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
                        class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-800 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white outline-none transition-all text-sm font-medium placeholder:text-slate-300" 
                        placeholder="Como quer ser chamado?">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1">
                        <label for="email" class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">E-mail Corporativo</label>
                        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                            class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-800 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white outline-none transition-all text-sm font-medium placeholder:text-slate-300" 
                            placeholder="nome@empresa.com">
                    </div>
                    <div class="space-y-1">
                        <label for="cpf" class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">CPF</label>
                        <input type="text" id="cpf" name="cpf" required value="<?= htmlspecialchars($old['cpf'] ?? '') ?>"
                            class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-800 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white outline-none transition-all text-sm font-medium placeholder:text-slate-300" 
                            placeholder="000.000.000-00">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1">
                        <label for="password" class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Senha</label>
                        <input type="password" id="password" name="password" minlength="8" required
                            class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-800 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white outline-none transition-all text-sm font-medium placeholder:text-slate-300" 
                            placeholder="••••••••">
                    </div>
                    <div class="space-y-1">
                        <label for="password_confirmation" class="block text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Confirmar Senha</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required
                            class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl text-slate-800 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white outline-none transition-all text-sm font-medium placeholder:text-slate-300" 
                            placeholder="••••••••">
                    </div>
                </div>

                <div style="margin-top: 32px;">
                    <button type="submit" 
                        style="background-color: #4f46e5 !important; color: white !important; padding: 22px 0 !important;"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-[1.25rem] font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-indigo-100 transition-all active:scale-[0.98] flex items-center justify-center gap-3 border-none ring-0">
                        FINALIZAR MEU CADASTRO
                        <i class="bi bi-rocket-takeoff-fill text-base"></i>
                    </button>
                </div>
            </form>

            <div class="mt-12 pt-8 text-center border-t border-slate-100">
                <p class="text-slate-400 text-[10px] font-black tracking-[0.1em] uppercase">
                    Já possui uma conta? 
                    <a href="<?= htmlspecialchars(route_path('/login'), ENT_QUOTES) ?>" class="text-indigo-600 hover:underline underline-offset-8 ml-2">Entrar agora</a>
                </p>
            </div>
        </div>

        <div class="mt-10 text-center">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                &copy; <?= date('Y') ?> WhatsAtende. Segurança de nível bancário.
            </p>
        </div>
    </div>
</div>
<?php include base_path('app/Views/partials/layout-auth-end.php'); ?>
