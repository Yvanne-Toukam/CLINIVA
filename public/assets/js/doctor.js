(() => {
  const calendarEl = document.getElementById('doctorCalendar');
  if (!calendarEl || typeof FullCalendar === 'undefined') {
    return;
  }

  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'timeGridWeek',
    locale: 'fr',
    height: 580,
    nowIndicator: true,
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    events: calendarEl.dataset.eventsUrl,
    eventDrop: (info) => moveEvent(info),
    eventResize: (info) => moveEvent(info)
  });

  const moveEvent = async (info) => {
    const body = new URLSearchParams();
    body.set('_csrf_token', token);
    body.set('appointment_id', info.event.id);
    body.set('start', info.event.startStr.substring(0, 19).replace('T', ' '));
    body.set('end', (info.event.endStr || info.event.startStr).substring(0, 19).replace('T', ' '));

    const response = await fetch(calendarEl.dataset.moveUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      body
    });

    if (!response.ok) {
      info.revert();
    }
  };

  calendar.render();
})();
