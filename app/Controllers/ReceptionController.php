<?php

declare(strict_types=1);

namespace Cliniva\Controllers;

use Cliniva\Core\Controller;
use Cliniva\Core\Response;
use Cliniva\Services\AppointmentService;
use Cliniva\Services\MedicineService;
use Cliniva\Services\ReceptionService;

final class ReceptionController extends Controller
{
    private $reception;
    private $appointments;
    private $medicines;

    public function __construct()
    {
        $this->reception = new ReceptionService();
        $this->appointments = new AppointmentService();
        $this->medicines = new MedicineService();
    }

    public function dashboard(): void
    {
        $this->view('reception/dashboard', array_merge($this->reception->dashboard((int) current_user()['id']), [
            'title' => 'Reception',
        ]));
    }

    public function history(): void
    {
        $this->view('reception/history', [
            'title' => 'Historique réception',
            'actions' => $this->reception->recentActions((int) current_user()['id']),
        ]);
    }

    public function medicines(): void
    {
        $this->view('reception/medicines', array_merge($this->medicines->managementData(), [
            'title' => 'Médicaments et stock',
        ]));
    }

    public function createMedicine(): void
    {
        try {
            $this->medicines->create((int) current_user()['id'], $_POST);
            flash('success', 'Médicament ajouté au catalogue. Enregistrez une entrée pour renseigner son stock.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/reception/medicines');
    }

    public function updateMedicine(): void
    {
        try {
            $this->medicines->update((int) current_user()['id'], $_POST);
            flash('success', 'Médicament mis à jour.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/reception/medicines');
    }

    public function toggleMedicineStatus(): void
    {
        try {
            $this->medicines->toggleStatus((int) ($_POST['medicine_id'] ?? 0), (int) current_user()['id']);
            flash('success', 'Statut du médicament mis à jour.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/reception/medicines');
    }

    public function recordMedicineStockMovement(): void
    {
        try {
            $this->medicines->recordStockMovement(
                (int) ($_POST['medicine_id'] ?? 0),
                (int) current_user()['id'],
                $_POST
            );
            flash('success', 'Mouvement de stock enregistré.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/reception/medicines');
    }

    public function resolveDoctorAlert(): void
    {
        try {
            $this->reception->resolveDoctorAlert((int) ($_POST['alert_id'] ?? 0), (int) current_user()['id']);
            flash('success', 'Alerte médecin traitée.');
        } catch (\InvalidArgumentException $exception) {
            flash('warning', $exception->getMessage());
        }
        Response::redirect('/reception');
    }

    public function newPatient(): void
    {
        $this->view('reception/patient_new', ['title' => 'Nouveau patient']);
    }

    public function createPatient(): void
    {
        try {
            $id = $this->reception->createPatient($_POST, (int) current_user()['id']);
            forget_old();
            flash('success', 'Patient cree avec le dossier #' . $id . '.');
            Response::redirect('/reception');
        } catch (\InvalidArgumentException $exception) {
            remember_old($_POST);
            remember_errors(explode("\n", $exception->getMessage()));
            Response::redirect('/reception/patients/new');
        }
    }

    public function searchPatients(): void
    {
        Response::json(['patients' => $this->reception->searchPatients((string) ($_GET['q'] ?? ''))]);
    }

    public function triage(): void
    {
        $this->view('reception/triage', [
            'title' => 'Triage',
            'queue' => $this->reception->queue(),
        ]);
    }

    public function recordTriage(): void
    {
        try {
            $this->reception->recordTriage($_POST, (int) current_user()['id']);
            flash('success', 'Triage enregistre et file d attente mise a jour.');
            Response::redirect('/reception/triage');
        } catch (\InvalidArgumentException $exception) {
            remember_old($_POST);
            remember_errors([$exception->getMessage()]);
            Response::redirect('/reception/triage');
        }
    }

    public function queue(): void
    {
        Response::json(['queue' => $this->reception->queue()]);
    }

    public function newAppointment(): void
    {
        $this->appointments->expirePendingAppointments();
        $this->view('reception/appointment_new', [
            'title' => 'Rendez-vous reception',
            'specialties' => $this->appointments->specialties(),
            'upcomingAppointments' => $this->reception->upcomingAppointments(),
            'pendingAppointments' => $this->reception->pendingAppointmentValidations(),
        ]);
    }

    public function appointmentDoctors(): void
    {
        Response::json(['doctors' => $this->appointments->doctorsBySpecialty((int) ($_GET['specialty_id'] ?? 0))]);
    }

    public function appointmentSlots(): void
    {
        Response::json(['slots' => $this->appointments->availableSlots((int) ($_GET['doctor_id'] ?? 0), (string) ($_GET['date'] ?? date('Y-m-d')))]);
    }

    public function createAppointment(): void
    {
        try {
            $this->appointments->createStaffAppointment((int) ($_POST['patient_id'] ?? 0), $_POST, (int) current_user()['id']);
            flash('success', 'Rendez-vous confirme.');
            Response::redirect('/reception/appointments/new');
        } catch (\InvalidArgumentException $exception) {
            remember_old($_POST);
            remember_errors([$exception->getMessage()]);
            Response::redirect('/reception/appointments/new');
        }
    }

    public function cancelAppointment(): void
    {
        try {
            $this->appointments->cancelStaffAppointment((int) ($_POST['appointment_id'] ?? 0), (int) current_user()['id']);
            flash('success', 'Rendez-vous annule.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/reception/appointments/new');
    }

    public function validateAppointment(): void
    {
        try {
            $this->appointments->validatePatientAppointment((int) ($_POST['appointment_id'] ?? 0), (int) current_user()['id']);
            flash('success', 'Rendez-vous validé et patient notifié.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/reception/appointments/new');
    }

    public function refuseAppointment(): void
    {
        try {
            $this->appointments->refusePatientAppointment(
                (int) ($_POST['appointment_id'] ?? 0),
                (int) current_user()['id'],
                (string) ($_POST['reason'] ?? '')
            );
            flash('success', 'Demande refusée et patient notifié.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/reception/appointments/new');
    }

    public function rescheduleAppointment(): void
    {
        try {
            $this->appointments->reschedulePatientAppointmentBySecretary(
                (int) ($_POST['appointment_id'] ?? 0),
                (int) current_user()['id'],
                (string) ($_POST['new_start'] ?? '')
            );
            flash('success', 'Proposition de report envoyée au patient.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/reception/appointments/new');
    }

    public function sendAppointmentMessage(): void
    {
        try {
            $this->appointments->sendAppointmentMessage(
                (int) ($_POST['appointment_id'] ?? 0),
                (int) current_user()['id'],
                (string) ($_POST['subject'] ?? ''),
                (string) ($_POST['body'] ?? '')
            );
            flash('success', 'Message envoyé au patient dans son espace et par e-mail si disponible.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/reception/appointments/new');
    }

    public function schedules(): void
    {
        $this->view('reception/schedules', [
            'title' => 'Horaires medecins',
            'doctors' => $this->reception->doctors(),
            'schedules' => $this->reception->schedules(),
        ]);
    }

    public function addSchedule(): void
    {
        try {
            $this->reception->addSchedule($_POST, (int) current_user()['id']);
            flash('success', 'Plage horaire ajoutee.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/reception/schedules');
    }

    public function addTimeOff(): void
    {
        try {
            $this->reception->addTimeOff($_POST, (int) current_user()['id']);
            flash('success', 'Exception ou conge ajoute.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/reception/schedules');
    }

    public function billing(): void
    {
        $this->view('reception/billing', [
            'title' => 'Facturation',
            'services' => $this->reception->services(),
            'recentInvoices' => $this->reception->dashboard((int) current_user()['id'])['recentInvoices'],
        ]);
    }

    public function createInvoice(): void
    {
        try {
            $invoiceId = $this->reception->createInvoice($_POST, (int) current_user()['id']);
            flash('success', 'Facture creee #' . $invoiceId . '.');
            Response::redirect('/reception/billing');
        } catch (\InvalidArgumentException $exception) {
            remember_old($_POST);
            remember_errors([$exception->getMessage()]);
            Response::redirect('/reception/billing');
        }
    }
}
