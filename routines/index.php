<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
    'required_roles' => ['student', 'admin']
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Routines
  function normalizeBatchCode(?string $batchCode): ?string {
    if ($batchCode === null) {
      return null;
    }

    $batchCode = trim($batchCode);

    if ((bool) preg_match('/^\d+-(CBSE|BSEB|ICSE)$/', $batchCode)) {
      return $batchCode . '-NULL';
    }

    return $batchCode;
  }

  function getRoutineEmbedUrl(?string $batchCode): ?string {
    $batchCode = normalizeBatchCode($batchCode);

    $routineMap = [
      '5-CBSE-NULL' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='CBSE'!A1&Item='CBSE'!A1%3AF34&wdInConfigurator=True&wdInConfigurator=True",
      '6-CBSE-NULL' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='CBSE'!H1&Item='CBSE'!H1%3AM34&wdInConfigurator=True&wdInConfigurator=True",
      '7-CBSE-NULL' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='CBSE'!O1&Item='CBSE'!O1%3AT34&wdInConfigurator=True&wdInConfigurator=True",
      '8-CBSE-NULL' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='CBSE'!V1&Item='CBSE'!V1%3AAA34&wdInConfigurator=True&wdInConfigurator=True",
      '9-CBSE-NULL' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='CBSE'!AC1&Item='CBSE'!AC1%3AAH34&wdInConfigurator=True&wdInConfigurator=True",
      '10-CBSE-NULL' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='CBSE'!AQ1&Item='CBSE'!AQ1%3AAV34&wdInConfigurator=True&wdInConfigurator=True",
      '11-CBSE-Science' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='CBSE'!BE1&Item='CBSE'!BE1%3ABJ34&wdInConfigurator=True&wdInConfigurator=True",
      '11-CBSE-Arts' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!AJ1&Item='Bihar%20Board'!AJ1%3AAO34&wdInConfigurator=True&wdInConfigurator=True",
      '11-CBSE-Humanities' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!AQ1&Item='Bihar%20Board'!AQ1%3AAV34&wdInConfigurator=True&wdInConfigurator=True",
      '12-CBSE-Science' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='CBSE'!BL1&Item='CBSE'!BL1%3ABQ34&wdInConfigurator=True&wdInConfigurator=True",
      '12-CBSE-Arts' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!BL1&Item='Bihar%20Board'!BL1%3ABQ34&wdInConfigurator=True&wdInConfigurator=True",
      '12-CBSE-Humanities' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!AX1&Item='Bihar%20Board'!AX1%3ABC34&wdInConfigurator=True&wdInConfigurator=True",
      '7-BSEB-NULL' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!A1&Item='Bihar%20Board'!A1%3AF34&wdInConfigurator=True&wdInConfigurator=True",
      '8-BSEB-NULL' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!H1&Item='Bihar%20Board'!H1%3AM34&wdInConfigurator=True&wdInConfigurator=True",
      '9-BSEB-NULL' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!O1&Item='Bihar%20Board'!O1%3AT34&wdInConfigurator=True&wdInConfigurator=True",
      '10-BSEB-NULL' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!V1&Item='Bihar%20Board'!V1%3AAA34&wdInConfigurator=True&wdInConfigurator=True",
      '11-BSEB-Science' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!AC1&Item='Bihar%20Board'!AC1%3AAH34&wdInConfigurator=True&wdInConfigurator=True",
      '11-BSEB-Arts' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!AJ1&Item='Bihar%20Board'!AJ1%3AAO34&wdInConfigurator=True&wdInConfigurator=True",
      '12-BSEB-Science' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!AJ1&Item='Bihar%20Board'!AJ1%3AAO34&wdInConfigurator=True&wdInConfigurator=True",
      '12-BSEB-Arts' => "https://1drv.ms/x/c/2f9715c06a8aac21/UQQhrIpqwBWXIIAvEx8AAAAAAE3Rt97k_rpt5BU?em=2&wdAllowInteractivity=False&ActiveCell='Bihar%20Board'!BL1&Item='Bihar%20Board'!BL1%3ABQ34&wdInConfigurator=True&wdInConfigurator=True",
    ];

    return $batchCode !== null ? ($routineMap[$batchCode] ?? null) : null;
  }

  if(checkForEquality($currentUserRole, 'student', 'strict')) {
    $selectedBatch = $userRecord['student_batch_details'] ?? null;
  } 
  else {
    $selectedBatch = $_GET['batch'] ?? null;
  }

  $selectedBatch = normalizeBatchCode($selectedBatch);
  $selectedRoutineUrl = getRoutineEmbedUrl($selectedBatch);

  $batchListConfig = retrieveActiveBatchlist($db2);
  $activeBatchList = json_decode((string) ($batchListConfig['value'] ?? '[]'), true);
  if (!is_array($activeBatchList)) {
    $activeBatchList = [];
  }
?>

<?php // Headers
  $page_title = "Routines | careerinstitute.co.in";

  require_once '../components/header.php';

  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'Routines';

  require_once '../components/breadcrumb.php';
?>

<?php if(checkForEquality($currentUserRole, 'admin', 'strict')): ?>
  <section class="section-border border-primary">
    <div class="container d-flex flex-column">
      <div class="row gx-0 align-items-center justify-content-center min-vh-100">
        <div class="col-12 px-0 px-lg-8 py-8">
          <p class="mb-4 display-4 fw-bold">
            Routine Viewer
            <span class="d-block fs-3 mt-3">for <?php echo date('F') . ' ' . date('Y'); ?></span>
          </p>

          <p class="my-5 mb-7 text-gray-800 fs-lg" style="max-width:48rem;">
            Choose a batch to review its published routine. This is useful for faculty planning and admin oversight across active batches.
          </p>

          <?php if (checkForEquality($activeBatchList, [], 'strict')): ?>
            <div class="alert alert-light border text-center px-4 py-3 my-4" style="max-width:42rem;">
              <strong>No active batches are configured yet.</strong>
              Add batches from the admin panel to use the routine viewer here.
            </div>

          <div class="d-flex flex-wrap align-items-center justify-content-center w-100">
            <?php else: ?>
              <form method="GET" action="./" class="mb-5 d-flex flex-column">
                <div class="row g-3 align-items-start justify-content-center w-100">
                  <label class="form-label fw-semibold">Select Batch</label>
                  <div class="col-auto col-lg">
                    <select class="form-select form-select-lg" name="batch" required>
                      <option value="" disabled <?php echo $selectedBatch === null ? 'selected' : ''; ?>>
                        Choose Batch for Routine Viewing
                      </option>
                      <?php foreach ($activeBatchList as $batch): ?>
                        <?php $normalizedBatch = normalizeBatchCode((string) $batch); ?>
                        <option value="<?php echo escapeOutput((string) $normalizedBatch); ?>" <?php echo checkForEquality($selectedBatch, $normalizedBatch, 'strict') ? 'selected' : ''; ?>>
                          <?php echo prettyPrintClassCode((string) $normalizedBatch); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 col-md-auto d-flex flex-wrap gap-1">
                    <button class="btn btn-primary rounded-pill" type="submit">
                      Load Routine
                    </button>
                    <a class="btn btn-primary rounded-pill mx-2" href="./">
                      Refresh the Page
                    </a>
                    <a class="btn btn-primary rounded-pill mx-2" href="../dashboard/">
                      Dashboard
                    </a>
                  </div>
                </div>
              </form>

            <?php endif; ?>

            <?php if (!checkForEquality($selectedBatch, null, 'strict') && !checkForEquality($selectedRoutineUrl, null, 'strict')): ?>
              
              <div class="d-flex justify-content-center w-100">
                <iframe class="my-5"
                        style="width:100%; max-width:600px; height:750px;"
                        frameborder="0"
                        src="<?php echo escapeOutput($selectedRoutineUrl); ?>">
                </iframe>
              </div>
              
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php elseif(checkForEquality($currentUserRole, 'student', 'strict')): ?>
  <section class="section-border border-primary">
    <div class="container d-flex flex-column">
      <div class="row gx-0 align-items-start justify-content-center min-vh-100">
        <div class="col-12 px-0 px-lg-8 py-8">
          <p class="my-10 mb-4 display-4 fw-bold">
            <?php if (checkForEquality($currentUserRole, 'student', 'strict')): ?>
              Routine of <?php echo prettyPrintClassCode((string) $selectedBatch); ?>
            <?php else: ?>
              Routine Viewer
            <?php endif; ?>
            <span class="d-block fs-3 mt-3">for <?php echo date('F') . ' ' . date('Y'); ?></span>
          </p>

          <p class="my-5 mb-7 text-gray-800 fs-lg" style="max-width:48rem;">
            <?php if (checkForEquality($currentUserRole, 'student', 'strict')): ?>
              Your batch routine is shown below. If the sheet takes longer than expected to load, refresh the page or report the issue.
            <?php else: ?>
              Choose a batch to review its published routine. This is useful for faculty planning and admin oversight across active batches.
            <?php endif; ?>
          </p>

          <div class="my-5 mb-7 d-flex flex-wrap gap-3 align-items-center justify-content-center">
            <a class="btn btn-primary rounded-pill mx-2" href="./<?php echo escapeOutput(!is_null($selectedBatch) && !checkForEquality($currentUserRole, 'student', 'strict') ? '?batch=' . urlencode((string) $selectedBatch) : ''); ?>">
              Refresh the Page
            </a>
            <a class="btn btn-primary rounded-pill mx-2" href="../dashboard/">
              Dashboard
            </a>
            <a class="btn btn-danger rounded-pill mx-2" 
              href="https://wa.me/+919661430521?text=I%27m%20facing%20problems%20with%20the%20Routine%20Link.%20Please%20check%20for%20possible%20issues." target="_blank">
              Report an Issue
            </a>
          </div>

          <?php if (!checkForEquality($currentUserRole, 'student', 'strict') && checkForEquality($activeBatchList, [], 'strict')): ?>
            <div class="alert alert-light border text-center px-4 py-3 my-4" style="max-width:42rem;">
              <strong>No active batches are configured yet.</strong>
              Add batches from the admin panel to use the routine viewer here.
            </div>

          <?php elseif (!checkForEquality($currentUserRole, 'student', 'strict')): ?>
            <form method="GET" action="./" class="w-100 mb-5" style="max-width:34rem;">
              <div class="row g-3 align-items-end justify-content-center">
                <div class="col-12 col-md">
                  <label class="form-label fw-semibold">Select Batch</label>
                  <select class="form-select form-select-lg" name="batch" required>
                    <option value="" disabled <?php echo $selectedBatch === null ? 'selected' : ''; ?>>
                      Choose Batch for Routine Viewing
                    </option>
                    <?php foreach ($activeBatchList as $batch): ?>
                      <?php $normalizedBatch = normalizeBatchCode((string) $batch); ?>
                      <option value="<?php echo escapeOutput((string) $normalizedBatch); ?>" <?php echo checkForEquality($selectedBatch, $normalizedBatch, 'strict') ? 'selected' : ''; ?>>
                        <?php echo prettyPrintClassCode((string) $normalizedBatch); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-md-auto">
                  <button class="btn btn-primary rounded-pill" type="submit">
                    Load Routine
                  </button>
                </div>
              </div>
            </form>

          <?php endif; ?>

          <?php if (!checkForEquality($selectedBatch, null, 'strict') && !checkForEquality($selectedRoutineUrl, null, 'strict')): ?>
            <div class="d-flex justify-content-center w-100">
              <iframe class="my-5"
                      width="550"
                      height="750"
                      frameborder="0"
                      src="<?php echo escapeOutput($selectedRoutineUrl); ?>">
              </iframe>
            </div>
          <?php elseif (!checkForEquality($selectedBatch, null, 'strict')): ?>
            <div class="alert alert-light border text-center px-4 py-3 my-5" style="max-width:42rem;">
              <strong>Routine not available.</strong>
              The selected batch does not have a published routine link yet.
            </div>
          <?php elseif (!checkForEquality($currentUserRole, 'student', 'strict')): ?>
            <div class="alert alert-light border text-center px-4 py-3 my-5" style="max-width:42rem;">
              <strong>Select a batch to continue.</strong>
              Published routines for staff will appear here once a batch is chosen.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>

<?php require_once '../components/footer.php'; ?>