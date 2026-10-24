<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\HealthService;

class HealthController
{
    public function __construct(private HealthService $health)
    {
    }

    public function snapshot(): void
    {
        require_auth();

        $snapshot = $this->health->snapshot();
        json_response($snapshot);
    }
}
