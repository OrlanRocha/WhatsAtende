<?php

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\EvolutionController as AdminEvolutionController;
use App\Controllers\Admin\LogController as AdminLogController;
use App\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Controllers\Admin\TicketController as AdminTicketController;
use App\Controllers\Admin\UserController as AdminUserController;
use App\Controllers\Admin\WebhookController as AdminWebhookController;
use App\Controllers\Admin\GroupController as AdminGroupController;
use App\Controllers\Admin\ReportController as AdminReportController;
use App\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Controllers\Api\EvolutionController as ApiEvolutionController;
use App\Controllers\Admin\IntegrationController as AdminIntegrationController;
use App\Controllers\Api\LogStreamController;
use App\Controllers\AuthController;
use App\Controllers\TicketController;
use App\Controllers\WebhookController;
use App\Controllers\HealthController;
use App\Controllers\FeedbackController;
use App\Controllers\ProfileController;
use App\Controllers\NotificationController;

use App\Controllers\Api\TicketController as ApiTicketController;

return [
    ['GET', '/', [TicketController::class, 'index']],
    ['GET', '/admin', [AdminDashboardController::class, 'index']],
    ['GET', '/admin/tickets', [AdminTicketController::class, 'index']],
    ['GET', '/admin/evolution/chats', [AdminEvolutionController::class, 'chats']],
    ['GET', '/admin/users', [AdminUserController::class, 'index']],
    ['GET', '/admin/users/create', [AdminUserController::class, 'create']],
    ['POST', '/admin/users', [AdminUserController::class, 'store']],
    ['GET', '/admin/users/{userId}/edit', [AdminUserController::class, 'edit']],
    ['POST', '/admin/users/{userId}', [AdminUserController::class, 'update']],
    ['POST', '/admin/users/{userId}/delete', [AdminUserController::class, 'destroy']],
    ['GET', '/admin/templates', [AdminTemplateController::class, 'index']],
    ['POST', '/admin/templates', [AdminTemplateController::class, 'store']],
    ['POST', '/admin/templates/{templateId}', [AdminTemplateController::class, 'update']],
    ['POST', '/admin/templates/{templateId}/delete', [AdminTemplateController::class, 'destroy']],
    ['GET', '/admin/groups', [AdminGroupController::class, 'index']],
    ['POST', '/admin/groups', [AdminGroupController::class, 'store']],
    ['POST', '/admin/groups/{id}', [AdminGroupController::class, 'update']],
    ['POST', '/admin/groups/{id}/delete', [AdminGroupController::class, 'destroy']],
    ['POST', '/admin/groups/{groupId}/targets', [AdminGroupController::class, 'updateTargets']],
    ['GET', '/admin/settings', [App\Controllers\Admin\SettingController::class, 'index']],
    ['POST', '/admin/settings', [App\Controllers\Admin\SettingController::class, 'store']],
    ['GET', '/admin/reports', [AdminReportController::class, 'index']],
    ['GET', '/admin/reports/export', [AdminReportController::class, 'export']],
    ['GET', '/admin/avaliacoes', [AdminFeedbackController::class, 'index']],
    ['GET', '/admin/logs', [AdminLogController::class, 'index']],
    ['GET', '/admin/webhook', [AdminWebhookController::class, 'index']],
    ['POST', '/admin/webhook', [AdminWebhookController::class, 'update']],
    ['POST', '/admin/webhook/test', [AdminWebhookController::class, 'test']],
    ['GET', '/admin/integrations', [AdminIntegrationController::class, 'index']],
    ['POST', '/admin/integrations', [AdminIntegrationController::class, 'store']],
    ['POST', '/admin/integrations/{id}', [AdminIntegrationController::class, 'update']],
    ['POST', '/admin/integrations/{id}/delete', [AdminIntegrationController::class, 'destroy']],
    ['POST', '/admin/integrations/{id}/default', [AdminIntegrationController::class, 'makeDefault']],
    ['GET', '/admin/integrations/{id}/connect', [AdminIntegrationController::class, 'connect']],
    ['POST', '/admin/integrations/{id}/restart', [AdminIntegrationController::class, 'restart']],
    ['POST', '/admin/integrations/{id}/logout', [AdminIntegrationController::class, 'logout']],
    ['GET', '/admin/integrations/{id}/status', [AdminIntegrationController::class, 'status']],
    ['POST', '/admin/integrations/{id}/sync', [AdminIntegrationController::class, 'sync']],
    ['GET', '/api/evolution', [ApiEvolutionController::class, 'handle']],
    ['POST', '/api/evolution', [ApiEvolutionController::class, 'handle']],
    ['GET', '/api/evolution/chats', [ApiEvolutionController::class, 'chats']],
    ['GET', '/api/evolution/messages', [ApiEvolutionController::class, 'messages']],
    ['GET', '/api/evolution/profile', [ApiEvolutionController::class, 'profile']],
    ['GET', '/api/evolution/media', [ApiEvolutionController::class, 'media']],
    ['POST', '/api/evolution/messages', [ApiEvolutionController::class, 'send']],
    ['POST', '/api/evolution/read', [ApiEvolutionController::class, 'markRead']],
    ['GET', '/api/tickets', [ApiTicketController::class, 'index']],
    ['GET', '/api/tickets/open', [ApiTicketController::class, 'getOpenQueue']],
    ['GET', '/api/tickets/{id}', [ApiTicketController::class, 'show']],
    ['POST', '/api/tickets', [ApiTicketController::class, 'store']],
    ['PUT', '/api/tickets/{id}/status', [ApiTicketController::class, 'updateStatus']],
    ['POST', '/api/tickets/{id}/assign', [ApiTicketController::class, 'assign']],
    ['POST', '/api/tickets/{id}/messages', [ApiTicketController::class, 'sendMessage']],
    ['GET', '/api/logs/live', [LogStreamController::class, 'live']],
    ['GET', '/api/notifications/unread', [NotificationController::class, 'unread']],
    ['POST', '/api/notifications/read-all', [NotificationController::class, 'markAllRead']],
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
    ['GET', '/tickets/mine', [TicketController::class, 'mine']],
    ['GET', '/tickets/overview', [TicketController::class, 'overview']],
    ['GET', '/tickets/today', [TicketController::class, 'today']],
    ['GET', '/tickets/{ticketId}', [TicketController::class, 'show']],
    ['POST', '/tickets/{ticketId}/assign', [TicketController::class, 'assign']],
    ['POST', '/tickets/{ticketId}/meta', [TicketController::class, 'updateMeta']],
    ['POST', '/tickets/native/start', [TicketController::class, 'startNativeConversation']],
    ['POST', '/tickets/{ticketId}/messages', [TicketController::class, 'storeMessage']],
    ['GET', '/tickets/{ticketId}/messages', [TicketController::class, 'messages']],
    ['POST', '/tickets/{ticketId}/status', [TicketController::class, 'updateStatus']],
    ['POST', '/tickets/{ticketId}/resolve', [TicketController::class, 'resolve']],
    ['GET', '/avaliacao/{token}', [FeedbackController::class, 'show']],
    ['POST', '/avaliacao/{token}', [FeedbackController::class, 'submit']],
    ['GET', '/profile', [ProfileController::class, 'index']],
    ['POST', '/profile', [ProfileController::class, 'update']],
    ['GET', '/health', [HealthController::class, 'snapshot']],
    ['POST', '/api/webhook', [WebhookController::class, 'handle']],
];
