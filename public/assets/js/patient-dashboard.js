(() => {
  const countdown = document.getElementById('nextAppointmentCountdown');
  if (!countdown) {
    return;
  }

  const target = Date.parse(countdown.dateTime);
  if (!Number.isFinite(target)) {
    return;
  }

  const fields = {
    days: document.getElementById('countdownDays'),
    hours: document.getElementById('countdownHours'),
    minutes: document.getElementById('countdownMinutes'),
    seconds: document.getElementById('countdownSeconds')
  };
  const tick = () => {
    const remaining = Math.max(0, target - Date.now());
    const seconds = Math.floor(remaining / 1000);
    fields.days.textContent = Math.floor(seconds / 86400);
    fields.hours.textContent = String(Math.floor((seconds % 86400) / 3600)).padStart(2, '0');
    fields.minutes.textContent = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
    fields.seconds.textContent = String(seconds % 60).padStart(2, '0');
  };

  tick();
  window.setInterval(tick, 1000);
})();
