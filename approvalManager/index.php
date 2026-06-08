<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Approvals
  if(checkForEquality(checkLoginStatus($db1), true, 'strict')) {
    if (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')) {
      $csrfTokenValue  = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');

      try { // By Default, Display Approval List
        $STMT_retrieveApprovalsList = 'SELECT * FROM approval_users ORDER BY approval_rate_limiting_timestamp DESC';
        $retrieveApprovalsList      = $db1->prepare($STMT_retrieveApprovalsList);
        $retrieveApprovalsList->execute();
        $approvalRecords = $retrieveApprovalsList->fetchAll(PDO::FETCH_ASSOC);
      }
      catch (PDOException $ex) {
        $approvalRecords = [];
      }

      // Approval Button Logic
      if (isset($_POST['approveStudentRecord'])) {
        $approvedEmail = escapeOutput($_POST['approved_email'] ?? null);
        $csrfToken     = escapeOutput($_POST['csrf_token']     ?? null);

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          $STMT_fetchUserRecord = $db1->prepare('SELECT * FROM approval_users WHERE approval_email = :email LIMIT 1');
          $STMT_fetchUserRecord->execute([':email' => $approvedEmail]);
          $fetchedUserRecord = $STMT_fetchUserRecord->fetch(PDO::FETCH_ASSOC);

          if (!$fetchedUserRecord) {
            setToast('User already approved or not found.', 'warning', 5000);
          }
          else {
            $approvedName         = $fetchedUserRecord['approval_name'];
            $approvedPassword     = $fetchedUserRecord['approval_password'];
            $approvedFatherName   = $fetchedUserRecord['approval_father_name'];
            $approvedBatchDetails = $fetchedUserRecord['approval_batch_details'];

            $currentAttempt = 0;
            $maxAttempts    = 3;

            while ($currentAttempt < $maxAttempts) {
              try {
                $db1->beginTransaction();

                $STMT_insertStudentDetails = 'INSERT INTO student_details (student_email, student_name, student_password, student_father_name, student_batch_details)
                                              VALUES (:student_email, :student_name, :student_password, :student_father_name, :student_batch_details)';
                $insertStudentDetails = $db1->prepare($STMT_insertStudentDetails);
                $insertStudentDetails->bindValue(':student_email',        $approvedEmail,        PDO::PARAM_STR);
                $insertStudentDetails->bindValue(':student_name',         $approvedName,         PDO::PARAM_STR);
                $insertStudentDetails->bindValue(':student_password',     $approvedPassword,     PDO::PARAM_STR);
                $insertStudentDetails->bindValue(':student_father_name',  $approvedFatherName,   PDO::PARAM_STR);
                $insertStudentDetails->bindValue(':student_batch_details',$approvedBatchDetails, PDO::PARAM_STR);
                $insertStudentDetails->execute();

                $studentID         = (int) $db1->lastInsertId();
                $generatedUserCode = generateUserCode('student', $studentID);

                $STMT_updateStudentDetails = 'UPDATE student_details
                                              SET student_usercode = :student_usercode
                                              WHERE student_id = :student_id
                                              LIMIT 1';
                $updateStudentDetails = $db1->prepare($STMT_updateStudentDetails);
                $updateStudentDetails->bindValue(':student_usercode', $generatedUserCode, PDO::PARAM_STR);
                $updateStudentDetails->bindValue(':student_id',       $studentID,         PDO::PARAM_INT);
                $updateStudentDetails->execute();

                $STMT_insertStudentConfigurations = 'INSERT INTO student_configurations (student_id, student_usercode, student_email, student_account_activation_status)
                                                    VALUES (:student_id, :student_usercode, :student_email, 1)';
                $insertStudentConfigurations = $db1->prepare($STMT_insertStudentConfigurations);
                $insertStudentConfigurations->bindValue(':student_id',       $studentID,         PDO::PARAM_INT);
                $insertStudentConfigurations->bindValue(':student_usercode', $generatedUserCode, PDO::PARAM_STR);
                $insertStudentConfigurations->bindValue(':student_email',    $approvedEmail,     PDO::PARAM_STR);
                $insertStudentConfigurations->execute();

                $generatedCurrentTimestamp = getCurrentTimestamp();
                $STMT_insertStudentTimestamps = 'INSERT INTO student_timestamps (student_id, student_usercode, student_email, student_account_creation_timestamp, student_batch_updating_timestamp)
                                                VALUES (:student_id, :student_usercode, :student_email, :student_account_creation_timestamp, :student_batch_updating_timestamp)';
                $insertStudentTimestamps = $db1->prepare($STMT_insertStudentTimestamps);
                $insertStudentTimestamps->bindValue(':student_id',                         $studentID,                PDO::PARAM_INT);
                $insertStudentTimestamps->bindValue(':student_usercode',                   $generatedUserCode,        PDO::PARAM_STR);
                $insertStudentTimestamps->bindValue(':student_email',                      $approvedEmail,            PDO::PARAM_STR);
                $insertStudentTimestamps->bindValue(':student_account_creation_timestamp', $generatedCurrentTimestamp,PDO::PARAM_STR);
                $insertStudentTimestamps->bindValue(':student_batch_updating_timestamp',   $generatedCurrentTimestamp,PDO::PARAM_STR);
                $insertStudentTimestamps->execute();

                $STMT_deleteApprovalRecord = 'DELETE FROM approval_users WHERE approval_email = :approval_email LIMIT 1';
                $deleteApprovalRecord      = $db1->prepare($STMT_deleteApprovalRecord);
                $deleteApprovalRecord->bindValue(':approval_email', $approvedEmail, PDO::PARAM_STR);
                $deleteApprovalRecord->execute();

                $db1->commit();
                setToast('Student approved successfully.', 'success', 5000);
                break;
              }
              catch (PDOException $ex) {
                if ($db1->inTransaction()) $db1->rollBack();

                if (!isRetryablePdoException($ex)) {
                  setToast('An error occurred while approving the student. Please contact the administrator.', 'danger', 7000);
                  logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error approving student: ' . $ex->getMessage());
                  break;
                }

                $currentAttempt++;
                sleep(3);
              }
            }
            if ($currentAttempt >= $maxAttempts) {
              setToast('Failed to approve student after multiple attempts. Please try again later.', 'danger', 7000);
            }
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      // Deletion Button Logic
      if (isset($_POST['deleteApprovalRecord'])) {
        $deleteEmail = escapeOutput($_POST['delete_email'] ?? null);
        $csrfToken   = escapeOutput($_POST['csrf_token']   ?? null);

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          if (!empty($deleteEmail)) {
            $currentAttempt = 0;
            $maxAttempts    = 3;

            while ($currentAttempt < $maxAttempts) {
              try {
                $STMT_deleteApproval = 'DELETE FROM approval_users WHERE approval_email = :email LIMIT 1';
                $deleteApproval      = $db1->prepare($STMT_deleteApproval);
                $deleteApproval->bindValue(':email', $deleteEmail, PDO::PARAM_STR);
                $deleteApproval->execute();

                if ($deleteApproval->rowCount() > 0) {
                  setToast('Approval request deleted successfully.', 'success', 5000);
                } else {
                  setToast('Record not found or already deleted.', 'warning', 5000);
                }

                break;
              }
              catch (PDOException $ex) {
                if (!isRetryablePdoException($ex)) {
                  setToast('An error occurred while deleting the record. Please contact the administrator.', 'danger', 7000);
                  logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error deleting approval record: ' . $ex->getMessage());
                  break;
                }

                $currentAttempt++;
                sleep(3);
              }
            }
            if ($currentAttempt >= $maxAttempts) {
              setToast('Failed to delete record after multiple attempts. Please try again later.', 'danger', 7000);
            }
          }
          else setToast('Invalid record. Please try again.', 'danger', 5000);
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      try { // After Button Actions, 
        $STMT_retrieveApprovalsList = 'SELECT * FROM approval_users ORDER BY approval_rate_limiting_timestamp DESC';
        $retrieveApprovalsList      = $db1->prepare($STMT_retrieveApprovalsList);
        $retrieveApprovalsList->execute();
        $approvalRecords = $retrieveApprovalsList->fetchAll(PDO::FETCH_ASSOC);
      }
      catch (PDOException) {
        $approvalRecords = [];
      }
    }
  }
?>

<?php // Headers
  $page_title = "Approvals | careerinstitute.co.in";

  require_once '../components/header.php';

  $breadcrumb_url_1    = '../dashboard/';
  $breadcrumb_title_1  = 'Dashboard';
  $breadcrumb_url_active   = './';
  $breadcrumb_title_active = 'Approvals';

  require_once '../components/breadcrumb.php';
?>

<?php if (checkForEquality(checkLoginStatus($db1), true, 'strict')): // User Logged In?>
  <?php if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode'] ?? null), 'admin', 'strict')): // For Admin ?>
    <link rel="stylesheet" type="text/css" href="./approvals.css" />

    <section class="section-border border-primary">
      <div class="container-xxl d-flex flex-column">
        <div class="row align-items-start justify-content-center min-vh-100 gx-0">
          <div class="col-12 px-8 py-8">

            <div class="mb-6">
              <h2 class="fw-bold mb-1">Pending Approvals</h2>
              <p class="text-body-secondary mb-0">Review and approve student registration requests.</p>
            </div>

            <?php if (!empty($approvalRecords)): ?>

              <!-- Search Box -->
              <div class="mb-4 d-flex align-items-center gap-3 flex-wrap">
                <div class="position-relative grow" style="max-width:420px;">
                  <span class="material-symbols-outlined position-absolute text-body-tertiary"
                        style="top:50%;right:.75rem;transform:translateY(-50%);font-size:1.1rem;pointer-events:none;">
                    search
                  </span>
                  <input type="search"
                        id="approvalSearchInput"
                        class="form-control ps-5"
                        placeholder="Search by name or email…"
                        autocomplete="off" />
                </div>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fs-sm" id="approvalCountBadge">
                  <?php echo count($approvalRecords); ?> Pending
                </span>
              </div>

              <?php
                $excludedColumns = [
                  'approval_password',
                  'approval_IP_address',
                  'approval_rate_limiting_timestamp'
                ];

                $columns = [];

                if (!empty($approvalRecords)) {
                  $columns = array_filter(
                    array_keys($approvalRecords[0]),
                    fn($col) => !in_array($col, $excludedColumns, true)
                  );
                }
              ?>

              <!-- Table -->
              <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm align-middle text-center" id="approvalTable">
                  
                  <!-- THEAD -->
                  <thead class="table-primary">
                    <tr>
                      <?php foreach ($columns as $column): ?>
                        <th>
                          <?php 
                            echo htmlspecialchars(
                              ucwords(str_replace('_', ' ', preg_replace('/^approval_/', '', $column))),
                              ENT_QUOTES,
                              'UTF-8'
                            ); 
                          ?>
                        </th>
                      <?php endforeach; ?>
                      <th>Actions</th>
                    </tr>
                  </thead>

                  <!-- TBODY -->
                  <tbody id="approvalTableBody">
                    <?php foreach ($approvalRecords as $record):
                      $rowEmail = htmlspecialchars($record['approval_email'] ?? '', ENT_QUOTES, 'UTF-8');
                      $rowName  = htmlspecialchars($record['approval_name']  ?? '', ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr data-search-email="<?php echo strtolower($rowEmail); ?>"
                        data-search-name="<?php echo strtolower($rowName); ?>">
                      
                      <?php foreach ($columns as $column): ?>
                        <td>
                          <?php 
                            if(checkForEquality($column, 'approval_batch_details', 'strict')) {
                              echo prettyPrintClassCode($record[$column] ?? '');
                            }
                            else echo htmlspecialchars((string) ($record[$column] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                      <?php endforeach; ?>

                      <td>
                        <div class="d-flex align-items-center justify-content-center gap-2">

                          <!-- Approve -->
                          <form method="POST" class="d-inline">
                            <input type="hidden" name="approved_email" value="<?php echo $rowEmail; ?>">
                            <input type="hidden" name="csrf_token"     value="<?php echo $csrfTokenValue; ?>">
                            <button type="submit"
                                    name="approveStudentRecord"
                                    class="btn btn-success btn-sm rounded-pill px-3 py-1">
                              Approve
                            </button>
                          </form>

                          <!-- Delete -->
                          <button type="button"
                                  class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1"
                                  onclick="openDeleteApprovalDialog(this)"
                                  data-email="<?php echo $rowEmail; ?>"
                                  data-name="<?php echo $rowName; ?>">
                            Delete
                          </button>

                        </div>
                      </td>

                    </tr>
                    <?php endforeach; ?>
                  </tbody>

                </table>

                <!-- Empty search state -->
                <div id="noApprovalResults" class="text-center py-6 d-none">
                  <span class="material-symbols-outlined text-body-tertiary mb-2" style="font-size:2.5rem;">search_off</span>
                  <p class="fw-semibold mb-1">No Results Found</p>
                  <p class="text-body-secondary fs-sm mb-0">No pending request matches your search query.</p>
                </div>

              </div>

            <?php else: ?>
              <div class="text-center py-7">
                <span class="material-symbols-outlined text-body-tertiary mb-3" style="font-size:3rem;">inbox</span>
                <p class="fw-semibold mb-1">No Pending Approvals</p>
                <p class="text-body-secondary fs-sm mb-0">All student requests have been processed.</p>
              </div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </section>

    <dialog class="ci-dialog" id="deleteApprovalDialog" aria-labelledby="deleteApprovalDialogTitle">
      <form method="POST" action="./">
        <div class="ci-dialog__header">
          <h5 class="fw-semibold mb-0" id="deleteApprovalDialogTitle">Delete Approval Request</h5>
          <button type="button" class="ci-dialog__close" onclick="closeDialog('deleteApprovalDialog')" aria-label="Close"></button>
        </div>
        <div class="ci-dialog__body">
          <p class="mb-1">Are you sure you want to delete the request for</p>
          <p class="fw-semibold mb-3" id="deleteApprovalNameDisplay">—</p>
          <p class="text-danger fs-sm mb-0 d-flex align-items-start gap-1">
            <span class="material-symbols-outlined mt-1" style="font-size:1rem;">warning</span>
            This will permanently remove the request. The student will need to re-apply.
          </p>
          <input type="hidden" name="delete_email" id="deleteApprovalEmail">
        </div>
        <div class="ci-dialog__footer">
          <button type="button"
                  class="btn btn-outline-secondary rounded-pill px-4"
                  onclick="closeDialog('deleteApprovalDialog')">Cancel</button>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">
          <button type="submit" name="deleteApprovalRecord" class="btn btn-danger rounded-pill px-4">Delete</button>
        </div>
      </form>
    </dialog>

    <script type="text/javascript">
      function openDialog(id)  { document.getElementById(id).showModal(); }
      function closeDialog(id) { document.getElementById(id).close(); }

      document.querySelectorAll('dialog.ci-dialog').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) {
          const r = dlg.getBoundingClientRect();
          if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) dlg.close();
        });
      });

      function openDeleteApprovalDialog(btn) {
        const { email, name } = btn.dataset;
        document.getElementById('deleteApprovalEmail').value          = email;
        document.getElementById('deleteApprovalNameDisplay').textContent = name + '?';
        openDialog('deleteApprovalDialog');
      }

      (function () {
        const input      = document.getElementById('approvalSearchInput');
        const noResults  = document.getElementById('noApprovalResults');
        const badge      = document.getElementById('approvalCountBadge');
        const total      = <?php echo count($approvalRecords); ?>;

        if (!input) return;

        input.addEventListener('input', function () {
          const query = this.value.trim().toLowerCase();
          const rows  = document.querySelectorAll('#approvalTableBody tr');
          let visible = 0;

          rows.forEach(function (row) {
            const match = !query
              || row.dataset.searchEmail.includes(query)
              || row.dataset.searchName.includes(query);
            row.classList.toggle('d-none', !match);
            if (match) visible++;
          });

          noResults.classList.toggle('d-none', visible > 0);
          badge.textContent = query
            ? visible + ' of ' + total + ' Pending'
            : total + ' Pending';
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