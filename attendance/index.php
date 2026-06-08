<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Attendance
  if (checkForEquality(checkLoginStatus($db1), true, 'strict')) {
    if (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')) {
      $csrfTokenValue  = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
      $batchListConfig = retrieveActiveBatchlist($db2);
      $activeBatchList = json_decode((string)($batchListConfig['value'] ?? '[]'), true);
      if (!is_array($activeBatchList)) $activeBatchList = [];

      $selectedBatch   = isset($_GET['batch']) ? escapeOutput($_GET['batch']) : null;
      $studentsInBatch = [];
      $batchAttendance = [];

      if ($selectedBatch !== null) {
        try {
          $s = $db1->prepare(
            'SELECT student_usercode, student_name
             FROM student_details
             WHERE student_batch_details = :b
             ORDER BY student_name ASC'
          );
          $s->bindValue(':b', $selectedBatch, PDO::PARAM_STR);
          $s->execute();
          $studentsInBatch = $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {}

        try {
          $s = $db2->prepare(
            'SELECT attendance_id, `attendance_.timestamp`, attendance_value
             FROM attendance_records
             WHERE attendance_batch_code = :b'
          );
          $s->bindValue(':b', $selectedBatch, PDO::PARAM_STR);
          $s->execute();
          foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = date('Y-d-m', strtotime((string)$row['attendance_.timestamp']));
            $batchAttendance[$key] = [
              'id'        => (int)$row['attendance_id'],
              'usercodes' => json_decode((string)$row['attendance_value'], true) ?? [],
            ];
          }
        } catch (PDOException) {}
      }

      /* CREATE */
      if (isset($_POST['createAttendance'])) {
        $csrf = escapeOutput($_POST['csrf_token'] ?? null);
        if (validateCsrfToken($csrf)) {
          unsetCsrfToken();
          $postBatch    = escapeOutput($_POST['attendance_batch'] ?? '');
          $postDate     = escapeOutput($_POST['attendance_date']  ?? '');
          $presentCodes = array_values(array_map('strval', (array)($_POST['present_students'] ?? [])));
          $ts           = strtotime((string)$postDate);

          if (!$ts || !$postBatch) {
            setToast('Invalid batch or date.', 'danger', 5000);
          } else {
            $dupId = false;
            try {
              $check = $db2->prepare(
                "SELECT attendance_id FROM attendance_records
                 WHERE attendance_batch_code = :b AND `attendance_.timestamp` LIKE :d LIMIT 1"
              );
              $check->execute([':b' => $postBatch, ':d' => date('d/m/Y', $ts) . '%']);
              $dupId = $check->fetchColumn();
            } catch (PDOException) {}

            if ($dupId !== false) {
              setToast('Attendance for this date already exists. Use Edit instead.', 'warning', 6000);
            } else {
              $fmtTs = getCurrentTimestamp();
              $val   = json_encode($presentCodes);
              $att   = 0;
              while ($att < 3) {
                try {
                  $s = $db2->prepare(
                    'INSERT INTO attendance_records
                     (attendance_batch_code, `attendance_.timestamp`, attendance_value)
                     VALUES (:b, :t, :v)'
                  );
                  $s->execute([':b' => $postBatch, ':t' => $fmtTs, ':v' => $val]);
                  setToast('Attendance created successfully.', 'success', 5000);
                  redirectTo('./?batch=' . rawurlencode($postBatch), 0);
                  break;
                } catch (PDOException $ex) {
                  if (!isRetryablePdoException($ex)) {
                    setToast('Error creating attendance. Contact administrator.', 'danger', 7000);
                    logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Create attendance: ' . $ex->getMessage());
                    break;
                  }
                  $att++; sleep(3);
                }
              }
              if ($att >= 3) setToast('Failed after multiple attempts. Try again later.', 'danger', 7000);
            }
          }
        } else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      /* UPDATE */
      if (isset($_POST['updateAttendance'])) {
        $csrf = escapeOutput($_POST['csrf_token'] ?? null);
        if (validateCsrfToken($csrf)) {
          unsetCsrfToken();
          $postBatch    = escapeOutput($_POST['attendance_batch'] ?? '');
          $postId       = (int)($_POST['attendance_id'] ?? 0);
          $presentCodes = array_values(array_map('strval', (array)($_POST['present_students'] ?? [])));
          $val = json_encode($presentCodes);
          $att = 0;
          while ($att < 3) {
            try {
              $s = $db2->prepare(
                'UPDATE attendance_records SET attendance_value = :v WHERE attendance_id = :id LIMIT 1'
              );
              $s->execute([':v' => $val, ':id' => $postId]);
              setToast('Attendance updated successfully.', 'success', 5000);
              redirectTo('./?batch=' . rawurlencode($postBatch), 0);
              break;
            } catch (PDOException $ex) {
              if (!isRetryablePdoException($ex)) {
                setToast('Error updating attendance. Contact administrator.', 'danger', 7000);
                logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Update attendance: ' . $ex->getMessage());
                break;
              }
              $att++; sleep(3);
            }
          }
          if ($att >= 3) setToast('Failed after multiple attempts. Try again later.', 'danger', 7000);
        } else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      /* DELETE */
      if (isset($_POST['deleteAttendance'])) {
        $csrf = escapeOutput($_POST['csrf_token'] ?? null);
        if (validateCsrfToken($csrf)) {
          unsetCsrfToken();
          $postBatch = escapeOutput($_POST['attendance_batch'] ?? '');
          $postId    = (int)($_POST['attendance_id'] ?? 0);
          $att = 0;
          while ($att < 3) {
            try {
              $s = $db2->prepare('DELETE FROM attendance_records WHERE attendance_id = :id LIMIT 1');
              $s->execute([':id' => $postId]);
              setToast('Attendance record deleted successfully.', 'success', 5000);
              redirectTo('./?batch=' . rawurlencode($postBatch), 0);
              break;
            } catch (PDOException $ex) {
              if (!isRetryablePdoException($ex)) {
                setToast('Error deleting attendance. Contact administrator.', 'danger', 7000);
                logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Delete attendance: ' . $ex->getMessage());
                break;
              }
              $att++; sleep(3);
            }
          }
          if ($att >= 3) setToast('Failed after multiple attempts. Try again later.', 'danger', 7000);
        } else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }
    }

    elseif (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')) {
      $attendanceByDate = [];
      $attendanceStats  = [];
      $month = (int)date('n');
      $year  = (int)date('Y');

      try {
        $s = $db1->prepare(
          'SELECT student_batch_details FROM student_details WHERE student_usercode = :uc LIMIT 1'
        );
        $s->bindValue(':uc', $_SESSION['usercode'], PDO::PARAM_STR);
        $s->execute();
        $studentBatch = $s->fetchColumn();

        if ($studentBatch) {
          $s = $db2->prepare(
            'SELECT `attendance_.timestamp`, attendance_value
             FROM attendance_records
             WHERE attendance_batch_code = :b'
          );
          $s->bindValue(':b', $studentBatch, PDO::PARAM_STR);
          $s->execute();

          foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = date('Y-m-d', strtotime((string)$row['attendance_.timestamp']));
            $ucs = json_decode((string)$row['attendance_value'], true) ?? [];
            if (in_array($_SESSION['usercode'], $ucs, true)) {
              $attendanceByDate[$key] = true;
              [$y, $m] = explode('-', $key);
              $y = (int)$y; $m = (int)$m;
              if (!isset($attendanceStats[$y])) {
                $attendanceStats[$y] = ['yearTotal' => 0, 'months' => array_fill(1, 12, 0)];
              }
              $attendanceStats[$y]['yearTotal']++;
              $attendanceStats[$y]['months'][$m]++;
            }
          }
        }
      } catch (PDOException) {}
    }
  }
?>

<?php // Headers
  $page_title = "Attendance | careerinstitute.co.in";

  require_once '../components/header.php';

  $breadcrumb_url_1        = '../dashboard/';
  $breadcrumb_title_1      = 'Dashboard';
  $breadcrumb_url_active   = './';
  $breadcrumb_title_active = 'Attendance';

  require_once '../components/breadcrumb.php';
?>

<?php if (checkForEquality(checkLoginStatus($db1), true, 'strict')): ?>

  <?php if (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): // For Admins ?>
    <link rel="stylesheet" href="./attendance-styler.css" />

    <section class="section-border border-primary">
      <div class="container-xxl d-flex flex-column">
        <div class="row align-items-start justify-content-center gx-0 min-vh-100">
          <div class="col-12 px-8 py-8">
            <?php if (checkForEquality($selectedBatch, null, 'strict')): ?>

              <h2 class="fw-bold mb-1">Save Attendance</h2>
              <p class="text-body-secondary mb-6">Select a batch to manage attendance records.</p>

              <?php if (empty($activeBatchList)): ?>
                <div class="alert alert-light border">No active batches configured. Set them up in Batch Manager first.</div>
              <?php else: ?>
                <form method="GET" action="./" class="row g-3 align-items-end" style="max-width: 520px;">
                  <div class="col">
                    <label class="form-label fw-semibold">Select Batch</label>
                    <select name="batch" class="form-select form-select-lg" required>
                      <option value="" disabled selected>Choose a batch…</option>
                      <?php foreach ($activeBatchList as $batch): ?>
                        <option value="<?= htmlspecialchars($batch, ENT_QUOTES, 'UTF-8'); ?>">
                          <?= htmlspecialchars(prettyPrintClassCode($batch), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-auto">
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Continue</button>
                  </div>
                </form>
              <?php endif; ?>

            <?php else: ?>
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-6">
                <div>
                  <h2 class="fw-bold mb-1">
                    Attendance &mdash;
                    <span class="text-primary"><?= htmlspecialchars(prettyPrintClassCode($selectedBatch), ENT_QUOTES, 'UTF-8'); ?></span>
                  </h2>
                  <p class="text-body-secondary mb-0">Click any date to create or manage its attendance record.</p>
                </div>
                <a href="./" class="btn btn-outline-secondary rounded-pill px-4">Change Batch</a>
              </div>

              <?php if (empty($studentsInBatch)): ?>
                <div class="alert alert-light border">No students found in this batch.</div>
              <?php else: ?>

                <div class="mx-auto" style="max-width: 400px;">
                  <div class="d-flex align-items-center justify-content-between mb-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" id="prevMonth">&#8592;</button>
                    <strong id="calTitle" class="fs-6 text-center"></strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" id="nextMonth">&#8594;</button>
                  </div>
                  <div class="att-grid" id="calGrid"></div>
                  <div class="mt-3 d-flex gap-4 fs-sm text-body-secondary">
                    <span><span style="display:inline-block;width:12px;height:12px;background:#d1fae5;border-radius:2px;vertical-align:middle;margin-right:4px;"></span>Has record</span>
                    <span><span style="display:inline-block;width:12px;height:12px;background:#e9ecef;border-radius:2px;vertical-align:middle;margin-right:4px;"></span>No record</span>
                  </div>
                </div>

                <!-- Attendance Dialog (create / edit) -->
                <dialog class="ci-dialog" id="attendanceDialog" aria-labelledby="attendanceDialogTitle">
                  <form method="POST" action="./?batch=<?= rawurlencode($selectedBatch); ?>" id="attendanceForm">
                    <div class="ci-dialog__header">
                      <div>
                        <h5 class="fw-semibold mb-0" id="attendanceDialogTitle">Attendance</h5>
                        <small class="text-body-secondary" id="attendanceDialogSub"></small>
                      </div>
                      <button type="button" class="ci-dialog__close" onclick="closeDialog('attendanceDialog')" aria-label="Close"></button>
                    </div>
                    <div class="ci-dialog__body">
                      <input type="hidden" name="attendance_batch" value="<?= htmlspecialchars($selectedBatch, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="attendance_date"  id="dialogDate">
                      <input type="hidden" name="attendance_id"    id="dialogRecordId">
                      <input type="hidden" name="csrf_token"       value="<?= $csrfTokenValue; ?>">

                      <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="fw-semibold fs-sm">Students Present</label>
                        <div class="d-flex gap-2">
                          <button type="button" class="btn btn-xs btn-outline-success rounded-pill px-2 py-0" onclick="toggleAll(true)">All Present</button>
                          <button type="button" class="btn btn-xs btn-outline-danger  rounded-pill px-2 py-0" onclick="toggleAll(false)">All Absent</button>
                        </div>
                      </div>
                      <div id="studentChecklist"
                          class="border rounded-3"
                          style="max-height: 340px; overflow-y: auto;"></div>
                    </div>
                    <div class="ci-dialog__footer" id="attendanceDialogFooter"></div>
                  </form>
                </dialog>

                <!-- Delete Confirm Dialog -->
                <dialog class="ci-dialog ci-dialog--sm" id="deleteConfirmDialog" aria-labelledby="deleteConfirmTitle">
                  <form method="POST" action="./?batch=<?= rawurlencode($selectedBatch); ?>">
                    <div class="ci-dialog__header">
                      <h5 class="fw-semibold mb-0" id="deleteConfirmTitle">Delete Attendance Record</h5>
                      <button type="button" class="ci-dialog__close" onclick="closeDialog('deleteConfirmDialog')" aria-label="Close"></button>
                    </div>
                    <div class="ci-dialog__body">
                      <p class="mb-1">Are you sure you want to delete the attendance record for</p>
                      <p class="fw-semibold mb-3" id="deleteConfirmDateDisplay">—</p>
                      <p class="text-danger fs-sm mb-0 d-flex align-items-start gap-1">
                        <span class="material-symbols-outlined mt-1" style="font-size:1rem;">warning</span>
                        This permanently removes the attendance data for all students on this date.
                      </p>
                      <input type="hidden" name="attendance_batch" value="<?= htmlspecialchars($selectedBatch, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="attendance_id"    id="deleteConfirmId">
                      <input type="hidden" name="csrf_token"       value="<?= $csrfTokenValue; ?>">
                    </div>
                    <div class="ci-dialog__footer">
                      <button type="button"  class="btn btn-outline-secondary rounded-pill px-4" onclick="closeDialog('deleteConfirmDialog')">Cancel</button>
                      <button type="submit"  name="deleteAttendance" class="btn btn-danger rounded-pill px-4">Delete</button>
                    </div>
                  </form>
                </dialog>

              <?php endif; ?>

            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <script>
      const BATCH_STUDENTS   = <?= json_encode(array_values($studentsInBatch), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      const BATCH_ATTENDANCE = <?= json_encode($batchAttendance,               JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

      let curMonth = new Date().getMonth() + 1;
      let curYear  = new Date().getFullYear();

      function renderCalendar(month, year) {
        const grid  = document.getElementById('calGrid');
        const title = document.getElementById('calTitle');
        grid.innerHTML = '';
        title.textContent = new Date(year, month - 1)
          .toLocaleString('default', { month: 'long', year: 'numeric' });

        ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(d => {
          const el = document.createElement('div');
          el.className = 'att-weekday';
          el.textContent = d;
          grid.appendChild(el);
        });

        const startDay  = new Date(year, month - 1, 1).getDay();
        const totalDays = new Date(year, month, 0).getDate();
        const today     = new Date();

        for (let i = 0; i < startDay; i++) {
          const e = document.createElement('div');
          e.className = 'att-day att-day--empty';
          grid.appendChild(e);
        }

        for (let day = 1; day <= totalDays; day++) {
          const dateKey = `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
          const hasRec  = !!BATCH_ATTENDANCE[dateKey];
          const cell    = document.createElement('div');

          cell.className = 'att-day' + (hasRec ? ' att-day--has' : '');
          if (day === today.getDate() && month === today.getMonth() + 1 && year === today.getFullYear()) {
            cell.classList.add('att-day--today');
          }
          cell.textContent = day;
          cell.title = hasRec
            ? `${dateKey} — record exists (${BATCH_ATTENDANCE[dateKey].usercodes.length} present)`
            : `${dateKey} — no record`;
          cell.addEventListener('click', () => openAttendanceDialog(dateKey));
          grid.appendChild(cell);
        }
      }

      function openAttendanceDialog(dateKey) {
        const rec    = BATCH_ATTENDANCE[dateKey] || null;
        const isEdit = rec !== null;

        document.getElementById('attendanceDialogTitle').textContent = dateKey;
        document.getElementById('attendanceDialogSub').textContent   = isEdit
          ? `Editing existing record — ${rec.usercodes.length} student(s) marked present.`
          : 'No record yet. Mark students present and create.';

        document.getElementById('dialogDate').value     = dateKey;
        document.getElementById('dialogRecordId').value = isEdit ? rec.id : '';

        // Build student checklist
        const list = document.getElementById('studentChecklist');
        list.innerHTML = '';
        BATCH_STUDENTS.forEach(s => {
          const checked    = isEdit && rec.usercodes.includes(s.student_usercode);
          const ucSafe     = esc(s.student_usercode);
          const nameSafe   = esc(s.student_name);
          const wrapper    = document.createElement('div');
          wrapper.className = 'form-check d-flex align-items-center gap-2 ms-5 px-3 py-2 border-bottom';
          wrapper.innerHTML =
            `<input class="form-check-input flex-shrink-0 mt-0" type="checkbox"
                    name="present_students[]" value="${ucSafe}" id="sc_${ucSafe}"
                    ${checked ? 'checked' : ''}>
             <label class="form-check-label d-flex flex-column" for="sc_${ucSafe}" style="cursor:pointer;">
               <span class="fw-semibold">${nameSafe}</span>
               <small class="text-body-secondary">${ucSafe}</small>
             </label>`;
          list.appendChild(wrapper);
        });

        // Footer varies by create vs edit
        const footer = document.getElementById('attendanceDialogFooter');
        if (isEdit) {
          footer.innerHTML =
            `<button type="button" class="btn btn-outline-danger rounded-pill px-3 me-auto"
                     onclick="openDeleteConfirm('${esc(dateKey)}', ${rec.id})">Delete Record</button>
             <button type="button" class="btn btn-outline-secondary rounded-pill px-3"
                     onclick="closeDialog('attendanceDialog')">Cancel</button>
             <button type="submit" name="updateAttendance" class="btn btn-primary rounded-pill px-4">Update</button>`;
        } else {
          footer.innerHTML =
            `<button type="button" class="btn btn-outline-secondary rounded-pill px-3"
                     onclick="closeDialog('attendanceDialog')">Cancel</button>
             <button type="submit" name="createAttendance" class="btn btn-success rounded-pill px-4">Create Record</button>`;
        }

        document.getElementById('attendanceDialog').showModal();
      }

      function openDeleteConfirm(dateKey, recordId) {
        document.getElementById('deleteConfirmDateDisplay').textContent = dateKey + '?';
        document.getElementById('deleteConfirmId').value = recordId;
        closeDialog('attendanceDialog');
        document.getElementById('deleteConfirmDialog').showModal();
      }

      function toggleAll(checked) {
        document.querySelectorAll('#studentChecklist input[type="checkbox"]').forEach(cb => cb.checked = checked);
      }

      function closeDialog(id) { document.getElementById(id).close(); }

      function esc(str) {
        return String(str)
          .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
          .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
      }

      document.querySelectorAll('dialog.ci-dialog').forEach(dlg => {
        dlg.addEventListener('click', e => {
          const r = dlg.getBoundingClientRect();
          if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
            dlg.close();
          }
        });
      });

      document.getElementById('prevMonth').addEventListener('click', () => {
        if (--curMonth < 1) { curMonth = 12; curYear--; }
        renderCalendar(curMonth, curYear);
      });

      document.getElementById('nextMonth').addEventListener('click', () => {
        if (++curMonth > 12) { curMonth = 1; curYear++; }
        renderCalendar(curMonth, curYear);
      });

      renderCalendar(curMonth, curYear);
    </script>

  <?php elseif (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')): // For Students ?>
    <link rel="stylesheet" href="./attendance-styler.css">

    <section class="section-border border-primary min-vh-100 d-flex align-items-center">
      <div class="container-xl">
        <div class="row justify-content-center">
          <div class="col-12 col-lg-9 py-8">
            <div class="mx-auto my-10 ff-inter" style="max-width: 40rem;">
              <div class="d-flex row align-items-center mb-3 text-center">
                <button type="button" class="col-auto btn btn-xs btn-light btn-outline-light" id="prevMonth">
                  <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10.5303 5.46967C10.8232 5.76256 10.8232 6.23744 10.5303 6.53033L5.81066 11.25H20C20.4142 11.25 20.75 11.5858 20.75 12C20.75 12.4142 20.4142 12.75 20 12.75H5.81066L10.5303 17.4697C10.8232 17.7626 10.8232 18.2374 10.5303 18.5303C10.2374 18.8232 9.76256 18.8232 9.46967 18.5303L3.46967 12.5303C3.17678 12.2374 3.17678 11.7626 3.46967 11.4697L9.46967 5.46967C9.76256 5.17678 10.2374 5.17678 10.5303 5.46967Z" fill="#1C274C"/>
                  </svg>
                </button>
                <div class="col fw-bold" id="calendarTitle"></div>
                <button type="button" class="col-auto btn btn-xs border border-primary" id="nextMonth">
                  <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M13.4697 5.46967C13.7626 5.17678 14.2374 5.17678 14.5303 5.46967L20.5303 11.4697C20.8232 11.7626 20.8232 12.2374 20.5303 12.5303L14.5303 18.5303C14.2374 18.8232 13.7626 18.8232 13.4697 18.5303C13.1768 18.2374 13.1768 17.7626 13.4697 17.4697L18.1893 12.75H4C3.58579 12.75 3.25 12.4142 3.25 12C3.25 11.5858 3.58579 11.25 4 11.25H18.1893L13.4697 6.53033C13.1768 6.23744 13.1768 5.76256 13.4697 5.46967Z" fill="#1C274C"/>
                  </svg>
                </button>
              </div>
              <div class="calendar-grid mb-10" id="calendarGrid"></div>
              <div id="calendar-data">
                <p class="my-1">Days present this year (<?= date('Y'); ?>): <strong id="yearTotal"></strong> days</p>
                <p class="my-1">Days present this month (<?= date('F') . ', ' . date('Y'); ?>): <strong id="monthTotal"></strong> days</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <script>
      window.ATTENDANCE_CONFIG = {
        attendanceDates: <?= json_encode(array_keys($attendanceByDate)); ?>,
        attendanceStats: <?= json_encode($attendanceStats); ?>,
        currentMonth: <?= $month; ?>,
        currentYear:  <?= $year; ?>
      };
    </script>

    <script src="./attendance-controller.js" defer></script>

  <?php else: // Restricted Access for Faculty ?>
    <section class="section-border border-primary min-vh-100">
      <div class="container-lg">
        <div class="d-flex flex-column align-items-center justify-content-center min-vh-100">
          <svg width="100" height="100" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" fill="#000000">
            <g><path d="M62 52c0 5.5-4.5 10-10 10H12C6.5 62 2 57.5 2 52V12C2 6.5 6.5 2 12 2h40c5.5 0 10 4.5 10 10v40z" fill="#ff002f"></path><path fill="#ffffff" d="M50 21.2L42.8 14L32 24.8L21.2 14L14 21.2L24.8 32L14 42.8l7.2 7.2L32 39.2L42.8 50l7.2-7.2L39.2 32z"></path></g>
          </svg>
          <div class="col-12 col-lg-9 col-md-10 px-8 py-8">
            <h1 class="display-3 fw-bold text-center">Access Denied.</h1>
            <p class="mb-5 text-center text-body-secondary">Access to this page is restricted.</p>
            <div class="text-center my-7">
              <a class="btn btn-primary rounded-pill" href="../dashboard/">Back to Dashboard</a>
            </div>
          </div>
        </div>
      </div>
    </section>

  <?php endif; ?>

<?php elseif (checkForEquality(checkLoginStatus($db1), false, 'strict')): ?>
  <section class="section-border border-primary min-vh-100">
    <div class="container-lg">
      <div class="d-flex flex-column align-items-center justify-content-center min-vh-100">
        <div class="col-12 col-lg-9 col-md-10 px-8 py-8">
          <h1 class="display-3 fw-bold text-center">Session Expired.</h1>
          <p class="mb-5 text-center text-body-secondary">You are currently logged out. Please sign in to access this page.</p>
          <div class="text-center my-7">
            <a class="btn btn-primary rounded-pill ff-sourcesans3" href="../login/">Back to Login</a>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>

<?php require_once '../components/footer.php'; ?>