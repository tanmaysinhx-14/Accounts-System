<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
    'required_roles' => ['student', 'faculty', 'admin']
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Dashboard
  if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')) {
    function fetchDashboardNotifications(PDO $db, string $role, string $studentBatch = ''): array {
      try {
        if (checkForEquality($role, 'student', 'strict')) {
          /*
          * Fetch non-expired student notifications, then filter in PHP
          * against the student's own batch code.
          * Using PHP-side filter keeps this compatible with any MariaDB version
          * and avoids JSON_CONTAINS edge cases with older drivers.
          */
          $stmt = $db->prepare(
            'SELECT notification_heading,
                    notification_subheading,
                    notification_expire_timestamp,
                    notification_batch_value
            FROM   notification_records
            WHERE  notification_user_role        = :role
              AND  notification_expire_timestamp > NOW()
            ORDER  BY notification_expire_timestamp ASC'
          );
          $stmt->bindValue(':role', 'student', PDO::PARAM_STR);
          $stmt->execute();
          $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

          return array_values(array_filter($rows, function (array $row) use ($studentBatch): bool {
            $batches = json_decode($row['notification_batch_value'] ?? '[]', true) ?: [];
            return in_array($studentBatch, $batches, true);
          }));
        }

        // Faculty → all non-expired faculty notifications (no batch check needed)
        // Admin   → all non-expired notifications of any role
        $whereRole = (checkForEquality($role, 'faculty', 'strict'))
          ? 'WHERE notification_user_role = :role AND notification_expire_timestamp > NOW()'
          : 'WHERE notification_expire_timestamp > NOW()'; // admin sees all

        $stmt = $db->prepare(
          "SELECT notification_heading,
                  notification_subheading,
                  notification_expire_timestamp,
                  notification_user_role,
                  notification_batch_value
          FROM   notification_records
          $whereRole
          ORDER  BY notification_expire_timestamp ASC"
        );
        if (checkForEquality($role, 'faculty', 'strict')) {
          $stmt->bindValue(':role', 'faculty', PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      }
      catch (PDOException) {
        return [];
      }
    }  
    $dashboardNotifications = fetchDashboardNotifications($db2, getUserRoleUsingUsercode($_SESSION['usercode']), $userRecord['student_batch_details']);
  }
?>

<?php // Headers
  $page_title = "Dashboard | careerinstitute.co.in";

  require_once '../components/header.php';
?>
<?php if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')): ?>
  <!-- Student Dashboard Here -->
  <?php if(checkForEquality((int)$userRecord['student_has_updated_account_profile'], 0, 'strict')): ?>
    <section class="section-border border-primary min-vh-100">
      <main class="pt-8 pt-md-11 pb-10 pb-md-15 bg-primary">
        <div class="shape shape-blur-3 text-white">
          <svg viewBox="0 0 1738 487" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 0h1420.92s713.43 457.505 0 485.868C707.502 514.231 0 0 0 0z" fill="url(#paint0_linear)"></path>
            <defs>
              <linearGradient id="paint0_linear" x1="0" y1="0" x2="1049.98" y2="912.68" gradientUnits="userSpaceOnUse">
                <stop stop-color="currentColor" stop-opacity=".075"></stop>
                <stop offset="1" stop-color="currentColor" stop-opacity="0"></stop>
              </linearGradient>
            </defs>
          </svg>      
        </div>

        <div class="container-lg text-white">
          <div class="row justify-content-center px-5">
            <div class="col-12 col-md-10">
              <p class="display-2 mb-3 fw-bold text-white">Complete your profile to get started!</p>
              <p class="my-7 lead">
                Welcome! Before you dive into our services, we just need a few details to personalize your experience.
              </p>
              <div class="row mt-10 mb-3">
                <a href="../profile/" class="col-auto btn btn-warning rounded-pill mx-2 px-5 py-2">Complete Your Profile</a>
                <a href="../logout/" class="col-auto btn btn-danger rounded-pill mx-2 px-5 py-2">Logout</a>
              </div>
            </div>
          </div>
        </div>
      </main>
    </section>

  <?php elseif(checkForEquality((int)$userRecord['student_has_updated_account_profile'], 1, 'strict')): ?>
    <section class="section-border border-primary min-vh-100">
      <main class="pt-8 pt-md-11 pb-10 pb-md-15 bg-primary">
        <div class="shape shape-blur-3 text-white">
          <svg viewBox="0 0 1738 487" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 0h1420.92s713.43 457.505 0 485.868C707.502 514.231 0 0 0 0z" fill="url(#paint0_linear)"></path>
            <defs>
              <linearGradient id="paint0_linear" x1="0" y1="0" x2="1049.98" y2="912.68" gradientUnits="userSpaceOnUse">
                <stop stop-color="currentColor" stop-opacity=".075"></stop>
                <stop offset="1" stop-color="currentColor" stop-opacity="0"></stop>
              </linearGradient>
            </defs>
          </svg>      
        </div>

        <div class="container">
          <div class="row justify-content-center px-5">
            <div class="col-12 col-md-10">
              <span class="badge py-2 px-4 mb-5 bg-white rounded-pill text-primary fs-sm fw-bold me-4">
                Student Dashboard
              </span>
              <style>
                .notif-arrow {animation: moveArrow 1.2s ease-in-out infinite;}

                @keyframes moveArrow {
                  0% { transform: translate(0, 0); opacity: 1; }
                  50% { transform: translate(5px, -5px);  }
                  100% { transform: translate(0, 0); opacity: 1; }
                }
              </style>
              <button class="badge bg-success rounded-pill border-0 px-4 mb-5 fs-sm fw-bold"
                      onclick="openDialog('notifDialog')">
                Notifications
                <svg class="notif-arrow"width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M7 17L17 7M17 7H8M17 7V16" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g>
                </svg>
              </button>
              <h1 class="display-2 text-white">
                Welcome, 
                <span class="fw-bold">
                  <?php echo escapeOutput($userRecord['student_username'] ?? 'User!'); ?>
                </span>
              </h1>
              <p class="lead text-white text-opacity-80 mb-6 mb-md-8">
                Access most of the feature at one glance.
              </p>
            </div>
          </div>
        </div>
      </main>

      <div class="position-relative">
        <div class="shape shape-bottom shape-fluid-x text-light">
          <svg viewBox="0 0 2880 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 48h2880V0h-720C1442.5 52 720 0 720 0H0v48z" fill="currentColor"></path>
          </svg>      
        </div>
      </div>


      <main class="mt-n7 mt-md-n15 mb-10">
        <div class="container mt-5">
          <div class="row justify-content-center gx-4 gap-5 gap-lg-0">

          <!-- STUDENT ACADEMIC SERVICES -->
            <div class="col-12 col-md col-lg-4">
              <div class="card shadow-lg mb-6 mb-lg-0 h-100">
                <div class="card-body">
                  <div class="text-center mb-7">
                    <span class="badge rounded-pill text-bg-primary-subtle">
                      <span class="h6 text-uppercase">Academic Services</span>
                    </span>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../testMarksheets/">Test Marksheets</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../courseMaterials/">Course Materials</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" 
                        href="../attendance/"
                        target="_blank">Attendance</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../routines/">
                      Class Routine for <?php echo prettyPrintClassCode($userRecord['student_batch_details'] ?? '<span class="text-danger">Error</span>'); ?>
                    </a>
                  </div>
                </div>
              </div>
            </div>

          <!-- STUDENT ACCOUNT SERVICES -->
            <div class="col-12 col-md col-lg-4">
              <div class="card shadow-lg mb-6 mb-lg-0 h-100">
                <div class="card-body">
                  <div class="text-center mb-7">
                    <span class="badge rounded-pill text-bg-primary-subtle">
                      <span class="h6 text-uppercase">Account Management</span>
                    </span>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../profile/">Account Profile</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../changePassword/">Change Password</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../deactivation/">Deactivate Account</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../logout/">Logout</a>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </main>
    </section>
    
  <?php endif ?>

<?php elseif(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'faculty', 'strict')): ?>
  <!-- Faculty Dashboard Here -->
  <section class="section-border border-primary min-vh-100">
    <main class="pt-8 pt-md-11 pb-10 pb-md-15 bg-primary">
      <div class="shape shape-blur-3 text-white">
        <svg viewBox="0 0 1738 487" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0 0h1420.92s713.43 457.505 0 485.868C707.502 514.231 0 0 0 0z" fill="url(#paint0_linear)"></path>
          <defs>
            <linearGradient id="paint0_linear" x1="0" y1="0" x2="1049.98" y2="912.68" gradientUnits="userSpaceOnUse">
              <stop stop-color="currentColor" stop-opacity=".075"></stop>
              <stop offset="1" stop-color="currentColor" stop-opacity="0"></stop>
            </linearGradient>
          </defs>
        </svg>
      </div>

      <div class="container">
        <div class="row justify-content-center px-5">
          <div class="col-12 col-md-10">
            <span class="badge py-2 px-4 mb-5 bg-white rounded-pill text-primary fs-sm fw-bold">
              Faculty Dashboard
            </span>
            <h1 class="display-2 text-white">
              Welcome,
              <span class="fw-bold">
                <?php echo escapeOutput($userRecord['faculty_name'] ?? 'Faculty'); ?>
              </span>
            </h1>
            <p class="lead text-white text-opacity-80 mb-6 mb-md-8">
              Keep teaching tools, attendance tracking, and account settings within quick reach.
            </p>
          </div>
        </div>
      </div>
    </main>

    <div class="position-relative">
      <div class="shape shape-bottom shape-fluid-x text-light">
        <svg viewBox="0 0 2880 48" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0 48h2880V0h-720C1442.5 52 720 0 720 0H0v48z" fill="currentColor"></path>
        </svg>
      </div>
    </div>


    <main class="mt-n8 mt-md-n14 pb-10">
      <div class="container mt-5">
        <div class="row gx-4">

          <div class="col-12 col-lg-4">
            <div class="card shadow-lg mb-6 mb-lg-0 h-100">
              <div class="card-body">
                <div class="text-center mb-7">
                  <span class="badge rounded-pill text-bg-primary-subtle">
                    <span class="h6 text-uppercase">Teaching Tools</span>
                  </span>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <i class="fe fe-arrow-right"></i>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../attendance/">Batch Attendance Viewer</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <i class="fe fe-arrow-right"></i>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../routines/">Routine Viewer</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <i class="fe fe-arrow-right"></i>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../dailyClassRecords/">Daily Class Records</a>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4">
            <div class="card shadow-lg mb-6 mb-lg-0 h-100">
              <div class="card-body">
                <div class="text-center mb-7">
                  <span class="badge rounded-pill text-bg-primary-subtle">
                    <span class="h6 text-uppercase">Account</span>
                  </span>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <i class="fe fe-arrow-right"></i>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../profile/">Account Profile</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <i class="fe fe-arrow-right"></i>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../changePassword/">Change Password</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <i class="fe fe-arrow-right"></i>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../logout/">Logout</a>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4">
            <div class="card shadow-lg mb-6 mb-lg-0 h-100">
              <div class="card-body">
                <div class="text-center mb-7">
                  <span class="badge rounded-pill text-bg-primary-subtle">
                    <span class="h6 text-uppercase">Current Status</span>
                  </span>
                </div>
                <p class="lead text-dark mb-3">
                  Username:
                  <span class="fw-bold">
                    <?php echo escapeOutput($userRecord['faculty_username'] ?? 'Not set'); ?>
                  </span>
                </p>
                <p class="text-gray-700 mb-3">
                  Email: <?php echo escapeOutput($userRecord['faculty_email'] ?? 'Not available'); ?>
                </p>
                <p class="text-gray-700 mb-3">
                  Profile setup:
                  <span class="fw-semibold">
                    <?php echo checkForEquality((int) ($userRecord['faculty_has_updated_account_profile'] ?? 0), 1, 'strict') ? 'Completed' : 'Needs review'; ?>
                  </span>
                </p>
                <p class="text-gray-700 mb-0">
                  Email updates:
                  <span class="fw-semibold">
                    <?php echo checkForEquality((int) ($userRecord['faculty_has_opted_email_communication'] ?? 0), 1, 'strict') ? 'Enabled' : 'Disabled'; ?>
                  </span>
                </p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </main>
  </section>

<?php elseif(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): ?>
  <!-- Admin Dashboard Here -->
  <section class="section-border border-primary min-vh-100">
    <main class="pt-8 pt-md-11 pb-10 pb-md-15 bg-primary">
      <div class="shape shape-blur-3 text-white">
        <svg viewBox="0 0 1738 487" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0 0h1420.92s713.43 457.505 0 485.868C707.502 514.231 0 0 0 0z" fill="url(#paint0_linear)"></path>
          <defs>
            <linearGradient id="paint0_linear" x1="0" y1="0" x2="1049.98" y2="912.68" gradientUnits="userSpaceOnUse">
              <stop stop-color="currentColor" stop-opacity=".075"></stop>
              <stop offset="1" stop-color="currentColor" stop-opacity="0"></stop>
            </linearGradient>
          </defs>
        </svg>
      </div>

      <div class="container">
        <div class="row justify-content-center px-5">
          <div class="col-12 col-md-10">
            <span class="badge py-2 px-4 mb-5 bg-white rounded-pill text-primary fs-sm fw-bold">
            Admin Dashboard
          </span>
            <h1 class="display-2 text-white">
              Welcome, 
              <span class="fw-bold">
                <?php echo escapeOutput($userRecord['admin_name'] ?? 'User!'); ?>
              </span>
            </h1>
            <p class="lead text-white text-opacity-80 mb-6 mb-md-8">
              Access most of the feature at one glance.
            </p>
          </div>
        </div>
      </div>
    </main>

    <div class="position-relative">
      <div class="shape shape-bottom shape-fluid-x text-light">
        <svg viewBox="0 0 2880 48" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0 48h2880V0h-720C1442.5 52 720 0 720 0H0v48z" fill="currentColor"></path>
        </svg>
      </div>
    </div>

    <main class="mt-n8 mt-md-n14 pb-10">
      <div class="container">
        <div class="row gx-4">

          <div class="col-12 col-lg-4">
            <div class="card shadow-lg mb-6 mb-lg-0">
              <div class="card-body">
                <div class="text-center mb-7">
                  <span class="badge rounded-pill text-bg-primary-subtle">
                    <span class="h6 text-uppercase">Student Services</span>
                  </span>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../approvalManager/">Approvals</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../attendance/">Attendance</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../courseMaterials/">Course Materials</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../routines/">Routines</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../userManager/?view=viewStudents">Student List</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../testMarksheets/">Test Marksheets</a>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4">
            <div class="card shadow-lg mb-6 mb-lg-0">
              <div class="card-body">
                <div class="text-center mb-7">
                  <span class="badge rounded-pill text-bg-primary-subtle">
                    <span class="h6 text-uppercase">Faculty Services</span>
                  </span>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../dailyClassRecords/">Daily Class Records</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../facultyManager/">Faculty Manager</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../userManager/?view=viewStudents">Facuulty List</a>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4">
            <div class="card shadow-lg mb-6 mb-lg-0">
              <div class="card-body">
                <div class="text-center mb-7">
                  <span class="badge rounded-pill text-bg-primary-subtle">
                    <span class="h6 text-uppercase">Admin & Account</span>
                  </span>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../batchlistManager/">Batchlist Manager</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../enquiryManager/">Enquiry Manager</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../notifications/">Notifications</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../changePassword/">Change Password</a>
                </div>
                <div class="d-flex my-5">
                  <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                    <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier"> 
                        <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                      </g>
                    </svg>
                  </div>
                  <a class="text-decoration-none text-dark lead" href="../logout/">Logout</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </section>

<?php endif ?>

<!-- NOTIFICATION DIALOG -->
<dialog class="ci-dialog" id="notifDialog">
  <div class="ci-dialog__header">
    <h5 class="fw-semibold mb-0">Notifications</h5>
    <button class="ci-dialog__close" onclick="closeDialog('notifDialog')"></button>
  </div>

  <div class="ci-dialog__body">

    <?php if (!empty($dashboardNotifications)): ?>
      <div class="d-flex flex-column gap-3">

        <?php foreach ($dashboardNotifications as $notif): 
          $nHeading = htmlspecialchars_decode($notif['notification_heading'] ?? '');
          $nSub     = htmlspecialchars_decode($notif['notification_subheading'] ?? '');
          $nExpiry  = $notif['notification_expire_timestamp'] ?? '';
          $expiresIn = $nExpiry ? ceil((strtotime($nExpiry) - time()) / 86400) : null;
        ?>

        <div class="border rounded-3 p-3">
          <div class="fw-semibold"><?php echo $nHeading; ?></div>
          <div class="text-body-secondary fs-sm mt-1"><?php echo $nSub; ?></div>
        </div>

        <?php endforeach; ?>

      </div>
    <?php else: ?>
      <div class="text-center py-5 text-body-secondary">
        No notifications available
      </div>
    <?php endif; ?>

  </div>

  <div class="ci-dialog__footer">
    <button class="btn btn-outline-secondary rounded-pill px-4"
            onclick="closeDialog('notifDialog')">
      Close
    </button>
  </div>
</dialog>

<script type="text/javascript">
  function openDialog(id) {
    document.getElementById(id).showModal();
  }

  function closeDialog(id) {
    document.getElementById(id).close();
  }
</script>

<script>
  function dismissNotif(index) {
    const el = document.getElementById('notif-' + index);
    if (el) {
      el.style.transition = 'opacity .2s ease, transform .2s ease';
      el.style.opacity    = '0';
      el.style.transform  = 'translateY(-6px)';
      setTimeout(function () {
        el.remove();
        const list = document.getElementById('notifList');
        if (list && !list.querySelector('.card')) {
          list.closest('.container').remove();
        }
      }, 200);
    }
  }
</script>

<?php require_once '../components/footer.php'; ?>