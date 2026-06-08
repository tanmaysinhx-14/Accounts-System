<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Logout
  if(checkForEquality(checkLoginStatus($db1), true, 'strict')) {
    $roleMap = $currentUserRole !== null ? getRoleDatabaseMap($currentUserRole) : null;
    $activeSessionID = $roleMap !== null ? ($userRecord[$roleMap['current_session_column']] ?? ($_SESSION['session_ID'] ?? null)) : ($_SESSION['session_ID'] ?? null);

    
    if (!is_null($roleMap) && !is_null($activeSessionID)) {
      $deviceRecord = fetchUserRecord($db1, $roleMap['device_table'], $roleMap['device_session_column'], $activeSessionID);

      $rememberMeEnabled = checkForEquality((int) ($deviceRecord[$roleMap['remember_me_column']] ?? 0), 1, 'strict');
    }
    else $rememberMeEnabled = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $csrfToken = escapeOutput($_POST['csrf_token']) ?? null;

      if(validateCsrfToken($csrfToken)) {
        unsetCsrfToken();
        
        if (isset($_POST['logoutUser'])) { // Logout the User (Save the REMEMBER ME data)
          $logoutSucceeded = true;

          if (!is_null($currentUserRole) && !is_null($activeSessionID)) {
            clearAuthenticationSession(true);
            redirectTo('../login/', 0);
          }
        }

        elseif (isset($_POST['clearRememberMe'])) { // Clear REMEMBER ME data
          if (!is_null($currentUserRole) && !is_null($activeSessionID)) {
            removeRememberMeDevice($db1, $db2, $currentUserRole, $_SESSION['usercode'], $activeSessionID);

            setToast('Saved login details cleared successfully.', 'success', 7000);
            redirectTo('./', 0);
          } 
          else {
            destroyCookie('rememberMe');
          }
        }
      } 
      else setToast('Page reload activity detected. Please try again.', 'danger', 7000);
    }
  }
?>

<?php // Headers
  $page_title = "Logout | careerinstitute.co.in";

  require_once '../components/header.php';

  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'Logout';

  require_once '../components/breadcrumb.php';
?>

<?php if(checkForEquality(checkLoginStatus($db1), true, 'strict')): // User Logged In ?>
  <section class="section-border border-primary min-vh-100">
    <div class="container-lg">
      <div class="col-12 col-lg-8 col-md-10 px-8 py-8">
        <span class="badge rounded-pill text-bg-primary-subtle mb-4">
          <?php echo ucfirst((string) $currentUserRole); ?> Account
        </span>
        <h1 class="display-4 fw-bold">
          Sign out from this session?
        </h1>
        <p class="mb-4 text-body-secondary">
          You are signed in as
          <span class="fw-semibold"><?php echo escapeOutput($_SESSION['email'] ?? ''); ?></span>.
          <?php if ($rememberMeEnabled): ?>
            Saved login is active for this device, and signing out will remove it from this browser for safety.
          <?php else: ?>
            Logging out will remove the current saved session from this device.
          <?php endif; ?>
        </p>

        <form method="POST" action="./">
          <input type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

          <div class="d-flex flex-wrap align-items-start gap-3 mb-4">
            <button class="btn btn-danger lead col-auto"
                    name="logoutUser"
                    type="submit">
              Logout only
            </button>

            <?php if (isset($_COOKIE['rememberMe'])): ?>
              <button class="btn btn-warning lead col-auto"
                      name="clearRememberMe"
                      type="submit">
                Logout and Clear Saved Login Details
              </button>
            <?php endif; ?>

            <a class="btn btn-outline-primary lead col-auto" href="../dashboard/">
              Back to Dashboard
            </a>
          </div>
        </form>
      </div>
    </div>
  </section>

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

<?php require_once '../components/footer.php'; ?>