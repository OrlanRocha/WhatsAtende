<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\TemplateService;
use PDOException;

class TemplateController
{
    public function __construct(private TemplateService $templateService)
    {
    }

    public function index(): void
    {
        require_role('admin');
        $templates = $this->templateService->list();

        if (is_ajax()) {
            json_response(['templates' => $templates]);
        }

        view('admin/templates/index', [
            'templates' => $templates,
            'status' => get_flash('admin_status'),
            'error' => get_flash('admin_error'),
            'formErrors' => get_flash('template_errors') ?? [],
            'old' => get_flash('template_old') ?? [],
        ]);
    }

    public function store(): void
    {
        $admin = require_role('admin');
        $isAjax = is_ajax();

        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $category = trim($_POST['category'] ?? '');

        $errors = $this->validateTemplate($title, $body);
        if ($errors !== []) {
            $this->handleFormError($errors, [
                'title' => $title,
                'body' => $body,
                'category' => $category,
            ], $isAjax);
        }

        try {
            $template = $this->templateService->create($title, $body, $category, (int) $admin->id);
        } catch (PDOException $exception) {
            $errors[] = 'Não foi possível salvar o template. Tente novamente.';
            $this->handleFormError($errors, [
                'title' => $title,
                'body' => $body,
                'category' => $category,
            ], $isAjax);
        }

        if ($isAjax) {
            json_response([
                'message' => 'Template criado com sucesso.',
                'template' => $template,
            ]);
        }

        set_flash('admin_status', 'Template criado com sucesso.');
        redirect('/admin/templates');
    }

    public function update(int $templateId): void
    {
        $admin = require_role('admin');
        $isAjax = is_ajax();

        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $category = trim($_POST['category'] ?? '');

        $errors = $this->validateTemplate($title, $body);
        if ($errors !== []) {
            $this->handleFormError($errors, [
                'title' => $title,
                'body' => $body,
                'category' => $category,
            ], $isAjax);
        }

        try {
            $this->templateService->update($templateId, $title, $body, $category, (int) $admin->id);
        } catch (PDOException $exception) {
            $errors[] = 'Não foi possível atualizar o template.';
            $this->handleFormError($errors, [
                'title' => $title,
                'body' => $body,
                'category' => $category,
            ], $isAjax);
        }

        if ($isAjax) {
            json_response([
                'message' => 'Template atualizado com sucesso.',
                'template' => $this->templateService->find($templateId),
            ]);
        }

        set_flash('admin_status', 'Template atualizado com sucesso.');
        redirect('/admin/templates');
    }

    public function destroy(int $templateId): void
    {
        $admin = require_role('admin');
        $isAjax = is_ajax();

        $this->templateService->delete($templateId, (int) $admin->id);

        if ($isAjax) {
            json_response(['message' => 'Template removido.']);
        }

        set_flash('admin_status', 'Template removido.');
        redirect('/admin/templates');
    }

    private function validateTemplate(string $title, string $body): array
    {
        $errors = [];
        if ($title === '') {
            $errors[] = 'Informe um título.';
        }

        if ($body === '') {
            $errors[] = 'Informe o conteúdo do template.';
        }

        return $errors;
    }

    private function handleFormError(array $errors, array $old, bool $ajax): void
    {
        if ($ajax) {
            json_response(['errors' => $errors], 422);
        }

        set_flash('template_errors', $errors);
        set_flash('template_old', $old);
        redirect('/admin/templates');
    }
}
