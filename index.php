  <?php include('./partials/header.php')?>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
      <div class="w-100 glass rounded-4 px-3 py-2 d-flex align-items-center">
        <a class="navbar-brand fw-bold text-primary" href="#top">CMS</a>

        <div class="d-flex align-items-center ms-auto gap-2">
          <button class="navbar-toggler hamburger-menu" type="button" data-bs-toggle="collapse" data-bs-target="#nav"
            aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
            <span></span>
            <span></span>
            <span></span>
          </button>

          <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav align-items-lg-center gap-lg-2">
              <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
              <li class="nav-item"><a class="nav-link" href="#roles">Roles</a></li>
              <li class="nav-item"><a class="nav-link" href="#modules">Modules</a></li>
              <li class="nav-item ms-lg-2">
                <a class="btn btn-primary rounded-3 px-3" href="./auth/login.php">
                  <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <header id="top" class="hero">
    <div class="container">
      <div class="w-100 glass rounded-4 px-3 py-2">
        <div class="row g-4 align-items-center">
          <div class="col-lg-7">
            <div class="d-flex flex-wrap gap-2 mb-3">
              <span class="badge badge-soft rounded-pill px-3 py-2">
                <i class="bi bi-shield-lock me-2"></i>Secure sessions + hashed passwords
              </span>
              <span class="badge badge-soft-success rounded-pill px-3 py-2">
                <i class="bi bi-person-badge me-2"></i>3 Roles: Admin, Faculty, Student
              </span>
              <span class="badge badge-soft-dark rounded-pill px-3 py-2">
                <i class="bi bi-database me-2"></i>PHP + MySQL
              </span>
            </div>

            <h1 class="display-6 fw-bold mb-3">One platform for attendance, exams, fees, timetable, and study materials.</h1>
            <p class="lead small-muted mb-4">
              A web-based college management system that centralizes data, reduces manual work,
              and provides role-based dashboards and reports.
            </p>

            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-primary btn-lg rounded-3 px-4" href="./auth/login.php">
                Get Started <i class="bi bi-arrow-right ms-2"></i>
              </a>
            </div>

            <div class="divider my-4"></div>

            <div class="row g-3">
              <div class="col-md-4">
                <div class="p-3 rounded-4 bg-white border">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-shield-lock text-primary fs-5"></i>
                    <span class="fw-semibold">Admin</span>
                  </div>
                  <p class="small-muted mb-0">Manage users, courses, attendance, exams, and fees</p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="p-3 rounded-4 bg-white border">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-person-workspace text-success fs-5"></i>
                    <span class="fw-semibold">Faculty</span>
                  </div>
                  <p class="small-muted mb-0">Mark attendance, enter marks, upload materials</p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="p-3 rounded-4 bg-white border">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-mortarboard text-info fs-5"></i>
                    <span class="fw-semibold">Student</span>
                  </div>
                  <p class="small-muted mb-0">View marks, attendance, fees, and materials</p>
                </div>
              </div>
            </div>

          </div>

          <div class="col-lg-5">
            <div class="row g-3">
              <div class="col-6">
                <div class="p-3 rounded-4 bg-white border">
                  <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bi bi-calendar-check text-primary fs-4"></i>
                  </div>
                  <div class="small fw-semibold text-center">Attendance</div>
                  <div class="small-muted text-center">Track daily sessions</div>
                </div>
              </div>
              <div class="col-6">
                <div class="p-3 rounded-4 bg-white border">
                  <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bi bi-award text-success fs-4"></i>
                  </div>
                  <div class="small fw-semibold text-center">Exams</div>
                  <div class="small-muted text-center">Marks & results</div>
                </div>
              </div>
              <div class="col-6">
                <div class="p-3 rounded-4 bg-white border">
                  <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bi bi-cash-coin text-warning fs-4"></i>
                  </div>
                  <div class="small fw-semibold text-center">Fees</div>
                  <div class="small-muted text-center">Payments tracking</div>
                </div>
              </div>
              <div class="col-6">
                <div class="p-3 rounded-4 bg-white border">
                  <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bi bi-calendar2-week text-info fs-4"></i>
                  </div>
                  <div class="small fw-semibold text-center">Timetable</div>
                  <div class="small-muted text-center">Class schedule</div>
                </div>
              </div>
              <div class="col-12">
                <div class="p-3 rounded-4 bg-white border">
                  <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bi bi-folder2-open text-danger fs-4"></i>
                  </div>
                  <div class="small fw-semibold text-center">Study Materials</div>
                  <div class="small-muted text-center">Upload & download resources</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Features -->
  <section id="features" class="py-5">
    <div class="container">
      <div class="mb-4">
        <h2 class="fw-bold mb-2">Core Features</h2>
        <p class="small-muted mb-0">Everything needed for Admin, Faculty, and Student workflows.</p>
      </div>

      <div class="row g-3">
        <div class="col-md-6 col-lg-4">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="icon-badge mb-3"><i class="bi bi-person-check"></i></div>
              <h5 class="fw-semibold">Role-based Access</h5>
              <p class="small-muted mb-0">Separate dashboards and permissions for Admin, Faculty, and Students.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="icon-badge mb-3"><i class="bi bi-calendar-check"></i></div>
              <h5 class="fw-semibold">Attendance</h5>
              <p class="small-muted mb-0">Mark attendance per subject and date with reports and summaries.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="icon-badge mb-3"><i class="bi bi-award"></i></div>
              <h5 class="fw-semibold">Exams and Results</h5>
              <p class="small-muted mb-0">Create exams, enter marks, publish results, and show GPA/CGPA.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="icon-badge mb-3"><i class="bi bi-cash-stack"></i></div>
              <h5 class="fw-semibold">Fees and Payments</h5>
              <p class="small-muted mb-0">Fee structures, invoices, payment tracking, and status dashboards.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="icon-badge mb-3"><i class="bi bi-clock-history"></i></div>
              <h5 class="fw-semibold">Timetable</h5>
              <p class="small-muted mb-0">Class routine per course and semester with room and time slots.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="icon-badge mb-3"><i class="bi bi-folder2-open"></i></div>
              <h5 class="fw-semibold">Study Materials</h5>
              <p class="small-muted mb-0">Faculty uploads PDFs and notes, students download by subject.</p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- Roles -->
  <section id="roles" class="py-5">
    <div class="container">
      <div class="mb-4">
        <h2 class="fw-bold mb-2">User Roles</h2>
        <p class="small-muted mb-0">Clear responsibilities with non-overlapping permissions.</p>
      </div>

      <div class="row g-3">
        <div class="col-lg-4">
          <div class="glass rounded-4 p-4 h-100 card-hover">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="bi bi-shield-lock fs-4"></i>
              <h5 class="m-0 fw-semibold">Admin</h5>
            </div>
            <ul class="small-muted mb-0">
              <li>Manage students, faculty, courses, and subjects</li>
              <li>Assign subjects to faculty</li>
              <li>Manage timetable, exams, results</li>
              <li>Manage fees, invoices, payments</li>
              <li>Reports and announcements</li>
            </ul>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="glass rounded-4 p-4 h-100 card-hover">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="bi bi-person-workspace fs-4"></i>
              <h5 class="m-0 fw-semibold">Faculty</h5>
            </div>
            <ul class="small-muted mb-0">
              <li>View assigned subjects and timetable</li>
              <li>Mark attendance</li>
              <li>Enter exam marks</li>
              <li>Upload study materials</li>
              <li>Message students</li>
            </ul>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="glass rounded-4 p-4 h-100 card-hover">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="bi bi-mortarboard fs-4"></i>
              <h5 class="m-0 fw-semibold">Student</h5>
            </div>
            <ul class="small-muted mb-0">
              <li>View timetable, attendance, marks, results</li>
              <li>Download study materials</li>
              <li>View invoices and payment status</li>
              <li>Message faculty</li>
              <li>Receive announcements</li>
            </ul>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- Modules -->
  <section id="modules" class="py-5">
    <div class="container">
      <div class="mb-4">
        <h2 class="fw-bold mb-2">Modules</h2>
        <p class="small-muted mb-0">Suggested navigation for your final PHP app.</p>
      </div>

      <div class="row g-3">
        <div class="col-md-6 col-lg-3">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="fw-semibold mb-1"><i class="bi bi-key me-2"></i>Auth</div>
              <div class="small-muted">Login, sessions, RBAC</div>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="fw-semibold mb-1"><i class="bi bi-people me-2"></i>Users</div>
              <div class="small-muted">Students and faculty CRUD</div>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="fw-semibold mb-1"><i class="bi bi-journal-bookmark me-2"></i>Academics</div>
              <div class="small-muted">Courses, subjects, enrollments</div>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="fw-semibold mb-1"><i class="bi bi-calendar2-week me-2"></i>Timetable</div>
              <div class="small-muted">Class routine management</div>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="fw-semibold mb-1"><i class="bi bi-check2-square me-2"></i>Attendance</div>
              <div class="small-muted">Sessions, details, reports</div>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="fw-semibold mb-1"><i class="bi bi-award me-2"></i>Exams</div>
              <div class="small-muted">Exams, marks, results</div>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="fw-semibold mb-1"><i class="bi bi-cash-coin me-2"></i>Fees</div>
              <div class="small-muted">Fees, invoices, payments</div>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="card border-0 rounded-4 card-hover h-100">
            <div class="card-body p-4">
              <div class="fw-semibold mb-1"><i class="bi bi-chat-dots me-2"></i>Messaging</div>
              <div class="small-muted">Student and faculty chat</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- Footer -->
  <footer class="py-4">
    <div class="container">
      <div class="glass rounded-4 p-3 px-md-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <div class="small-muted">© <span id="year"></span> OmniCollege CMS</div>
        <div class="d-flex gap-3">
          <a class="link-dark text-decoration-none" href="#top">Top</a>
          <a class="link-dark text-decoration-none" href="login.php">Login</a>
          <a class="link-dark text-decoration-none" href="#features">Features</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Demo Modal -->
  <div class="modal fade" id="demoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content rounded-4 border-0">
        <div class="modal-header">
          <h5 class="modal-title fw-semibold">Demo Navigation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <a class="d-block p-3 rounded-4 border text-decoration-none text-dark" href="login.php">
                <div class="fw-semibold"><i class="bi bi-shield-lock me-2"></i>Admin</div>
                <div class="small-muted">CRUD + reports + settings</div>
              </a>
            </div>
            <div class="col-md-4">
              <a class="d-block p-3 rounded-4 border text-decoration-none text-dark" href="login.php">
                <div class="fw-semibold"><i class="bi bi-person-workspace me-2"></i>Faculty</div>
                <div class="small-muted">Attendance + marks + uploads</div>
              </a>
            </div>
            <div class="col-md-4">
              <a class="d-block p-3 rounded-4 border text-decoration-none text-dark" href="login.php">
                <div class="fw-semibold"><i class="bi bi-mortarboard me-2"></i>Student</div>
                <div class="small-muted">Routine + results + invoices</div>
              </a>
            </div>
          </div>

          <hr class="my-4" />

          <div class="small-muted">
            Next step: connect this homepage buttons to PHP routes, then build dashboards per role.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Close</button>
          <a class="btn btn-primary" href="login.php">Go to Login</a>
        </div>
      </div>
    </div>
  </div>

  <?php include('./partials/footer.php')?>




