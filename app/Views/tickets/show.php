<?php

declare(strict_types=1);

$templates = $templates ?? [];

view('atendimento', [
    'ticket' => $ticket,
    'templates' => $templates,
]);
