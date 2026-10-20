(() => {
  const form = document.getElementById('appointmentForm');
  const config = {
    doctorsUrl: form ? form.dataset.doctorsUrl || '' : '',
    slotsUrl: form ? form.dataset.slotsUrl || '' : '',
    selectedDoctor: form ? form.dataset.selectedDoctor || '' : '',
    selectedStart: form ? form.dataset.selectedStart || '' : ''
  };
  const specialty = document.getElementById('specialty_id');
  const doctor = document.getElementById('doctor_id');
  const date = document.getElementById('appointment_date');
  const slots = document.getElementById('slots');
  const startInput = document.getElementById('start_datetime');
  const doctorAvailability = document.getElementById('doctorAvailability');

  if (!specialty || !doctor || !date || !slots || !startInput) {
    return;
  }

  const setSlotsMessage = (message) => {
    slots.innerHTML = `<div class="text-secondary">${message}</div>`;
  };

  const formatSlotDate = (value) => {
    const parsed = new Date(value.replace(' ', 'T'));
    return new Intl.DateTimeFormat('fr-CM', {
      weekday: 'long',
      day: '2-digit',
      month: 'long',
      hour: '2-digit',
      minute: '2-digit'
    }).format(parsed);
  };

  const loadDoctors = async () => {
    const specialtyId = specialty.value;
    doctor.innerHTML = '<option value="">Chargement...</option>';
    doctor.disabled = true;
    doctorAvailability.textContent = '';
    startInput.value = '';
    setSlotsMessage('Selectionnez un medecin.');

    if (!specialtyId) {
      doctor.innerHTML = '<option value="">Choisir une specialite</option>';
      return;
    }

    const response = await fetch(`${config.doctorsUrl}?specialty_id=${encodeURIComponent(specialtyId)}`, {
      headers: { Accept: 'application/json' }
    });
    const data = await response.json();

    doctor.innerHTML = '<option value="">Choisir</option>';
    data.doctors.forEach((item) => {
      const option = document.createElement('option');
      option.value = item.id;
      option.textContent = `Dr ${item.first_name} ${item.last_name}`;
      option.dataset.slots = JSON.stringify(item.next_slots || []);
      doctor.appendChild(option);
    });

    doctor.disabled = data.doctors.length === 0;
    if (data.doctors.length === 0) {
      doctor.innerHTML = '<option value="">Aucun medecin disponible</option>';
    }

    if (config.selectedDoctor) {
      doctor.value = config.selectedDoctor;
      config.selectedDoctor = '';
    }

    updateDoctorHint();
    await loadSlots();
  };

  const updateDoctorHint = () => {
    const selected = doctor.selectedOptions[0];
    if (!selected || !selected.dataset.slots) {
      doctorAvailability.textContent = '';
      return;
    }

    const nextSlots = JSON.parse(selected.dataset.slots || '[]');
    doctorAvailability.textContent = nextSlots.length > 0
      ? `Prochains creneaux : ${nextSlots.map((slot) => formatSlotDate(slot.start)).join(', ')}`
      : 'Aucun creneau trouve sur les prochains jours.';
  };

  const loadSlots = async () => {
    startInput.value = '';
    if (!doctor.value || !date.value) {
      setSlotsMessage('Selectionnez un medecin et une date.');
      return;
    }

    setSlotsMessage('Chargement des creneaux...');
    const response = await fetch(`${config.slotsUrl}?doctor_id=${encodeURIComponent(doctor.value)}&date=${encodeURIComponent(date.value)}`, {
      headers: { Accept: 'application/json' }
    });
    const data = await response.json();

    slots.innerHTML = '';
    if (!data.slots || data.slots.length === 0) {
      setSlotsMessage('Aucun creneau disponible pour cette date.');
      return;
    }

    data.slots.forEach((slot) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-outline-primary slot-button';
      button.textContent = slot.label;
      button.dataset.start = slot.start;

      if (config.selectedStart === slot.start) {
        button.classList.add('active');
        startInput.value = slot.start;
        config.selectedStart = '';
      }

      button.addEventListener('click', () => {
        slots.querySelectorAll('.slot-button').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
        startInput.value = slot.start;
      });

      slots.appendChild(button);
    });
  };

  specialty.addEventListener('change', loadDoctors);
  doctor.addEventListener('change', () => {
    updateDoctorHint();
    loadSlots();
  });
  date.addEventListener('change', loadSlots);

  if (specialty.value) {
    loadDoctors();
  }
})();
