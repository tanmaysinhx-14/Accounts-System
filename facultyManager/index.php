<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts();

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Faculty Manager
  if(checkForEquality(checkLoginStatus($db1), true, 'strict')) {
    if(checkForEquality(getUserRoleUsingUserCode($_SESSION['usercode']), 'admin', 'strict')) {
      function fetchFacultyRecords(PDO $db): array {
        try {
          $STMT_fetchFaculty = 'SELECT fd.faculty_id,
                                      fd.faculty_usercode,
                                      fd.faculty_name,
                                      fd.faculty_email,
                                      fd.faculty_username,
                                      fd.faculty_bio,
                                      fd.faculty_reference_code,
                                      fc.faculty_account_activation_status,
                                      ft.faculty_account_creation_timestamp
                                FROM faculty_details fd
                                LEFT JOIN faculty_configurations fc
                                  ON fd.faculty_id = fc.faculty_id
                                LEFT JOIN faculty_timestamps ft
                                  ON fd.faculty_id = ft.faculty_id
                                ORDER BY fd.faculty_id DESC';
          $fetchFaculty = $db->query($STMT_fetchFaculty);
          return $fetchFaculty->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        catch (PDOException) {
          return [];
        }
      }

      // Backend for Faculty Account Creation
      if (isset($_POST['createFacultyBtn'])) {
        $enteredName     = escapeOutput($_POST['faculty_name'])     ?? null;
        $enteredEmail    = escapeOutput($_POST['faculty_email'])    ?? null;
        $csrfToken       = escapeOutput($_POST['csrf_token'])       ?? null;

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          if (validateEmail($enteredEmail)) {
            if (!checkUserRecord($db1, 'faculty_details', ['faculty_email' => $enteredEmail])) {
              $generatedCode        = generateReferenceCode();
              $hashedPassword       = password_hash($generatedCode, PASSWORD_DEFAULT);
              $creationTimestamp    = getCurrentTimestamp();

              $currentAttemptForInsertingFacultyData = 0;
              $maxRetriesForInsertingFacultyData     = 3;

              while ($currentAttemptForInsertingFacultyData < $maxRetriesForInsertingFacultyData) {
                try {
                  $db1->beginTransaction();

                  $STMT_insertFacultyDetails = 'INSERT INTO faculty_details (faculty_email, faculty_name, faculty_password, faculty_reference_code)
                                                                    VALUES (:faculty_email, :faculty_name, :faculty_password, :faculty_reference_code)';
                  $insertFacultyDetails = $db1->prepare($STMT_insertFacultyDetails);
                  $insertFacultyDetails->bindValue(':faculty_email',          $enteredEmail,   PDO::PARAM_STR);
                  $insertFacultyDetails->bindValue(':faculty_name',           $enteredName,    PDO::PARAM_STR);
                  $insertFacultyDetails->bindValue(':faculty_password',       $hashedPassword, PDO::PARAM_STR);
                  $insertFacultyDetails->bindValue(':faculty_reference_code', $generatedCode,  PDO::PARAM_STR);
                  $insertFacultyDetails->execute();

                  $facultyId       = (int) $db1->lastInsertId();
                  $facultyUsercode = generateUserCode('faculty', $facultyId);

                  $STMT_updateFacultyUsercode = 'UPDATE faculty_details 
                                                SET faculty_usercode = :faculty_usercode 
                                                WHERE faculty_id = :faculty_id
                                                LIMIT 1';
                  $updateFacultyUsercode = $db1->prepare($STMT_updateFacultyUsercode);
                  $updateFacultyUsercode->bindValue(':faculty_usercode', $facultyUsercode, PDO::PARAM_STR);
                  $updateFacultyUsercode->bindValue(':faculty_id',       $facultyId,       PDO::PARAM_INT);
                  $updateFacultyUsercode->execute();

                  $STMT_insertFacultyConfig = 'INSERT INTO faculty_configurations (faculty_id, faculty_usercode, faculty_email, faculty_account_activation_status, faculty_has_updated_username, faculty_has_updated_account_profile, faculty_has_opted_email_communication)
                                        VALUES (:faculty_id, :faculty_usercode, :faculty_email, 0, 0, 0, 1)';
                  $insertFacultyConfig = $db1->prepare($STMT_insertFacultyConfig);
                  $insertFacultyConfig->bindValue(':faculty_id',       $facultyId,       PDO::PARAM_INT);
                  $insertFacultyConfig->bindValue(':faculty_usercode', $facultyUsercode, PDO::PARAM_STR);
                  $insertFacultyConfig->bindValue(':faculty_email',    $enteredEmail,    PDO::PARAM_STR);
                  $insertFacultyConfig->execute();

                  $STMT_insertFacultyTimestamps = 'INSERT INTO faculty_timestamps (faculty_id, faculty_usercode, faculty_email, faculty_account_creation_timestamp)
                                                                          VALUES (:faculty_id, :faculty_usercode, :faculty_email, :faculty_account_creation_timestamp)';
                  $insertFacultyTimestamps = $db1->prepare($STMT_insertFacultyTimestamps);
                  $insertFacultyTimestamps->bindValue(':faculty_id',                         $facultyId,         PDO::PARAM_INT);
                  $insertFacultyTimestamps->bindValue(':faculty_usercode',                   $facultyUsercode,   PDO::PARAM_STR);
                  $insertFacultyTimestamps->bindValue(':faculty_email',                      $enteredEmail,      PDO::PARAM_STR);
                  $insertFacultyTimestamps->bindValue(':faculty_account_creation_timestamp', $creationTimestamp, PDO::PARAM_STR);
                  $insertFacultyTimestamps->execute();

                  $db1->commit();
                  
                  $displayEmail = htmlspecialchars($enteredEmail,  ENT_QUOTES, 'UTF-8');

                  $mail = createConfiguredMailer();
                  $mail->addAddress($enteredEmail);
                  $mail->isHTML(true);
                  $mail->Subject = 'Faculty Account Created | Career Institute';
                  $mail->Body    = '
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                      <meta charset="UTF-8">
                      <title>Account Activation</title>
                    </head>
                    <body style="margin:0; padding:0; background-color:#f4f4f4;">
                      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                            style="border-collapse:collapse; background-color:#f4f4f4;">
                        <tr>
                          <td align="center" style="padding:20px 10px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                                  style="max-width:600px; border-collapse:collapse; background-color:#ffffff;">

                              <tr>
                                <td align="center" style="background-color:#007BFF; padding:20px;">
                                  <h1 style="margin:0; font-family:Arial, sans-serif; font-size:24px; line-height:1.4; color:#ffffff;">
                                    Career Institute
                                  </h1>
                                </td>
                              </tr>

                              <tr>
                                <td style="padding:25px 25px 10px 25px; font-family:Arial, sans-serif; color:#333333;">
                                  <h2 style="margin:0 0 15px 0; font-size:20px; color:#007BFF; line-height:1.4;">
                                    Welcome, ' . $displayEmail . '!
                                  </h2>
                                  <p style="margin:0 0 12px 0; font-size:14px; line-height:1.6;">
                                    Thank you for creating an account with Career Institute.
                                  </p>
                                  <p style="margin:0 0 12px 0; font-size:14px; line-height:1.6;">
                                    Use the below reference code as your password for logging into your account.
                                  </p>
                                </td>
                              </tr>

                              <tr>
                                <td align="center" style="padding:10px 25px 20px 25px;">
                                  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                      <td align="center" bgcolor="#007BFF" style="border-radius:4px;">
                                        <span style="display:inline-block;
                                                    padding:10px 24px;
                                                    font-family:Arial, sans-serif;
                                                    font-size:14px;
                                                    font-weight:bold;
                                                    color:#ffffff;
                                                    border-radius:4px;">
                                          ' . escapeOutput($generatedCode) . '
                                        </span>
                                      </td>
                                    </tr>
                                  </table>
                                </td>
                              </tr>

                              <tr>
                                <td style="padding:0 25px 20px 25px; font-family:Arial, sans-serif; color:#333333;">
                                  <p style="margin:0 0 12px 0; font-size:14px; line-height:1.6;">
                                    If you did not create an account with Career Institute, you can safely ignore this email.
                                    If you have any concerns, please contact us at
                                    <a href="mailto:careerinstitutepatna@gmail.com" style="color:#007BFF; text-decoration:none;">
                                      careerinstitutepatna@gmail.com
                                    </a>
                                    or visit our
                                    <a href="https://careerinstitute.co.in/servicePages/contactUs/"
                                      style="color:#007BFF; text-decoration:none;">Contact Us</a> page.
                                  </p>
                                </td>
                              </tr>

                              <tr>
                                <td style="padding:0 25px 25px 25px; font-family:Arial, sans-serif; color:#333333;">
                                  <p style="margin:0 0 4px 0; font-size:14px; line-height:1.6;">Best regards,</p>
                                  <p style="margin:0 0 4px 0; font-size:14px; line-height:1.6;"><strong>The Career Institute Team</strong></p>
                                  <p style="margin:0; font-size:13px; line-height:1.6;"><em>Your Future, Our Commitment.</em></p>
                                </td>
                              </tr>

                              <tr>
                                <td align="center" style="background-color:#f4f4f4; padding:15px 20px;
                                            font-family:Arial, sans-serif; color:#888888;">
                                  <p style="margin:0 0 6px 0; font-size:11px; line-height:1.6;">
                                    You are receiving this email because your email address was used to create an account at
                                    <a href="https://careerinstitute.co.in" style="color:#007BFF; text-decoration:none;">Career Institute</a>.
                                  </p>
                                  <p style="margin:0; font-size:11px; line-height:1.6;">
                                    K-180 Shashi Complex (2nd Floor), Kali Mandir Road, Kankarbagh, Patna 800020
                                  </p>
                                </td>
                              </tr>

                            </table>
                          </td>
                        </tr>
                      </table>
                    </body>
                    </html>
                  ';
                  $mail->AltBody = "Welcome to Career Institute!\n\nYour faculty account was created successfully.\nReference code: " . escapeOutput($generatedCode) . "\n\nDon/'t share this code with anyone, as it serves as your temporary password for first-time login. Make sure to reset your password after logging in.";

                  $currentAttemptForSendingFacultyEmail = 0;
                  $maxRetriesForSendingFacultyEmail     = 3;

                  while ($currentAttemptForSendingFacultyEmail < $maxRetriesForSendingFacultyEmail) {
                    try {
                      if ($mail->send()) {
                        setToast('Account Details Submitted Successfully. Waiting for Approval from Admin.', 'success', 15000);
                        break;
                      }
                    }
                    catch (Exception $ex) {
                      if (!isRetryableSmtpFailure($mail)) {
                        logAppError($db2, null, getCurrentURL(), 'MAIL', 'Error occurred while Sending Activation Mail: ' . $mail->ErrorInfo);
                        break;
                      }
                    }
                    $currentAttemptForSendingFacultyEmail++;
                    sleep(5);
                  }

                  setToast('Faculty account for ' . escapeOutput($enteredName) . ' was created successfully.', 'success', 6000);

                  break;
                }
                catch (PDOException $ex) {
                  if ($db1->inTransaction()) $db1->rollBack();

                  if (!isRetryablePdoException($ex)) {
                    setToast('An error occurred while creating the faculty account. Please contact the system administrator.' . $ex->getMessage(), 'danger', 7000);
                    logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error creating faculty record: ' . $ex->getMessage());
                    break;
                  }

                  $currentAttemptForInsertingFacultyData++;
                  sleep(3);
                }
              }
              if ($currentAttemptForInsertingFacultyData >= $maxRetriesForInsertingFacultyData) {
                setToast('Failed to create faculty account after multiple attempts. Please try again later.', 'danger', 7000);
              }
            }
            else {
              $facultyEmailValidationStatus = 'is-invalid';
              $facultyEmailHelpText         = '<span class="text-danger d-flex align-items-center gap-1 mt-1">
                                                <span class="material-symbols-outlined" style="font-size:1rem;">info</span> 
                                                This email address is already associated with another faculty member.
                                              </span>';
            }
          }
          else {
            $facultyEmailValidationStatus = 'is-invalid';
            $facultyEmailHelpText         = '<span class="text-danger d-flex align-items-center gap-1 mt-1">
                                              <span class="material-symbols-outlined" style="font-size:1rem;">info</span>
                                              Please enter a valid email address.
                                            </span>';
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      // Backend for Faculty Account Editing (using Dialog Box)
      if (isset($_POST['editFacultyBtn'])) {
        $editId         = (int)   (escapeOutput($_POST['edit_faculty_id'])   ?? 0);
        $editName       = escapeOutput($_POST['edit_faculty_name'])          ?? null;
        $editEmail      = escapeOutput($_POST['edit_faculty_email'])         ?? null;
        $editUsername   = escapeOutput($_POST['edit_faculty_username'])      ?? null;
        $editBio        = escapeOutput($_POST['edit_faculty_bio'])           ?? null;
        $editActivation = (int) isset($_POST['edit_faculty_account_activation_status']);
        $csrfToken      = escapeOutput($_POST['csrf_token'])                 ?? null;

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          $isValid = true;

          if ($editId <= 0) {
            setToast('Invalid faculty record. Please try again.', 'danger', 6000);
            $isValid = false;
          }

          if ($isValid && (empty($editName) || strlen($editName) < 2)) {
            setToast('Please enter a valid full name (min. 2 characters).', 'danger', 6000);
            $isValid = false;
          }

          if ($isValid && !validateEmail($editEmail)) {
            setToast('Please enter a valid email address.', 'danger', 6000);
            $isValid = false;
          }

          if ($isValid) {
            try {
              $checkEmail = $db1->prepare('SELECT faculty_id FROM faculty_details WHERE faculty_email = :email AND faculty_id != :id');
              $checkEmail->bindValue(':email', $editEmail, PDO::PARAM_STR);
              $checkEmail->bindValue(':id',    $editId,    PDO::PARAM_INT);
              $checkEmail->execute();
              if ($checkEmail->fetch()) {
                setToast('This email address is already in use by another faculty member.', 'danger', 6000);
                $isValid = false;
              }
            }
            catch (PDOException) {
              setToast('An error occurred during validation. Please try again.', 'danger', 6000);
              $isValid = false;
            }
          }

          $finalUsername = !empty($editUsername) ? $editUsername : null;
          $finalBio      = !empty($editBio)      ? $editBio      : null;

          if ($isValid && $finalUsername !== null) {
            try {
              $checkUsername = $db1->prepare('SELECT faculty_id FROM faculty_details WHERE faculty_username = :un AND faculty_id != :id');
              $checkUsername->bindValue(':un',  $finalUsername, PDO::PARAM_STR);
              $checkUsername->bindValue(':id',  $editId,        PDO::PARAM_INT);
              $checkUsername->execute();
              if ($checkUsername->fetch()) {
                setToast('This username is already taken by another faculty member.', 'danger', 6000);
                $isValid = false;
              }
            }
            catch (PDOException) {
              setToast('An error occurred during validation. Please try again.', 'danger', 6000);
              $isValid = false;
            }
          }

          if ($isValid) {
            $currentAttempt = 0;
            $maxRetries     = 3;

            while ($currentAttempt < $maxRetries) {
              try {
                $db1->beginTransaction();

                $STMT_updateFacultyDetails = 'UPDATE faculty_details
                                              SET faculty_name     = :name,
                                                  faculty_email    = :email,
                                                  faculty_username = :username,
                                                  faculty_bio      = :bio
                                              WHERE faculty_id = :id
                                              LIMIT 1';
                $updateFacultyDetails = $db1->prepare($STMT_updateFacultyDetails);
                $updateFacultyDetails->bindValue(':name',     $editName,      PDO::PARAM_STR);
                $updateFacultyDetails->bindValue(':email',    $editEmail,     PDO::PARAM_STR);
                $updateFacultyDetails->bindValue(':username', $finalUsername, $finalUsername ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $updateFacultyDetails->bindValue(':bio',      $finalBio,      $finalBio      ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $updateFacultyDetails->bindValue(':id',       $editId,        PDO::PARAM_INT);
                $updateFacultyDetails->execute();

                $STMT_updateFacultyConfig = 'UPDATE faculty_configurations
                                            SET faculty_account_activation_status = :status,
                                                faculty_email                   = :email
                                            WHERE faculty_id = :id
                                            LIMIT 1';
                $updateFacultyConfig = $db1->prepare($STMT_updateFacultyConfig);
                $updateFacultyConfig->bindValue(':status', $editActivation, PDO::PARAM_INT);
                $updateFacultyConfig->bindValue(':email',  $editEmail,      PDO::PARAM_STR);
                $updateFacultyConfig->bindValue(':id',     $editId,         PDO::PARAM_INT);
                $updateFacultyConfig->execute();

                $STMT_updateFacultyTimestamps = 'UPDATE faculty_timestamps
                                                SET faculty_email = :email
                                                WHERE faculty_id = :id
                                                LIMIT 1';
                $updateFacultyTimestamps = $db1->prepare($STMT_updateFacultyTimestamps);
                $updateFacultyTimestamps->bindValue(':email', $editEmail, PDO::PARAM_STR);
                $updateFacultyTimestamps->bindValue(':id',    $editId,    PDO::PARAM_INT);
                $updateFacultyTimestamps->execute();

                $db1->commit();

                setToast('Faculty record updated successfully.', 'success', 5000);
                break;
              }
              catch (PDOException $ex) {
                if ($db1->inTransaction()) $db1->rollBack();

                if (!isRetryablePdoException($ex)) {
                  setToast('An error occurred while updating the record. Please contact the system administrator.', 'danger', 7000);
                  logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error updating faculty record: ' . $ex->getMessage());
                  break;
                }

                $currentAttempt++;
                sleep(3);
              }
            }
            if ($currentAttempt >= $maxRetries) {
              setToast('Failed to update the record after multiple attempts. Please try again later.', 'danger', 7000);
            }
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      // Backend for Faculty Account Deletion (using Dialog Box)
      if (isset($_POST['deleteFacultyBtn'])) {
        $deleteId  = (int) (escapeOutput($_POST['delete_faculty_id']) ?? null);
        $csrfToken = escapeOutput($_POST['csrf_token'])               ?? null;

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          if ($deleteId != null) {
            $currentAttemptForDeletingFacultyRecord = 0;
            $maxRetriesForDeletingFacultyRecord     = 3;

            while ($currentAttemptForDeletingFacultyRecord < $maxRetriesForDeletingFacultyRecord) {
              try {
                $db1->beginTransaction();

                $generatedUsercode = generateUserCode('faculty', $deleteId);
                
                // Multiple Rows to be deleted
                foreach (['faculty_configurations', 'faculty_timestamps', 'faculty_devicedetails'] as $table) {
                  $stmt = $db1->prepare("DELETE FROM {$table} WHERE faculty_usercode = :usercode");
                  $stmt->bindValue(':usercode', $generatedUsercode, PDO::PARAM_STR);
                  $stmt->execute();
                }

                // One unique row to be deleted
                $STMT_deleteFacultyDetails = 'DELETE FROM faculty_details WHERE faculty_usercode = :usercode LIMIT 1';
                $deleteFacultyDetails = $db1->prepare($STMT_deleteFacultyDetails);
                $deleteFacultyDetails->bindValue(':usercode', $generatedUsercode, PDO::PARAM_STR);
                $deleteFacultyDetails->execute();

                $db1->commit();

                setToast('Faculty record deleted successfully.', 'success', 5000);
                break;
              }
              catch (PDOException $ex) {
                if ($db1->inTransaction()) $db1->rollBack();

                if (!isRetryablePdoException($ex)) {
                  setToast('An error occurred while deleting the record. Please contact the system administrator.', 'danger', 7000);
                  logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error deleting faculty record: ' . $ex->getMessage());
                  break;
                }

                $currentAttemptForDeletingFacultyRecord++;
                sleep(3);
              }
            }
            if ($currentAttemptForDeletingFacultyRecord >= $maxRetriesForDeletingFacultyRecord) {
              setToast('Failed to delete the record after multiple attempts. Please try again later.', 'danger', 7000);
            }
          }
          else setToast('Invalid faculty record. Please try again.', 'danger', 6000);
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      $facultyRecords = fetchFacultyRecords($db1);
      $csrfTokenValue = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
    }
  }
?>

<?php // Headers 
  $page_title = "Faculty Records | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
  
  $breadcrumb_url_1    = '../dashboard/';
  $breadcrumb_title_1  = 'Dashboard';

  $breadcrumb_url_active    = './';
  $breadcrumb_title_active  = 'Faculty Records';
  
  require_once '../components/breadcrumb.php';
?>

<?php if(checkForEquality(checkLoginStatus($db1), true, 'strict')): // User Logged In?>
  <?php if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): // For Admins?>
    <section class="section-border border-primary ff-inter">
      <div class="container-fluid px-4 px-lg-6 py-7">
        <div class="row mb-5">
          <div class="col-12">
            <h2 class="fw-bold mb-1">Faculty Management</h2>
            <p class="text-body-secondary mb-0">View existing faculty records and register new faculty accounts.</p>
          </div>
        </div>

        <div class="row g-4 align-items-start">
          <div class="col-12 col-lg-7 col-xl-8">
            <div class="card border shadow-lg">
              <div class="card-header py-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                  <div>
                    <h3 class="mb-0 fw-semibold">Faculty Records</h3>
                    <small class="text-body-secondary">All registered faculty members in the system</small>
                  </div>
                  <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fs-sm" id="facultyCountBadge">
                    <?php echo count($facultyRecords); ?> <?php echo count($facultyRecords) === 1 ? 'Member' : 'Members'; ?>
                  </span>
                </div>

                <!-- Search box (only show when there are records) -->
                <?php if (!empty($facultyRecords)): ?>
                <div class="position-relative">
                  <span class="material-symbols-outlined position-absolute text-body-tertiary"
                        style="top:50%;right:1rem;transform:translateY(-50%);font-size:1.1rem;pointer-events:none;">
                    search
                  </span>
                  <input type="search"
                        id="facultySearchInput"
                        class="form-control ps-5"
                        placeholder="Search by name or email…"
                        autocomplete="off" />
                </div>
                <?php endif; ?>

              </div>

              <div class="card-body p-0">
                <?php if (empty($facultyRecords)): ?>
                  <div class="text-center py-7 px-4">
                    <span class="material-symbols-outlined text-body-tertiary mb-3" style="font-size:3rem;">person_off</span>
                    <p class="fw-semibold mb-1">No Faculty Records Found</p>
                    <p class="text-body-secondary fs-sm mb-0">Use the form on the right to add the first faculty member.</p>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="facultyTable">
                      <thead class="table-light">
                        <tr>
                          <th class="ps-4 py-3 fw-semibold text-body-secondary text-uppercase fs-xs">Faculty</th>
                          <th class="py-3 fw-semibold text-body-secondary text-uppercase fs-xs">User Code</th>
                          <th class="py-3 fw-semibold text-body-secondary text-uppercase fs-xs text-center">Status</th>
                          <th class="py-3 fw-semibold text-body-secondary text-uppercase fs-xs">Share Credentials</th>
                          <th class="pe-4 py-3 fw-semibold text-body-secondary text-uppercase fs-xs">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="facultyTableBody">
                        <?php foreach ($facultyRecords as $record):
                          $facultyId              = (int)  ($record['faculty_id']                    ?? 0);
                          $displayFacultyName     = htmlspecialchars($record['faculty_name']           ?? '—', ENT_QUOTES, 'UTF-8');
                          $displayFacultyEmail    = htmlspecialchars($record['faculty_email']          ?? '—', ENT_QUOTES, 'UTF-8');
                          $displayFacultyUsercode = htmlspecialchars($record['faculty_usercode']       ?? '—', ENT_QUOTES, 'UTF-8');
                          $displayFacultyUsername = htmlspecialchars($record['faculty_username']       ?? '',  ENT_QUOTES, 'UTF-8');
                          $displayFacultyBio      = htmlspecialchars($record['faculty_bio']            ?? '',  ENT_QUOTES, 'UTF-8');
                          $displayReferenceCode   = htmlspecialchars($record['faculty_reference_code'] ?? '',  ENT_QUOTES, 'UTF-8');
                          $displayCreationTs      = htmlspecialchars($record['faculty_account_creation_timestamp'] ?? '—', ENT_QUOTES, 'UTF-8');
                          $isActivated            = (bool) ($record['faculty_account_activation_status'] ?? false);
                          $hasCredentials         = !empty($displayReferenceCode) && !$isActivated;
                        ?>
                        <tr data-search-name="<?php echo strtolower($displayFacultyName); ?>"
                            data-search-email="<?php echo strtolower($displayFacultyEmail); ?>">
                          <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                              <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold"
                                  style="width:38px;height:38px;font-size:.85rem;flex-shrink:0;">
                                <?php echo strtoupper(substr($record['faculty_name'] ?? '?', 0, 1)); ?>
                              </div>
                              <div>
                                <div class="fw-semibold lh-sm"><?php echo $displayFacultyName; ?></div>
                                <div class="text-body-secondary fs-xs"><?php echo $displayFacultyEmail; ?></div>
                              </div>
                            </div>
                          </td>
                          <td class="py-3">
                            <code class="fs-xs bg-secondary-subtle px-2 py-1 rounded"><?php echo $displayFacultyUsercode; ?></code>
                          </td>
                          <td class="py-3 text-center">
                            <?php if ($isActivated): ?>
                              <span class="badge bg-success-subtle text-success rounded-pill px-3">Active</span>
                            <?php else: ?>
                              <span class="badge bg-warning-subtle text-warning rounded-pill px-3">Pending</span>
                            <?php endif; ?>
                          </td>
                          <td class="py-3">
                            <?php if ($hasCredentials): ?>
                              <div class="d-flex align-items-center gap-2">
                                <button type="button"
                                        class="btn btn-sm d-flex align-items-center gap-1 p-1"
                                        onclick="shareViaEmail(this)"
                                        data-name="<?php echo $displayFacultyName; ?>"
                                        data-email="<?php echo $displayFacultyEmail; ?>"
                                        data-usercode="<?php echo $displayFacultyUsercode; ?>"
                                        data-refcode="<?php echo $displayReferenceCode; ?>"
                                        title="Send credentials via Email">
                                  <svg height="36" width="36" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="#000000">
                                    <g><path d="M13.025 17H3.707l5.963-5.963L12 12.83l2.33-1.794 1.603 1.603a5.463 5.463 0 0 1 1.004-.41l-1.808-1.808L21 5.9v6.72a5.514 5.514 0 0 1 1 .64V5.5A1.504 1.504 0 0 0 20.5 4h-17A1.504 1.504 0 0 0 2 5.5v11A1.5 1.5 0 0 0 3.5 18h9.525c-.015-.165-.025-.331-.025-.5s.01-.335.025-.5zM3 16.293V5.901l5.871 4.52zM20.5 5c.009 0 .016.005.025.005L12 11.57 3.475 5.005c.009 0 .016-.005.025-.005zm-2 8a4.505 4.505 0 0 0-4.5 4.5 4.403 4.403 0 0 0 .05.5 4.49 4.49 0 0 0 4.45 4h.5v-1h-.5a3.495 3.495 0 0 1-3.45-3 3.455 3.455 0 0 1-.05-.5 3.498 3.498 0 0 1 5.947-2.5H20v.513A2.476 2.476 0 0 0 18.5 15a2.5 2.5 0 1 0 1.733 4.295A1.497 1.497 0 0 0 23 18.5v-1a4.555 4.555 0 0 0-4.5-4.5zm0 6a1.498 1.498 0 0 1-1.408-1 1.483 1.483 0 0 1-.092-.5 1.5 1.5 0 0 1 3 0 1.483 1.483 0 0 1-.092.5 1.498 1.498 0 0 1-1.408 1zm3.5-.5a.5.5 0 0 1-1 0v-3.447a3.639 3.639 0 0 1 1 2.447z"></path><path fill="none" d="M0 0h24v24H0z"></path></g>
                                  </svg>
                                </button>
                                <button type="button"
                                        class="btn btn-sm d-flex align-items-center gap-1 p-1"
                                        onclick="shareViaWhatsApp(this)"
                                        data-name="<?php echo $displayFacultyName; ?>"
                                        data-email="<?php echo $displayFacultyEmail; ?>"
                                        data-usercode="<?php echo $displayFacultyUsercode; ?>"
                                        data-refcode="<?php echo $displayReferenceCode; ?>"
                                        title="Send credentials via WhatsApp">
                                  <svg height="36" width="36" xmlns="http://www.w3.org/2000/svg" aria-label="WhatsApp" role="img" viewBox="0 0 512 512" fill="#000000">
                                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><rect width="512" height="512" rx="15%" fill="#25d366"></rect><path fill="#25d366" stroke="#ffffff" stroke-width="26" d="M123 393l14-65a138 138 0 1150 47z"></path><path fill="#ffffff" d="M308 273c-3-2-6-3-9 1l-12 16c-3 2-5 3-9 1-15-8-36-17-54-47-1-4 1-6 3-8l9-14c2-2 1-4 0-6l-12-29c-3-8-6-7-9-7h-8c-2 0-6 1-10 5-22 22-13 53 3 73 3 4 23 40 66 59 32 14 39 12 48 10 11-1 22-10 27-19 1-3 6-16 2-18"></path></g>
                                  </svg>
                              </div>
                            <?php else: ?>
                              <span class="text-body-tertiary fst-italic fs-sm">—</span>
                            <?php endif; ?>
                          </td>
                          <td class="pe-4 py-3">
                            <div class="d-flex align-items-center gap-2">
                              <button type="button"
                                      class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 px-3"
                                      onclick="openEditModal(this)"
                                      data-id="<?php echo $facultyId; ?>"
                                      data-name="<?php echo $displayFacultyName; ?>"
                                      data-email="<?php echo $displayFacultyEmail; ?>"
                                      data-username="<?php echo $displayFacultyUsername; ?>"
                                      data-bio="<?php echo $displayFacultyBio; ?>"
                                      data-activated="<?php echo $isActivated ? '1' : '0'; ?>"
                                      title="Edit this faculty record">
                                <svg width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                                  <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z"/>
                                </svg>
                                Edit
                              </button>
                              <button type="button"
                                      class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 px-3"
                                      onclick="openDeleteModal(this)"
                                      data-id="<?php echo $facultyId; ?>"
                                      data-name="<?php echo $displayFacultyName; ?>"
                                      title="Delete this faculty record">
                                <svg width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                                  <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                  <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                                </svg>
                                Delete
                              </button>
                            </div>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>

                    <!-- Empty-search state (hidden by default) -->
                    <div id="noSearchResults" class="text-center py-7 px-4 d-none">
                      <span class="material-symbols-outlined text-body-tertiary mb-3" style="font-size:3rem;">search_off</span>
                      <p class="fw-semibold mb-1">No Results Found</p>
                      <p class="text-body-secondary fs-sm mb-0">No faculty member matches your search query.</p>
                    </div>

                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-5 col-xl-4">
            <form class="d-flex flex-column gap-4" method="POST" action="./">
              <div class="card border shadow-lg">
                <div class="card-header py-3">
                  <h4 class="mb-0 fw-semibold">Basic Details</h4>
                  <small class="text-body-secondary">Faculty member's personal information</small>
                </div>
                <div class="card-body p-4">

                  <div class="mb-3">
                    <label class="visually-hidden" for="faculty_name">Full Name</label>
                    <input class="form-control <?php echo $facultyNameValidationStatus ?? null; ?>"
                          id="faculty_name"
                          type="text"
                          name="faculty_name"
                          placeholder="Enter Full Name..."
                          value="<?php echo htmlspecialchars($_POST['faculty_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                          required />
                    <div class="form-text">
                      <?php echo $facultyNameHelpText ?? 'Enter the faculty member\'s full name.'; ?>
                    </div>
                  </div>

                  <div class="mb-0">
                    <label class="visually-hidden" for="faculty_email">Email Address</label>
                    <input class="form-control <?php echo $facultyEmailValidationStatus ?? null; ?>"
                          id="faculty_email"
                          type="email"
                          name="faculty_email"
                          placeholder="Enter Email Address..."
                          value="<?php echo htmlspecialchars($_POST['faculty_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                          required />
                    <div class="form-text">
                      <?php echo $facultyEmailHelpText ?? 'This will be used for login and communication.'; ?>
                    </div>
                  </div>

                </div>
              </div>

              <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">

              <div class="d-flex align-items-start gap-2 px-1">
                <span class="material-symbols-outlined text-body-secondary mt-1" style="font-size:1rem;">info</span>
                <p class="text-body-secondary fs-sm mb-0">
                  A temporary 8-character password will be auto-generated and stored securely.
                  Use the Email or WhatsApp buttons in the records panel to share credentials with the faculty member.
                </p>
              </div>

              <button class="btn btn-primary rounded-pill w-100" name="createFacultyBtn" type="submit">
                Create Faculty Account
              </button>

            </form>
          </div>

        </div>
      </div>
    </section>

    <!-- CUSTOM STYLING FOR DIALOG BOXES -->
    <style>
      dialog.ci-dialog {
        border: none;
        border-radius: .75rem;
        box-shadow: 0 1rem 3rem rgba(0,0,0,.175);
        padding: 0;
        width: min(520px, 94vw);
        max-height: 90dvh;
        overflow-y: auto;
      }
      dialog.ci-dialog.ci-dialog--sm {
        width: min(360px, 94vw);
      }
      dialog.ci-dialog::backdrop {
        background: rgba(0,0,0,.45);
        backdrop-filter: blur(2px);
      }
      /* Animate open */
      dialog.ci-dialog[open] {
        animation: ciDialogIn .18s ease-out;
      }
      @keyframes ciDialogIn {
        from { opacity: 0; transform: translateY(-12px) scale(.97); }
        to   { opacity: 1; transform: translateY(0)     scale(1);   }
      }
      .ci-dialog__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--bs-border-color, #dee2e6);
      }
      .ci-dialog__body   { padding: 1.25rem; }
      .ci-dialog__footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .5rem;
        padding: .875rem 1.25rem;
        border-top: 1px solid var(--bs-border-color, #dee2e6);
      }
      /* Reuse Bootstrap's btn-close look without its JS */
      .ci-dialog__close {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        padding: 0;
        border: none;
        border-radius: .25rem;
        background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em no-repeat;
        opacity: .55;
        cursor: pointer;
        flex-shrink: 0;
      }
      .ci-dialog__close:hover { opacity: 1; background-color: rgba(0,0,0,.08); }
    </style>

    <!-- EDITING DIALOG BOX -->
    <dialog class="ci-dialog" id="editFacultyDialog" aria-labelledby="editDialogTitle">
      <form method="POST" action="./">
        <div class="ci-dialog__header">
          <div>
            <h5 class="fw-semibold mb-0" id="editDialogTitle">Edit Faculty Record</h5>
            <small class="text-body-secondary">Changes are saved immediately on submit.</small>
          </div>
          <button type="button" class="ci-dialog__close" onclick="closeDialog('editFacultyDialog')" aria-label="Close"></button>
        </div>
        <div class="ci-dialog__body">

          <input type="hidden" name="edit_faculty_id" id="edit_faculty_id">

          <div class="mb-3">
            <label class="form-label fw-semibold fs-sm mb-1" for="edit_faculty_name">Full Name</label>
            <input class="form-control"
                  id="edit_faculty_name"
                  type="text"
                  name="edit_faculty_name"
                  placeholder="Enter Full Name..."
                  required />
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold fs-sm mb-1" for="edit_faculty_email">Email Address</label>
            <input class="form-control"
                  id="edit_faculty_email"
                  type="email"
                  name="edit_faculty_email"
                  placeholder="Enter Email Address..."
                  required />
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold fs-sm mb-1" for="edit_faculty_username">
              Username <span class="text-body-tertiary fw-normal">(optional)</span>
            </label>
            <input class="form-control"
                  id="edit_faculty_username"
                  type="text"
                  name="edit_faculty_username"
                  placeholder="Enter Username..." />
            <div class="form-text">Leave blank to keep unset.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold fs-sm mb-1" for="edit_faculty_bio">
              Bio <span class="text-body-tertiary fw-normal">(optional)</span>
            </label>
            <textarea class="form-control"
                      id="edit_faculty_bio"
                      name="edit_faculty_bio"
                      rows="2"
                      maxlength="255"
                      placeholder="Short bio..."></textarea>
          </div>

          <div class="d-flex align-items-center gap-2 border rounded-3 px-3 py-3">
            <input class="form-check-input mt-0 shrink-0"
                  type="checkbox"
                  name="edit_faculty_account_activation_status"
                  id="edit_faculty_account_activation_status" />
            <label class="form-check-label fs-sm" for="edit_faculty_account_activation_status">
              <span class="fw-semibold">Mark as Activated</span>
              <span class="d-block text-body-secondary">Activating will hide the credential sharing buttons for this member.</span>
            </label>
          </div>

        </div>
        <div class="ci-dialog__footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="closeDialog('editFacultyDialog')">Cancel</button>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">
          <button type="submit" name="editFacultyBtn" class="btn btn-primary rounded-pill px-4">Save Changes</button>
        </div>
      </form>
    </dialog>

    <!-- DELETION DIALOG BOX -->
    <dialog class="ci-dialog ci-dialog--sm" id="deleteFacultyDialog" aria-labelledby="deleteDialogTitle">
      <form method="POST" action="./">
        <div class="ci-dialog__header">
          <h5 class="fw-semibold mb-0" id="deleteDialogTitle">Delete Faculty Record</h5>
          <button type="button" class="ci-dialog__close" onclick="closeDialog('deleteFacultyDialog')" aria-label="Close"></button>
        </div>
        <div class="ci-dialog__body">
          <p class="mb-1">Are you sure you want to delete the record for</p>
          <p class="fw-semibold mb-3" id="delete_faculty_name_display">—</p>
          <p class="text-danger fs-sm mb-0 d-flex align-items-start gap-1">
            <span class="material-symbols-outlined mt-1" style="font-size:1rem;">warning</span>
            This will permanently remove all associated data and cannot be undone.
          </p>
          <input type="hidden" name="delete_faculty_id" id="delete_faculty_id">
        </div>
        <div class="ci-dialog__footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="closeDialog('deleteFacultyDialog')">Cancel</button>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">
          <button type="submit" name="deleteFacultyBtn" class="btn btn-danger rounded-pill px-4">Delete</button>
        </div>
      </form>
    </dialog>

    <script type="text/javascript">
      // Credential sharing
      function buildCredentialMessage(name, email, usercode, refCode) {
        return `Dear ${name},\n\nYour faculty account at Career Institute has been created successfully. Please find your login credentials below.\n\nLogin Details:\n  Email Address : ${email}\n  Temporary Password : ${refCode}\n  User Code : ${usercode}\n\nPlease log in at your earliest convenience and change your password from the account settings.\n\nBest regards,\nCareer Institute`;
      }

      function shareViaEmail(btn) {
        const { name, email, usercode, refcode } = btn.dataset;
        const subject = encodeURIComponent('Your Career Institute Faculty Account — Login Credentials');
        const body    = encodeURIComponent(buildCredentialMessage(name, email, usercode, refcode));
        window.location.href = `mailto:${email}?subject=${subject}&body=${body}`;
      }

      function shareViaWhatsApp(btn) {
        const { name, email, usercode, refcode } = btn.dataset;
        const text = encodeURIComponent(buildCredentialMessage(name, email, usercode, refcode));
        window.open(`https://wa.me/?text=${text}`, '_blank');
      }

      // Dialog helpers (no Bootstrap JS needed)
      function openDialog(id)  { document.getElementById(id).showModal(); }
      function closeDialog(id) { document.getElementById(id).close(); }

      // Close on backdrop click
      document.querySelectorAll('dialog.ci-dialog').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) {
          const r = dlg.getBoundingClientRect();
          if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) dlg.close();
        });
      });

      // Edit dialog
      function openEditModal(btn) {
        const { id, name, email, username, bio, activated } = btn.dataset;
        document.getElementById('edit_faculty_id').value       = id;
        document.getElementById('edit_faculty_name').value     = name;
        document.getElementById('edit_faculty_email').value    = email;
        document.getElementById('edit_faculty_username').value = username;
        document.getElementById('edit_faculty_bio').value      = bio;
        document.getElementById('edit_faculty_account_activation_status').checked = activated === '1';
        openDialog('editFacultyDialog');
      }

      // Delete dialog
      function openDeleteModal(btn) {
        const { id, name } = btn.dataset;
        document.getElementById('delete_faculty_id').value                 = id;
        document.getElementById('delete_faculty_name_display').textContent = name + '?';
        openDialog('deleteFacultyDialog');
      }

      // Live search
      (function () {
        const input          = document.getElementById('facultySearchInput');
        const noResults      = document.getElementById('noSearchResults');
        const countBadge     = document.getElementById('facultyCountBadge');
        const totalMembers   = <?php echo count($facultyRecords); ?>;

        if (!input) return; // no records on page

        input.addEventListener('input', function () {
          const query = this.value.trim().toLowerCase();
          const rows  = document.querySelectorAll('#facultyTableBody tr');
          let visible = 0;

          rows.forEach(function (row) {
            const nameMatch  = row.dataset.searchName.includes(query);
            const emailMatch = row.dataset.searchEmail.includes(query);
            const show       = !query || nameMatch || emailMatch;
            row.classList.toggle('d-none', !show);
            if (show) visible++;
          });

          // Toggle empty-state message
          noResults.classList.toggle('d-none', visible > 0);

          // Update the member count badge to reflect filtered results
          if (query) {
            countBadge.textContent = visible + ' of ' + totalMembers + (totalMembers === 1 ? ' Member' : ' Members');
          } else {
            countBadge.textContent = totalMembers + (totalMembers === 1 ? ' Member' : ' Members');
          }
        });
      })();
    </script>

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