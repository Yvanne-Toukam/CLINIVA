(() => {
  const password = document.querySelector('#password');
  const meter = document.querySelector('.password-meter');
  const bar = document.querySelector('#password-meter-bar');
  const strength = document.querySelector('#password-strength');
  if (!password || !meter || !bar || !strength) {
    return;
  }

  const update = () => {
    const value = password.value;
    const requirements = [
      value.length >= 10,
      /[A-Z]/.test(value),
      /[a-z]/.test(value),
      /\d/.test(value),
      /[^A-Za-z0-9]/.test(value),
    ];
    const score = requirements.filter(Boolean).length;
    const strengthLevel = value.length === 0 ? 0 : score;
    const labels = ['Saisissez un mot de passe.', 'Très faible', 'Faible', 'Moyen', 'Bon', 'Fort'];
    const classes = ['is-empty', 'is-weak', 'is-low', 'is-medium', 'is-good', 'is-strong'];

    meter.setAttribute('aria-valuenow', String(strengthLevel));
    bar.style.width = `${strengthLevel * 20}%`;
    bar.className = classes[strengthLevel];
    strength.textContent = labels[strengthLevel];
  };

  password.addEventListener('input', update);
  update();
})();
