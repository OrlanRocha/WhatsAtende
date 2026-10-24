    </main>
</div>
</div>

<!-- Command Palette -->
<div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80 backdrop-blur-sm z-[200]" data-command-backdrop hidden></div>
<dialog class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-0 m-0 z-[201] overflow-hidden backdrop:bg-transparent" data-command-dialog hidden>
    <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 dark:border-slate-800">
        <i class="bi bi-search text-slate-400"></i>
        <input type="text" class="flex-1 bg-transparent border-0 focus:ring-0 text-sm font-medium text-slate-700 dark:text-slate-200 placeholder:text-slate-400 outline-none" placeholder="Buscar comando ou módulo..." data-command-input autofocus>
        <button type="button" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-[10px] text-slate-500 font-bold border border-slate-200 dark:border-slate-700 font-mono hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" data-command-close>ESC</button>
    </div>
    <div class="max-h-[60vh] overflow-y-auto p-2" data-command-results>
        <!-- Results injected here by JS -->
    </div>
</dialog>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3/dist/umd/simple-datatables.js" defer></script>
<script src="<?= htmlspecialchars(asset('js/app.js'), ENT_QUOTES) ?>" type="module"></script>
</body>
</html>
