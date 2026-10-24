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
        
        if (is_ajax()) {
            json_response(['groups' => $groups]);
            return;
        }

        view('admin/groups/index', [
            'groups' => $groups,
            'pageTitle' => 'Gestão de Grupos · WhatsAtende',
            'breadcrumbs' => [
                ['label' => 'Administração'],
                ['label' => 'Grupos']
            ]
        ]);
    }

    public function store(): void
    {
        $user = require_role('admin', 'dev');
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            if (is_ajax()) {
                json_response(['message' => 'Nome do grupo é obrigatório.'], 422);
            }
            redirect('/admin/groups');
        }

        try {
            $this->groups->createGroup($name, $description !== '' ? $description : null);
            if (is_ajax()) {
                json_response(['message' => 'Grupo criado com sucesso.']);
            }
            redirect('/admin/groups');
        } catch (Throwable $e) {
            if (is_ajax()) {
                json_response(['message' => 'Não foi possível criar o grupo.', 'error' => $e->getMessage()], 500);
            }
            redirect('/admin/groups');
        }
    }

    public function update(int $id): void
    {
        $user = require_role('admin', 'dev');
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            json_response(['message' => 'Nome do grupo é obrigatório.'], 422);
        }

        try {
            $this->groups->updateGroup($id, $name, $description !== '' ? $description : null);
            json_response(['message' => 'Grupo atualizado com sucesso.']);
        } catch (Throwable $e) {
            json_response(['message' => 'Não foi possível atualizar o grupo.', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): void
    {
        require_role('admin', 'dev');
        try {
            if ($this->groups->deleteGroup($id)) {
                json_response(['message' => 'Grupo removido.']);
            } else {
                json_response(['message' => 'Não é possível remover um grupo com chamados vinculados.'], 400);
            }
        } catch (Throwable $e) {
            json_response(['message' => 'Não foi possível remover o grupo.', 'error' => $e->getMessage()], 500);
        }
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
