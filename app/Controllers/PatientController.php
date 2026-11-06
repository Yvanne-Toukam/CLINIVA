<?php

declare(strict_types=1);

namespace Cliniva\Controllers;

use Cliniva\Core\Controller;
use Cliniva\Core\Response;
use Cliniva\Services\AppointmentService;
use Cliniva\Services\PatientPortalService;

final class PatientController extends Controller
{
    private $appointments;
    private $portal;

    public function __construct()
    {
        $this->appointments = new AppointmentService();
        $this->portal = new PatientPortalService();
    }

    public function dashboard(): void
    {
        try {
            $data = $this->appointments->patientDashboard((int) current_user()['id']);
            $data['messages'] = $this->portal->messages((int) current_user()['id']);
            $this->view('patient/dashboard', array_merge($data, ['title' => 'Espace patient']));
        } catch (\PDOException $exception) {
            $this->databaseFailure($exception);
        } catch (\RuntimeException $exception) {
            flash('danger', $exception->getMessage());
            Response::redirect('/dashboard');
        }
    }

    public function newAppointment(): void
    {
        $this->appointments->expirePendingAppointments();
        $editAppointment = null;
        if (isset($_GET['edit_id'])) {
            try {
                $editAppointment = $this->appointments->appointmentForPatientEdit(
                    (int) current_user()['id'],
                    (int) $_GET['edit_id']
                );
            } catch (\PDOException $exception) {
                $this->databaseFailure($exception);
            } catch (\InvalidArgumentException|\RuntimeException $exception) {
                flash('danger', $exception->getMessage());
                Response::redirect('/patient/appointments/new');
            }
        }
        $this->view('patient/appointment_new', [
            'title' => 'Nouveau rendez-vous',
            'specialties' => $this->appointments->specialties(),
            'editAppointment' => $editAppointment,
            'upcoming' => $this->appointments->upcomingAppointmentsForUser((int) current_user()['id']),
        ]);
    }

    public function doctors(): void
    {
        $specialtyId = (int) ($_GET['specialty_id'] ?? 0);
        Response::json([
            'doctors' => $specialtyId > 0 ? $this->appointments->doctorsBySpecialty($specialtyId) : [],
        ]);
    }

    public function slots(): void
    {
        $doctorId = (int) ($_GET['doctor_id'] ?? 0);
        $date = (string) ($_GET['date'] ?? date('Y-m-d'));
        $ignoreAppointmentId = (int) ($_GET['ignore_appointment_id'] ?? 0);
        Response::json([
            'slots' => $doctorId > 0
                ? ($ignoreAppointmentId > 0
                    ? $this->appointments->availableSlotsForPatient((int) current_user()['id'], $doctorId, $date, $ignoreAppointmentId)
                    : $this->appointments->availableSlots($doctorId, $date))
                : [],
        ]);
    }

    public function createAppointment(): void
    {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        try {
            if ($appointmentId > 0) {
                $this->appointments->reschedulePatientAppointment((int) current_user()['id'], $appointmentId, $_POST);
                flash('success', 'Votre demande de modification a été transmise à la secrétaire pour validation.');
                Response::redirect('/patient');
            }
            $result = $this->appointments->createPatientAppointment((int) current_user()['id'], $_POST);
            flash('success', 'Votre demande de rendez-vous a été envoyée à la secrétaire pour validation.');

            Response::redirect('/patient');
        } catch (\PDOException $exception) {
            $this->databaseFailure($exception);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            remember_old($_POST);
            remember_errors([$exception->getMessage()]);
            Response::redirect('/patient/appointments/new');
        }
    }

    public function rescheduleAppointment(): void
    {
        $this->createAppointment();
    }

    public function profile(): void
    {
        try {
            $this->view('patient/profile', array_merge($this->portal->profile((int) current_user()['id']), [
                'title' => 'Mon profil',
            ]));
        } catch (\PDOException $exception) {
            $this->databaseFailure($exception);
        } catch (\RuntimeException $exception) {
            flash('danger', $exception->getMessage());
            Response::redirect('/patient');
        }
    }

    public function updateProfile(): void
    {
        $previousEmail = strtolower((string) (current_user()['email'] ?? ''));
        try {
            $this->portal->updateProfile((int) current_user()['id'], $_POST, $_FILES['photo'] ?? []);
            flash('success', 'Votre profil a été mis à jour.');
            if (isset($_POST['email']) && strtolower(trim((string) $_POST['email'])) !== $previousEmail) {
                flash('info', 'Un lien de vérification a été envoyé à votre nouvelle adresse e-mail.');
            }
        } catch (\PDOException $exception) {
            $this->databaseFailure($exception);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            remember_old($_POST);
            remember_errors(explode("\n", $exception->getMessage()));
            Response::redirect('/patient/profile');
        }

        Response::redirect('/patient/profile');
    }

    public function profilePhoto(): void
    {
        try {
            $path = $this->portal->profilePhotoPath((int) current_user()['id']);
        } catch (\PDOException $exception) {
            $this->databaseFailure($exception);
        } catch (\RuntimeException $exception) {
            http_response_code(404);
            exit('Photo de profil introuvable.');
        }

        header('Content-Type: image/webp');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function documents(): void
    {
        try {
            $this->view('patient/documents', array_merge($this->portal->documents((int) current_user()['id']), [
                'title' => 'Mes documents',
            ]));
        } catch (\PDOException $exception) {
            $this->databaseFailure($exception);
        } catch (\RuntimeException $exception) {
            flash('danger', $exception->getMessage());
            Response::redirect('/patient');
        }
    }

    public function downloadDocument(): void
    {
        try {
            $download = $this->portal->download(
                (int) current_user()['id'],
                (string) ($_GET['type'] ?? ''),
                (int) ($_GET['id'] ?? 0)
            );
        } catch (\PDOException $exception) {
            $this->databaseFailure($exception);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            http_response_code($exception instanceof \InvalidArgumentException ? 400 : 404);
            exit(e($exception->getMessage()));
        }

        $filename = str_replace(['"', '\\', "\r", "\n"], '', $download['filename']);
        header('Content-Type: ' . $download['mime']);
        header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($download['filename']));
        header('Content-Length: ' . strlen($download['contents']));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $download['contents'];
        exit;
    }

    public function history(): void
    {
        try {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $this->view('patient/history', array_merge($this->portal->history((int) current_user()['id'], $page), [
                'title' => 'Mon historique',
            ]));
        } catch (\PDOException $exception) {
            $this->databaseFailure($exception);
        } catch (\RuntimeException $exception) {
            flash('danger', $exception->getMessage());
            Response::redirect('/patient');
        }
    }

    public function confirmAppointment(): void
    {
        $ok = $this->appointments->confirmAppointment((int) ($_GET['id'] ?? 0), (string) ($_GET['token'] ?? ''));
        flash($ok ? 'success' : 'danger', $ok ? 'Rendez-vous confirme.' : 'Lien de confirmation invalide ou expire.');
        Response::redirect(current_user() !== null && current_role() === 'patient' ? '/patient' : '/login');
    }

    public function cancelAppointment(): void
    {
        try {
            $this->appointments->cancelPatientAppointment((int) current_user()['id'], (int) ($_POST['appointment_id'] ?? 0));
            flash('success', 'Rendez-vous annule.');
        } catch (\PDOException $exception) {
            $this->databaseFailure($exception);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/patient');
    }

    public function respondToAppointment(): void
    {
        try {
            $this->appointments->respondToAppointment(
                (int) current_user()['id'],
                (int) ($_POST['appointment_id'] ?? 0),
                (string) ($_POST['response'] ?? '')
            );
            flash('success', 'Votre réponse a été transmise à la secrétaire.');
        } catch (\PDOException $exception) {
            $this->databaseFailure($exception);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/patient');
    }

    private function databaseFailure(\PDOException $exception): never
    {
        error_log('Patient portal database error: ' . $exception->getMessage());
        http_response_code(500);
        exit('Une erreur est survenue pendant le traitement de votre demande. Réessayez plus tard.');
    }
}
