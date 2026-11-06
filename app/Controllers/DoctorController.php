<?php

declare(strict_types=1);

namespace Cliniva\Controllers;

use Cliniva\Core\Controller;
use Cliniva\Core\Response;
use Cliniva\Services\AuditLogger;
use Cliniva\Services\DoctorService;
use Cliniva\Services\ExportService;

final class DoctorController extends Controller
{
    private $doctor;

    public function __construct()
    {
        $this->doctor = new DoctorService();
    }

    public function dashboard(): void
    {
        try {
            $this->view('doctor/dashboard', array_merge($this->doctor->dashboard((int) current_user()['id']), [
                'title' => 'Agenda medecin',
            ]));
        } catch (\RuntimeException $exception) {
            flash('danger', $exception->getMessage());
            Response::redirect('/dashboard');
        }
    }

    public function appointments(): void
    {
        try {
            $data = $this->doctor->filteredAppointments(
                (int) current_user()['id'],
                (string) ($_GET['date'] ?? ''),
                (string) ($_GET['status'] ?? '')
            );
            $this->view('doctor/appointments', array_merge($data, ['title' => 'Mes rendez-vous']));
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
            Response::redirect('/doctor/appointments');
        } catch (\RuntimeException $exception) {
            flash('danger', $exception->getMessage());
            Response::redirect('/dashboard');
        }
    }

    public function calendarEvents(): void
    {
        Response::json($this->doctor->calendarEvents(
            (int) current_user()['id'],
            (string) ($_GET['start'] ?? ''),
            (string) ($_GET['end'] ?? '')
        ));
    }

    public function moveAppointment(): void
    {
        try {
            $this->doctor->moveAppointment(
                (int) current_user()['id'],
                (int) ($_POST['appointment_id'] ?? 0),
                str_replace('T', ' ', (string) ($_POST['start'] ?? '')),
                str_replace('T', ' ', (string) ($_POST['end'] ?? ''))
            );
            Response::json(['ok' => true]);
        } catch (\InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function signalAppointmentImpediment(): void
    {
        try {
            $this->doctor->signalAppointmentImpediment(
                (int) current_user()['id'],
                (int) ($_POST['appointment_id'] ?? 0),
                (string) ($_POST['message'] ?? '')
            );
            flash('success', 'Empêchement transmis à la réception.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }
        Response::redirect('/doctor');
    }

    public function medicalRecord(): void
    {
        try {
            $this->view('doctor/record', array_merge($this->doctor->medicalRecord((int) current_user()['id'], (int) ($_GET['patient_id'] ?? 0)), [
                'title' => 'Dossier medical',
            ]));
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
            Response::redirect('/doctor');
        }
    }

    public function downloadMedicalSummaryDocx(): void
    {
        try {
            $data = $this->doctor->medicalRecord((int) current_user()['id'], (int) ($_GET['patient_id'] ?? 0));
            $patient = $data['patient'];
            $sections = [
                'Identité' => [
                    'Dossier : ' . $patient['record_number'],
                    'Date de naissance : ' . $patient['birth_date'],
                    'Sexe : ' . $patient['sex'],
                ],
                'Allergies' => array_map(static function (array $allergy): string {
                    return $allergy['allergen'] . ' (' . $allergy['severity'] . ')';
                }, $data['allergies']) ?: ['Aucune allergie renseignée.'],
                'Antécédents' => array_map(static function (array $item): string {
                    return $item['type'] . ' : ' . $item['label'] . (!empty($item['notes']) ? ' - ' . $item['notes'] : '');
                }, $data['history']) ?: ['Aucun antécédent renseigné.'],
                'Constantes récentes' => array_map(static function (array $vital): string {
                    return date('d/m/Y H:i', strtotime((string) $vital['recorded_at']))
                        . ' : TA ' . ($vital['systolic'] ?? '-') . '/' . ($vital['diastolic'] ?? '-')
                        . ', température ' . ($vital['temperature'] ?? '-') . ' °C, pouls ' . ($vital['pulse'] ?? '-')
                        . ', SpO2 ' . ($vital['oxygen_saturation'] ?? '-') . ' %, poids ' . ($vital['weight_kg'] ?? '-') . ' kg';
                }, $data['vitals']) ?: ['Aucune constante enregistrée.'],
                'Consultations' => array_map(static function (array $consultation): string {
                    return date('d/m/Y', strtotime((string) $consultation['created_at'])) . ' - ' . $consultation['motive']
                        . (!empty($consultation['diagnosis']) ? '\nDiagnostic : ' . $consultation['diagnosis'] : '')
                        . (!empty($consultation['anamnesis']) ? '\nAnamnèse : ' . $consultation['anamnesis'] : '')
                        . (!empty($consultation['clinical_exam']) ? '\nExamen clinique : ' . $consultation['clinical_exam'] : '')
                        . (!empty($consultation['care_plan']) ? '\nPlan de soins : ' . $consultation['care_plan'] : '');
                }, $data['consultations']) ?: ['Aucune consultation enregistrée.'],
            ];

            $path = tempnam(sys_get_temp_dir(), 'cliniva-summary-');
            if ($path === false) {
                throw new \RuntimeException('Impossible de préparer le document.');
            }
            try {
                (new ExportService())->generateMedicalSummaryDocx(
                    $patient['first_name'] . ' ' . $patient['last_name'],
                    $sections,
                    $path
                );
                (new AuditLogger())->log('medical_record_docx_export', 'patient', (int) $patient['id'], (int) current_user()['id']);
                $contents = file_get_contents($path);
                if ($contents === false) {
                    throw new \RuntimeException('Impossible de lire le document généré.');
                }
            } finally {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            $recordNumber = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $patient['record_number']) ?: 'patient';
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="resume-' . $recordNumber . '.docx"');
            header('Content-Length: ' . strlen($contents));
            header('Cache-Control: private, no-store, max-age=0');
            header('X-Content-Type-Options: nosniff');
            echo $contents;
            exit;
        } catch (\InvalidArgumentException $exception) {
            http_response_code(404);
            exit(e($exception->getMessage()));
        } catch (\Throwable $exception) {
            error_log('Medical summary export failed: ' . $exception->getMessage());
            http_response_code(500);
            exit('Le résumé médical ne peut pas être généré pour le moment.');
        }
    }

    public function startConsultation(): void
    {
        try {
            $id = $this->doctor->startConsultation((int) current_user()['id'], (int) ($_POST['appointment_id'] ?? 0));
            Response::redirect('/doctor/consultation?id=' . $id);
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
            Response::redirect('/doctor');
        }
    }

    public function consultation(): void
    {
        try {
            $this->view('doctor/consultation', array_merge($this->doctor->consultationForm((int) current_user()['id'], (int) ($_GET['id'] ?? 0)), [
                'title' => 'Consultation',
            ]));
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
            Response::redirect('/doctor');
        }
    }

    public function saveConsultation(): void
    {
        $id = (int) ($_POST['consultation_id'] ?? 0);
        try {
            $this->doctor->saveConsultation((int) current_user()['id'], $id, $_POST);
            flash('success', 'Consultation enregistree.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/doctor/consultation?id=' . $id);
    }

    public function closeConsultation(): void
    {
        $id = (int) ($_POST['consultation_id'] ?? 0);
        try {
            $this->doctor->closeConsultation((int) current_user()['id'], $id);
            flash('success', 'Consultation cloturee.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/doctor/consultation?id=' . $id);
    }

    public function addExamRequest(): void
    {
        $id = (int) ($_POST['consultation_id'] ?? 0);
        try {
            $this->doctor->addExamRequest((int) current_user()['id'], $id, $_POST);
            flash('success', 'Examen prescrit.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/doctor/consultation?id=' . $id);
    }

    public function addPrescription(): void
    {
        $id = (int) ($_POST['consultation_id'] ?? 0);
        try {
            $this->doctor->addPrescription((int) current_user()['id'], $id, $_POST);
            flash('success', 'Ordonnance mise a jour.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/doctor/consultation?id=' . $id);
    }

    public function cancelPrescriptionItem(): void
    {
        $consultationId = (int) ($_POST['consultation_id'] ?? 0);
        try {
            $this->doctor->cancelPrescriptionItem(
                (int) current_user()['id'],
                $consultationId,
                (int) ($_POST['prescription_item_id'] ?? 0)
            );
            flash('success', 'Prescription annulée et stock réintégré.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/doctor/consultation?id=' . $consultationId);
    }

    public function schedules(): void
    {
        $this->view('doctor/schedules', array_merge($this->doctor->schedules((int) current_user()['id']), [
            'title' => 'Mes disponibilites',
        ]));
    }

    public function addSchedule(): void
    {
        try {
            $this->doctor->addSchedule((int) current_user()['id'], $_POST);
            flash('success', 'Disponibilite ajoutee.');
        } catch (\InvalidArgumentException $exception) {
            flash('danger', $exception->getMessage());
        }

        Response::redirect('/doctor/schedules');
    }
}
