  <script src="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false || strpos($_SERVER['REQUEST_URI'], '/faculty/') !== false || strpos($_SERVER['REQUEST_URI'], '/student/') !== false || strpos($_SERVER['REQUEST_URI'], '/settings/') !== false) ? '../public/js/footer.js' : './public/js/footer.js'; ?>"></script>

  <!-- Bootstrap 5 JS Bundle -->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js">
  </script>

</body>
</html>