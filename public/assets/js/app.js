(() => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

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
