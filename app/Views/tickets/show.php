<?php

declare(strict_types=1);

$templates = $templates ?? [];

view('atendimento', [
    'ticket' => $ticket,
    'templates' => $templates,
    'supportGroups' => $supportGroups,
    'userGroups' => $userGroups,
    'seriousnessOptions' => $seriousnessOptions,
    'agents' => $agents,
]);
