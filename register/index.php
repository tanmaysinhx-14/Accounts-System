<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts();

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Student Registration
  if(checkForEquality(checkLoginStatus($db1), false, 'strict')) {
    $registrationSuccessData = null;

    function getRegistrationRequestIpAddress(): string {
      $ipAddress = null;

      if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']))
        $ipAddress = (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
      elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipAddress = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
      else
        $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

      return filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : 'Unknown';
    }

    function getApprovalRegistrationRateLimitStatus(PDO $db, string $ipAddress, int $maxIpRequests = 5, int $ipWindowSeconds = 3600): array {
      if ($ipAddress === 'Unknown') {
        return [
          'limited'     => false,
          'reason'      => null,
          'retry_after' => 0,
        ];
      }

      try {
        $ipWindowSeconds = max(60, $ipWindowSeconds);

        $STMT_countRecentIpRequests = 'SELECT COUNT(*) AS recentRequestCount,
                                              UNIX_TIMESTAMP(MIN(approval_rate_limiting_timestamp)) AS oldestRequestTimestamp
                                      FROM approval_users
                                      WHERE approval_IP_address = :approval_IP_address
                                        AND approval_rate_limiting_timestamp >= DATE_SUB(NOW(), INTERVAL ' . (int) $ipWindowSeconds . ' SECOND)';

        $countRecentIpRequests = $db->prepare($STMT_countRecentIpRequests);
        $countRecentIpRequests->bindValue(':approval_IP_address', $ipAddress, PDO::PARAM_STR);
        $countRecentIpRequests->execute();

        $rateLimitRecord    = $countRecentIpRequests->fetch(PDO::FETCH_ASSOC) ?: [];
        $recentRequestCount = (int) ($rateLimitRecord['recentRequestCount'] ?? 0);

        if ($recentRequestCount >= $maxIpRequests) {
          $oldestRequestTimestamp = (int) ($rateLimitRecord['oldestRequestTimestamp'] ?? time());
          $retryAfter = max(1, ($oldestRequestTimestamp + $ipWindowSeconds) - time());

          return [
            'limited'     => true,
            'reason'      => 'ip',
            'retry_after' => $retryAfter,
          ];
        }
      }
      catch (PDOException) {
        return [
          'limited'     => false,
          'reason'      => null,
          'retry_after' => 0,
        ];
      }

      return [
        'limited'     => false,
        'reason'      => null,
        'retry_after' => 0,
      ];
    }

    if (isset($_POST['registerStudentBtn'])) {
      $enteredEmail             = escapeOutput($_POST['email']                      ?? null);
      $enteredNewPassword       = escapeOutput($_POST['new_password']               ?? null);
      $enteredName              = escapeOutput($_POST['student_name']               ?? null);
      $enteredFatherName        = escapeOutput($_POST['father_name']                ?? null);
      $enteredBatch             = escapeOutput($_POST['batchSelect']                ?? null);
      $enteredConfirmPassword   = escapeOutput($_POST['confirm_password']           ?? null);
      $optForEmailCommunication = escapeOutput($_POST['optForEmailCommunication']   ?? 0);
      $csrfToken                = escapeOutput($_POST['csrf_token']                 ?? null);
      $registrationIpAddress    = getRegistrationRequestIpAddress();

      if (validateCsrfToken($csrfToken)) {
        unsetCsrfToken();

        if (isset($_POST['agreeTermsAndConditions'])) {
          if (validateEmail($enteredEmail)
                &&
              validatePassword($enteredNewPassword)
                &&
              validatePassword($enteredConfirmPassword)
                &&
              checkForEquality($enteredNewPassword, $enteredConfirmPassword, 'strict')) {

            $registrationRateLimitStatus = getApprovalRegistrationRateLimitStatus($db1, $registrationIpAddress);

            if (($registrationRateLimitStatus['limited'] ?? false) === true) {
              $retryAfter        = max(1, (int) ($registrationRateLimitStatus['retry_after'] ?? 0));
              $retryAfterMinutes = max(1, (int) ceil($retryAfter / 60));

              setToast('Too many registration requests were submitted from this network. Please wait about ' . $retryAfterMinutes . ' minutes before trying again.', 'danger', 7000);

              $emailValidationStatus = 'is-invalid';
              $emailHelpText         = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                          <span class="material-symbols-outlined me-1">info</span>
                                          Too many recent registration requests from this network.
                                        </span>';
            }
            elseif (!checkUserRecord($db1, 'approval_users',  ['approval_email' => $enteredEmail])
                  &&
                    !checkUserRecord($db1, 'student_details', ['student_email'   => $enteredEmail])) {

              if (checkForEquality($enteredNewPassword, $enteredConfirmPassword, 'strict')) {
                $hashedPassword    = password_hash($enteredNewPassword, PASSWORD_DEFAULT);
                $approvalTimestamp = getCurrentTimestamp();
                $generatedCode     = generateReferenceCode();

                $currentAttempt = 0;
                $maxRetries     = 3;

                while ($currentAttempt < $maxRetries) {
                  try {
                    $STMT_logApprovalUser = 'INSERT INTO approval_users
                                              (approval_email, approval_password, approval_code, approval_name, approval_father_name, approval_batch_details, approval_IP_address, approval_timestamp)
                                            VALUES
                                              (:approval_email, :approval_password, :approval_code, :approval_name, :approval_father_name, :approval_batch_details, :approval_IP_address, :approval_timestamp)';
                    $logApprovalUser = $db1->prepare($STMT_logApprovalUser);
                    $logApprovalUser->bindValue(':approval_email',          $enteredEmail,         PDO::PARAM_STR);
                    $logApprovalUser->bindValue(':approval_password',       $hashedPassword,       PDO::PARAM_STR);
                    $logApprovalUser->bindValue(':approval_code',           $generatedCode,        PDO::PARAM_STR);
                    $logApprovalUser->bindValue(':approval_name',           $enteredName,          PDO::PARAM_STR);
                    $logApprovalUser->bindValue(':approval_father_name',    $enteredFatherName,    PDO::PARAM_STR);
                    $logApprovalUser->bindValue(':approval_batch_details',  $enteredBatch,         PDO::PARAM_STR);
                    $logApprovalUser->bindValue(':approval_IP_address',     $registrationIpAddress,PDO::PARAM_STR);
                    $logApprovalUser->bindValue(':approval_timestamp',      $approvalTimestamp,    PDO::PARAM_STR);
                    $logApprovalUser->execute();
                    break;
                  }
                  catch (PDOException $ex) {
                    if (!isRetryablePdoException($ex)) {
                      setToast('Error occurred while Registering User. Contact Admin.', 'danger', 7000);
                      logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error occurred while Registering Student: ' . $ex->getMessage());
                      exit;
                    }

                    $currentAttempt++;
                    sleep(5);
                  }
                }
                if ($currentAttempt >= $maxRetries) {
                  setToast('Error occurred while Registering User. Contact Admin.', 'danger', 7000);
                  exit;
                }

                $displayEmail        = htmlspecialchars($enteredEmail,    ENT_QUOTES, 'UTF-8');
                $displayGeneratedCode = htmlspecialchars($generatedCode,  ENT_QUOTES, 'UTF-8');

                $mail = createConfiguredMailer();
                $mail->addAddress($enteredEmail);
                $mail->isHTML(true);
                $mail->Subject = 'Account Creation Successful | Career Institute';
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
                                  To activate your account, please reach out to us at the office and provide the activation code below.
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
                                        ' . $displayGeneratedCode . '
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
                $mail->AltBody = "Welcome to Career Institute!\n\nYour account creation request was submitted successfully.\nReference code: " . $generatedCode . "\n\nPlease keep this code for the approval process.";

                $activationEmailSent = false;
                $emailAttempt        = 0;
                $maxEmailRetries     = 3;

                while ($emailAttempt < $maxEmailRetries) {
                  try {
                    if ($mail->send()) {
                      $activationEmailSent = true;
                      break;
                    }
                  }
                  catch (Exception $ex) {
                    if (!isRetryableSmtpFailure($mail)) {
                      logAppError($db2, null, getCurrentURL(), 'MAIL', 'Error occurred while Sending Activation Mail: ' . $mail->ErrorInfo);
                      break;
                    }
                  }
                  $emailAttempt++;
                  sleep(5);
                }

                $registrationSuccessData = [
                  'email'          => $enteredEmail,
                  'name'           => $enteredName,
                  'batch'          => $enteredBatch,
                  'generated_code' => $generatedCode,
                  'created_at'     => $approvalTimestamp, // display-only, never written to DB
                  'email_sent'     => $activationEmailSent,
                ];

                if ($activationEmailSent) {
                  setToast('Account Details Submitted Successfully. Waiting for Approval from Admin.', 'success', 15000);
                } else {
                  setToast('Account Details Submitted Successfully. Keep the displayed code safely.', 'warning', 15000);
                }
              }
              else {
                setToast('New Password and Confirm Password do not match.', 'danger', 7000);

                $newPasswordValidationStatus     = 'is-invalid';
                $confirmPasswordValidationStatus = 'is-invalid';
                $newPasswordHelpText             = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                                      <span class="material-symbols-outlined me-1">info</span>
                                                      Entered Passwords do not match. Please enter the details carefully!
                                                    </span>';
                $confirmPasswordHelpText         = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                                      <span class="material-symbols-outlined me-1">info</span>
                                                      Entered Passwords do not match. Please enter the details carefully!
                                                    </span>';
              }
            }
            else {
              if (checkUserRecord($db1, 'approval_users', ['approval_email' => $enteredEmail])) {
                setToast('This E-mail was recently used for account creation. Try another one.', 'danger', 7000);

                $emailValidationStatus = 'is-invalid';
                $emailHelpText         = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                            <span class="material-symbols-outlined me-1">info</span>
                                            This E-mail was recently used for account creation. Contact Office.
                                          </span>';
              }
              elseif (checkUserRecord($db1, 'student_details', ['student_email' => $enteredEmail])) {
                setToast('The E-mail entered is already associated with another account. Try another one.', 'danger', 7000);

                $emailValidationStatus = 'is-invalid';
                $emailHelpText         = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                            <span class="material-symbols-outlined me-1">info</span>
                                            The E-mail entered is already associated with another account. Try another one.
                                          </span>';
              }
            }
          }
          else {
            setToast('One or more Input Fields were wrongly filled.', 'danger', 7000);

            if (!validateEmail($enteredEmail)) {
              $emailValidationStatus = 'is-invalid';
              $emailHelpText         = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                          <span class="material-symbols-outlined me-1">info</span>
                                          E-Mail entered is not valid.
                                        </span>';
            }
            elseif (!validatePassword($enteredNewPassword) && !validatePassword($enteredConfirmPassword)) {
              $newPasswordValidationStatus     = 'is-invalid';
              $confirmPasswordValidationStatus = 'is-invalid';
              $newPasswordHelpText             = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                                    <span class="material-symbols-outlined me-1">info</span>
                                                    Password must be at least 8 characters with 1 symbol and 1 numeral.
                                                  </span>';
              $confirmPasswordHelpText         = $newPasswordHelpText;
            }
            elseif (!validatePassword($enteredNewPassword)) {
              $newPasswordValidationStatus = 'is-invalid';
              $newPasswordHelpText         = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                                <span class="material-symbols-outlined me-1">info</span>
                                                Password must be at least 8 characters with 1 symbol and 1 numeral.
                                              </span>';
            }
            elseif (!validatePassword($enteredConfirmPassword)) {
              $confirmPasswordValidationStatus = 'is-invalid';
              $confirmPasswordHelpText         = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                                    <span class="material-symbols-outlined me-1">info</span>
                                                    Password must be at least 8 characters with 1 symbol and 1 numeral.
                                                  </span>';
            }
            elseif (!checkForEquality($enteredNewPassword, $enteredConfirmPassword, 'strict')) {
              $newPasswordValidationStatus     = 'is-invalid';
              $confirmPasswordValidationStatus = 'is-invalid';
              $newPasswordHelpText             = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                                    <span class="material-symbols-outlined me-1">info</span>
                                                    Entered Passwords do not match. Please enter the details carefully!
                                                  </span>';
              $confirmPasswordHelpText         = $newPasswordHelpText;
            }
          }
        }
        else {
          setToast('Agree to Terms and Conditions before proceeding ahead.', 'danger', 7000);

          $agreeTermsAndConditionsValidationStatus = 'is-invalid';
          $agreeTermsAndConditionsHelpText         = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                                        <span class="material-symbols-outlined me-1">info</span>
                                                        Accept to Terms and Conditions before proceeding.
                                                      </span>';
        }
      }
      else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
    }
  }
?>

<?php // Headers
  $page_title = "Register | careerinstitute.co.in";

  require_once '../components/header.php';
?>

<?php if (checkForEquality(checkLoginStatus($db1), false, 'strict')): // User Logged Out ?>
  <section class="section-border border-primary min-vh-100">
    <div class="container d-flex flex-column justify-content-center py-10">
      <div class="row justify-content-center">
        <div class="col-12 col-xl-8">

          <p class="display-4 fw-bold text-center">Register</p>
          <p class="text-lead mb-5 text-center text-body-secondary">
            Create your student account to access classes, materials, and your dashboard.
          </p>

          <?php if (is_array($registrationSuccessData)): ?>
            <?php
              $displayName      = escapeOutput($registrationSuccessData['name']           ?? 'Student');
              $displayEmail     = escapeOutput($registrationSuccessData['email']          ?? '');
              $displayCode      = escapeOutput($registrationSuccessData['generated_code'] ?? '');
              $displayCreatedAt = escapeOutput($registrationSuccessData['created_at']     ?? getCurrentTimestamp());
              $displayBatch     = escapeOutput(prettyPrintClassCode($registrationSuccessData['batch'] ?? ''));
              $emailSent        = (bool) ($registrationSuccessData['email_sent']          ?? false);
            ?>

            <div class="card border shadow-lg mb-5">
              <div class="card-header py-3 text-center">
                <span class="badge bg-success-subtle text-success rounded-pill px-4 py-2 mb-3">
                  Request Submitted
                </span>
                <h2 class="h3 fw-bold mb-1">Account Creation Request Received</h2>
                <small class="text-body-secondary">
                  Your details were saved successfully and are now waiting for admin approval.
                </small>
              </div>
              <div class="card-body p-6 p-md-8">
                <div class="row g-4 align-items-start">
                  <div class="col-12 col-md-5">
                    <p class="text-uppercase small text-body-secondary fw-bold mb-2">Reference Code</p>
                    <p class="display-5 fw-bold text-primary mb-3"><?php echo $displayCode; ?></p>
                    <p class="text-body-secondary mb-0">
                      Keep this code safe. You need it during the approval process.
                    </p>
                  </div>
                  <div class="col-12 col-md-7">
                    <div class="border rounded-3 p-4 bg-light">
                      <p class="fw-semibold mb-2">Submitted Details</p>
                      <p class="mb-1"><span class="text-body-secondary">Name:</span> <?php echo $displayName; ?></p>
                      <p class="mb-1"><span class="text-body-secondary">Email:</span> <?php echo $displayEmail; ?></p>
                      <p class="mb-1"><span class="text-body-secondary">Batch:</span> <?php echo $displayBatch; ?></p>
                      <p class="mb-0"><span class="text-body-secondary">Submitted:</span> <?php echo $displayCreatedAt; ?></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="card border shadow-sm mb-7">
              <div class="card-header py-3">
                <h3 class="h4 fw-semibold mb-0">Next Instructions</h3>
              </div>
              <div class="card-body p-6">
                <p class="mb-3">
                  <span class="text-success d-flex align-items-start justify-content-center my-3">
                    <span class="material-symbols-outlined me-1 mt-1">info</span>
                    Use this reference code to track your request and communicate with the admin.
                  </span>
                </p>
                <p class="mt-5 mb-0 fs-sm text-uppercase text-body-secondary text-center">
                  <?php echo $emailSent
                    ? 'You created an account with Career Institute.'
                    : 'The confirmation email could not be sent right now, but your request was saved successfully.'; ?>
                </p>
              </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
              <a class="btn btn-outline-primary rounded-pill px-5" href="./">Back to Register</a>
            </div>

          <?php else: ?>
            <form class="my-4 d-flex flex-column gap-4" method="POST" action="./">
              <div class="card border shadow-lg">
                <div class="card-header pt-3">
                  <h4 class="mb-0 fw-semibold">Basic Details</h4>
                  <small class="text-body-secondary">Your personal and academic information</small>
                </div>

                <div class="card-body p-7">

                  <div class="row g-3 mb-3">

                    <div class="col-12 col-md-6">
                      <div class="form-floating">
                        <input class="form-control <?php echo $studentNameValidationStatus ?? null; ?>"
                              id="student_name"
                              type="text"
                              name="student_name"
                              placeholder=" "
                              required>
                        <label for="student_name">Student Name <span class="text-danger">*</span></label>
                      </div>
                      <div class="form-text"><?php echo $studentNameHelpText ?? ''; ?></div>
                    </div>

                    <div class="col-12 col-md-6">
                      <div class="form-floating">
                        <input class="form-control <?php echo $fatherNameValidationStatus ?? null; ?>"
                              id="father_name"
                              type="text"
                              name="father_name"
                              placeholder=" "
                              required>
                        <label for="father_name">Father Name <span class="text-danger">*</span></label>
                      </div>
                      <div class="form-text"><?php echo $fatherNameHelpText ?? ''; ?></div>
                    </div>

                  </div>

                  <div class="row g-3">

                    <div class="col-12 col-md-6">

                      <div class="form-floating mb-3">
                        <select class="form-select" id="boardSelect" name="boardSelect" required>
                          <option value="" disabled selected></option>
                          <option value="CBSE">CBSE</option>
                          <option value="BSEB">BSEB</option>
                          <option value="ICSE">ICSE</option>
                        </select>
                        <label for="boardSelect">Board of Education <span class="text-danger">*</span></label>
                      </div>

                      <div class="form-floating">
                        <select class="form-select <?php echo $studentBatchValidationStatus ?? null; ?>"
                                id="batchSelect"
                                name="batchSelect"
                                required
                                disabled>
                          <option value="" disabled selected></option>
                          <?php
                            $array_batchlist = retrieveActiveBatchlist($db2);
                            $batches = json_decode($array_batchlist["value"], true);
                            $batches = array_values($batches);

                            foreach ($batches as $code) {
                              $pretty = prettyPrintClassCode($code);
                              foreach (['CBSE', 'BSEB', 'ICSE'] as $board) {
                                if (str_contains($code, $board)) {
                                  echo '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '" data-board="' . $board . '" style="display:none">'
                                      . htmlspecialchars($pretty, ENT_QUOTES, 'UTF-8') . '</option>';
                                  break;
                                }
                              }
                            }
                          ?>
                        </select>
                        <label for="batchSelect">Select Batch <span class="text-danger">*</span></label>
                      </div>

                      <div class="form-text"><?php echo $studentBatchHelpText ?? ''; ?></div>

                    </div>

                    <div class="col-12 col-md-6">
                      <div class="form-floating">
                        <input class="form-control <?php echo $emailValidationStatus ?? null; ?>"
                              id="email"
                              type="email"
                              name="email"
                              placeholder=" "
                              required>
                        <label for="email">Email Address <span class="text-danger">*</span></label>
                      </div>
                      <div class="form-text"><?php echo $emailHelpText ?? ''; ?></div>
                    </div>

                  </div>

                </div>
              </div>

              <div class="card border shadow-lg mb-3">
                <div class="card-header py-3">
                  <h3 class="mb-0 fw-semibold">Account Credentials</h3>
                  <small class="text-body-secondary">Set a secure password for your account</small>
                </div>

                <div class="card-body p-7">

                  <div class="row g-3 mb-3">

                    <div class="col-12 mb-3">
                      <div class="form-floating position-relative">
                        
                        <input class="form-control pe-5 <?php echo $newPasswordValidationStatus ?? null; ?>"
                              id="new_password"
                              type="password"
                              name="new_password"
                              placeholder=" "
                              required>

                        <label for="new_password">Password <span class="text-danger">*</span></label>

                        <button type="button"
                                class="btn position-absolute top-50 end-0 translate-middle-y me-2 p-0 border-0 bg-transparent"
                                onclick="togglePassword('new_password', this)">
                          
                          <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                            <path class="eye-open" d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM8 13c-3.314 0-6-3-6-5s2.686-5 6-5 6 3 6 5-2.686 5-6 5z"/>
                            <path class="eye-open" d="M8 5a3 3 0 100 6 3 3 0 000-6z"/>
                            <path class="eye-closed d-none" d="M13.359 11.238C14.742 10.06 16 8 16 8s-3-5.5-8-5.5c-1.28 0-2.47.29-3.536.77l1.53 1.53A3 3 0 018 5a3 3 0 013 3c0 .343-.058.672-.165.978l2.524 2.23zM2.454 1.146l12.4 12.4-.708.708-2.078-2.078C10.978 12.68 9.54 13.5 8 13.5 3 13.5 0 8 0 8s1.273-2.338 3.361-3.738L1.746 1.854l.708-.708z"/>
                          </svg>

                        </button>

                      </div>

                      <div class="form-text">
                        <?php echo $newPasswordHelpText ?? 'Min. 8 characters with 1 symbol and 1 numeral.'; ?>
                      </div>
                    </div>

                    <div class="col-12 mb-3">
                      <div class="form-floating position-relative">
                        
                        <input class="form-control pe-5 <?php echo $confirmPasswordValidationStatus ?? null; ?>"
                              id="confirm_password"
                              type="password"
                              name="confirm_password"
                              placeholder=" "
                              required>

                        <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>

                        <button type="button"
                                class="btn position-absolute top-50 end-0 translate-middle-y me-2 p-0 border-0 bg-transparent"
                                onclick="togglePassword('confirm_password', this)">
                          
                          <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                            <path class="eye-open" d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM8 13c-3.314 0-6-3-6-5s2.686-5 6-5 6 3 6 5-2.686 5-6 5z"/>
                            <path class="eye-open" d="M8 5a3 3 0 100 6 3 3 0 000-6z"/>
                            <path class="eye-closed d-none" d="M13.359 11.238C14.742 10.06 16 8 16 8s-3-5.5-8-5.5c-1.28 0-2.47.29-3.536.77l1.53 1.53A3 3 0 018 5a3 3 0 013 3c0 .343-.058.672-.165.978l2.524 2.23zM2.454 1.146l12.4 12.4-.708.708-2.078-2.078C10.978 12.68 9.54 13.5 8 13.5 3 13.5 0 8 0 8s1.273-2.338 3.361-3.738L1.746 1.854l.708-.708z"/>
                          </svg>

                        </button>

                      </div>

                      <div class="form-text">
                        <?php echo $confirmPasswordHelpText ?? 'Re-enter your password for verification.'; ?>
                      </div>
                    </div>

                  </div>

                  <div class="row g-3">
                    <div class="col-12">
                      <div class="d-flex align-items-start gap-2">
                        <input class="form-check-input mt-1 <?php echo $agreeTermsAndConditionsValidationStatus ?? null; ?>"
                              type="checkbox"
                              name="agreeTermsAndConditions"
                              id="agreeTermsAndConditions"
                              checked
                              required>
                        <label class="form-check-label" for="agreeTermsAndConditions">
                          Agree to <a href="https://careerinstitute.co.in/terms&Service/">Terms and Conditions</a>.
                        </label>
                      </div>
                      <div class="form-text"><?php echo $agreeTermsAndConditionsHelpText ?? null; ?></div>
                    </div>
                  </div>

                  <input type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                </div>
              </div>

              <button class="btn btn-primary rounded-pill w-100" name="registerStudentBtn" type="submit">
                Register as Student
              </button>

            </form>

            <p class="text-center text-body-secondary mb-2">
              Already have an account? <a href="../login/">Login</a>.
            </p>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </section>

  <script type="text/javascript">
    function togglePassword(fieldId, btn) {
      const input     = document.getElementById(fieldId);
      const eyeOpen   = btn.querySelector('.eye-open');
      const eyeClosed = btn.querySelector('.eye-closed');

      if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.classList.add('d-none');
        eyeClosed.classList.remove('d-none');
      } else {
        input.type = 'password';
        eyeOpen.classList.remove('d-none');
        eyeClosed.classList.add('d-none');
      }
    }

    const boardSelect = document.getElementById('boardSelect');
    if (boardSelect) {
      boardSelect.addEventListener('change', function () {
        const board       = this.value;
        const batchSelect = document.getElementById('batchSelect');
        const options     = batchSelect.querySelectorAll('option[data-board]');

        batchSelect.value = '';
        options.forEach(function (opt) {
          const matches  = opt.dataset.board === board;
          opt.style.display = matches ? '' : 'none';
          opt.disabled      = !matches;
        });

        batchSelect.disabled = ![...options].some(function (o) { return o.dataset.board === board; });
      });
    }
  </script>

<?php elseif (checkForEquality(checkLoginStatus($db1), true, 'strict')): // User Logged In ?>
  <section class="section-border border-primary">
    <div class="container-lg">
      <div class="d-flex flex-column align-items-center justify-content-center min-vh-100">
        <svg height="100px" width="100px" version="1.1" id="_x32_" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve" fill="#000000">
          <g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <style type="text/css"> .st0{fill:#000000;} </style> <g> <path class="st0" d="M256,0C114.616,0,0,114.612,0,256s114.616,256,256,256s256-114.612,256-256S397.385,0,256,0z M207.678,378.794 c0-17.612,14.281-31.893,31.893-31.893c17.599,0,31.88,14.281,31.88,31.893c0,17.595-14.281,31.884-31.88,31.884 C221.959,410.678,207.678,396.389,207.678,378.794z M343.625,218.852c-3.596,9.793-8.802,18.289-14.695,25.356 c-11.847,14.148-25.888,22.718-37.442,29.041c-7.719,4.174-14.533,7.389-18.769,9.769c-2.905,1.604-4.479,2.95-5.256,3.826 c-0.768,0.926-1.029,1.306-1.496,2.826c-0.273,1.009-0.558,2.612-0.558,5.091c0,6.868,0,12.512,0,12.512 c0,6.472-5.248,11.728-11.723,11.728h-28.252c-6.475,0-11.732-5.256-11.732-11.728c0,0,0-5.645,0-12.512 c0-6.438,0.752-12.744,2.405-18.777c1.636-6.008,4.215-11.718,7.508-16.694c6.599-10.083,15.542-16.802,23.984-21.48 c7.401-4.074,14.723-7.455,21.516-11.281c6.789-3.793,12.843-7.91,17.302-12.372c2.988-2.975,5.31-6.05,7.087-9.52 c2.335-4.628,3.955-10.067,3.992-18.389c0.012-2.463-0.698-5.702-2.632-9.405c-1.926-3.686-5.066-7.694-9.264-11.29 c-8.45-7.248-20.843-12.545-35.054-12.521c-16.285,0.058-27.186,3.876-35.587,8.62c-8.36,4.776-11.029,9.595-11.029,9.595 c-4.268,3.718-10.603,3.85-15.025,0.314l-21.71-17.397c-2.719-2.173-4.322-5.438-4.396-8.926c-0.063-3.479,1.425-6.81,4.061-9.099 c0,0,6.765-10.43,22.451-19.38c15.62-8.992,36.322-15.488,61.236-15.429c20.215,0,38.839,5.562,54.268,14.661 c15.434,9.148,27.897,21.744,35.851,36.876c5.281,10.074,8.525,21.43,8.533,33.38C349.211,198.042,347.248,209.058,343.625,218.852 z"></path> </g> </g>
        </svg>
        <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
          <h1 class="display-3 fw-bold text-center">
            Session Active.
          </h1>
          <p class="mb-5 text-center text-body-secondary">
            You are currently logged in. Please sign out to access this page.
          </p>
          <div class="text-center my-7">
            <a class="btn btn-primary rounded-pill ff-sourcesans3" href="../logout/">
              Back to Logout
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>

<?php require_once '../components/footer.php'; ?>