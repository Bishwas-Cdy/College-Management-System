<?php include('../partials/header.php')?>

<main class="login-page py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">

        <div class="glass rounded-4 p-5 border login-card">
          <!-- Title -->
          <div class="text-center mb-5">
            <div class="mb-3">
              <i class="bi bi-shield-lock fs-1 text-primary"></i>
            </div>
            <h2 class="fw-bold mb-2">Welcome</h2>
            <p class="small-muted mb-0">
              Sign in to your account
            </p>
          </div>

          <!-- Error message (later from PHP) -->
          <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger small mb-4" role="alert">
              <i class="bi bi-exclamation-circle me-2"></i>
              Invalid email or password.
            </div>
          <?php endif; ?>

          <!-- Login Form -->
          <form method="POST" action="login_process.php" autocomplete="off">

            <!-- Email -->
            <div class="mb-3">
              <label for="email" class="form-label fw-semibold">Email address</label>
              <div class="input-group input-group-custom">
                <span class="input-group-text">
                  <i class="bi bi-envelope"></i>
                </span>
                <input
                  type="email"
                  class="form-control form-control-custom"
                  id="email"
                  name="email"
                  placeholder="user@college.edu"
                  required
                >
              </div>
            </div>

            <!-- Password -->
            <div class="mb-4">
              <label for="password" class="form-label fw-semibold">Password</label>
              <div class="input-group input-group-custom">
                <span class="input-group-text">
                  <i class="bi bi-lock"></i>
                </span>
                <input
                  type="password"
                  class="form-control form-control-custom"
                  id="password"
                  name="password"
                  placeholder="Enter your password"
                  required
                >
                <button class="btn btn-outline-secondary toggle-password" type="button">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>


            <!-- Submit -->
            <div class="d-grid mb-3">
              <button type="submit" class="btn btn-primary btn-lg rounded-3 login-btn">
                <i class="bi bi-box-arrow-in-right me-2"></i>
                Login
              </button>
            </div>

          </form>

          <!-- Info -->
          <div class="text-center">
            <p class="small-muted mb-0">
              Don't have an account?
              <br>
              <small>Contact the college administration for credentials.</small>
            </p>
          </div>
        </div>

      </div>
    </div>
  </div>
</main>

<script>
  // Password visibility toggle
  document.querySelector('.toggle-password').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      icon.classList.remove('bi-eye');
      icon.classList.add('bi-eye-slash');
    } else {
      passwordInput.type = 'password';
      icon.classList.remove('bi-eye-slash');
      icon.classList.add('bi-eye');
    }
  });
</script>

<?php include('../partials/footer.php')?>