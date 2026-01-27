  <script>
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', (e) => {
        const id = a.getAttribute('href');
        if (!id || id === '#') return;
        const target = document.querySelector(id);
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    // Simple counter animation when visible
    const counters = document.querySelectorAll('[data-counter]');
    const animateCounter = (el) => {
      const end = Number(el.getAttribute('data-counter')) || 0;
      const durationMs = 700;
      const startTime = performance.now();
      const startVal = 0;

      const tick = (t) => {
        const p = Math.min((t - startTime) / durationMs, 1);
        const val = Math.floor(startVal + (end - startVal) * p);
        el.textContent = val.toString();
        if (p < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    };

    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.4 });

    counters.forEach(c => io.observe(c));

    // Footer year
    document.getElementById('year').textContent = String(new Date().getFullYear());
  </script>

  <!-- Bootstrap 5 JS Bundle -->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js">
  </script>

</body>
</html>