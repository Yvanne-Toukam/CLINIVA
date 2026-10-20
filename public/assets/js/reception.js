(() => {
  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const debounce = (callback, delay = 250) => {
    let timer = 0;
    return (...args) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => callback(...args), delay);
    };
  };

  document.querySelectorAll('.patient-search').forEach((input) => {
    const results = input.parentElement.querySelector('.search-results');
    const target = document.getElementById(input.dataset.target);
    const url = input.dataset.url;

    const search = debounce(async () => {
      const query = input.value.trim();
      target.value = '';
      results.innerHTML = '';
      results.classList.remove('active');

      if (query.length < 2) {
        return;
      }

      const response = await fetch(`${url}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!data.patients || data.patients.length === 0) {
        results.innerHTML = '<div class="list-group-item text-secondary">Aucun patient trouve</div>';
        results.classList.add('active');
        return;
      }

      results.innerHTML = data.patients.map((patient) => `
        <button type="button" class="list-group-item list-group-item-action" data-id="${patient.id}" data-label="${escapeHtml(patient.record_number)} - ${escapeHtml(patient.first_name)} ${escapeHtml(patient.last_name)}">
          <strong>${escapeHtml(patient.record_number)}</strong> - ${escapeHtml(patient.first_name)} ${escapeHtml(patient.last_name)}
          <span class="text-secondary small">${escapeHtml(patient.phone)}</span>
        </button>
      `).join('');
      results.classList.add('active');
    });

    input.addEventListener('input', search);
    results.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-id]');
      if (!button) {
        return;
      }

      target.value = button.dataset.id;
      input.value = button.dataset.label;
      results.innerHTML = '';
      results.classList.remove('active');
    });
  });

  const queueList = document.getElementById('queueList');
  if (!queueList || !queueList.dataset.url) {
    return;
  }

  const renderQueue = (queue) => {
    if (!queue || queue.length === 0) {
      queueList.innerHTML = '<p class="text-secondary mb-0">La file d attente est vide.</p>';
      return;
    }

    queueList.innerHTML = `
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Priorite</th><th>Patient</th><th>Arrivee</th><th>Motif</th></tr></thead>
          <tbody>
            ${queue.map((entry) => `
              <tr>
                <td><span class="status-badge bg-light text-primary border">${escapeHtml(entry.priority)}</span></td>
                <td><strong>${escapeHtml(entry.record_number)}</strong><br>${escapeHtml(entry.first_name)} ${escapeHtml(entry.last_name)}</td>
                <td>${escapeHtml(String(entry.arrived_at).substring(11, 16))}</td>
                <td>${escapeHtml(entry.immediate_reason || '')}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
  };

  const refreshQueue = async () => {
    const response = await fetch(queueList.dataset.url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
      return;
    }

    const data = await response.json();
    renderQueue(data.queue);
  };

  window.setInterval(refreshQueue, 12000);
})();
