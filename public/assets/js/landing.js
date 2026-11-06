(() => {
  const counters = document.querySelectorAll('.landing-counter');
  if (!counters.length) {
    return;
  }

  const renderCounter = (counter) => {
    const target = Number.parseInt(counter.dataset.count || '0', 10);
    if (!Number.isFinite(target) || target <= 0) {
      counter.textContent = '0';
      return;
    }

    const duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 700;
    const startedAt = performance.now();
    const update = (now) => {
      const progress = duration === 0 ? 1 : Math.min((now - startedAt) / duration, 1);
      counter.textContent = String(Math.round(target * progress));
      if (progress < 1) {
        window.requestAnimationFrame(update);
      }
    };

    window.requestAnimationFrame(update);
  };

  if (!('IntersectionObserver' in window)) {
    counters.forEach(renderCounter);
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        renderCounter(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.4 });

  counters.forEach((counter) => observer.observe(counter));
})();
