<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts();

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Notification Manager
  if (checkForEquality(checkLoginStatus($db1), true, 'strict')) {
    if (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')) {

      function fetchNotificationRecords(PDO $db): array {
        try {
          $stmt = $db->query('SELECT id,
                                     notification_heading,
                                     notification_subheading,
                                     notification_expire_timestamp,
                                     notification_user_role,
                                     notification_batch_value
                              FROM notification_records
                              ORDER BY id DESC');
          return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        catch (PDOException) { return []; }
      }

      function formatBatchLabel(string $code): string {
        [$grade, $board, $stream] = array_pad(explode('-', $code, 3), 3, 'NULL');
        $roman = ['5'=>'V','6'=>'VI','7'=>'VII','8'=>'VIII','9'=>'IX','10'=>'X','11'=>'XI','12'=>'XII'];
        $gradeLabel = $roman[$grade] ?? $grade;
        $streamLabel = ($stream && $stream !== 'NULL') ? ' (' . ucfirst(strtolower($stream)) . ')' : '';
        return $gradeLabel . ' ' . $board . $streamLabel;
      }

      $activeBatchesRaw  = retrieveActiveBatchlist($db2);
      $activeBatchList   = json_decode($activeBatchesRaw['value'] ?? '[]', true) ?: [];

      // --- CREATE ---
      if (isset($_POST['createNotificationBtn'])) {
        $heading    = escapeOutput($_POST['notification_heading'])          ?? null;
        $subheading = escapeOutput($_POST['notification_subheading'])       ?? null;
        $expiry     = escapeOutput($_POST['notification_expire_timestamp']) ?? null;
        $role       = escapeOutput($_POST['notification_user_role'])        ?? null;
        $batches    = ($role === 'student' && isset($_POST['notification_batches']))
                        ? array_map('htmlspecialchars', $_POST['notification_batches'])
                        : [];
        $batchValue = ($role === 'student') ? json_encode($batches) : json_encode([]);
        $csrfToken  = escapeOutput($_POST['csrf_token']) ?? null;

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          $isValid = true;

          if (empty($heading) || strlen($heading) < 3) {
            setToast('Heading must be at least 3 characters.', 'danger', 6000);
            $isValid = false;
          }
          if ($isValid && empty($subheading)) {
            setToast('Subheading cannot be empty.', 'danger', 6000);
            $isValid = false;
          }
          if ($isValid && empty($expiry)) {
            setToast('Please select an expiry date and time.', 'danger', 6000);
            $isValid = false;
          }
          if ($isValid && !in_array($role, ['faculty', 'student'], true)) {
            setToast('Please select a valid target role.', 'danger', 6000);
            $isValid = false;
          }
          if ($isValid && $role === 'student' && empty($batches)) {
            setToast('Please select at least one batch for student notifications.', 'danger', 6000);
            $isValid = false;
          }

          if ($isValid) {
            $attempt    = 0;
            $maxRetries = 3;

            while ($attempt < $maxRetries) {
              try {
                $stmt = $db2->prepare('INSERT INTO notification_records
                                        (notification_heading, notification_subheading, notification_expire_timestamp, notification_user_role, notification_batch_value)
                                        VALUES (:heading, :subheading, :expiry, :role, :batches)');
                $stmt->bindValue(':heading',    $heading,    PDO::PARAM_STR);
                $stmt->bindValue(':subheading', $subheading, PDO::PARAM_STR);
                $stmt->bindValue(':expiry',     $expiry,     PDO::PARAM_STR);
                $stmt->bindValue(':role',       $role,       PDO::PARAM_STR);
                $stmt->bindValue(':batches',    $batchValue, PDO::PARAM_STR);
                $stmt->execute();

                setToast('Notification created successfully.', 'success', 5000);
                break;
              }
              catch (PDOException $ex) {
                if (!isRetryablePdoException($ex)) {
                  setToast('Failed to create notification. Please contact the system administrator.', 'danger', 7000);
                  logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error creating notification: ' . $ex->getMessage());
                  break;
                }
                $attempt++;
                sleep(3);
              }
            }
            if ($attempt >= $maxRetries) {
              setToast('Failed to create notification after multiple attempts. Please try again later.', 'danger', 7000);
            }
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      // --- EDIT ---
      if (isset($_POST['editNotificationBtn'])) {
        $editId      = (int) (escapeOutput($_POST['edit_notification_id'])          ?? 0);
        $heading     = escapeOutput($_POST['edit_notification_heading'])             ?? null;
        $subheading  = escapeOutput($_POST['edit_notification_subheading'])          ?? null;
        $expiry      = escapeOutput($_POST['edit_notification_expire_timestamp'])    ?? null;
        $role        = escapeOutput($_POST['edit_notification_user_role'])           ?? null;
        $batches     = ($role === 'student' && isset($_POST['edit_notification_batches']))
                         ? array_map('htmlspecialchars', $_POST['edit_notification_batches'])
                         : [];
        $batchValue  = ($role === 'student') ? json_encode($batches) : json_encode([]);
        $csrfToken   = escapeOutput($_POST['csrf_token']) ?? null;

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          $isValid = true;

          if ($editId <= 0) { setToast('Invalid notification record.', 'danger', 6000); $isValid = false; }
          if ($isValid && (empty($heading) || strlen($heading) < 3)) { setToast('Heading must be at least 3 characters.', 'danger', 6000); $isValid = false; }
          if ($isValid && empty($subheading)) { setToast('Subheading cannot be empty.', 'danger', 6000); $isValid = false; }
          if ($isValid && empty($expiry)) { setToast('Please select an expiry date and time.', 'danger', 6000); $isValid = false; }
          if ($isValid && !in_array($role, ['faculty', 'student'], true)) { setToast('Please select a valid target role.', 'danger', 6000); $isValid = false; }
          if ($isValid && $role === 'student' && empty($batches)) { setToast('Please select at least one batch for student notifications.', 'danger', 6000); $isValid = false; }

          if ($isValid) {
            $attempt    = 0;
            $maxRetries = 3;

            while ($attempt < $maxRetries) {
              try {
                $stmt = $db2->prepare('UPDATE notification_records
                                       SET notification_heading             = :heading,
                                           notification_subheading          = :subheading,
                                           notification_expire_timestamp    = :expiry,
                                           notification_user_role           = :role,
                                           notification_batch_value         = :batches
                                       WHERE id = :id
                                       LIMIT 1');
                $stmt->bindValue(':heading',    $heading,    PDO::PARAM_STR);
                $stmt->bindValue(':subheading', $subheading, PDO::PARAM_STR);
                $stmt->bindValue(':expiry',     $expiry,     PDO::PARAM_STR);
                $stmt->bindValue(':role',       $role,       PDO::PARAM_STR);
                $stmt->bindValue(':batches',    $batchValue, PDO::PARAM_STR);
                $stmt->bindValue(':id',         $editId,     PDO::PARAM_INT);
                $stmt->execute();

                setToast('Notification updated successfully.', 'success', 5000);
                break;
              }
              catch (PDOException $ex) {
                if (!isRetryablePdoException($ex)) {
                  setToast('Failed to update notification. Please contact the system administrator.', 'danger', 7000);
                  logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error updating notification: ' . $ex->getMessage());
                  break;
                }
                $attempt++;
                sleep(3);
              }
            }
            if ($attempt >= $maxRetries) {
              setToast('Failed to update notification after multiple attempts. Please try again later.', 'danger', 7000);
            }
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      // --- DELETE ---
      if (isset($_POST['deleteNotificationBtn'])) {
        $deleteId  = (int) (escapeOutput($_POST['delete_notification_id']) ?? 0);
        $csrfToken = escapeOutput($_POST['csrf_token']) ?? null;

        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          if ($deleteId > 0) {
            $attempt    = 0;
            $maxRetries = 3;

            while ($attempt < $maxRetries) {
              try {
                $stmt = $db2->prepare('DELETE FROM notification_records WHERE id = :id LIMIT 1');
                $stmt->bindValue(':id', $deleteId, PDO::PARAM_INT);
                $stmt->execute();

                setToast('Notification deleted successfully.', 'success', 5000);
                break;
              }
              catch (PDOException $ex) {
                if (!isRetryablePdoException($ex)) {
                  setToast('Failed to delete notification. Please contact the system administrator.', 'danger', 7000);
                  logAppError($db2, null, getCurrentURL(), 'DATABASE', 'Error deleting notification: ' . $ex->getMessage());
                  break;
                }
                $attempt++;
                sleep(3);
              }
            }
            if ($attempt >= $maxRetries) {
              setToast('Failed to delete notification after multiple attempts. Please try again later.', 'danger', 7000);
            }
          }
          else setToast('Invalid notification record.', 'danger', 6000);
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      $notificationRecords = fetchNotificationRecords($db2);
      $csrfTokenValue      = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
    }
  }
?>

<?php
  $page_title = "Notification Manager | careerinstitute.co.in";
  require_once '../components/header.php';

  $breadcrumb_url_1   = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active   = './';
  $breadcrumb_title_active = 'Notification Manager';

  require_once '../components/breadcrumb.php';
?>

<?php if (checkForEquality(checkLoginStatus($db1), true, 'strict')): ?>
  <?php if (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): ?>

    <section class="section-border border-primary ff-inter">
      <div class="container-fluid px-4 px-lg-6 py-7">

        <div class="row mb-5">
          <div class="col-12">
            <h2 class="fw-bold mb-1">Notification Manager</h2>
            <p class="text-body-secondary mb-0">Create and manage notifications for faculty and student batches.</p>
          </div>
        </div>

        <div class="row g-4 align-items-start">

          <!-- LEFT: Records Table -->
          <div class="col-12 col-lg-7 col-xl-8">
            <div class="card border shadow-lg">
              <div class="card-header py-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                  <div>
                    <h3 class="mb-0 fw-semibold">Notification Records</h3>
                    <small class="text-body-secondary">All active and scheduled notifications</small>
                  </div>
                  <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fs-sm" id="notifCountBadge">
                    <?php echo count($notificationRecords); ?> <?php echo count($notificationRecords) === 1 ? 'Record' : 'Records'; ?>
                  </span>
                </div>

                <?php if (!empty($notificationRecords)): ?>
                <div class="position-relative">
                  <span class="material-symbols-outlined position-absolute text-body-tertiary"
                        style="top:50%;right:1rem;transform:translateY(-50%);font-size:1.1rem;pointer-events:none;">search</span>
                  <input type="search"
                         id="notifSearchInput"
                         class="form-control ps-5"
                         placeholder="Search by heading or subheading…"
                         autocomplete="off" />
                </div>
                <?php endif; ?>
              </div>

              <div class="card-body p-0">
                <?php if (empty($notificationRecords)): ?>
                  <div class="text-center py-7 px-4">
                    <span class="material-symbols-outlined text-body-tertiary mb-3" style="font-size:3rem;">notifications_off</span>
                    <p class="fw-semibold mb-1">No Notifications Found</p>
                    <p class="text-body-secondary fs-sm mb-0">Use the form on the right to create the first notification.</p>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="notifTable">
                      <thead class="table-light">
                        <tr>
                          <th class="ps-4 py-3 fw-semibold text-body-secondary text-uppercase fs-xs">Notification</th>
                          <th class="py-3 fw-semibold text-body-secondary text-uppercase fs-xs">Target</th>
                          <th class="py-3 fw-semibold text-body-secondary text-uppercase fs-xs">Expires</th>
                          <th class="pe-4 py-3 fw-semibold text-body-secondary text-uppercase fs-xs">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="notifTableBody">
                        <?php foreach ($notificationRecords as $record):
                          $notifId         = (int) ($record['id'] ?? 0);
                          $displayHeading  = htmlspecialchars($record['notification_heading']          ?? '—', ENT_QUOTES, 'UTF-8');
                          $displaySub      = htmlspecialchars($record['notification_subheading']       ?? '—', ENT_QUOTES, 'UTF-8');
                          $displayExpiry   = htmlspecialchars($record['notification_expire_timestamp'] ?? '—', ENT_QUOTES, 'UTF-8');
                          $displayRole     = htmlspecialchars($record['notification_user_role']        ?? '—', ENT_QUOTES, 'UTF-8');
                          $rawBatches      = json_decode($record['notification_batch_value'] ?? '[]', true) ?: [];
                          $batchLabels     = array_map('formatBatchLabel', $rawBatches);
                          $isExpired       = !empty($displayExpiry) && strtotime($displayExpiry) < time();
                          $batchDataAttr   = htmlspecialchars(json_encode($rawBatches), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr data-search-heading="<?php echo strtolower($displayHeading); ?>"
                            data-search-sub="<?php echo strtolower($displaySub); ?>">
                          <td class="ps-4 py-3">
                            <div class="fw-semibold lh-sm"><?php echo $displayHeading; ?></div>
                            <div class="text-body-secondary fs-xs mt-1"><?php echo $displaySub; ?></div>
                          </td>
                          <td class="py-3">
                            <?php if ($displayRole === 'faculty'): ?>
                              <span class="badge bg-info-subtle text-info rounded-pill px-3">Faculty</span>
                            <?php else: ?>
                              <div>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 mb-1">Students</span>
                                <?php if (!empty($batchLabels)): ?>
                                  <div class="fs-xs text-body-tertiary"><?php echo implode(', ', array_map('htmlspecialchars', $batchLabels)); ?></div>
                                <?php endif; ?>
                              </div>
                            <?php endif; ?>
                          </td>
                          <td class="py-3">
                            <span class="<?php echo $isExpired ? 'text-danger' : 'text-body-secondary'; ?> fs-xs">
                              <?php echo $displayExpiry; ?>
                              <?php if ($isExpired): ?>
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-2 ms-1">Expired</span>
                              <?php endif; ?>
                            </span>
                          </td>
                          <td class="pe-4 py-3">
                            <div class="d-flex align-items-center gap-2">
                              <button type="button"
                                      class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 px-3"
                                      onclick="openEditModal(this)"
                                      data-id="<?php echo $notifId; ?>"
                                      data-heading="<?php echo $displayHeading; ?>"
                                      data-sub="<?php echo $displaySub; ?>"
                                      data-expiry="<?php echo $displayExpiry; ?>"
                                      data-role="<?php echo $displayRole; ?>"
                                      data-batches="<?php echo $batchDataAttr; ?>"
                                      title="Edit this notification">
                                <svg width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                                  <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z"/>
                                </svg>
                                Edit
                              </button>
                              <button type="button"
                                      class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 px-3"
                                      onclick="openDeleteModal(this)"
                                      data-id="<?php echo $notifId; ?>"
                                      data-heading="<?php echo $displayHeading; ?>"
                                      title="Delete this notification">
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

                    <div id="noSearchResults" class="text-center py-7 px-4 d-none">
                      <span class="material-symbols-outlined text-body-tertiary mb-3" style="font-size:3rem;">search_off</span>
                      <p class="fw-semibold mb-1">No Results Found</p>
                      <p class="text-body-secondary fs-sm mb-0">No notification matches your search query.</p>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- RIGHT: Create Form -->
          <div class="col-12 col-lg-5 col-xl-4">
            <form class="d-flex flex-column gap-4" method="POST" action="./">

              <div class="card border shadow-lg">
                <div class="card-header py-3">
                  <h4 class="mb-0 fw-semibold">New Notification</h4>
                  <small class="text-body-secondary">Fill in the details to publish a notification</small>
                </div>
                <div class="card-body p-4 d-flex flex-column gap-3">

                  <div>
                    <label class="form-label fw-semibold fs-sm mb-1" for="notification_heading">Heading</label>
                    <input class="form-control"
                           id="notification_heading"
                           type="text"
                           name="notification_heading"
                           placeholder="Enter notification heading..."
                           value="<?php echo htmlspecialchars($_POST['notification_heading'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           required />
                  </div>

                  <div>
                    <label class="form-label fw-semibold fs-sm mb-1" for="notification_subheading">Subheading</label>
                    <textarea class="form-control"
                              id="notification_subheading"
                              name="notification_subheading"
                              rows="2"
                              maxlength="255"
                              placeholder="Enter notification subheading..."
                              required><?php echo htmlspecialchars($_POST['notification_subheading'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                  </div>

                  <div>
                    <label class="form-label fw-semibold fs-sm mb-1" for="notification_expire_timestamp">Expiry Date &amp; Time</label>
                    <input class="form-control"
                           id="notification_expire_timestamp"
                           type="datetime-local"
                           name="notification_expire_timestamp"
                           value="<?php echo htmlspecialchars($_POST['notification_expire_timestamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           required />
                  </div>

                  <div>
                    <label class="form-label fw-semibold fs-sm mb-1" for="notification_user_role">Target Role</label>
                    <select class="form-select"
                            id="notification_user_role"
                            name="notification_user_role"
                            onchange="toggleBatchSection(this.value, 'create')"
                            required>
                      <option value="" disabled <?php echo empty($_POST['notification_user_role']) ? 'selected' : ''; ?>>Select role…</option>
                      <option value="faculty" <?php echo (($_POST['notification_user_role'] ?? '') === 'faculty') ? 'selected' : ''; ?>>Faculty</option>
                      <option value="student" <?php echo (($_POST['notification_user_role'] ?? '') === 'student') ? 'selected' : ''; ?>>Student</option>
                    </select>
                  </div>

                  <!-- Batch selector (student only) -->
                  <div id="create_batch_section" class="<?php echo (($_POST['notification_user_role'] ?? '') === 'student') ? '' : 'd-none'; ?>">
                    <label class="form-label fw-semibold fs-sm mb-2">Target Batches</label>
                    <?php if (!empty($activeBatchList)): ?>
                      <div class="border rounded-3 p-3 d-flex flex-column gap-2" style="max-height:200px;overflow-y:auto;">
                        <?php foreach ($activeBatchList as $batchCode): ?>
                          <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="notification_batches[]"
                                   id="create_batch_<?php echo htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8'); ?>"
                                   value="<?php echo htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8'); ?>"
                                   <?php echo (isset($_POST['notification_batches']) && in_array($batchCode, $_POST['notification_batches'])) ? 'checked' : ''; ?> />
                            <label class="form-check-label fs-sm" for="create_batch_<?php echo htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo htmlspecialchars(formatBatchLabel($batchCode), ENT_QUOTES, 'UTF-8'); ?>
                            </label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                      <div class="d-flex gap-2 mt-2">
                        <button type="button" class="btn btn-link btn-sm p-0 fs-xs text-decoration-none" onclick="toggleAllBatches('create', true)">Select All</button>
                        <span class="text-body-tertiary fs-xs">·</span>
                        <button type="button" class="btn btn-link btn-sm p-0 fs-xs text-decoration-none" onclick="toggleAllBatches('create', false)">Deselect All</button>
                      </div>
                    <?php else: ?>
                      <div class="text-body-secondary fs-sm border rounded-3 p-3">
                        <span class="material-symbols-outlined align-middle" style="font-size:1rem;">info</span>
                        No active batches found. Please activate batches from the Batchlist Manager first.
                      </div>
                    <?php endif; ?>
                  </div>

                </div>
              </div>

              <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">

              <button class="btn btn-primary rounded-pill w-100" name="createNotificationBtn" type="submit">
                Publish Notification
              </button>

            </form>
          </div>

        </div>
      </div>
    </section>

    <style>
      dialog.ci-dialog {
        border: none;
        border-radius: .75rem;
        box-shadow: 0 1rem 3rem rgba(0,0,0,.175);
        padding: 0;
        width: min(540px, 94vw);
        max-height: 90dvh;
        overflow-y: auto;
      }
      dialog.ci-dialog.ci-dialog--sm { width: min(360px, 94vw); }
      dialog.ci-dialog::backdrop { background: rgba(0,0,0,.45); backdrop-filter: blur(2px); }
      dialog.ci-dialog[open] { animation: ciDialogIn .18s ease-out; }
      @keyframes ciDialogIn {
        from { opacity: 0; transform: translateY(-12px) scale(.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
      }
      .ci-dialog__header {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: 1rem; padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--bs-border-color, #dee2e6);
      }
      .ci-dialog__body   { padding: 1.25rem; }
      .ci-dialog__footer {
        display: flex; align-items: center; justify-content: flex-end;
        gap: .5rem; padding: .875rem 1.25rem;
        border-top: 1px solid var(--bs-border-color, #dee2e6);
      }
      .ci-dialog__close {
        display: flex; align-items: center; justify-content: center;
        width: 1.75rem; height: 1.75rem; padding: 0; border: none; border-radius: .25rem;
        background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em no-repeat;
        opacity: .55; cursor: pointer; flex-shrink: 0;
      }
      .ci-dialog__close:hover { opacity: 1; background-color: rgba(0,0,0,.08); }
    </style>

    <!-- EDIT DIALOG -->
    <dialog class="ci-dialog" id="editNotifDialog" aria-labelledby="editNotifDialogTitle">
      <form method="POST" action="./">
        <div class="ci-dialog__header">
          <div>
            <h5 class="fw-semibold mb-0" id="editNotifDialogTitle">Edit Notification</h5>
            <small class="text-body-secondary">Changes are saved immediately on submit.</small>
          </div>
          <button type="button" class="ci-dialog__close" onclick="closeDialog('editNotifDialog')" aria-label="Close"></button>
        </div>
        <div class="ci-dialog__body d-flex flex-column gap-3">

          <input type="hidden" name="edit_notification_id" id="edit_notification_id">

          <div>
            <label class="form-label fw-semibold fs-sm mb-1" for="edit_notification_heading">Heading</label>
            <input class="form-control" id="edit_notification_heading" type="text" name="edit_notification_heading" required />
          </div>

          <div>
            <label class="form-label fw-semibold fs-sm mb-1" for="edit_notification_subheading">Subheading</label>
            <textarea class="form-control" id="edit_notification_subheading" name="edit_notification_subheading" rows="2" maxlength="255"></textarea>
          </div>

          <div>
            <label class="form-label fw-semibold fs-sm mb-1" for="edit_notification_expire_timestamp">Expiry Date &amp; Time</label>
            <input class="form-control" id="edit_notification_expire_timestamp" type="datetime-local" name="edit_notification_expire_timestamp" required />
          </div>

          <div>
            <label class="form-label fw-semibold fs-sm mb-1" for="edit_notification_user_role">Target Role</label>
            <select class="form-select" id="edit_notification_user_role" name="edit_notification_user_role"
                    onchange="toggleBatchSection(this.value, 'edit')" required>
              <option value="faculty">Faculty</option>
              <option value="student">Student</option>
            </select>
          </div>

          <div id="edit_batch_section" class="d-none">
            <label class="form-label fw-semibold fs-sm mb-2">Target Batches</label>
            <?php if (!empty($activeBatchList)): ?>
              <div class="border rounded-3 p-3 d-flex flex-column gap-2" style="max-height:200px;overflow-y:auto;" id="edit_batch_list">
                <?php foreach ($activeBatchList as $batchCode): ?>
                  <div class="form-check">
                    <input class="form-check-input edit-batch-check"
                           type="checkbox"
                           name="edit_notification_batches[]"
                           id="edit_batch_<?php echo htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8'); ?>"
                           value="<?php echo htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8'); ?>" />
                    <label class="form-check-label fs-sm" for="edit_batch_<?php echo htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8'); ?>">
                      <?php echo htmlspecialchars(formatBatchLabel($batchCode), ENT_QUOTES, 'UTF-8'); ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-link btn-sm p-0 fs-xs text-decoration-none" onclick="toggleAllBatches('edit', true)">Select All</button>
                <span class="text-body-tertiary fs-xs">·</span>
                <button type="button" class="btn btn-link btn-sm p-0 fs-xs text-decoration-none" onclick="toggleAllBatches('edit', false)">Deselect All</button>
              </div>
            <?php else: ?>
              <div class="text-body-secondary fs-sm border rounded-3 p-3">
                No active batches available.
              </div>
            <?php endif; ?>
          </div>

        </div>
        <div class="ci-dialog__footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="closeDialog('editNotifDialog')">Cancel</button>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">
          <button type="submit" name="editNotificationBtn" class="btn btn-primary rounded-pill px-4">Save Changes</button>
        </div>
      </form>
    </dialog>

    <!-- DELETE DIALOG -->
    <dialog class="ci-dialog ci-dialog--sm" id="deleteNotifDialog" aria-labelledby="deleteNotifDialogTitle">
      <form method="POST" action="./">
        <div class="ci-dialog__header">
          <h5 class="fw-semibold mb-0" id="deleteNotifDialogTitle">Delete Notification</h5>
          <button type="button" class="ci-dialog__close" onclick="closeDialog('deleteNotifDialog')" aria-label="Close"></button>
        </div>
        <div class="ci-dialog__body">
          <p class="mb-1">Are you sure you want to delete the notification</p>
          <p class="fw-semibold mb-3" id="delete_notif_heading_display">—</p>
          <p class="text-danger fs-sm mb-0 d-flex align-items-start gap-1">
            <span class="material-symbols-outlined mt-1" style="font-size:1rem;">warning</span>
            This action is permanent and cannot be undone.
          </p>
          <input type="hidden" name="delete_notification_id" id="delete_notification_id">
        </div>
        <div class="ci-dialog__footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="closeDialog('deleteNotifDialog')">Cancel</button>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenValue; ?>">
          <button type="submit" name="deleteNotificationBtn" class="btn btn-danger rounded-pill px-4">Delete</button>
        </div>
      </form>
    </dialog>

    <script>
      function openDialog(id)  { document.getElementById(id).showModal(); }
      function closeDialog(id) { document.getElementById(id).close(); }

      document.querySelectorAll('dialog.ci-dialog').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) {
          const r = dlg.getBoundingClientRect();
          if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) dlg.close();
        });
      });

      function toggleBatchSection(role, prefix) {
        const section = document.getElementById(prefix + '_batch_section');
        if (!section) return;
        role === 'student' ? section.classList.remove('d-none') : section.classList.add('d-none');
      }

      function toggleAllBatches(prefix, state) {
        const checks = prefix === 'edit'
          ? document.querySelectorAll('.edit-batch-check')
          : document.querySelectorAll('[name="notification_batches[]"]');
        checks.forEach(c => c.checked = state);
      }

      function openEditModal(btn) {
        const { id, heading, sub, expiry, role, batches } = btn.dataset;

        document.getElementById('edit_notification_id').value                  = id;
        document.getElementById('edit_notification_heading').value             = heading;
        document.getElementById('edit_notification_subheading').value         = sub;
        document.getElementById('edit_notification_expire_timestamp').value   = expiry;
        document.getElementById('edit_notification_user_role').value          = role;

        toggleBatchSection(role, 'edit');

        const selectedBatches = JSON.parse(batches || '[]');
        document.querySelectorAll('.edit-batch-check').forEach(function (chk) {
          chk.checked = selectedBatches.includes(chk.value);
        });

        openDialog('editNotifDialog');
      }

      function openDeleteModal(btn) {
        const { id, heading } = btn.dataset;
        document.getElementById('delete_notification_id').value          = id;
        document.getElementById('delete_notif_heading_display').textContent = heading + '?';
        openDialog('deleteNotifDialog');
      }

      (function () {
        const input        = document.getElementById('notifSearchInput');
        const noResults    = document.getElementById('noSearchResults');
        const countBadge   = document.getElementById('notifCountBadge');
        const total        = <?php echo count($notificationRecords); ?>;

        if (!input) return;

        input.addEventListener('input', function () {
          const query   = this.value.trim().toLowerCase();
          const rows    = document.querySelectorAll('#notifTableBody tr');
          let   visible = 0;

          rows.forEach(function (row) {
            const show = !query || row.dataset.searchHeading.includes(query) || row.dataset.searchSub.includes(query);
            row.classList.toggle('d-none', !show);
            if (show) visible++;
          });

          noResults.classList.toggle('d-none', visible > 0);
          countBadge.textContent = query
            ? visible + ' of ' + total + (total === 1 ? ' Record' : ' Records')
            : total + (total === 1 ? ' Record' : ' Records');
        });
      })();
    </script>

  <?php else: ?>
    <section class="section-border border-primary">
      <div class="container-lg">
        <div class="d-flex flex-column align-items-center justify-content-center min-vh-100">
          <svg width="100" height="100" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" fill="#000000">
            <path d="M62 52c0 5.5-4.5 10-10 10H12C6.5 62 2 57.5 2 52V12C2 6.5 6.5 2 12 2h40c5.5 0 10 4.5 10 10v40z" fill="#ff002f"/>
            <path fill="#ffffff" d="M50 21.2L42.8 14L32 24.8L21.2 14L14 21.2L24.8 32L14 42.8l7.2 7.2L32 39.2L42.8 50l7.2-7.2L39.2 32z"/>
          </svg>
          <div class="col-12 col-lg-9 col-md-10 px-8 py-8 text-center">
            <h1 class="display-3 fw-bold">Access Denied.</h1>
            <p class="mb-5 text-body-secondary">Access to this page is restricted. Unauthorized access attempts may be monitored.</p>
            <a class="btn btn-primary rounded-pill ff-sourcesans3" href="../dashboard/">Back to Dashboard</a>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

<?php elseif (checkForEquality(checkLoginStatus($db1), false, 'strict')): ?>
  <section class="section-border border-primary min-vh-100">
    <div class="container-lg">
      <div class="d-flex flex-column align-items-center justify-content-center text-center px-8 py-8">
        <h1 class="display-3 fw-bold">Session Expired.</h1>
        <p class="mb-5 text-body-secondary">You are currently logged out. Please sign in to access this page.</p>
        <a class="btn btn-primary rounded-pill ff-sourcesans3" href="../login/">Back to Login</a>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php require_once '../components/footer.php'; ?>
