<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\GroupService;
use InvalidArgumentException;
use Throwable;

class GroupController
{
    public function __construct(private GroupService $groups)
    {
    }

    public function index(): void
    {
        require_role('admin', 'dev');
        $groups = $this->groups->listGroups();
        json_response(['groups' => $groups]);
    }

    public function updateTargets(int $groupId): void
    {
        $user = require_role('admin', 'dev');
        $body = file_get_contents('php://input') ?: '';
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            $payload = $_POST ?? [];
        }
        try {
            $tma = isset($payload['target_tma']) ? (int) $payload['target_tma'] : null;
            $tme = isset($payload['target_tme']) ? (int) $payload['target_tme'] : null;

            if ($tma !== null && $tma < 0) {
                throw new InvalidArgumentException('TMA deve ser positivo.');
            }
            if ($tme !== null && $tme < 0) {
                throw new InvalidArgumentException('TME deve ser positivo.');
            }

            if (!$this->groups->groupExists($groupId)) {
                throw new InvalidArgumentException('Grupo não encontrado.');
            }

            $this->groups->updateTargets($groupId, $tma, $tme, (int) ($user->id ?? 0));
            json_response([
                'message' => 'Metas atualizadas com sucesso.',
                'targets' => [
                    'target_tma' => $tma,
                    'target_tme' => $tme,
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            json_response(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            json_response(['message' => 'Não foi possível atualizar as metas.', 'error' => $exception->getMessage()], 500);
        }
    }
}
