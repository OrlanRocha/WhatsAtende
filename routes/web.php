<?php

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\EvolutionController as AdminEvolutionController;
use App\Controllers\Admin\LogController as AdminLogController;
use App\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Controllers\Admin\TicketController as AdminTicketController;
use App\Controllers\Admin\UserController as AdminUserController;
use App\Controllers\Admin\WebhookController as AdminWebhookController;
use App\Controllers\Api\EvolutionController as ApiEvolutionController;
use App\Controllers\Api\LogStreamController;
use App\Controllers\AuthController;
use App\Controllers\TicketController;
use App\Controllers\WebhookController;
use App\Controllers\HealthController;

return [
    ['GET', '/', [TicketController::class, 'index']],
    ['GET', '/admin', [AdminDashboardController::class, 'index']],
    ['GET', '/admin/tickets', [AdminTicketController::class, 'index']],
    ['GET', '/admin/evolution/chats', [AdminEvolutionController::class, 'chats']],
    ['GET', '/admin/users', [AdminUserController::class, 'index']],
    ['GET', '/admin/users/create', [AdminUserController::class, 'create']],
    ['POST', '/admin/users', [AdminUserController::class, 'store']],
    ['GET', '/admin/users/{id}/edit', [AdminUserController::class, 'edit']],
    ['POST', '/admin/users/{id}', [AdminUserController::class, 'update']],
    ['POST', '/admin/users/{id}/delete', [AdminUserController::class, 'destroy']],
    ['GET', '/admin/templates', [AdminTemplateController::class, 'index']],
    ['POST', '/admin/templates', [AdminTemplateController::class, 'store']],
    ['POST', '/admin/templates/{id}', [AdminTemplateController::class, 'update']],
    ['POST', '/admin/templates/{id}/delete', [AdminTemplateController::class, 'destroy']],
    ['GET', '/admin/logs', [AdminLogController::class, 'index']],
    ['GET', '/admin/webhook', [AdminWebhookController::class, 'index']],
    ['POST', '/admin/webhook', [AdminWebhookController::class, 'update']],
    ['POST', '/admin/webhook/test', [AdminWebhookController::class, 'test']],
    ['GET', '/api/evolution', [ApiEvolutionController::class, 'handle']],
    ['POST', '/api/evolution', [ApiEvolutionController::class, 'handle']],
    ['GET', '/api/evolution/chats', [ApiEvolutionController::class, 'chats']],
    ['GET', '/api/evolution/messages', [ApiEvolutionController::class, 'messages']],
    ['GET', '/api/evolution/profile', [ApiEvolutionController::class, 'profile']],
    ['GET', '/api/evolution/media', [ApiEvolutionController::class, 'media']],
    ['POST', '/api/evolution/messages', [ApiEvolutionController::class, 'send']],
    ['POST', '/api/evolution/read', [ApiEvolutionController::class, 'markRead']],
    ['GET', '/api/logs/live', [LogStreamController::class, 'live']],
    ['GET', '/login', [AuthController::class, 'showLoginForm']],
    ['POST', '/login', [AuthController::class, 'login']],
    ['POST', '/logout', [AuthController::class, 'logout']],
    ['GET', '/register', [AuthController::class, 'showRegisterForm']],
    ['POST', '/register', [AuthController::class, 'register']],
    ['GET', '/forgot-password', [AuthController::class, 'showForgotPasswordForm']],
    ['POST', '/forgot-password', [AuthController::class, 'sendResetLink']],
    ['GET', '/reset-password/{token}', [AuthController::class, 'showResetPasswordForm']],
    ['POST', '/reset-password', [AuthController::class, 'resetPassword']],
    ['GET', '/tickets', [TicketController::class, 'index']],
    ['GET', '/tickets/overview', [TicketController::class, 'overview']],
    ['GET', '/tickets/today', [TicketController::class, 'today']],
    ['GET', '/tickets/{id}', [TicketController::class, 'show']],
    ['POST', '/tickets/{id}/assign', [TicketController::class, 'assign']],
    ['POST', '/tickets/native/start', [TicketController::class, 'startNativeConversation']],
    ['POST', '/tickets/{id}/messages', [TicketController::class, 'storeMessage']],
    ['GET', '/tickets/{id}/messages', [TicketController::class, 'messages']],
    ['POST', '/tickets/{id}/resolve', [TicketController::class, 'resolve']],
    ['GET', '/health', [HealthController::class, 'snapshot']],
    ['POST', '/api/webhook', [WebhookController::class, 'handle']],
];
