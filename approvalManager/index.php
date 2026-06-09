<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
    'required_roles' => ['admin'],
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Approvals
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

  try { // After Button Actions, Reload the Approval List
    $STMT_retrieveApprovalsList = 'SELECT * FROM approval_users ORDER BY approval_rate_limiting_timestamp DESC';
    $retrieveApprovalsList      = $db1->prepare($STMT_retrieveApprovalsList);
    $retrieveApprovalsList->execute();
    $approvalRecords = $retrieveApprovalsList->fetchAll(PDO::FETCH_ASSOC);
  }
  catch (PDOException) {
    $approvalRecords = [];
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

<section class="section-border border-primary">
  <div class="container-xxl d-flex flex-column">
    <div class="row gx-0 align-items-start justify-content-center min-vh-100">
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
  function openDeleteApprovalDialog(btn) {
    const { email, name } = btn.dataset;
    document.getElementById('deleteApprovalEmail').value          = email;
    document.getElementById('deleteApprovalNameDisplay').textContent = name + '?';
    openDialog('deleteApprovalDialog');
  }

  initLiveSearch({
    inputId:      'approvalSearchInput',
    tableBodyId:  'approvalTableBody',
    noResultsId:  'noApprovalResults',
    badgeId:      'approvalCountBadge',
    total:        <?php echo count($approvalRecords); ?>,
    searchAttrs:  ['searchEmail', 'searchName'],
    singularLabel: 'Pending',
    pluralLabel:   'Pending',
  });
</script>

<?php require_once '../components/footer.php'; ?>