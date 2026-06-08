<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Profile 
  if(checkForEquality(checkLoginStatus($db1), true, 'strict')) {
    if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')) {
      $isProfileEditable = getSecondsPassed($userRecord['student_profile_updating_timestamp']) >= 86400;
      $isBatchEditable = isEligibleForBatchChange($userRecord['student_batch_updating_timestamp']);

      if (isset($_POST['saveUpdatedUsernameBtn'])) {
        $updatedUsername = escapeOutput($_POST['updatedUsername']) ?? null;
        $csrfToken       = escapeOutput($_POST['csrf_token']) ?? null;
        if(validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          if (validateUsername($updatedUsername)) { // Username Validation Successful
            $usernameCheckStatus = checkUserRecord($db1, 'student_details', ['student_username' => $updatedUsername]);

            if(checkForEquality($usernameCheckStatus, true, 'strict')) { // Username Already Exists
              setToast('This Username has already been taken. Please try another one.', 'danger', 7000);
            }
            else { // Unique Username Found
              $currentAttemptForInsertingStudentUsername = 0;
              $maxRetriesForInsertingStudentUsername = 3;
              while ($currentAttemptForInsertingStudentUsername < $maxRetriesForInsertingStudentUsername) {
                try {
                  $db1->beginTransaction();

                  $STMT_updateUsernameInStudentDetails = 'UPDATE student_details 
                                                          SET student_username = :student_username
                                                          WHERE student_usercode = :student_usercode';
                  $insert_usernameInStudentDetails = $db1->prepare($STMT_updateUsernameInStudentDetails);

                  $insert_usernameInStudentDetails->bindValue(':student_username', $updatedUsername, PDO::PARAM_STR);
                  $insert_usernameInStudentDetails->bindValue(':student_usercode', $_SESSION['usercode'], PDO::PARAM_STR);

                  $insert_usernameInStudentDetails->execute();



                  $STMT_updateUsernameInStudentConfigurations = 'UPDATE student_configurations 
                                                                SET student_has_updated_username = :student_has_updated_username
                                                                WHERE student_usercode = :student_usercode';
                  $insert_usernameInStudentConfigurations = $db1->prepare($STMT_updateUsernameInStudentConfigurations);

                  $insert_usernameInStudentConfigurations->bindValue(':student_has_updated_username', 1, PDO::PARAM_BOOL);
                  $insert_usernameInStudentConfigurations->bindValue(':student_usercode', $_SESSION['usercode'], PDO::PARAM_STR);

                  $insert_usernameInStudentConfigurations->execute();

                  if($db1->commit()) {
                    setToast('Username Updated Successfully. Changes will reflect soon.', 'success', 7000);
                    redirectTo('./', 0); // Refresh the page
                    break; // Success
                  }
                }
                catch(PDOException $ex) {
                  if ($db1->inTransaction()) {
                    $db1->rollBack();
                  }

                  if(!isRetryablePdoException($ex)) {
                    setToast('Error occured while Updating Username. Contact Admin.', 'danger', 7000);

                    logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while Updating Username: ' . $ex->getMessage());

                    break;
                  }
                  $currentAttemptForInsertingStudentUsername++;
                  sleep(5);
                }
              }
              if ($currentAttemptForInsertingStudentUsername >= $maxRetriesForInsertingStudentUsername) {
                setToast('Error occured while Updating Username. Contact Admin.', 'danger', 7000);
              }
            }
          }
          else { // Username Validation Failed
            setToast('Enter Username according to the Instructions provided.', 'danger', 7000);
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      elseif (isset($_POST['saveAdditionalDetails'])) {
        $updatedFatherName   = escapeOutput($_POST['fatherName']);
        $updatedGuardianName = escapeOutput($_POST['guardianName']);
        $batchDetails        = escapeOutput($_POST['batchDetails']);
        $updatedSchoolName   = escapeOutput($_POST['schoolName']);
        $updatedStudentBio   = escapeOutput($_POST['studentBio']);

        $csrfToken           = escapeOutput($_POST['csrf_token']) ?? null;
        
        if(validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          if($isProfileEditable) {
            if (validateRequiredFormFields([$updatedFatherName, $updatedGuardianName, $batchDetails, $updatedSchoolName])) {
              $currentTimestamp = getCurrentTimestamp();

              $currentAttemptForUpdatingStudentDetails = 0;
              $maxRetriesForUpdatingStudentDetails = 3;
              while ($currentAttemptForUpdatingStudentDetails < $maxRetriesForUpdatingStudentDetails) {
                try {
                  $db1->beginTransaction();

                  if ($isBatchEditable) { // Student Eligible for Batch Change
                    $STMT_updateStudentDetails = 'UPDATE student_details 
                                                  SET student_father_name       = :student_father_name,
                                                      student_guardian_name     = :student_guardian_name,
                                                      student_batch_details     = :student_batch_details,
                                                      student_school_name       = :student_school_name,
                                                      student_bio              = :student_bio
                                                  WHERE student_usercode = :student_usercode';
                  }
                  else { // Student not eligible for Batch Change
                    $STMT_updateStudentDetails = 'UPDATE student_details 
                                                  SET student_father_name       = :student_father_name,
                                                      student_guardian_name     = :student_guardian_name,
                                                      student_school_name       = :student_school_name,
                                                      student_bio              = :student_bio
                                                  WHERE student_usercode = :student_usercode';
                  }

                  
                  $update_studentDetails = $db1->prepare($STMT_updateStudentDetails);

                  $update_studentDetails->bindValue(':student_father_name',     $updatedFatherName,    PDO::PARAM_STR);
                  $update_studentDetails->bindValue(':student_guardian_name',   $updatedGuardianName,  PDO::PARAM_STR);
                  if ($isBatchEditable) { // Student Eligible for Batch Change
                    $update_studentDetails->bindValue(':student_batch_details', $batchDetails,         PDO::PARAM_STR);
                  }
                  $update_studentDetails->bindValue(':student_school_name',     $updatedSchoolName,    PDO::PARAM_STR);
                  $update_studentDetails->bindValue(':student_bio',             $updatedStudentBio,    PDO::PARAM_STR);
                  $update_studentDetails->bindValue(':student_usercode',        $_SESSION['usercode'], PDO::PARAM_STR);

                  $update_studentDetails->execute();


                  // User Profile has been updated before. No need to change configurations.
                  if (checkForEquality($userRecord['student_has_updated_account_profile'], 0, 'strict')) {
                    $STMT_updateStudentConfiguration = 'UPDATE student_configurations 
                                                        SET student_has_updated_account_profile = :student_has_updated_account_profile
                                                        WHERE student_usercode = :student_usercode';
                    $update_studentConfiguration = $db1->prepare($STMT_updateStudentConfiguration);

                    $update_studentConfiguration->bindValue(':student_has_updated_account_profile', 1, PDO::PARAM_INT);
                    $update_studentConfiguration->bindValue(':student_usercode', $_SESSION['usercode'], PDO::PARAM_STR);

                    $update_studentConfiguration->execute();
                  }



                  $STMT_uploadProfileUpdationTimestamp = 'UPDATE student_timestamps
                                                          SET student_profile_updating_timestamp = :student_profile_updating_timestamp
                                                          WHERE student_usercode = :student_usercode';
                  $uploadProfileUpdationTimestamp = $db1->prepare($STMT_uploadProfileUpdationTimestamp);

                  $uploadProfileUpdationTimestamp->bindValue(':student_profile_updating_timestamp', $currentTimestamp, PDO::PARAM_STR);
                  $uploadProfileUpdationTimestamp->bindValue(':student_usercode', $_SESSION['usercode'], PDO::PARAM_STR);

                  $uploadProfileUpdationTimestamp->execute();


                  if ($db1->commit()) { // Success
                    setToast('Profile Details updated successfully.', 'success', 7000);

                    redirectTo('./', 0);
                    break;
                  }
                }
                catch (PDOException $ex) {
                  if($db1->inTransaction()) {
                    $db1->rollBack();
                  }

                  if(!isRetryablePdoException($ex)) {
                    setToast('Error occured while Updating Additional Details. Contact Admin.', 'danger', 7000);

                    logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while Updating Additional Details: ' . $ex->getMessage());

                    break;
                  }
                  $currentAttemptForUpdatingStudentDetails++;
                  sleep(5);
                }
              }
              if($currentAttemptForUpdatingStudentDetails >= $maxRetriesForUpdatingStudentDetails) {
                setToast('Error occured while Updating Additional Details. Contact Admin.', 'danger', 7000);
              }
            }
            else { // Required Fields are empty
              setToast('One or more required fields are empty!', 'danger', 7000);
            }
          }
          else { // Profile Change Cooldown
            setToast('Profile Details can be changed again after 1 day!', 'danger', 7000);
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      elseif (isset($_POST['saveConfigurations'])) {
        $csrfToken           = escapeOutput($_POST['csrf_token']) ?? null;

        if(validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          $STMT_updateStudentEmailPreference = 'UPDATE student_configurations
                                                SET student_has_opted_email_communication = :student_has_opted_email_communication
                                                WHERE student_usercode = :student_usercode
                                                LIMIT 1';

          $updateStudentEmailPreference = $db1->prepare($STMT_updateStudentEmailPreference);

          if(isset($_POST['emailConfiguration'])) {
            $updateStudentEmailPreference->bindValue(':student_has_opted_email_communication', 1, PDO::PARAM_INT);
          }
          else {
            $updateStudentEmailPreference->bindValue(':student_has_opted_email_communication', 0, PDO::PARAM_INT);
          }
          
          $updateStudentEmailPreference->bindValue(':student_usercode', $_SESSION['usercode'], PDO::PARAM_STR);

          $currentAttemptForUpdatingEmailPreference = 0;
          $maxRetriesForUpdatingEmailPreference = 3;

          while($currentAttemptForUpdatingEmailPreference < $maxRetriesForUpdatingEmailPreference) {
            try {
              if($updateStudentEmailPreference->execute()) {
                setToast('Email Communication Preference saved successfully.', 'success', 7000);
                redirectTo('./', 0); // Refresh the page
                break;
              }
            }
            catch (PDOException $ex) {
              if(!isRetryablePdoException($ex)) {
                setToast('Error occured while Updating Email Preference. Contact Admin.', 'danger', 7000);

                logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while updating Email Preference: ' . $ex->getMessage());

                break;
              }
            }
            $currentAttemptForUpdatingEmailPreference++;
            sleep(5);
          }
          if($currentAttemptForUpdatingEmailPreference >= $maxRetriesForUpdatingEmailPreference) {
            setToast('Error occured while updating Email Preference. Contact Admin.', 'danger', 7000);
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }
    }
  }
?>

<?php // Headers 
  $page_title = "Profile | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
  
  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'Account Profile';
  
  require_once '../components/breadcrumb.php';
?>

<?php if(checkForEquality(checkLoginStatus($db1), true, 'strict')): // User Logged In ?>
  <?php if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')): // For Students ?>

    <!-- USERNAME ENTERING FORM -->
    <?php if(checkForEquality($userRecord['student_has_updated_username'], 0, 'strict')): ?>
      <section class="section-border border-primary min-vh-100">
        <!-- FOR SMALL SCREENS -->  
        <header class="bg-dark pt-9 pb-11 d-md-none">
          <div class="container-lg px-7">
            <div class="d-flex flex-column">
              <div class="col">
                <h1 class="fw-bold text-white mb-2">
                  Account Settings
                </h1>
                <p class="fs-lg text-white text-opacity-75 mb-0">
                  Settings for <span class="text-white fw-bold"><?php echo escapeOutput($_SESSION['email'] ?? null); ?></span>
                </p>
              </div>
              <div class="col-auto mt-7">
                <a class="btn btn-primary rounded-pill text-white" href="../dashboard/">
                  Back to Dashboard
                </a>
              </div>
            </div>
          </div>
        </header>

        <!-- FOR LARGE SCREENS -->
        <header class="bg-dark pt-9 pb-11 d-none d-md-block">
          <div class="container-lg">
            <div class="row align-items-center">
              <div class="col">
                <h1 class="fw-bold text-white mb-2">
                  Account Settings
                </h1>
                <p class="fs-lg text-white text-opacity-75 mb-0">
                  Settings for <span class="text-white fw-bold"><?php echo escapeOutput($_SESSION['email'] ?? null); ?></span>
                </p>
              </div>
              <div class="col-auto">
                <a class="btn btn-primary rounded-pill text-white" href="../dashboard/">
                  Back to Dashboard
                </a>
              </div>
            </div>
          </div>
        </header>

        <main class="py-8 py-md-11 px-5 px-lg-0 my-md-n6">
          <div class="container-lg">
            <div class="row">
              <div class="col-12">
                <div class="card card-bleed shadow-light-lg mb-6">
                  <div class="card-body mb-5">
                    <div class="row align-items-center">
                      <div class="col">
                        <h3 class="fw-bold">Update your Username <text class="text-danger">*</text></h3>
                      </div>
                    </div>
                    <form method="POST" action="./">
                      <input class="form-control bg-body my-5" 
                            type="text" 
                            id="updatedUsername" 
                            name="updatedUsername"
                            placeholder="Enter your Username here." />
                      <div id="updatedUsername" class="form-text">
                        <ul>
                          <li class="text-gray-700">Username must be 3–16 characters long.</li>
                          <li class="text-gray-700">Username must contain only letters and numbers. No Symbols are allowed.</li>
                          <li class="text-gray-700">Username must include at least one letter and one number character.</li>
                        </ul>
                      </div>
                      <input type="hidden" 
                            name="csrf_token" 
                            value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

                      <div class="col-12 col-sm-auto">
                        <button class="mt-7 btn btn-sm bg-primary rounded-pill text-white" 
                                name="saveUpdatedUsernameBtn"
                                type="submit">Save your Preference</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
      </section>
    
    <!-- FIRST TIME PROFILE UPDATING -->
    <?php elseif(checkForEquality($userRecord['student_has_updated_username'], 1, 'strict')): ?>
      <section class="section-border border-primary min-vh-100">  
        <!-- FOR SMALL SCREENS -->  
        <header class="bg-white pt-9 pb-11 d-md-none">
          <div class="container-lg px-7">
            <div class="d-flex flex-column">
              <div class="col">
                <h1 class="fw-bold text-white mb-2">
                  Account Settings
                </h1>
                <p class="fs-lg text-white text-opacity-75 mb-0">
                  Settings for <span class="text-white fw-bold"><?php echo escapeOutput($_SESSION['email'] ?? null); ?></span>
                </p>
              </div>
              <div class="col-auto mt-7">
                <a class="btn btn-primary rounded-pill text-white" href="../dashboard/">
                  Back to Dashboard
                </a>
              </div>
            </div>
          </div>
        </header>

        <!-- FOR LARGE SCREENS -->
        <header class="bg-dark pt-9 pb-11 d-none d-md-block">
          <div class="container-lg">
            <div class="row align-items-center">
              <div class="col">
                <h1 class="fw-bold text-white mb-2">
                  Account Settings
                </h1>
                <p class="fs-lg text-white text-opacity-75 mb-0">
                  Settings for <span class="text-white fw-bold"><?php echo escapeOutput($_SESSION['email'] ?? null); ?></span>
                </p>
              </div>
              <div class="col-auto">
                <a class="btn btn-primary rounded-pill text-white" href="../dashboard/">
                  Back to Dashboard
                </a>
              </div>
            </div>
          </div>
        </header>

        <main class="py-8 py-md-11 px-5 px-lg-0 my-md-n6">
          <div class="container-lg">
            <div class="row">

              <div class="col-12 col-lg-3 d-lg-block">
                <div class="card card-bleed shadow-light-lg">
                  <div class="collapse d-md-block" id="sidenavCollapse">
                    <div class="card-body">
                      <h6 class="fw-bold text-uppercase mb-3">
                        Account
                      </h6>
                      <ul class="card-list list text-gray-700 mb-6">
                        <li class="list-item active">
                          <a class="list-link text-reset" href="#basic" data-scroll='{"offset": 0}'>
                            Basic Information
                          </a>
                        </li>
                        <li class="list-item">
                          <a class="list-link text-reset" href="#additional" data-scroll='{"offset": 0}'>
                            Additional Information
                          </a>
                        </li>
                        <li class="list-item">
                          <a class="list-link text-reset" href="#communication" data-scroll='{"offset": 0}'>
                            Communication Preferences
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-lg-9">
                <!-- BASIC INFORMATION -->
                <div class="card card-border border-primary card-border-xl shadow-light-lg mb-5" id="basic">
                  <div class="card-header">
                    <h4 class="mb-0">Basic Information</h4>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-12 col-md-6">
                        <div class="form-group">
                          <label class="form-label" for="usercode">Student Usercode</label>
                          <input class="form-control bg-body" 
                                type="text" 
                                id="usercode" 
                                value="<?php echo isset($userRecord['student_usercode']) ? escapeOutput($userRecord['student_usercode']) : null; ?>" 
                                disabled />
                        </div>
                      </div>
                      <div class="col-12 col-md-6">
                        <div class="form-group">
                          <label class="form-label" for="email">Student Email</label>
                          <input class="form-control bg-body" 
                                type="text" 
                                id="email" 
                                value="<?php echo isset($userRecord['student_email']) ? escapeOutput($userRecord['student_email']) : null; ?>" 
                                disabled />
                        </div>
                      </div>
                      <div class="col-12 col-md-6">
                        <div class="form-group">
                          <label class="form-label" for="username">Student Username</label>
                          <input class="form-control bg-body" 
                                type="text" 
                                id="username" 
                                value="<?php echo isset($userRecord['student_username']) ? escapeOutput($userRecord['student_username']) : null; ?>" 
                                disabled />
                        </div>
                      </div>
                      <div class="col-12 col-md-6">
                        <div class="form-group">
                          <label class="form-label" for="username">Account Created at: </label>
                          <input class="form-control bg-body" 
                                type="text" 
                                id="username" 
                                value="<?php echo isset($userRecord['student_account_creation_timestamp']) ? escapeOutput($userRecord['student_account_creation_timestamp']) : null; ?>" 
                                disabled />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- ADDITIONAL DETAILS -->
                <div class="card card-border border-primary card-border-xl shadow-light-lg mb-5" id="additional">
                  <div class="card-header">
                    <h4 class="mb-0">Basic Information</h4>
                    <span>(Batch details can be edited every 24 hours)</span>
                  </div>
                  <div class="card-body">
                    <form method="POST" action="./">
                      <div class="row">
                        <div class="col-12 col-md-6">
                          <div class="form-group">
                            <label class="form-label" for="fatherName">Father's Name <text class="text-danger">*</text></label>
                            <input class="form-control"
                                    id="fatherName"
                                    type="text"
                                    name="fatherName"
                                    value="<?php echo isset($userRecord['student_father_name']) ? escapeOutput($userRecord['student_father_name']) : ''; ?>"
                                    placeholder="Enter your Father's Name" 
                                    <?php echo $isProfileEditable ? 'required' : 'disabled'; ?> />
                          </div>
                        </div>
                        <div class="col-12 col-md-6">
                          <div class="form-group">
                            <label class="form-label" for="guardianName">Guardian's Name <text class="text-danger">*</text></label>
                            <input class="form-control" 
                                    id="guardianName" 
                                    type="text" 
                                    name="guardianName"
                                    value="<?php echo isset($userRecord['student_guardian_name']) ? escapeOutput($userRecord['student_guardian_name']) : ''; ?>"
                                    placeholder="Enter your Guardian's Name" 
                                    <?php echo $isProfileEditable ? 'required' : 'disabled'; ?> />
                          </div>
                        </div>
                      </div>

                      <?php if (!$isBatchEditable): ?>
                        <div class="row">
                          <div class="col-auto">
                            <div class="form-group">
                              <button id="batchBtn" 
                                    class="btn btn-primary dropdown-toggle mb-3" 
                                    type="button"
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false" 
                                    disabled>
                                <?php echo isset($userRecord['student_batch_details']) ? prettyPrintClassCode(escapeOutput($userRecord['student_batch_details'])) : 'Select Your Batch Details'; ?> 
                              </button>

                              <input type="hidden" 
                                  name="batchDetails" 
                                  value="<?php echo escapeOutput($userRecord['student_batch_details']) ?? null;  ?>"
                              />
                            </div>
                          </div>
                        </div>

                      <?php else: ?>
                        <div class="row">
                          <div class="col-auto">
                            <div class="form-group">
                              <p class="form-label">Board of Education <span class="text-primary"> (Batch Details can be changed now)</span></p>
                              <button id="boardBtn" class="btn btn-primary dropdown-toggle mb-3" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                      <?php echo $isBatchEditable ? '' : 'disabled'; ?>>
                                Select Board of Education
                              </button>
                              <ul class="dropdown-menu" id="boardMenu">
                                <li><a class="dropdown-item board-option" data-value="CBSE" href="#">CBSE</a></li>
                                <li><a class="dropdown-item board-option" data-value="BSEB" href="#">BSEB</a></li>
                                <li><a class="dropdown-item board-option" data-value="ICSE" href="#">ICSE</a></li>
                              </ul>
                              <input type="hidden" name="boardDetails" id="boardInput">
                            </div>
                          </div>
                          <div class="col-auto">
                            <div class="form-group">
                              <p class="form-label">Batch Details <span class="text-danger">*</span></p>
                              <button id="batchBtn" class="btn btn-primary dropdown-toggle mb-3" type="button" data-bs-toggle="dropdown" aria-expanded="false" disabled aria-disabled="true"
                                      <?php echo $isBatchEditable ? '' : 'disabled'; ?>>
                                Select Your Batch Details
                              </button>
                              <ul class="dropdown-menu" id="batchMenu"></ul>
                              <input type="hidden" name="batchDetails" id="batchInput">
                            </div>
                          </div>
                          <!-- SCRIPTS FOR BATCH ADJUSTMENT -->
                          <?php
                            $array_batchlist = retrieveActiveBatchlist($db2);
                            $batches = json_decode($array_batchlist["value"], true);
                            $batches = array_values($batches);
                            $prettyMap = [];
                            foreach ($batches as $code) {
                                $prettyMap[$code] = prettyPrintClassCode($code);
                            }
                            $js_batches = json_encode($batches, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            $js_pretty  = json_encode($prettyMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                          ?>
                          <script>
                            const ALL_BATCHES = <?php echo $js_batches; ?>;
                            const PRETTY_BATCH_MAP = <?php echo $js_pretty; ?>;

                            function populateBatchMenu(filtered) {
                              const menu = document.getElementById('batchMenu');
                              menu.innerHTML = '';
                              if (!filtered.length) {
                                const li = document.createElement('li');
                                li.innerHTML = '<span class="dropdown-item-text">No batches available for selected board</span>';
                                menu.appendChild(li);
                                return;
                              }
                              filtered.forEach(code => {
                                const pretty = PRETTY_BATCH_MAP[code] || code;
                                const li = document.createElement('li');
                                li.innerHTML = `<a class="dropdown-item batch-option" data-value="${String(code)}" href="#">${String(pretty)}</a>`;
                                menu.appendChild(li);
                              });
                            }

                            document.querySelectorAll('.board-option').forEach(item => {
                              item.addEventListener('click', e => {
                                e.preventDefault();
                                const board = item.getAttribute('data-value').trim();
                                document.getElementById('boardInput').value = board;
                                document.getElementById('boardBtn').innerText = board;
                                const filtered = ALL_BATCHES.filter(code => String(code).includes(board));
                                populateBatchMenu(filtered);
                                const batchBtn = document.getElementById('batchBtn');
                                if (filtered.length) {
                                  batchBtn.removeAttribute('disabled');
                                  batchBtn.setAttribute('aria-disabled', 'false');
                                } else {
                                  batchBtn.setAttribute('disabled', '');
                                  batchBtn.setAttribute('aria-disabled', 'true');
                                }
                              });
                            });

                            document.getElementById('batchMenu').addEventListener('click', e => {
                              const target = e.target;
                              if (target && target.classList.contains('batch-option')) {
                                e.preventDefault();
                                const code = target.getAttribute('data-value').trim();
                                const pretty = PRETTY_BATCH_MAP[code] || code;
                                document.getElementById('batchInput').value = code;
                                document.getElementById('batchBtn').innerText = pretty;
                              }
                            });
                          </script>
                        </div>

                      <?php endif; ?>

                      <div class="row">
                        <div class="col-12 col-md-6">
                          <div class="col mb-4 mb-lg-0">
                            <div class="mb-4 mb-lg-0">
                              <label class="form-label" for="schoolName">School Name <text class="text-danger">*</text></label>
                              <input class="form-control" 
                                    id="schoolName" 
                                    type="text" 
                                    name="schoolName"
                                    value="<?php echo isset($userRecord['student_school_name']) ? escapeOutput($userRecord['student_school_name']) : ''; ?>"
                                    placeholder="Enter your School Name" 
                                    <?php echo $isProfileEditable ? 'required' : 'disabled'; ?> />
                            </div>
                          </div>
                        </div>
                        <div class="col-12 col-md-6">
                          <div class="col mb-4 mb-lg-0">
                            <div class="mb-4 mb-lg-0">
                              <label class="form-label" for="studentBio">Student Bio</label>
                              <input class="form-control" 
                                    id="studentBio" 
                                    type="text" 
                                    name="studentBio"
                                    value="<?php echo isset($userRecord['student_bio']) ? htmlspecialchars_decode($userRecord['student_bio']) : ''; ?>"
                                    placeholder="Enter something about you ..."
                                    <?php echo $isProfileEditable ? '' : 'disabled'; ?> />
                            </div>
                          </div>
                        </div>
                      </div>
                      <input type="hidden" 
                            name="csrf_token" 
                            value="<?php echo htmlspecialchars(generateCsrfToken()); ?>" 
                      />
                          <br>
                      <?php echo $profileAdditionalInfoFormStatus ?? null; ?>
                      <button class="btn btn-sm btn-warning rounded-pill text-dark" 
                                type="submit"
                                name="saveAdditionalDetails" 
                                <?php echo $isProfileEditable ? '' : 'disabled'; ?>>
                        Save Details
                      </button>
                    </form>
                  </div>
                </div>
                
                <!-- COMMUNICATION PREFERENCES -->
                <div class="card card-border border-primary card-border-xl shadow-light-lg mb-5" id="communication">
                  <div class="card-header">
                    <h4 class="mb-0">Communication Preferences</h4>
                  </div>
                  <div class="card-body">
                    <form method="POST" action="./">
                      <div class="list-group-item">
                        <div class="row align-items-center">
                          <div class="col">
                            <p class="mb-3">
                              E-Mail Notifications on
                              <text class="text-primary">
                                <?php echo isset($_SESSION['email']) ? escapeOutput($_SESSION['email']) : null; ?> 
                              </text>
                            </p>
                            <small class="text-gray-700">
                              You will receieve promotional messages/advertisements along with important security notifications regarding your account.
                            </small>
                          </div>
                          <div class="col-auto">
                            <div class="form-check form-switch">
                              <input class="form-check-input" 
                                    type="checkbox" 
                                    role="switch" 
                                    id="emailNotifications" 
                                    name="emailConfiguration"
                                    <?php echo isset($userRecord['student_has_opted_email_communication']) && $userRecord['student_has_opted_email_communication'] === 1 ? 'checked' : ''; ?>
                              />
                            </div>
                          </div>
                        </div>
                        <input type="hidden" 
                              name="csrf_token" 
                              value="<?php echo htmlspecialchars(generateCsrfToken()); ?>" 
                        />
                        <?php echo $profileConfigurationFormStatus ?? null; ?>
                        <button class="btn btn-sm btn-warning rounded-pill text-dark mt-5" 
                                type="submit"
                                name="saveConfigurations">
                          Save Configurations
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
      </section>

    <?php endif; ?>

  <?php else: // Restricted Access for Faculty and Admins ?>
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