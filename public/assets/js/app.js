(() => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const backToTopButton = document.getElementById('back-to-top');

  if (backToTopButton) {
    const updateBackToTopVisibility = () => {
      backToTopButton.hidden = window.scrollY < 400;
    };

    window.addEventListener('scroll', updateBackToTopVisibility, { passive: true });
    updateBackToTopVisibility();

    backToTopButton.addEventListener('click', () => {
      window.scrollTo({
        top: 0,
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
      });
    });
  }

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() !== 'POST') {
      return;
    }

    const action = new URL(form.action, window.location.href).pathname.toLowerCase();
    const submitter = event.submitter;
    const submitterText = submitter instanceof HTMLElement
      ? `${submitter.textContent} ${submitter.getAttribute('aria-label') || ''} ${submitter.getAttribute('title') || ''} ${'value' in submitter ? submitter.value : ''}`.trim()
      : '';
    const normalizedText = submitterText.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    let message = '';
    if (/(^|\/)logout\/?$/.test(action)) {
      message = 'Voulez-vous vraiment vous déconnecter ?';
    } else if (/(delete|remove|destroy|supprimer|suppression)/.test(action)) {
      message = 'Voulez-vous vraiment supprimer cet élément ? Cette action peut être irréversible.';
    } else if (/(cancel|annul)/.test(action) || /\b(annuler|supprimer)\b/.test(normalizedText)) {
      message = 'Voulez-vous vraiment annuler cette action ?';
    } else if (/\bdesactiver\b/.test(normalizedText)) {
      message = 'Voulez-vous vraiment désactiver cet élément ?';
    }

    if (message !== '' && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  document.querySelectorAll('.flash-alert').forEach((alert) => {
    const timeout = Number(alert.dataset.flashTimeout || 5000);
    if (Number.isFinite(timeout) && timeout > 0) {
      setTimeout(() => {
        if (window.bootstrap && typeof window.bootstrap.Alert !== 'undefined') {
          const instance = window.bootstrap.Alert.getOrCreateInstance(alert);
          instance.close();
          return;
        }

        alert.classList.remove('show');
        alert.classList.add('d-none');
      }, timeout);
    }
  });

  window.clinivaFetch = async (url, options = {}) => {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');

    if (token && ['POST', 'PUT', 'PATCH', 'DELETE'].includes((options.method || 'GET').toUpperCase())) {
      headers.set('X-CSRF-Token', token);
    }

    const response = await fetch(url, { ...options, headers });
    if (!response.ok) {
      throw new Error('La requete a echoue.');
    }

    return response.json();
  };
})();
