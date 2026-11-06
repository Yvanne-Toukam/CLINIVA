<?php

declare(strict_types=1);

use Cliniva\Controllers\HomeController;
use Cliniva\Controllers\AuthController;
use Cliniva\Controllers\PatientController;
use Cliniva\Controllers\ReceptionController;
use Cliniva\Controllers\DoctorController;
use Cliniva\Controllers\AdminController;

$router->get('/dashboard', [HomeController::class, 'dashboard'], ['admin', 'medecin', 'secretaire', 'patient']);
$router->post('/contact', [HomeController::class, 'submitContact']);
$router->get('/admin', [AdminController::class, 'dashboard'], ['admin']);
$router->get('/admin/manage', [AdminController::class, 'management'], ['admin']);
$router->post('/admin/users', [AdminController::class, 'createStaffUser'], ['admin']);
$router->post('/admin/users/status', [AdminController::class, 'toggleUserStatus'], ['admin']);
$router->post('/admin/users/update', [AdminController::class, 'updateStaffUser'], ['admin']);
$router->post('/admin/users/password-reset', [AdminController::class, 'requestPasswordReset'], ['admin']);
$router->post('/admin/specialties', [AdminController::class, 'createSpecialty'], ['admin']);
$router->post('/admin/specialties/update', [AdminController::class, 'updateSpecialty'], ['admin']);
$router->post('/admin/specialties/status', [AdminController::class, 'toggleSpecialtyStatus'], ['admin']);
$router->post('/admin/services', [AdminController::class, 'createService'], ['admin']);
$router->post('/admin/services/update', [AdminController::class, 'updateService'], ['admin']);
$router->post('/admin/services/status', [AdminController::class, 'toggleServiceStatus'], ['admin']);
$router->post('/admin/settings', [AdminController::class, 'savePublicSettings'], ['admin']);
$router->post('/admin/contact-messages/status', [AdminController::class, 'updateContactMessageStatus'], ['admin']);
$router->get('/patient', [PatientController::class, 'dashboard'], ['patient']);
$router->get('/patient/profile', [PatientController::class, 'profile'], ['patient']);
$router->post('/patient/profile', [PatientController::class, 'updateProfile'], ['patient']);
$router->get('/patient/profile/photo', [PatientController::class, 'profilePhoto'], ['patient']);
$router->get('/patient/documents', [PatientController::class, 'documents'], ['patient']);
$router->get('/patient/documents/download', [PatientController::class, 'downloadDocument'], ['patient']);
$router->get('/patient/history', [PatientController::class, 'history'], ['patient']);
$router->get('/patient/appointments/new', [PatientController::class, 'newAppointment'], ['patient']);
$router->get('/patient/appointments/doctors', [PatientController::class, 'doctors'], ['patient']);
$router->get('/patient/appointments/slots', [PatientController::class, 'slots'], ['patient']);
$router->post('/patient/appointments', [PatientController::class, 'createAppointment'], ['patient']);
$router->post('/patient/appointments/cancel', [PatientController::class, 'cancelAppointment'], ['patient']);
$router->post('/patient/appointments/reschedule', [PatientController::class, 'rescheduleAppointment'], ['patient']);
$router->post('/patient/appointments/respond', [PatientController::class, 'respondToAppointment'], ['patient']);
$router->get('/appointments/confirm', [PatientController::class, 'confirmAppointment']);

$router->get('/reception', [ReceptionController::class, 'dashboard'], ['admin', 'secretaire']);
$router->get('/reception/history', [ReceptionController::class, 'history'], ['admin', 'secretaire']);
$router->get('/reception/medicines', [ReceptionController::class, 'medicines'], ['admin', 'secretaire']);
$router->post('/reception/medicines', [ReceptionController::class, 'createMedicine'], ['admin', 'secretaire']);
$router->post('/reception/medicines/update', [ReceptionController::class, 'updateMedicine'], ['admin', 'secretaire']);
$router->post('/reception/medicines/status', [ReceptionController::class, 'toggleMedicineStatus'], ['admin', 'secretaire']);
$router->post('/reception/medicines/stock', [ReceptionController::class, 'recordMedicineStockMovement'], ['admin', 'secretaire']);
$router->post('/reception/doctor-alerts/resolve', [ReceptionController::class, 'resolveDoctorAlert'], ['admin', 'secretaire']);
$router->get('/reception/patients/new', [ReceptionController::class, 'newPatient'], ['admin', 'secretaire']);
$router->post('/reception/patients', [ReceptionController::class, 'createPatient'], ['admin', 'secretaire']);
$router->get('/reception/patients/search', [ReceptionController::class, 'searchPatients'], ['admin', 'secretaire']);
$router->get('/reception/triage', [ReceptionController::class, 'triage'], ['admin', 'secretaire']);
$router->post('/reception/triage', [ReceptionController::class, 'recordTriage'], ['admin', 'secretaire']);
$router->get('/reception/queue', [ReceptionController::class, 'queue'], ['admin', 'secretaire']);
$router->get('/reception/appointments/new', [ReceptionController::class, 'newAppointment'], ['admin', 'secretaire']);
$router->get('/reception/appointments/doctors', [ReceptionController::class, 'appointmentDoctors'], ['admin', 'secretaire']);
$router->get('/reception/appointments/slots', [ReceptionController::class, 'appointmentSlots'], ['admin', 'secretaire']);
$router->post('/reception/appointments', [ReceptionController::class, 'createAppointment'], ['admin', 'secretaire']);
$router->post('/reception/appointments/cancel', [ReceptionController::class, 'cancelAppointment'], ['admin', 'secretaire']);
$router->post('/reception/appointments/validate', [ReceptionController::class, 'validateAppointment'], ['admin', 'secretaire']);
$router->post('/reception/appointments/refuse', [ReceptionController::class, 'refuseAppointment'], ['admin', 'secretaire']);
$router->post('/reception/appointments/reschedule', [ReceptionController::class, 'rescheduleAppointment'], ['admin', 'secretaire']);
$router->post('/reception/appointments/message', [ReceptionController::class, 'sendAppointmentMessage'], ['admin', 'secretaire']);
$router->get('/reception/schedules', [ReceptionController::class, 'schedules'], ['admin', 'secretaire']);
$router->post('/reception/schedules', [ReceptionController::class, 'addSchedule'], ['admin', 'secretaire']);
$router->post('/reception/time-off', [ReceptionController::class, 'addTimeOff'], ['admin', 'secretaire']);
$router->get('/reception/billing', [ReceptionController::class, 'billing'], ['admin', 'secretaire']);
$router->post('/reception/invoices', [ReceptionController::class, 'createInvoice'], ['admin', 'secretaire']);

$router->get('/doctor', [DoctorController::class, 'dashboard'], ['medecin']);
$router->get('/doctor/appointments', [DoctorController::class, 'appointments'], ['medecin']);
$router->get('/doctor/calendar/events', [DoctorController::class, 'calendarEvents'], ['medecin']);
$router->post('/doctor/calendar/move', [DoctorController::class, 'moveAppointment'], ['medecin']);
$router->post('/doctor/appointments/impediment', [DoctorController::class, 'signalAppointmentImpediment'], ['medecin']);
$router->get('/doctor/record', [DoctorController::class, 'medicalRecord'], ['medecin']);
$router->get('/doctor/record/export', [DoctorController::class, 'downloadMedicalSummaryDocx'], ['medecin']);
$router->post('/doctor/consultations/start', [DoctorController::class, 'startConsultation'], ['medecin']);
$router->get('/doctor/consultation', [DoctorController::class, 'consultation'], ['medecin']);
$router->post('/doctor/consultation/save', [DoctorController::class, 'saveConsultation'], ['medecin']);
$router->post('/doctor/consultation/close', [DoctorController::class, 'closeConsultation'], ['medecin']);
$router->post('/doctor/consultation/exams', [DoctorController::class, 'addExamRequest'], ['medecin']);
$router->post('/doctor/consultation/prescriptions', [DoctorController::class, 'addPrescription'], ['medecin']);
$router->post('/doctor/consultation/prescriptions/cancel', [DoctorController::class, 'cancelPrescriptionItem'], ['medecin']);
$router->get('/doctor/schedules', [DoctorController::class, 'schedules'], ['medecin']);
$router->post('/doctor/schedules', [DoctorController::class, 'addSchedule'], ['medecin']);

$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/verify-email', [AuthController::class, 'verifyEmail']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['admin', 'medecin', 'secretaire', 'patient']);

$router->get('/2fa/setup', [AuthController::class, 'showTwoFactorSetup']);
$router->post('/2fa/setup', [AuthController::class, 'completeTwoFactorSetup']);
$router->get('/2fa/verify', [AuthController::class, 'showTwoFactorVerify']);
$router->post('/2fa/verify', [AuthController::class, 'verifyTwoFactor']);

$router->get('/password/forgot', [AuthController::class, 'showForgotPassword']);
$router->post('/password/forgot', [AuthController::class, 'sendResetLink']);
$router->get('/password/reset', [AuthController::class, 'showResetPassword']);
$router->post('/password/reset', [AuthController::class, 'resetPassword']);
$router->get('/password/change', [AuthController::class, 'showChangePassword'], ['admin', 'medecin', 'secretaire', 'patient']);
$router->post('/password/change', [AuthController::class, 'changePassword'], ['admin', 'medecin', 'secretaire', 'patient']);
