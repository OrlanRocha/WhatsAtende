<?php

use App\Controllers\AuthController;
use App\Controllers\TicketController;
use App\Controllers\WebhookController;

return [
    ['GET', '/', [TicketController::class, 'index']],
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
