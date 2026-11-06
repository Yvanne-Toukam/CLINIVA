<?php

declare(strict_types=1);

namespace Cliniva\Controllers;

use Cliniva\Core\Controller;
use Cliniva\Core\Response;
use Cliniva\Services\AdminService;

final class AdminController extends Controller
{
    private $admin;

    public function __construct()
    {
        $this->admin = new AdminService();
    }

    public function dashboard(): void
    {
        $this->view('admin/dashboard', array_merge($this->admin->dashboard(), [
            'title' => 'Tableau de bord administrateur',
        ]));
    }

    public function management(): void
    {
        $this->view('admin/manage', array_merge($this->admin->managementData($_GET), [
            'title' => 'Gestion administrative',
        ]));
    }

    public function createStaffUser(): void
    {
        try {
            $id = $this->admin->createStaffUser($_POST, (int) current_user()['id']);
            flash('success', 'Compte créé #' . $id . '.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage');
    }

    public function toggleUserStatus(): void
    {
        try {
            $this->admin->toggleUserStatus((int) ($_POST['user_id'] ?? 0), (int) current_user()['id']);
            flash('success', 'Statut du compte mis à jour.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage');
    }

    public function updateStaffUser(): void
    {
        try {
            $this->admin->updateStaffUser($_POST, (int) current_user()['id']);
            flash('success', 'Compte staff mis à jour.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage');
    }

    public function requestPasswordReset(): void
    {
        try {
            $link = $this->admin->requestPasswordReset((int) ($_POST['user_id'] ?? 0), (int) current_user()['id']);
            flash('success', 'Lien de réinitialisation mis en file pour cet utilisateur.');
            if (!is_production() && $link !== null) {
                flash('info', 'Lien local de réinitialisation : ' . $link);
            }
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage');
    }

    public function createSpecialty(): void
    {
        try {
            $this->admin->createSpecialty($_POST, (int) current_user()['id']);
            flash('success', 'Spécialité ajoutée.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage');
    }

    public function updateSpecialty(): void
    {
        try {
            $this->admin->updateSpecialty($_POST, (int) current_user()['id']);
            flash('success', 'Spécialité mise à jour.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage');
    }

    public function toggleSpecialtyStatus(): void
    {
        try {
            $this->admin->toggleSpecialtyStatus((int) ($_POST['specialty_id'] ?? 0), (int) current_user()['id']);
            flash('success', 'Statut de la spécialité mis à jour.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage');
    }

    public function createService(): void
    {
        try {
            $this->admin->createService($_POST, (int) current_user()['id']);
            flash('success', 'Tarif ajouté.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage');
    }

    public function updateService(): void
    {
        try {
            $this->admin->updateService($_POST, (int) current_user()['id']);
            flash('success', 'Tarif mis à jour.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage');
    }

    public function toggleServiceStatus(): void
    {
        try {
            $this->admin->toggleServiceStatus((int) ($_POST['service_id'] ?? 0), (int) current_user()['id']);
            flash('success', 'Statut du tarif mis à jour.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage');
    }

    public function updateContactMessageStatus(): void
    {
        try {
            $this->admin->updateContactMessageStatus(
                (int) ($_POST['message_id'] ?? 0),
                (string) ($_POST['status'] ?? ''),
                (int) current_user()['id']
            );
            flash('success', 'Statut du message de contact mis à jour.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage#contact-messages');
    }

    public function savePublicSettings(): void
    {
        try {
            $settings = $_POST['settings'] ?? [];
            if (!is_array($settings)) {
                throw new \InvalidArgumentException('Paramètres invalides.');
            }
            $this->admin->savePublicSettings($settings, (int) current_user()['id']);
            flash('success', 'Coordonnées publiques enregistrées.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/admin/manage#public-settings');
    }
}
