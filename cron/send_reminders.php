<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

use Cliniva\Core\Env;
use Cliniva\Services\AppointmentService;
use Cliniva\Services\NotificationService;

Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set((string) env('APP_TIMEZONE', 'Africa/Douala'));

$appointments = new AppointmentService();
$appointments->expirePendingAppointments();
$reminders = $appointments->queueScheduledReminders();
$count = (new NotificationService())->sendPendingEmails();

echo sprintf("Rappels mis en file: %d\nNotifications envoyees: %d\n", $reminders, $count);
