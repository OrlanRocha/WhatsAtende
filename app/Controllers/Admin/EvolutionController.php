<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\EvolutionService;

class EvolutionController
{
    public function __construct(private EvolutionService $evolution)
    {
    }

    public function chats(): void
    {
        require_role('admin');

        $result = $this->evolution->fetchChatsOverview();
        $statusCode = (int) ($result['status'] ?? 500);

        $chats = $result['chats'] ?? [];
        $error = $result['error'] ?? null;

        if (($result['success'] ?? false)) {
            $statusCode = 200;
        }

        if (is_ajax()) {
            json_response([
                'status' => $statusCode,
                'chats' => $chats,
                'error' => $error,
                'fetched_at' => date('Y-m-d H:i:s'),
            ], $statusCode);
        }

        view('admin/evolution/chats', [
            'chats' => $chats,
            'error' => $error,
        ]);
    }
}
