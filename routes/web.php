<?php

use App\Controllers\TicketController;
use App\Controllers\WebhookController;

return [
    ['GET', '/tickets', [TicketController::class, 'index']],
    ['GET', '/tickets/{id}', [TicketController::class, 'show']],
    ['POST', '/tickets/{id}/assign', [TicketController::class, 'assign']],
    ['POST', '/tickets/{id}/messages', [TicketController::class, 'storeMessage']],
    ['GET', '/tickets/{id}/messages', [TicketController::class, 'messages']],
    ['POST', '/tickets/{id}/resolve', [TicketController::class, 'resolve']],
    ['POST', '/api/webhook', [WebhookController::class, 'handle']],
];
