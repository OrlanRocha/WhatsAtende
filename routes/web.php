<?php

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\LogController as AdminLogController;
use App\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Controllers\Admin\TicketController as AdminTicketController;
use App\Controllers\Admin\UserController as AdminUserController;
use App\Controllers\Admin\WebhookController as AdminWebhookController;
use App\Controllers\AuthController;
use App\Controllers\TicketController;
use App\Controllers\WebhookController;

return [
    ['GET', '/', [TicketController::class, 'index']],
    ['GET', '/admin', [AdminDashboardController::class, 'index']],
    ['GET', '/admin/tickets', [AdminTicketController::class, 'index']],
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
    ['GET', '/tickets/{id}', [TicketController::class, 'show']],
    ['POST', '/tickets/{id}/assign', [TicketController::class, 'assign']],
    ['POST', '/tickets/{id}/messages', [TicketController::class, 'storeMessage']],
    ['GET', '/tickets/{id}/messages', [TicketController::class, 'messages']],
    ['POST', '/tickets/{id}/resolve', [TicketController::class, 'resolve']],
    ['POST', '/api/webhook', [WebhookController::class, 'handle']],
];
