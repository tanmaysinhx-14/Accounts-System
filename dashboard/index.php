<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Dashboard
  if(checkForEquality(checkLoginStatus($db1), true, 'strict')) {
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
  }
?>

<?php // Headers
  $page_title = "Dashboard | careerinstitute.co.in";

  require_once '../components/header.php';
?>

<?php if(checkForEquality(checkLoginStatus($db1), true, 'strict')): // User is logged in ?>
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
                    <a class="text-decoration-none text-dark lead" href="../dailyClassRecords/">Daily Class Routines</a>
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

<?php elseif(checkForEquality(checkLoginStatus($db1), false, 'strict')): // User Logged Out ?>
  <section class="section-border border-primary">
    <div class="container-lg">
      <div class="d-flex flex-column align-items-center justify-content-center min-vh-100">
        <svg height="100px" width="100px" version="1.1" id="_x32_" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve" fill="#000000">
          <g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <style type="text/css"> .st0{fill:#000000;} </style> <g> <path class="st0" d="M256,0C114.616,0,0,114.612,0,256s114.616,256,256,256s256-114.612,256-256S397.385,0,256,0z M207.678,378.794 c0-17.612,14.281-31.893,31.893-31.893c17.599,0,31.88,14.281,31.88,31.893c0,17.595-14.281,31.884-31.88,31.884 C221.959,410.678,207.678,396.389,207.678,378.794z M343.625,218.852c-3.596,9.793-8.802,18.289-14.695,25.356 c-11.847,14.148-25.888,22.718-37.442,29.041c-7.719,4.174-14.533,7.389-18.769,9.769c-2.905,1.604-4.479,2.95-5.256,3.826 c-0.768,0.926-1.029,1.306-1.496,2.826c-0.273,1.009-0.558,2.612-0.558,5.091c0,6.868,0,12.512,0,12.512 c0,6.472-5.248,11.728-11.723,11.728h-28.252c-6.475,0-11.732-5.256-11.732-11.728c0,0,0-5.645,0-12.512 c0-6.438,0.752-12.744,2.405-18.777c1.636-6.008,4.215-11.718,7.508-16.694c6.599-10.083,15.542-16.802,23.984-21.48 c7.401-4.074,14.723-7.455,21.516-11.281c6.789-3.793,12.843-7.91,17.302-12.372c2.988-2.975,5.31-6.05,7.087-9.52 c2.335-4.628,3.955-10.067,3.992-18.389c0.012-2.463-0.698-5.702-2.632-9.405c-1.926-3.686-5.066-7.694-9.264-11.29 c-8.45-7.248-20.843-12.545-35.054-12.521c-16.285,0.058-27.186,3.876-35.587,8.62c-8.36,4.776-11.029,9.595-11.029,9.595 c-4.268,3.718-10.603,3.85-15.025,0.314l-21.71-17.397c-2.719-2.173-4.322-5.438-4.396-8.926c-0.063-3.479,1.425-6.81,4.061-9.099 c0,0,6.765-10.43,22.451-19.38c15.62-8.992,36.322-15.488,61.236-15.429c20.215,0,38.839,5.562,54.268,14.661 c15.434,9.148,27.897,21.744,35.851,36.876c5.281,10.074,8.525,21.43,8.533,33.38C349.211,198.042,347.248,209.058,343.625,218.852 z"></path> </g> </g>
        </svg>
        <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
          <h1 class="display-3 fw-bold text-center">
            Session Expired.
          </h1>
          <p class="mb-5 text-center text-body-secondary">
            You are currently logged out. Please sign in to access this page.
          </p>
          <div class="text-center my-7">
            <a class="btn btn-primary rounded-pill ff-sourcesans3" href="../login/">
              Back to Login
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>

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