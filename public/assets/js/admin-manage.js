(() => {
  const role = document.getElementById('user-role');
  const doctorFields = document.getElementById('doctor-fields');
  const specialty = document.getElementById('user-specialty');
  const license = document.getElementById('user-license');

  if (!role || !doctorFields || !specialty || !license) {
    return;
  }

  const updateDoctorFields = () => {
    const isDoctor = role.value === 'medecin';
    doctorFields.classList.toggle('d-none', !isDoctor);
    doctorFields.setAttribute('aria-hidden', String(!isDoctor));
    specialty.required = isDoctor;
    license.required = isDoctor;
    specialty.disabled = !isDoctor;
    license.disabled = !isDoctor;
  };

  role.addEventListener('change', updateDoctorFields);
  updateDoctorFields();
})();
