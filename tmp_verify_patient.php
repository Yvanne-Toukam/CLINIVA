<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/app.php';

$_SESSION['user'] = [
    'id' => 4,
    'role' => 'patient',
    'first_name' => 'Joel',
    'last_name' => 'Talla',
    'email' => 'patient@cliniva.local',
];

$service = new Cliniva\Services\AppointmentService();
$doctors = $service->doctorsBySpecialty(1);

echo "RELATIVE_URL=" . relative_url('/patient/appointments/doctors') . PHP_EOL;
echo "DOCTORS_COUNT=" . count($doctors) . PHP_EOL;
foreach ($doctors as $doctor) {
    echo $doctor['first_name'] . ' ' . $doctor['last_name'] . ' | next_slots=' . count($doctor['next_slots']) . PHP_EOL;
}
