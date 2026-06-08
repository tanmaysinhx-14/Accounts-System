<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
    'required_roles' => ['admin'],
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for User Manager
  if(checkForEquality(checkLoginStatus($db1), true, 'strict')) {
    if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')) {
      function ciEscape(mixed $value): string {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
      }

      function ciNullableString(mixed $value): ?string {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
      }

      function ciBindNullableString(PDOStatement $stmt, string $parameter, ?string $value): void {
        $stmt->bindValue($parameter, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
      }

      function ciJsonForDataset(array $record): string {
        unset(
          $record['student_password'],
          $record['faculty_password'],
          $record['faculty_reference_code'],
          $record['admin_password'],
          $record['student_current_active_session'],
          $record['faculty_current_active_session'],
          $record['admin_current_active_session']
        );

        return htmlspecialchars(
          json_encode($record, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
          ENT_QUOTES,
          'UTF-8'
        );
      }

      function ciFormatColumnLabel(string $column, ?string $role = null): string {
        if ($role !== null) {
          $column = preg_replace('/^' . preg_quote($role, '/') . '_/', '', $column);
        }

        return ucwords(str_replace('_', ' ', $column));
      }

      function ciFetchStudentRecords(PDO $db): array {
        try {
          $stmt = $db->query(
            'SELECT sd.student_id,
                    sd.student_usercode,
                    sd.student_email,
                    sd.student_username,
                    sd.student_name,
                    sd.student_father_name,
                    sd.student_guardian_name,
                    sd.student_batch_details,
                    sd.student_school_name,
                    sd.student_bio,
                    sc.student_current_active_session,
                    sc.student_account_activation_status,
                    sc.student_has_updated_username,
                    sc.student_has_updated_account_profile,
                    sc.student_has_opted_email_communication,
                    sc.student_malpractice_counter,
                    st.student_account_creation_timestamp,
                    st.student_last_login_timestamp,
                    st.student_last_OTP_request_timestamp,
                    st.student_last_email_request_timestamp,
                    st.student_batch_updating_timestamp,
                    st.student_profile_updating_timestamp,
                    st.student_account_deactivation_timestamp
            FROM student_details sd
            LEFT JOIN student_configurations sc
              ON sd.student_id = sc.student_id
            LEFT JOIN student_timestamps st
              ON sd.student_id = st.student_id
            ORDER BY sd.student_id DESC'
          );

          return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        catch (PDOException) {
          return [];
        }
      }

      function ciFetchFacultyRecords(PDO $db): array {
        try {
          $stmt = $db->query(
            'SELECT fd.faculty_id,
                    fd.faculty_usercode,
                    fd.faculty_email,
                    fd.faculty_username,
                    fd.faculty_name,
                    fd.faculty_bio,
                    fc.faculty_current_active_session,
                    fc.faculty_account_activation_status,
                    fc.faculty_has_used_account_activation,
                    fc.faculty_has_updated_username,
                    fc.faculty_has_updated_account_profile,
                    fc.faculty_has_opted_email_communication,
                    ft.faculty_account_activation_timestamp,
                    ft.faculty_account_creation_timestamp,
                    ft.faculty_last_login_timestamp,
                    ft.faculty_last_OTP_request_timestamp,
                    ft.faculty_last_email_request_timestamp
            FROM faculty_details fd
            LEFT JOIN faculty_configurations fc
              ON fd.faculty_id = fc.faculty_id
            LEFT JOIN faculty_timestamps ft
              ON fd.faculty_id = ft.faculty_id
            ORDER BY fd.faculty_id DESC'
          );

          return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        catch (PDOException) {
          return [];
        }
      }

      function ciFetchAdminRecords(PDO $db): array {
        try {
          $stmt = $db->query(
            'SELECT admin_id,
                    admin_usercode,
                    admin_email,
                    admin_username,
                    admin_name,
                    admin_bio,
                    admin_current_active_session
            FROM admin_details
            ORDER BY admin_id DESC'
          );

          return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        catch (PDOException) {
          return [];
        }
      }

      function ciStudentEmailExists(PDO $db, string $email, int $studentId): bool {
        $stmt = $db->prepare('SELECT student_id FROM student_details WHERE student_email = :email AND student_id != :id LIMIT 1');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
      }

      function ciStudentUsernameExists(PDO $db, string $username, int $studentId): bool {
        $stmt = $db->prepare('SELECT student_id FROM student_details WHERE student_username = :username AND student_id != :id LIMIT 1');
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->bindValue(':id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
      }

      function ciFacultyEmailExists(PDO $db, string $email, int $facultyId): bool {
        $stmt = $db->prepare('SELECT faculty_id FROM faculty_details WHERE faculty_email = :email AND faculty_id != :id LIMIT 1');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':id', $facultyId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
      }

      function ciFacultyUsernameExists(PDO $db, string $username, int $facultyId): bool {
        $stmt = $db->prepare('SELECT faculty_id FROM faculty_details WHERE faculty_username = :username AND faculty_id != :id LIMIT 1');
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->bindValue(':id', $facultyId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
      }

      function ciFetchUsercodeById(PDO $db, string $table, string $idColumn, string $usercodeColumn, int $id): ?string {
        $stmt = $db->prepare("SELECT {$usercodeColumn} FROM {$table} WHERE {$idColumn} = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $usercode = $stmt->fetchColumn();
        return is_string($usercode) && $usercode !== '' ? $usercode : null;
      }

      $allowedParameter = ['viewStudents', 'viewFaculties', 'viewAdmins', null];
      $viewParameter = $_GET['view'] ?? null;

      if (!in_array($viewParameter, $allowedParameter, true)) {
        redirectTo('./', 0);
        exit;
      }

      if (isset($_POST['viewUserBtn'])) {
        $selectedUserRole = escapeOutput($_POST['user_role'] ?? null);

        switch ($selectedUserRole) {
          case 'student':
            redirectTo('./?view=viewStudents', 0);
            break;
          case 'faculty':
            redirectTo('./?view=viewFaculties', 0);
            break;
          case 'admin':
            redirectTo('./?view=viewAdmins', 0);
            break;
          default:
            redirectTo('./', 0);
        }
      }

      if (isset($_POST['editStudentRecord'])) {
        $csrfToken = escapeOutput($_POST['csrf_token'] ?? null);

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          $studentId = (int) ($_POST['edit_student_id'] ?? 0);
          $studentName = trim((string) ($_POST['edit_student_name'] ?? ''));
          $studentEmail = trim((string) ($_POST['edit_student_email'] ?? ''));
          $studentUsername = ciNullableString($_POST['edit_student_username'] ?? null);
          $studentFatherName = ciNullableString($_POST['edit_student_father_name'] ?? null);
          $studentGuardianName = ciNullableString($_POST['edit_student_guardian_name'] ?? null);
          $studentBatchDetails = ciNullableString($_POST['edit_student_batch_details'] ?? null);
          $studentSchoolName = ciNullableString($_POST['edit_student_school_name'] ?? null);
          $studentBio = ciNullableString($_POST['edit_student_bio'] ?? null);
          $activationStatus = isset($_POST['edit_student_account_activation_status']) ? 1 : 0;
          $usernameStatus = isset($_POST['edit_student_has_updated_username']) ? 1 : 0;
          $profileStatus = isset($_POST['edit_student_has_updated_account_profile']) ? 1 : 0;
          $emailPreference = isset($_POST['edit_student_has_opted_email_communication']) ? 1 : 0;
          $malpracticeCounter = max(0, min(9, (int) ($_POST['edit_student_malpractice_counter'] ?? 0)));

          $isValid = true;

          if ($studentId <= 0 || $studentName === '' || strlen($studentName) < 2) {
            setToast('Please provide a valid student record and name.', 'danger', 6000);
            $isValid = false;
          }

          if ($isValid && !validateEmail($studentEmail)) {
            setToast('Please provide a valid student email address.', 'danger', 6000);
            $isValid = false;
          }

          if ($isValid && $studentUsername !== null && !validateUsername($studentUsername)) {
            setToast('Student username must be 3-16 alphanumeric characters and include both letters and numbers.', 'danger', 7000);
            $isValid = false;
          }

          if ($isValid) {
            try {
              if (ciStudentEmailExists($db1, $studentEmail, $studentId)) {
                setToast('This email address is already used by another student.', 'danger', 6000);
                $isValid = false;
              }
              elseif ($studentUsername !== null && ciStudentUsernameExists($db1, $studentUsername, $studentId)) {
                setToast('This username is already used by another student.', 'danger', 6000);
                $isValid = false;
              }
            }
            catch (PDOException) {
              setToast('Unable to validate the student record. Please try again.', 'danger', 6000);
              $isValid = false;
            }
          }

          if ($isValid) {
            $attempt = 0;
            $maxRetries = 3;

            while ($attempt < $maxRetries) {
              try {
                $db1->beginTransaction();

                $updateDetails = $db1->prepare(
                  'UPDATE student_details
                  SET student_email = :email,
                      student_username = :username,
                      student_name = :name,
                      student_father_name = :fatherName,
                      student_guardian_name = :guardianName,
                      student_batch_details = :batchDetails,
                      student_school_name = :schoolName,
                      student_bio = :bio
                  WHERE student_id = :id
                  LIMIT 1'
                );
                $updateDetails->bindValue(':email', $studentEmail, PDO::PARAM_STR);
                ciBindNullableString($updateDetails, ':username', $studentUsername);
                $updateDetails->bindValue(':name', $studentName, PDO::PARAM_STR);
                ciBindNullableString($updateDetails, ':fatherName', $studentFatherName);
                ciBindNullableString($updateDetails, ':guardianName', $studentGuardianName);
                ciBindNullableString($updateDetails, ':batchDetails', $studentBatchDetails);
                ciBindNullableString($updateDetails, ':schoolName', $studentSchoolName);
                ciBindNullableString($updateDetails, ':bio', $studentBio);
                $updateDetails->bindValue(':id', $studentId, PDO::PARAM_INT);
                $updateDetails->execute();

                $updateConfig = $db1->prepare(
                  'UPDATE student_configurations
                  SET student_email = :email,
                      student_account_activation_status = :activationStatus,
                      student_has_updated_username = :usernameStatus,
                      student_has_updated_account_profile = :profileStatus,
                      student_has_opted_email_communication = :emailPreference,
                      student_malpractice_counter = :malpracticeCounter
                  WHERE student_id = :id
                  LIMIT 1'
                );
                $updateConfig->bindValue(':email', $studentEmail, PDO::PARAM_STR);
                $updateConfig->bindValue(':activationStatus', $activationStatus, PDO::PARAM_INT);
                $updateConfig->bindValue(':usernameStatus', $usernameStatus, PDO::PARAM_INT);
                $updateConfig->bindValue(':profileStatus', $profileStatus, PDO::PARAM_INT);
                $updateConfig->bindValue(':emailPreference', $emailPreference, PDO::PARAM_INT);
                $updateConfig->bindValue(':malpracticeCounter', $malpracticeCounter, PDO::PARAM_INT);
                $updateConfig->bindValue(':id', $studentId, PDO::PARAM_INT);
                $updateConfig->execute();

                $updateTimestamps = $db1->prepare(
                  'UPDATE student_timestamps
                  SET student_email = :email,
                      student_profile_updating_timestamp = :profileUpdatedAt
                  WHERE student_id = :id
                  LIMIT 1'
                );
                $updateTimestamps->bindValue(':email', $studentEmail, PDO::PARAM_STR);
                $updateTimestamps->bindValue(':profileUpdatedAt', getCurrentTimestamp(), PDO::PARAM_STR);
                $updateTimestamps->bindValue(':id', $studentId, PDO::PARAM_INT);
                $updateTimestamps->execute();

                $updateDeviceDetails = $db1->prepare(
                  'UPDATE student_devicedetails
                  SET student_email = :email
                  WHERE student_id = :id'
                );
                $updateDeviceDetails->bindValue(':email', $studentEmail, PDO::PARAM_STR);
                $updateDeviceDetails->bindValue(':id', $studentId, PDO::PARAM_INT);
                $updateDeviceDetails->execute();

                $db1->commit();
                setToast('Student record updated successfully.', 'success', 5000);
                redirectTo('./?view=viewStudents', 0);
                break;
              }
              catch (PDOException $ex) {
                if ($db1->inTransaction()) {
                  $db1->rollBack();
                }

                if (!isRetryablePdoException($ex)) {
                  setToast('An error occurred while updating the student record.', 'danger', 7000);
                  logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error updating student record: ' . $ex->getMessage());
                  break;
                }

                $attempt++;
                sleep(3);
              }
            }

            if ($attempt >= $maxRetries) {
              setToast('Failed to update the student record after multiple attempts.', 'danger', 7000);
            }
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      if (isset($_POST['editFacultyRecord'])) {
        $csrfToken = escapeOutput($_POST['csrf_token'] ?? null);

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          $facultyId = (int) ($_POST['edit_faculty_id'] ?? 0);
          $facultyName = trim((string) ($_POST['edit_faculty_name'] ?? ''));
          $facultyEmail = trim((string) ($_POST['edit_faculty_email'] ?? ''));
          $facultyUsername = ciNullableString($_POST['edit_faculty_username'] ?? null);
          $facultyBio = ciNullableString($_POST['edit_faculty_bio'] ?? null);
          $activationStatus = isset($_POST['edit_faculty_account_activation_status']) ? 1 : 0;
          $usedActivationStatus = isset($_POST['edit_faculty_has_used_account_activation']) ? 1 : 0;
          $usernameStatus = isset($_POST['edit_faculty_has_updated_username']) ? 1 : 0;
          $profileStatus = isset($_POST['edit_faculty_has_updated_account_profile']) ? 1 : 0;
          $emailPreference = isset($_POST['edit_faculty_has_opted_email_communication']) ? 1 : 0;

          $isValid = true;

          if ($facultyId <= 0 || $facultyName === '' || strlen($facultyName) < 2) {
            setToast('Please provide a valid faculty record and name.', 'danger', 6000);
            $isValid = false;
          }

          if ($isValid && !validateEmail($facultyEmail)) {
            setToast('Please provide a valid faculty email address.', 'danger', 6000);
            $isValid = false;
          }

          if ($isValid && $facultyUsername !== null && !validateUsername($facultyUsername)) {
            setToast('Faculty username must be 3-16 alphanumeric characters and include both letters and numbers.', 'danger', 7000);
            $isValid = false;
          }

          if ($isValid) {
            try {
              if (ciFacultyEmailExists($db1, $facultyEmail, $facultyId)) {
                setToast('This email address is already used by another faculty member.', 'danger', 6000);
                $isValid = false;
              }
              elseif ($facultyUsername !== null && ciFacultyUsernameExists($db1, $facultyUsername, $facultyId)) {
                setToast('This username is already used by another faculty member.', 'danger', 6000);
                $isValid = false;
              }
            }
            catch (PDOException) {
              setToast('Unable to validate the faculty record. Please try again.', 'danger', 6000);
              $isValid = false;
            }
          }

          if ($isValid) {
            $attempt = 0;
            $maxRetries = 3;

            while ($attempt < $maxRetries) {
              try {
                $db1->beginTransaction();

                $updateDetails = $db1->prepare(
                  'UPDATE faculty_details
                  SET faculty_email = :email,
                      faculty_username = :username,
                      faculty_name = :name,
                      faculty_bio = :bio
                  WHERE faculty_id = :id
                  LIMIT 1'
                );
                $updateDetails->bindValue(':email', $facultyEmail, PDO::PARAM_STR);
                ciBindNullableString($updateDetails, ':username', $facultyUsername);
                $updateDetails->bindValue(':name', $facultyName, PDO::PARAM_STR);
                ciBindNullableString($updateDetails, ':bio', $facultyBio);
                $updateDetails->bindValue(':id', $facultyId, PDO::PARAM_INT);
                $updateDetails->execute();

                $updateConfig = $db1->prepare(
                  'UPDATE faculty_configurations
                  SET faculty_email = :email,
                      faculty_account_activation_status = :activationStatus,
                      faculty_has_used_account_activation = :usedActivationStatus,
                      faculty_has_updated_username = :usernameStatus,
                      faculty_has_updated_account_profile = :profileStatus,
                      faculty_has_opted_email_communication = :emailPreference
                  WHERE faculty_id = :id
                  LIMIT 1'
                );
                $updateConfig->bindValue(':email', $facultyEmail, PDO::PARAM_STR);
                $updateConfig->bindValue(':activationStatus', $activationStatus, PDO::PARAM_INT);
                $updateConfig->bindValue(':usedActivationStatus', $usedActivationStatus, PDO::PARAM_INT);
                $updateConfig->bindValue(':usernameStatus', $usernameStatus, PDO::PARAM_INT);
                $updateConfig->bindValue(':profileStatus', $profileStatus, PDO::PARAM_INT);
                $updateConfig->bindValue(':emailPreference', $emailPreference, PDO::PARAM_INT);
                $updateConfig->bindValue(':id', $facultyId, PDO::PARAM_INT);
                $updateConfig->execute();

                $updateTimestamps = $db1->prepare(
                  'UPDATE faculty_timestamps
                  SET faculty_email = :email
                  WHERE faculty_id = :id
                  LIMIT 1'
                );
                $updateTimestamps->bindValue(':email', $facultyEmail, PDO::PARAM_STR);
                $updateTimestamps->bindValue(':id', $facultyId, PDO::PARAM_INT);
                $updateTimestamps->execute();

                $updateDeviceDetails = $db1->prepare(
                  'UPDATE faculty_devicedetails
                  SET faculty_email = :email
                  WHERE faculty_id = :id'
                );
                $updateDeviceDetails->bindValue(':email', $facultyEmail, PDO::PARAM_STR);
                $updateDeviceDetails->bindValue(':id', $facultyId, PDO::PARAM_INT);
                $updateDeviceDetails->execute();

                $db1->commit();
                setToast('Faculty record updated successfully.', 'success', 5000);
                redirectTo('./?view=viewFaculties', 0);
                break;
              }
              catch (PDOException $ex) {
                if ($db1->inTransaction()) {
                  $db1->rollBack();
                }

                if (!isRetryablePdoException($ex)) {
                  setToast('An error occurred while updating the faculty record.', 'danger', 7000);
                  logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error updating faculty record: ' . $ex->getMessage());
                  break;
                }

                $attempt++;
                sleep(3);
              }
            }

            if ($attempt >= $maxRetries) {
              setToast('Failed to update the faculty record after multiple attempts.', 'danger', 7000);
            }
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      if (isset($_POST['deleteStudentRecord'])) {
        $csrfToken = escapeOutput($_POST['csrf_token'] ?? null);

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          $studentId = (int) ($_POST['delete_student_id'] ?? 0);
          $studentUsercode = $studentId > 0
            ? ciFetchUsercodeById($db1, 'student_details', 'student_id', 'student_usercode', $studentId)
            : null;

          if ($studentUsercode === null) {
            setToast('Invalid student record. Please try again.', 'danger', 6000);
          }
          else {
            $attempt = 0;
            $maxRetries = 3;

            while ($attempt < $maxRetries) {
              $db2TransactionStarted = false;

              try {
                $db1->beginTransaction();

                if ($db2 instanceof PDO) {
                  $db2->beginTransaction();
                  $db2TransactionStarted = true;

                  foreach ([
                    'DELETE FROM attendance_records WHERE student_usercode = :usercode',
                    'DELETE FROM email_records WHERE email_usercode = :usercode',
                    'DELETE FROM app_errorLog WHERE error_usercode = :usercode',
                  ] as $sql) {
                    $stmt = $db2->prepare($sql);
                    $stmt->bindValue(':usercode', $studentUsercode, PDO::PARAM_STR);
                    $stmt->execute();
                  }
                }

                foreach (['student_devicedetails', 'student_configurations', 'student_timestamps'] as $table) {
                  $stmt = $db1->prepare("DELETE FROM {$table} WHERE student_usercode = :usercode");
                  $stmt->bindValue(':usercode', $studentUsercode, PDO::PARAM_STR);
                  $stmt->execute();
                }

                $deleteDetails = $db1->prepare('DELETE FROM student_details WHERE student_usercode = :usercode LIMIT 1');
                $deleteDetails->bindValue(':usercode', $studentUsercode, PDO::PARAM_STR);
                $deleteDetails->execute();

                if ($db2TransactionStarted) {
                  $db2->commit();
                }

                $db1->commit();
                setToast('Student record deleted successfully.', 'success', 5000);
                redirectTo('./?view=viewStudents', 0);
                break;
              }
              catch (PDOException $ex) {
                if ($db1->inTransaction()) {
                  $db1->rollBack();
                }
                if ($db2TransactionStarted && $db2 instanceof PDO && $db2->inTransaction()) {
                  $db2->rollBack();
                }

                if (!isRetryablePdoException($ex)) {
                  setToast('An error occurred while deleting the student record.', 'danger', 7000);
                  logAppError($db2, $studentUsercode, getCurrentURL(), 'DATABASE', 'Error deleting student record: ' . $ex->getMessage());
                  break;
                }

                $attempt++;
                sleep(3);
              }
            }

            if ($attempt >= $maxRetries) {
              setToast('Failed to delete the student record after multiple attempts.', 'danger', 7000);
            }
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      if (isset($_POST['deleteFacultyRecord'])) {
        $csrfToken = escapeOutput($_POST['csrf_token'] ?? null);

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          $facultyId = (int) ($_POST['delete_faculty_id'] ?? 0);
          $facultyUsercode = $facultyId > 0
            ? ciFetchUsercodeById($db1, 'faculty_details', 'faculty_id', 'faculty_usercode', $facultyId)
            : null;

          if ($facultyUsercode === null) {
            setToast('Invalid faculty record. Please try again.', 'danger', 6000);
          }
          else {
            $attempt = 0;
            $maxRetries = 3;

            while ($attempt < $maxRetries) {
              $db2TransactionStarted = false;

              try {
                $db1->beginTransaction();

                if ($db2 instanceof PDO) {
                  $db2->beginTransaction();
                  $db2TransactionStarted = true;

                  foreach ([
                    'DELETE FROM email_records WHERE email_usercode = :usercode',
                    'DELETE FROM app_errorLog WHERE error_usercode = :usercode',
                  ] as $sql) {
                    $stmt = $db2->prepare($sql);
                    $stmt->bindValue(':usercode', $facultyUsercode, PDO::PARAM_STR);
                    $stmt->execute();
                  }
                }

                foreach (['faculty_devicedetails', 'faculty_configurations', 'faculty_timestamps'] as $table) {
                  $stmt = $db1->prepare("DELETE FROM {$table} WHERE faculty_usercode = :usercode");
                  $stmt->bindValue(':usercode', $facultyUsercode, PDO::PARAM_STR);
                  $stmt->execute();
                }

                $deleteDetails = $db1->prepare('DELETE FROM faculty_details WHERE faculty_usercode = :usercode LIMIT 1');
                $deleteDetails->bindValue(':usercode', $facultyUsercode, PDO::PARAM_STR);
                $deleteDetails->execute();

                if ($db2TransactionStarted) {
                  $db2->commit();
                }

                $db1->commit();
                setToast('Faculty record deleted successfully.', 'success', 5000);
                redirectTo('./?view=viewFaculties', 0);
                break;
              }
              catch (PDOException $ex) {
                if ($db1->inTransaction()) {
                  $db1->rollBack();
                }
                if ($db2TransactionStarted && $db2 instanceof PDO && $db2->inTransaction()) {
                  $db2->rollBack();
                }

                if (!isRetryablePdoException($ex)) {
                  setToast('An error occurred while deleting the faculty record.', 'danger', 7000);
                  logAppError($db2, $facultyUsercode, getCurrentURL(), 'DATABASE', 'Error deleting faculty record: ' . $ex->getMessage());
                  break;
                }

                $attempt++;
                sleep(3);
              }
            }

            if ($attempt >= $maxRetries) {
              setToast('Failed to delete the faculty record after multiple attempts.', 'danger', 7000);
            }
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      $fetchedRecords = [];
      $activeRecordRole = null;
      $pageHeading = 'User Records';
      $pageSubheading = 'Choose a record type to inspect account data.';

      if (checkForEquality($viewParameter, 'viewStudents', 'strict')) {
        $fetchedRecords = ciFetchStudentRecords($db1);
        $activeRecordRole = 'student';
        $pageHeading = 'Student Records';
        $pageSubheading = 'View, edit, or delete registered student accounts.';
      }
      elseif (checkForEquality($viewParameter, 'viewFaculties', 'strict')) {
        $fetchedRecords = ciFetchFacultyRecords($db1);
        $activeRecordRole = 'faculty';
        $pageHeading = 'Faculty Records';
        $pageSubheading = 'View, edit, or delete registered faculty accounts.';
      }
      elseif (checkForEquality($viewParameter, 'viewAdmins', 'strict')) {
        $fetchedRecords = ciFetchAdminRecords($db1);
        $activeRecordRole = 'admin';
        $pageHeading = 'Admin Records';
        $pageSubheading = 'View registered administrator accounts.';
      }

      $batchListConfig = retrieveActiveBatchlist($db2);
      $activeBatchList = json_decode((string) ($batchListConfig['value'] ?? '[]'), true);
      if (!is_array($activeBatchList)) {
        $activeBatchList = [];
      }

      $csrfTokenValue = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
    }
  }
?>

<?php // Headers
  $page_title = "View Users List | careerinstitute.co.in";

  require_once '../components/header.php';

  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'View Users List';

  require_once '../components/breadcrumb.php';
?>

<?php if (checkForEquality(checkLoginStatus($db1), true, 'strict')): // User Logged In ?>
  <?php if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): // For Admins?>
    <style>
      dialog.ci-dialog {
        border: none;
        border-radius: .75rem;
        box-shadow: 0 1rem 3rem rgba(0,0,0,.175);
        padding: 0;
        width: min(680px, 94vw);
        max-height: 90dvh;
        overflow-y: auto;
      }
      dialog.ci-dialog.ci-dialog--sm {
        width: min(380px, 94vw);
      }
      dialog.ci-dialog::backdrop {
        background: rgba(0,0,0,.45);
        backdrop-filter: blur(2px);
      }
      dialog.ci-dialog[open] {
        animation: ciDialogIn .18s ease-out;
      }
      @keyframes ciDialogIn {
        from { opacity: 0; transform: translateY(-10px) scale(.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
      }
      .ci-dialog__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--bs-border-color, #dee2e6);
      }
      .ci-dialog__body {
        padding: 1.25rem;
      }
      .ci-dialog__footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .5rem;
        padding: .875rem 1.25rem;
        border-top: 1px solid var(--bs-border-color, #dee2e6);
      }
      .ci-dialog__close {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        padding: 0;
        border: none;
        border-radius: .25rem;
        background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center / 1em no-repeat;
        opacity: .55;
        cursor: pointer;
        flex-shrink: 0;
      }
      .ci-dialog__close:hover {
        opacity: 1;
        background-color: rgba(0,0,0,.08);
      }
      .ci-record-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
      }
      .ci-record-field {
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: .5rem;
        padding: .75rem;
        min-width: 0;
      }
      .ci-record-field__label {
        color: var(--bs-secondary-color, #6c757d);
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: .25rem;
      }
      .ci-record-field__value {
        overflow-wrap: anywhere;
      }
      @media (max-width: 767.98px) {
        .ci-record-grid {
          grid-template-columns: 1fr;
        }
      }
    </style>

    <section class="section-border border-primary ff-inter">
      <div class="container-xxl d-flex flex-column">
        <div class="row align-items-start justify-content-center gx-0 min-vh-100">
          <div class="col-12 px-8 py-8">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-6">
              <div>
                <h2 class="fw-bold mb-1"><?php echo ciEscape($pageHeading); ?></h2>
                <p class="text-body-secondary mb-0"><?php echo ciEscape($pageSubheading); ?></p>
              </div>
              <?php if ($viewParameter !== null): ?>
                <a class="btn btn-outline-secondary rounded-pill px-4" href="./">Change List</a>
              <?php endif; ?>
            </div>

            <form method="POST"
                  action="./"
                  class="mb-8 px-lg-10 <?php echo ($viewParameter) ? 'd-none' : 'd-block'; ?>">
              <div class="d-flex row justify-content-center align-items-center mb-10">
                <div class="col form-group mb-7">
                  <label class="form-label fw-semibold">Select User Role</label>
                  <select class="form-select form-select-lg"
                          name="user_role"
                          required>
                    <option value="" selected disabled>Choose User Role</option>
                    <option value="student">Students</option>
                    <option value="faculty">Faculty</option>
                    <option value="admin">Admin</option>
                  </select>
                </div>
                <div class="col">
                  <button class="btn btn-primary btn-sm rounded-pill"
                          type="submit"
                          name="viewUserBtn">View User List</button>
                </div>
              </div>
            </form>

            <?php if ($viewParameter !== null): ?>
              <?php if (!empty($fetchedRecords)): ?>
                <div class="mb-4 d-flex align-items-center gap-3 flex-wrap">
                  <div class="position-relative grow" style="max-width:420px;">
                    <span class="material-symbols-outlined position-absolute text-body-tertiary"
                          style="top:50%;right:.75rem;transform:translateY(-50%);font-size:1.1rem;pointer-events:none;">
                      search
                    </span>
                    <input type="search"
                          id="recordSearchInput"
                          class="form-control ps-5"
                          placeholder="Search by name, email, or user code..."
                          autocomplete="off" />
                  </div>
                  <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fs-sm" id="recordCountBadge">
                    <?php echo count($fetchedRecords); ?> Records
                  </span>
                </div>

                <div class="table-responsive">
                  <table class="table table-bordered table-striped table-sm align-middle text-center" id="recordTable">
                    <thead class="table-primary">
                      <tr>
                        <?php if ($activeRecordRole === 'student'): ?>
                          <th>Student</th>
                          <th>User Code</th>
                          <th>Batch</th>
                          <th>Status</th>
                          <th>Email Updates</th>
                          <th>Actions</th>
                        <?php elseif ($activeRecordRole === 'faculty'): ?>
                          <th>Faculty</th>
                          <th>User Code</th>
                          <th>Status</th>
                          <th>Email Updates</th>
                          <th>Actions</th>
                        <?php else: ?>
                          <th>Admin</th>
                          <th>User Code</th>
                          <th>Current Session</th>
                          <th>Actions</th>
                        <?php endif; ?>
                      </tr>
                    </thead>
                    <tbody id="recordTableBody">
                      <?php foreach ($fetchedRecords as $record): ?>
                        <?php
                          $displayName = $activeRecordRole === 'student'
                            ? ($record['student_name'] ?? '')
                            : ($activeRecordRole === 'faculty' ? ($record['faculty_name'] ?? '') : ($record['admin_name'] ?? ''));
                          $displayEmail = $activeRecordRole === 'student'
                            ? ($record['student_email'] ?? '')
                            : ($activeRecordRole === 'faculty' ? ($record['faculty_email'] ?? '') : ($record['admin_email'] ?? ''));
                          $displayUsercode = $activeRecordRole === 'student'
                            ? ($record['student_usercode'] ?? '')
                            : ($activeRecordRole === 'faculty' ? ($record['faculty_usercode'] ?? '') : ($record['admin_usercode'] ?? ''));
                          $searchName = strtolower(ciEscape($displayName));
                          $searchEmail = strtolower(ciEscape($displayEmail));
                          $searchUsercode = strtolower(ciEscape($displayUsercode));
                        ?>
                        <tr data-search-name="<?php echo $searchName; ?>"
                            data-search-email="<?php echo $searchEmail; ?>"
                            data-search-usercode="<?php echo $searchUsercode; ?>">
                          <?php if ($activeRecordRole === 'student'): ?>
                            <td class="text-start">
                              <div class="fw-semibold"><?php echo ciEscape($displayName ?: 'Unnamed Student'); ?></div>
                              <div class="text-body-secondary fs-sm"><?php echo ciEscape($displayEmail); ?></div>
                            </td>
                            <td><code class="fs-sm"><?php echo ciEscape($displayUsercode); ?></code></td>
                            <td><?php echo ciEscape($record['student_batch_details'] ?? ''); ?></td>
                            <td>
                              <?php if ((int) ($record['student_account_activation_status'] ?? 0) === 1): ?>
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">Active</span>
                              <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning rounded-pill px-3">Inactive</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <?php if ((int) ($record['student_has_opted_email_communication'] ?? 0) === 1): ?>
                                <span class="badge bg-primary-subtle text-primary rounded-pill px-3">Enabled</span>
                              <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3">Disabled</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                                <button type="button"
                                        class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1"
                                        data-record="<?php echo ciJsonForDataset($record); ?>"
                                        data-role="student"
                                        onclick="openViewRecordDialog(this)">
                                  View
                                </button>
                                <button type="button"
                                        class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1"
                                        data-record="<?php echo ciJsonForDataset($record); ?>"
                                        onclick="openEditStudentDialog(this)">
                                  Edit
                                </button>
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1"
                                        data-id="<?php echo (int) ($record['student_id'] ?? 0); ?>"
                                        data-name="<?php echo ciEscape($displayName ?: $displayUsercode); ?>"
                                        onclick="openDeleteStudentDialog(this)">
                                  Delete
                                </button>
                              </div>
                            </td>
                          <?php elseif ($activeRecordRole === 'faculty'): ?>
                            <td class="text-start">
                              <div class="fw-semibold"><?php echo ciEscape($displayName ?: 'Unnamed Faculty'); ?></div>
                              <div class="text-body-secondary fs-sm"><?php echo ciEscape($displayEmail); ?></div>
                            </td>
                            <td><code class="fs-sm"><?php echo ciEscape($displayUsercode); ?></code></td>
                            <td>
                              <?php if ((int) ($record['faculty_account_activation_status'] ?? 0) === 1): ?>
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">Active</span>
                              <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning rounded-pill px-3">Pending</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <?php if ((int) ($record['faculty_has_opted_email_communication'] ?? 0) === 1): ?>
                                <span class="badge bg-primary-subtle text-primary rounded-pill px-3">Enabled</span>
                              <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3">Disabled</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                                <button type="button"
                                        class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1"
                                        data-record="<?php echo ciJsonForDataset($record); ?>"
                                        data-role="faculty"
                                        onclick="openViewRecordDialog(this)">
                                  View
                                </button>
                                <button type="button"
                                        class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1"
                                        data-record="<?php echo ciJsonForDataset($record); ?>"
                                        onclick="openEditFacultyDialog(this)">
                                  Edit
                                </button>
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1"
                                        data-id="<?php echo (int) ($record['faculty_id'] ?? 0); ?>"
                                        data-name="<?php echo ciEscape($displayName ?: $displayUsercode); ?>"
                                        onclick="openDeleteFacultyDialog(this)">
                                  Delete
                                </button>
                              </div>
                            </td>
                          <?php else: ?>
                            <td class="text-start">
                              <div class="fw-semibold"><?php echo ciEscape($displayName ?: 'Unnamed Admin'); ?></div>
                              <div class="text-body-secondary fs-sm"><?php echo ciEscape($displayEmail); ?></div>
                            </td>
                            <td><code class="fs-sm"><?php echo ciEscape($displayUsercode); ?></code></td>
                            <td>
                              <?php echo !empty($record['admin_current_active_session'])
                                ? '<span class="badge bg-success-subtle text-success rounded-pill px-3">Signed in</span>'
                                : '<span class="badge bg-secondary-subtle text-secondary rounded-pill px-3">No session</span>'; ?>
                            </td>
                            <td>
                              <button type="button"
                                      class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1"
                                      data-record="<?php echo ciJsonForDataset($record); ?>"
                                      data-role="admin"
                                      onclick="openViewRecordDialog(this)">
                                View
                              </button>
                            </td>
                          <?php endif; ?>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>

                  <div id="noRecordResults" class="text-center py-6 d-none">
                    <span class="material-symbols-outlined text-body-tertiary mb-2" style="font-size:2.5rem;">search_off</span>
                    <p class="fw-semibold mb-1">No Results Found</p>
                    <p class="text-body-secondary fs-sm mb-0">No record matches your search query.</p>
                  </div>
                </div>
              <?php else: ?>
                <div class="text-center py-7">
                  <span class="material-symbols-outlined text-body-tertiary mb-3" style="font-size:3rem;">inbox</span>
                  <p class="fw-semibold mb-1">No Records Found</p>
                  <p class="text-body-secondary fs-sm mb-0">There are no records available for this list.</p>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <dialog class="ci-dialog" id="viewRecordDialog" aria-labelledby="viewRecordDialogTitle">
      <div class="ci-dialog__header">
        <div>
          <h5 class="fw-semibold mb-0" id="viewRecordDialogTitle">Record Details</h5>
          <small class="text-body-secondary" id="viewRecordDialogSubtitle"></small>
        </div>
        <button type="button" class="ci-dialog__close" onclick="closeDialog('viewRecordDialog')" aria-label="Close"></button>
      </div>
      <div class="ci-dialog__body">
        <div class="ci-record-grid" id="viewRecordGrid"></div>
      </div>
      <div class="ci-dialog__footer">
        <button type="button"
                class="btn btn-outline-secondary rounded-pill px-4"
                onclick="closeDialog('viewRecordDialog')">Close</button>
      </div>
    </dialog>

    <dialog class="ci-dialog" id="editStudentDialog" aria-labelledby="editStudentDialogTitle">
      <form method="POST" action="./?view=viewStudents">
        <div class="ci-dialog__header">
          <div>
            <h5 class="fw-semibold mb-0" id="editStudentDialogTitle">Edit Student Record</h5>
            <small class="text-body-secondary">Updates apply to details, configuration, and timestamp email references.</small>
          </div>
          <button type="button" class="ci-dialog__close" onclick="closeDialog('editStudentDialog')" aria-label="Close"></button>
        </div>
        <div class="ci-dialog__body">
          <input type="hidden" name="edit_student_id" id="edit_student_id">

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_student_name">Full Name</label>
              <input class="form-control" id="edit_student_name" name="edit_student_name" type="text" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_student_email">Email</label>
              <input class="form-control" id="edit_student_email" name="edit_student_email" type="email" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_student_username">Username</label>
              <input class="form-control" id="edit_student_username" name="edit_student_username" type="text">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_student_batch_details">Batch</label>
              <select class="form-select" id="edit_student_batch_details" name="edit_student_batch_details">
                <option value="">No Batch Selected</option>
                <?php foreach ($activeBatchList as $batch): ?>
                  <option value="<?php echo ciEscape($batch); ?>"><?php echo ciEscape($batch); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_student_father_name">Father Name</label>
              <input class="form-control" id="edit_student_father_name" name="edit_student_father_name" type="text">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_student_guardian_name">Guardian Name</label>
              <input class="form-control" id="edit_student_guardian_name" name="edit_student_guardian_name" type="text">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_student_school_name">School Name</label>
              <input class="form-control" id="edit_student_school_name" name="edit_student_school_name" type="text">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_student_bio">Bio</label>
              <textarea class="form-control" id="edit_student_bio" name="edit_student_bio" rows="2" maxlength="255"></textarea>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_student_malpractice_counter">Malpractice Counter</label>
              <input class="form-control" id="edit_student_malpractice_counter" name="edit_student_malpractice_counter" type="number" min="0" max="9" step="1">
            </div>
          </div>

          <div class="row g-3 mt-2">
            <?php
              $studentToggles = [
                'edit_student_account_activation_status' => 'Account Activated',
                'edit_student_has_updated_username' => 'Username Updated',
                'edit_student_has_updated_account_profile' => 'Profile Updated',
                'edit_student_has_opted_email_communication' => 'Email Communication',
              ];
            ?>
            <?php foreach ($studentToggles as $toggleId => $toggleLabel): ?>
              <div class="col-12 col-md-6">
                <div class="border rounded-3 px-3 py-3 h-100">
                  <input class="form-check-input me-2"
                        type="checkbox"
                        id="<?php echo $toggleId; ?>"
                        name="<?php echo $toggleId; ?>">
                  <label class="form-check-label fs-sm fw-semibold" for="<?php echo $toggleId; ?>"><?php echo $toggleLabel; ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="ci-dialog__footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="closeDialog('editStudentDialog')">Cancel</button>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">
          <button type="submit" name="editStudentRecord" class="btn btn-primary rounded-pill px-4">Save Changes</button>
        </div>
      </form>
    </dialog>

    <dialog class="ci-dialog" id="editFacultyDialog" aria-labelledby="editFacultyDialogTitle">
      <form method="POST" action="./?view=viewFaculties">
        <div class="ci-dialog__header">
          <div>
            <h5 class="fw-semibold mb-0" id="editFacultyDialogTitle">Edit Faculty Record</h5>
            <small class="text-body-secondary">Updates apply to details, configuration, and timestamp email references.</small>
          </div>
          <button type="button" class="ci-dialog__close" onclick="closeDialog('editFacultyDialog')" aria-label="Close"></button>
        </div>
        <div class="ci-dialog__body">
          <input type="hidden" name="edit_faculty_id" id="edit_faculty_id">

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_faculty_name">Full Name</label>
              <input class="form-control" id="edit_faculty_name" name="edit_faculty_name" type="text" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_faculty_email">Email</label>
              <input class="form-control" id="edit_faculty_email" name="edit_faculty_email" type="email" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_faculty_username">Username</label>
              <input class="form-control" id="edit_faculty_username" name="edit_faculty_username" type="text">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold fs-sm mb-1" for="edit_faculty_bio">Bio</label>
              <textarea class="form-control" id="edit_faculty_bio" name="edit_faculty_bio" rows="2" maxlength="255"></textarea>
            </div>
          </div>

          <div class="row g-3 mt-2">
            <?php
              $facultyToggles = [
                'edit_faculty_account_activation_status' => 'Account Activated',
                'edit_faculty_has_used_account_activation' => 'Used Activation',
                'edit_faculty_has_updated_username' => 'Username Updated',
                'edit_faculty_has_updated_account_profile' => 'Profile Updated',
                'edit_faculty_has_opted_email_communication' => 'Email Communication',
              ];
            ?>
            <?php foreach ($facultyToggles as $toggleId => $toggleLabel): ?>
              <div class="col-12 col-md-6">
                <div class="border rounded-3 px-3 py-3 h-100">
                  <input class="form-check-input me-2"
                        type="checkbox"
                        id="<?php echo $toggleId; ?>"
                        name="<?php echo $toggleId; ?>">
                  <label class="form-check-label fs-sm fw-semibold" for="<?php echo $toggleId; ?>"><?php echo $toggleLabel; ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="ci-dialog__footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="closeDialog('editFacultyDialog')">Cancel</button>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">
          <button type="submit" name="editFacultyRecord" class="btn btn-primary rounded-pill px-4">Save Changes</button>
        </div>
      </form>
    </dialog>

    <dialog class="ci-dialog ci-dialog--sm" id="deleteStudentDialog" aria-labelledby="deleteStudentDialogTitle">
      <form method="POST" action="./?view=viewStudents">
        <div class="ci-dialog__header">
          <h5 class="fw-semibold mb-0" id="deleteStudentDialogTitle">Delete Student Record</h5>
          <button type="button" class="ci-dialog__close" onclick="closeDialog('deleteStudentDialog')" aria-label="Close"></button>
        </div>
        <div class="ci-dialog__body">
          <p class="mb-1">Are you sure you want to delete the record for</p>
          <p class="fw-semibold mb-3" id="delete_student_name_display">-</p>
          <p class="text-danger fs-sm mb-0 d-flex align-items-start gap-1">
            <span class="material-symbols-outlined mt-1" style="font-size:1rem;">warning</span>
            This permanently removes the student account, device sessions, timestamps, attendance rows, queued email rows, and related error logs.
          </p>
          <input type="hidden" name="delete_student_id" id="delete_student_id">
        </div>
        <div class="ci-dialog__footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="closeDialog('deleteStudentDialog')">Cancel</button>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">
          <button type="submit" name="deleteStudentRecord" class="btn btn-danger rounded-pill px-4">Delete</button>
        </div>
      </form>
    </dialog>

    <dialog class="ci-dialog ci-dialog--sm" id="deleteFacultyDialog" aria-labelledby="deleteFacultyDialogTitle">
      <form method="POST" action="./?view=viewFaculties">
        <div class="ci-dialog__header">
          <h5 class="fw-semibold mb-0" id="deleteFacultyDialogTitle">Delete Faculty Record</h5>
          <button type="button" class="ci-dialog__close" onclick="closeDialog('deleteFacultyDialog')" aria-label="Close"></button>
        </div>
        <div class="ci-dialog__body">
          <p class="mb-1">Are you sure you want to delete the record for</p>
          <p class="fw-semibold mb-3" id="delete_faculty_name_display">-</p>
          <p class="text-danger fs-sm mb-0 d-flex align-items-start gap-1">
            <span class="material-symbols-outlined mt-1" style="font-size:1rem;">warning</span>
            This permanently removes the faculty account, device sessions, timestamps, queued email rows, and related error logs.
          </p>
          <input type="hidden" name="delete_faculty_id" id="delete_faculty_id">
        </div>
        <div class="ci-dialog__footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="closeDialog('deleteFacultyDialog')">Cancel</button>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">
          <button type="submit" name="deleteFacultyRecord" class="btn btn-danger rounded-pill px-4">Delete</button>
        </div>
      </form>
    </dialog>

    <script type="text/javascript">
      const recordLabels = {
        student_id: 'Student ID',
        student_usercode: 'Student User Code',
        student_email: 'Student Email',
        student_username: 'Student Username',
        student_name: 'Student Name',
        student_father_name: 'Father Name',
        student_guardian_name: 'Guardian Name',
        student_batch_details: 'Batch Details',
        student_school_name: 'School Name',
        student_bio: 'Bio',
        student_account_activation_status: 'Account Activated',
        student_has_updated_username: 'Username Updated',
        student_has_updated_account_profile: 'Profile Updated',
        student_has_opted_email_communication: 'Email Communication',
        student_malpractice_counter: 'Malpractice Counter',
        student_account_creation_timestamp: 'Account Creation Timestamp',
        student_last_login_timestamp: 'Last Login Timestamp',
        student_last_OTP_request_timestamp: 'Last OTP Request Timestamp',
        student_last_email_request_timestamp: 'Last Email Request Timestamp',
        student_batch_updating_timestamp: 'Batch Updating Timestamp',
        student_profile_updating_timestamp: 'Profile Updating Timestamp',
        student_account_deactivation_timestamp: 'Account Deactivation Timestamp',
        faculty_id: 'Faculty ID',
        faculty_usercode: 'Faculty User Code',
        faculty_email: 'Faculty Email',
        faculty_username: 'Faculty Username',
        faculty_name: 'Faculty Name',
        faculty_bio: 'Bio',
        faculty_account_activation_status: 'Account Activated',
        faculty_has_used_account_activation: 'Used Activation',
        faculty_has_updated_username: 'Username Updated',
        faculty_has_updated_account_profile: 'Profile Updated',
        faculty_has_opted_email_communication: 'Email Communication',
        faculty_account_activation_timestamp: 'Account Activation Timestamp',
        faculty_account_creation_timestamp: 'Account Creation Timestamp',
        faculty_last_login_timestamp: 'Last Login Timestamp',
        faculty_last_OTP_request_timestamp: 'Last OTP Request Timestamp',
        faculty_last_email_request_timestamp: 'Last Email Request Timestamp',
        admin_id: 'Admin ID',
        admin_usercode: 'Admin User Code',
        admin_email: 'Admin Email',
        admin_username: 'Admin Username',
        admin_name: 'Admin Name',
        admin_bio: 'Bio',
        admin_current_active_session: 'Current Active Session'
      };

      const booleanRecordKeys = new Set([
        'student_account_activation_status',
        'student_has_updated_username',
        'student_has_updated_account_profile',
        'student_has_opted_email_communication',
        'faculty_account_activation_status',
        'faculty_has_used_account_activation',
        'faculty_has_updated_username',
        'faculty_has_updated_account_profile',
        'faculty_has_opted_email_communication'
      ]);

      function openDialog(id) {
        document.getElementById(id).showModal();
      }

      function closeDialog(id) {
        document.getElementById(id).close();
      }

      function parseRecord(btn) {
        return JSON.parse(btn.dataset.record || '{}');
      }

      function setValue(id, value) {
        const element = document.getElementById(id);
        if (element) element.value = value ?? '';
      }

      function setChecked(id, value) {
        const element = document.getElementById(id);
        if (element) element.checked = String(value ?? '0') === '1';
      }

      function ensureSelectOption(selectId, value) {
        const select = document.getElementById(selectId);
        if (!select || !value) return;

        const hasOption = Array.from(select.options).some(function (option) {
          return option.value === String(value);
        });

        if (!hasOption) {
          const option = document.createElement('option');
          option.value = String(value);
          option.textContent = String(value) + ' (inactive)';
          select.appendChild(option);
        }
      }

      function formatRecordValue(key, value) {
        if (value === null || value === undefined || value === '') return '-';
        if (booleanRecordKeys.has(key) && String(value) === '1') return 'Yes';
        if (booleanRecordKeys.has(key) && String(value) === '0') return 'No';
        return String(value);
      }

      document.querySelectorAll('dialog.ci-dialog').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) {
          const rect = dlg.getBoundingClientRect();
          if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
            dlg.close();
          }
        });
      });

      function openViewRecordDialog(btn) {
        const record = parseRecord(btn);
        const role = btn.dataset.role || 'user';
        const grid = document.getElementById('viewRecordGrid');
        const title = document.getElementById('viewRecordDialogTitle');
        const subtitle = document.getElementById('viewRecordDialogSubtitle');
        const name = record[role + '_name'] || record[role + '_username'] || record[role + '_usercode'] || 'Record Details';
        const email = record[role + '_email'] || '';

        title.textContent = name;
        subtitle.textContent = email;
        grid.innerHTML = '';

        Object.keys(record).forEach(function (key) {
          const field = document.createElement('div');
          field.className = 'ci-record-field';

          const label = document.createElement('div');
          label.className = 'ci-record-field__label';
          label.textContent = recordLabels[key] || key.replaceAll('_', ' ');

          const value = document.createElement('div');
          value.className = 'ci-record-field__value';
          value.textContent = formatRecordValue(key, record[key]);

          field.append(label, value);
          grid.appendChild(field);
        });

        openDialog('viewRecordDialog');
      }

      function openEditStudentDialog(btn) {
        const record = parseRecord(btn);
        setValue('edit_student_id', record.student_id);
        setValue('edit_student_name', record.student_name);
        setValue('edit_student_email', record.student_email);
        setValue('edit_student_username', record.student_username);
        setValue('edit_student_father_name', record.student_father_name);
        setValue('edit_student_guardian_name', record.student_guardian_name);
        ensureSelectOption('edit_student_batch_details', record.student_batch_details);
        setValue('edit_student_batch_details', record.student_batch_details);
        setValue('edit_student_school_name', record.student_school_name);
        setValue('edit_student_bio', record.student_bio);
        setValue('edit_student_malpractice_counter', record.student_malpractice_counter || 0);
        setChecked('edit_student_account_activation_status', record.student_account_activation_status);
        setChecked('edit_student_has_updated_username', record.student_has_updated_username);
        setChecked('edit_student_has_updated_account_profile', record.student_has_updated_account_profile);
        setChecked('edit_student_has_opted_email_communication', record.student_has_opted_email_communication);
        openDialog('editStudentDialog');
      }

      function openEditFacultyDialog(btn) {
        const record = parseRecord(btn);
        setValue('edit_faculty_id', record.faculty_id);
        setValue('edit_faculty_name', record.faculty_name);
        setValue('edit_faculty_email', record.faculty_email);
        setValue('edit_faculty_username', record.faculty_username);
        setValue('edit_faculty_bio', record.faculty_bio);
        setChecked('edit_faculty_account_activation_status', record.faculty_account_activation_status);
        setChecked('edit_faculty_has_used_account_activation', record.faculty_has_used_account_activation);
        setChecked('edit_faculty_has_updated_username', record.faculty_has_updated_username);
        setChecked('edit_faculty_has_updated_account_profile', record.faculty_has_updated_account_profile);
        setChecked('edit_faculty_has_opted_email_communication', record.faculty_has_opted_email_communication);
        openDialog('editFacultyDialog');
      }

      function openDeleteStudentDialog(btn) {
        setValue('delete_student_id', btn.dataset.id);
        document.getElementById('delete_student_name_display').textContent = (btn.dataset.name || 'this student') + '?';
        openDialog('deleteStudentDialog');
      }

      function openDeleteFacultyDialog(btn) {
        setValue('delete_faculty_id', btn.dataset.id);
        document.getElementById('delete_faculty_name_display').textContent = (btn.dataset.name || 'this faculty member') + '?';
        openDialog('deleteFacultyDialog');
      }

      (function () {
        const input = document.getElementById('recordSearchInput');
        const noResults = document.getElementById('noRecordResults');
        const badge = document.getElementById('recordCountBadge');
        const total = <?php echo count($fetchedRecords); ?>;

        if (!input) return;

        input.addEventListener('input', function () {
          const query = this.value.trim().toLowerCase();
          const rows = document.querySelectorAll('#recordTableBody tr');
          let visible = 0;

          rows.forEach(function (row) {
            const match = !query
              || row.dataset.searchName.includes(query)
              || row.dataset.searchEmail.includes(query)
              || row.dataset.searchUsercode.includes(query);

            row.classList.toggle('d-none', !match);
            if (match) visible++;
          });

          noResults.classList.toggle('d-none', visible > 0);
          badge.textContent = query
            ? visible + ' of ' + total + ' Records'
            : total + ' Records';
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

<?php elseif (checkForEquality(checkLoginStatus($db1), false, 'strict')): // User Logged Out ?>
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