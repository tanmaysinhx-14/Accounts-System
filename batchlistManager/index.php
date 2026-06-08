<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Active Batchlist 
  if(checkForEquality(checkLoginStatus($db1), true, 'strict')) {
    if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')) {
      if (isset($_POST['submitActiveBatchlist'])) {
        $csrf_token = escapeOutput($_POST['csrf_token']) ?? null;
        if(validateCsrfToken($csrf_token)) {
          unsetCsrfToken();

          $activeBatchlist = [];

          if (isset($_POST['CBSEOptions'])) {
            foreach ($_POST['CBSEOptions'] as $cbseOption) {
              array_push($activeBatchlist, $cbseOption);
            }
          }

          if (isset($_POST['BSEBOptions'])) {
            foreach ($_POST['BSEBOptions'] as $bsebOption) {
              array_push($activeBatchlist, $bsebOption);
            }
          }

          if (isset($_POST['ICSEOptions'])) {
            foreach ($_POST['ICSEOptions'] as $icseOption) {
              array_push($activeBatchlist, $icseOption);
            }
          }

          $currentAttemptForUpdatingBatchlist = 0;
          $maxAttemptsForUpdatingBatchlist = 3;

          while($currentAttemptForUpdatingBatchlist < $maxAttemptsForUpdatingBatchlist) {
            try {
              $STMT_updateActiveBatchlist = "UPDATE app_configurations
                                            SET value = :value
                                            WHERE parameter = :parameter";

              $updateActiveBatchlist = $db1->prepare($STMT_updateActiveBatchlist);

              $updateActiveBatchlist->bindValue(':value', json_encode($activeBatchlist), PDO::PARAM_STR);
              $updateActiveBatchlist->bindValue(':parameter', 'active_batchlist', PDO::PARAM_STR);

              if ($updateActiveBatchlist->execute()) {
                setToast('Batchlist Updated Successfully.', 'success', 7000);
                break;
              }
            }
            catch (PDOException $ex) {
              if(!isRetryablePdoException($ex)) {
                setToast('Error occured while Updating Batchlist. Contact Admin.', 'danger', 7000);

                logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while Updating Batchlist: ' . $ex->getMessage());

                break;
              }
            }
            $currentAttemptForUpdatingBatchlist++;
            sleep(5);
          }
          if ($currentAttemptForUpdatingBatchlist >= $maxAttemptsForUpdatingBatchlist) {
            setToast('Error occured while Updating Batchlist. Contact Admin.', 'danger', 7000);
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      function checkForActiveBatches(array $currentActiveBatches, string $batchToCheck) {
        foreach ($currentActiveBatches as $activeBatch) {
          if (checkForEquality($activeBatch, $batchToCheck, 'strict')) {
            return true;
          }
        }
      }

      $currentActiveBatches = ($db1);
    }
  }
?>

<?php // Headers 
  $page_title = "Activation | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
  
  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'Active Batchlist';
  
  require_once '../components/breadcrumb.php';
?>

<?php if(checkForEquality(checkLoginStatus($db1), true, 'strict')): // User Logged In ?>
  <?php if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): // For Admin ?>
    <section class="section-border border-primary min-vh-100">
      <div class="container-fluid d-flex flex-column">
        <div class="row align-items-center justify-content-center gx-0">
          <div class="col-12 px-md-12 px-8 px-md-8 py-8 py-md-8">
            <form method="POST" action="./">
              <div class="d-flex row mb-10">
                <!-- CBSE Batch Options -->
                <div class="col-12 col-lg-4">
                  <div class="display-4 text-center fw-bold mb-7">
                    CBSE
                  </div>

                  <!-- CBSE Single Batch Options -->
                  <div class="form-group row">
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="5-CBSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '5-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">V CBSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="6-CBSE-NULL"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '6-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">VI CBSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="7-CBSE-NULL"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '7-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">VII CBSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="8-CBSE-NULL"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '8-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">VIII CBSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="9-CBSE-NULL"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '9-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">IX CBSE</label>
                    </div>
                  </div>

                  <!-- CBSE Multiple Batch Options -->
                  <hr class="m-0 mt-3">

                  <!-- CBSE Xth Options -->
                  <div class="form-group row justify-content-center">
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="10-CBSE-NULL"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">X CBSE</label>
                    </div>
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="10-CBSE-D"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-CBSE-D') ? 'checked' : ''; ?> />
                      <label class="form-check-label">X CBSE (D)</label>
                    </div>

                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="10-CBSE-E"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-CBSE-E') ? 'checked' : ''; ?> />
                      <label class="form-check-label">X CBSE (E)</label>
                    </div>

                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="10-CBSE-P"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-CBSE-P') ? 'checked' : ''; ?> />
                      <label class="form-check-label">X CBSE (P)</label>
                    </div>
                  </div>

                  <hr class="m-0 mt-3">

                  <!-- CBSE XIth Options -->
                  <div class="form-group row">
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="11-CBSE-Science"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '11-CBSE-Science') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">XI CBSE (Science)</label>
                    </div>
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="11-CBSE-Arts"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '11-CBSE-Arts') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">XI CBSE (Arts)</label>
                    </div>
                  </div>

                  <!-- CBSE XIIth Options -->
                  <hr class="m-0 mt-3">
                  <div class="form-group row">
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="12-CBSE-Science" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '12-CBSE-Science') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">XII CBSE (Science)</label>
                    </div>
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="12-CBSE-Arts"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '12-CBSE-Arts') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">XII CBSE (Arts)</label>
                    </div>
                  </div>
                </div>

                <!-- BSEB Batch Options -->
                <div class="col-12 col-lg-4">
                  <div class="display-4 text-center fw-bold mb-7">
                    BSEB
                  </div>

                  <!-- BSEB Single Batch Options -->
                  <div class="form-group row">
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="7-BSEB-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '7-BSEB-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">VII BSEB</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="8-BSEB-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '8-BSEB-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">VIII BSEB</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="9-BSEB-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '9-BSEB-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">IX BSEB</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="10-BSEB-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-BSEB-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">X BSEB</label>
                    </div>
                  </div>

                  <!-- BSEB XIth Options -->
                  <hr class="m-0 mt-3">

                  <div class="form-group row">
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="11-BSEB-Science" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '11-BSEB-Science') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">XI BSEB (Science)</label>
                    </div>
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="11-BSEB-Arts" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '11-BSEB-Arts') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">XI BSEB (Arts)</label>
                    </div>
                  </div>

                  <hr class="m-0 mt-3">

                  <!-- BSEB XIIth Options -->
                  <div class="form-group row">
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="12-BSEB-Science" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '12-BSEB-Science') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">XII BSEB (Science)</label>
                    </div>
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="12-BSEB-Arts" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '12-BSEB-Arts') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">XI BSEB (Arts)</label>
                    </div>
                  </div>
                </div>

                <!-- ICSE Batch Options -->
                <div class="col-12 col-lg-4">
                  <div class="display-4 text-center fw-bold mb-7">
                    ICSE
                  </div>

                  <!-- ICSE Single Batch Options -->
                  <div class="form-group row">
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="5-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '5-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">V ICSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="6-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '6-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">VI ICSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="7-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '7-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">VII ICSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="8-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '8-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">VIII ICSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="9-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '9-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">IX ICSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="10-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">X ICSE</label>
                    </div>
                  </div>
                </div>
              </div>

              <input type="hidden" 
                     name="csrf_token" 
                     value="<?php echo htmlspecialchars(generateCsrfToken()); ?>"
              />

              <button type="submit" 
                      name="submitActiveBatchlist" 
                      class="btn btn-primary rounded-pill mt-3">
                Update Active Batchlist
              </button>
            </form>
          </div>
        </div>
      </div>
    </section>
  
  <?php else: // Restricted Access for Faculty and Students ?>
    <section class="section-border border-primary">
      <div class="container-lg">
        <div class="d-flex flex-column align-items-center justify-content-center min-vh-100">
          <svg width="100" height="100" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="iconify iconify--emojione" preserveAspectRatio="xMidYMid meet" fill="#000000">
            <g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M62 52c0 5.5-4.5 10-10 10H12C6.5 62 2 57.5 2 52V12C2 6.5 6.5 2 12 2h40c5.5 0 10 4.5 10 10v40z" fill="#ff002f"></path><path fill="#ffffff" d="M50 21.2L42.8 14L32 24.8L21.2 14L14 21.2L24.8 32L14 42.8l7.2 7.2L32 39.2L42.8 50l7.2-7.2L39.2 32z"></path></g>
          </svg>
          <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
            <h1 class="display-3 fw-bold text-center">
              Access Denied.
            </h1>
            <p class="mb-5 text-center text-body-secondary">
              Access to this page is restricted. Unauthorized access attempts may be monitored.
            </p>
            <div class="text-center my-7">
              <a class="btn btn-primary rounded-pill ff-sourcesans3" href="../dashboard/">
                Back to Dashboard
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

  <?php endif; ?>

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