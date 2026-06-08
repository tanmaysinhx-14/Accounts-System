<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Enquiry Manager
  if(checkForEquality(checkLoginStatus($db1), true, 'strict')) {
    if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode'] ?? null), 'admin', 'strict')) {
      /* Get the Current Stage Parameter from the URL and cross-check it against the below array */
      $allowedStages = ['selectManagerTask', 'viewEnquiryDetails', 'submitEnquiryDetails', 'oldEnquiryDetails'];
      $stage = $_GET['stage'] ?? null;
      if (!in_array($stage, $allowedStages, true)) {
        redirectTo('./?stage=selectManagerTask', 0);
        exit;
      }
      $enquiryFormStatus = $stage; // Important to determine which Form to appear



      /* Stores the Form Data during redirects in the Enquiry List Table */
      $selectedType     = null;
      $selectedCategory = null;
      if (
        (isset($_POST['viewEnquiriesBtn']) || isset($_POST['updateEnquiryStatus']))
        && isset($_POST['enquiry_type'], $_POST['enquiry_category'])
      ) {
        $selectedType     = escapeOutput($_POST['enquiry_type']);
        $selectedCategory = escapeOutput($_POST['enquiry_category']);
      }
      elseif (isset($_GET['type'], $_GET['category'])) {
        $selectedType     = escapeOutput($_GET['type']);
        $selectedCategory = escapeOutput($_GET['category']);
      }



      // Enquiry Creator Form Logic
      if (isset($_POST['submitEnquiryDetails'])) {
        $enquiryType     = escapeOutput($_POST['enquiry_type']) ?? null;
        $enquiryCategory = escapeOutput($_POST['enquiry_category']) ?? null;
        $csrfToken       = escapeOutput($_POST['csrf_token']) ?? null;

        if(validateCsrfToken($csrfToken)) {
          $data = $_POST;
          unset(
            $data['csrf_token'],
            $data['submitEnquiryDetails']
          );

          $enquiryMetaData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

          $currentAttemptForUploadingEnquiryData = 0;
          $maxRetriesForUploadingEnquiryData = 3;
          while ($currentAttemptForUploadingEnquiryData < $maxRetriesForUploadingEnquiryData) {
            try {
              $STMT_uploadEnquiryData = "INSERT INTO enquiry_details (enquiry_type, enquiry_category, enquiry_metadata, enquiry_timestamp) 
                                        VALUES (:enquiry_type, :enquiry_category, :enquiry_metadata, :enquiry_timestamp)";

              $uploadEnquiryData = $db1->prepare($STMT_uploadEnquiryData);

              $uploadEnquiryData->bindValue(':enquiry_type', $enquiryType, PDO::PARAM_STR);
              $uploadEnquiryData->bindValue(':enquiry_category', $enquiryCategory, PDO::PARAM_STR);
              $uploadEnquiryData->bindValue(':enquiry_metadata', $enquiryMetaData, PDO::PARAM_STR);
              $uploadEnquiryData->bindValue(':enquiry_timestamp', getCurrentTimestamp(), PDO::PARAM_STR);

              if($uploadEnquiryData->execute()) {
                setToast('Enquiry Data saved successfully!', 'success', 7000);
                break;
              }
            }
            catch (PDOException $ex) {
              if(!isRetryablePdoException($ex)) {
                setToast('Error occured while Uploading Enquiry Data. Contact Admin', 'danger', 7000);

                logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while Uploading Enquiry Data: ' . $ex->getMessage());

                break;
              }

              $currentAttemptForUploadingEnquiryData++;
              sleep(5);
            } 
          }
          if($currentAttemptForUploadingEnquiryData >= $maxRetriesForUploadingEnquiryData) {
            setToast('Error occured while Uploading Enquiry Data. Contact Admin', 'danger', 7000);
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }

      $recordsPerPage = 10;
      $currentPage = isset($_GET['page']) && ctype_digit($_GET['page']) && $_GET['page'] > 0
        ? (int) $_GET['page']
        : 1;
      $offset = ($currentPage - 1) * $recordsPerPage;

      // Current Enquiry List Viewer Logic
      if ($selectedType && $selectedCategory) {
        $currentAttemptForFetchingEnquiryRecords = 0;
        $maxRetriesForFetchingEnquiryRecords = 3;

        $STMT_countRecords = "
          SELECT COUNT(*)
          FROM enquiry_details
          WHERE enquiry_type = :enquiry_type
            AND enquiry_category = :enquiry_category
            AND enquiry_has_been_previewed = 0
        ";

        $countStmt = $db1->prepare($STMT_countRecords);
        $countStmt->bindValue(':enquiry_type', $selectedType, PDO::PARAM_STR);
        $countStmt->bindValue(':enquiry_category', $selectedCategory, PDO::PARAM_STR);
        $countStmt->execute();

        $totalRecords = (int) $countStmt->fetchColumn();
        $totalPages   = (int) ceil($totalRecords / $recordsPerPage);

        while ($currentAttemptForFetchingEnquiryRecords < $maxRetriesForFetchingEnquiryRecords) {
          try {
            $STMT_fetchEnquiryRecords = "
              SELECT * FROM enquiry_details
              WHERE enquiry_type = :enquiry_type
                AND enquiry_category = :enquiry_category
                AND enquiry_has_been_previewed = 0
              ORDER BY enquiry_timestamp DESC
              LIMIT :limit OFFSET :offset

            ";

            $fetchEnquiryRecords = $db1->prepare($STMT_fetchEnquiryRecords);
            $fetchEnquiryRecords->bindValue(':enquiry_type', $selectedType, PDO::PARAM_STR);
            $fetchEnquiryRecords->bindValue(':enquiry_category', $selectedCategory, PDO::PARAM_STR);
            $fetchEnquiryRecords->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
            $fetchEnquiryRecords->bindValue(':offset', $offset, PDO::PARAM_INT);

            if ($fetchEnquiryRecords->execute()) {
              setToast('Enquiry Details fetched successfully.', 'success', 7000);

              $fetchedRecords = $fetchEnquiryRecords->fetchAll(PDO::FETCH_ASSOC);
              break;
            }
          }
          catch (PDOException $ex) {
            if (!isRetryablePdoException($ex)) {
              setToast('Error occured while Fetching Enquiry Details. Contact Admin.', 'danger', 7000);

              logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while Fetching Enquiry Records: ' . $ex->getMessage());

              break;
            }
            $currentAttemptForFetchingEnquiryRecords++;
            sleep(5);
          }
        }
        if($currentAttemptForFetchingEnquiryRecords >= $maxRetriesForFetchingEnquiryRecords) {
          setToast('Error occured while Fetching Enquiry Details. Contact Admin.', 'danger', 7000);
        }
      }

      // Action Button Logic which moves Current Enquiry List Records into Old Records 
      if (isset($_POST['updateEnquiryStatus'])) {
        $record_id = escapeOutput($_POST['record_id'] ?? '');

        $currentAttemptForUpdatingRecordStatus = 0;
        $maxRetriesForUpdatingRecordStatus = 3;

        while ($currentAttemptForUpdatingRecordStatus < $maxRetriesForUpdatingRecordStatus) {
          try {
            $STMT_updateEnquiryRecords = "UPDATE enquiry_details
                                          SET enquiry_has_been_previewed = :enquiry_has_been_previewed
                                          WHERE enquiry_id = :enquiry_id
                                          LIMIT 1";

            $updateEnquiryRecords = $db1->prepare($STMT_updateEnquiryRecords);

            $updateEnquiryRecords->bindValue(':enquiry_has_been_previewed', 1, PDO::PARAM_INT);
            $updateEnquiryRecords->bindValue(':enquiry_id', $record_id, PDO::PARAM_INT);
            
            $autoScrollRow = (int)$record_id - 1;

            if($updateEnquiryRecords->execute()) {
              setToast('Enquiry Details updated successfully.', 'success', 7000);

              redirectTo("./?stage=viewEnquiryDetails&type={$selectedType}&category={$selectedCategory}&page={$currentPage}", 0);

              break;
            }
          }
          catch (PDOException $ex) {
            if(!isRetryablePdoException($ex)) {
              setToast('Error occured while Updating Enquiry Details. Contact Admin.', 'danger', 7000);

              logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while Updating Enquiry Record Status: ' . $ex->getMessage());

              break;
            }

            $currentAttemptForUpdatingRecordStatus++;
            sleep(5);
          }
        }
        if($currentAttemptForUpdatingRecordStatus >= $maxRetriesForUpdatingRecordStatus) {
          setToast('Error occured while Updating Enquiry Details. Contact Admin.', 'danger', 7000);
        }
      }
    }
  }
?>

<?php // Headers 
  $page_title = "View Users List | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
  
  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';
  
  require_once '../components/breadcrumb.php';

  if(checkForEquality(getUserRoleUsingUserCode($_SESSION['usercode']), 'admin', 'strict')) {
    if(checkForEquality($enquiryFormStatus, 'submitEnquiryDetails', 'strict')) {
      $breadcrumb_url_2 = './?stage=selectManagerTask';
      $breadcrumb_title_2 = 'Enquiry Manager';
    }
    elseif (checkForEquality($enquiryFormStatus, 'viewEnquiryDetails', 'strict')) {
      $breadcrumb_url_2 = './?stage=selectManagerTask';
      $breadcrumb_title_2 = 'Enquiry Manager';
    }
    elseif (checkForEquality($enquiryFormStatus, 'oldEnquiryDetails', 'strict')) {
      $breadcrumb_url_2 = './?stage=selectManagerTask';
      $breadcrumb_title_2 = 'Enquiry Manager';
    }

    if(checkForEquality($enquiryFormStatus, 'selectManagerTask', 'strict')) {
      $breadcrumb_url_active = './?stage=selectManagerTask';
      $breadcrumb_title_active = 'Enquiry Manager';
    }
    elseif(checkForEquality($enquiryFormStatus, 'submitEnquiryDetails', 'strict')) {
      $breadcrumb_url_active = './?stage=submitEnquiryDetails';
      $breadcrumb_title_active = 'Entering Enquiry Details';
    }
    elseif (checkForEquality($enquiryFormStatus, 'viewEnquiryDetails', 'strict')) {
      $breadcrumb_url_active = './?stage=viewEnquiryDetails';
      $breadcrumb_title_active = 'Viewing Enquiry Details';
    }
    elseif (checkForEquality($enquiryFormStatus, 'oldEnquiryDetails', 'strict')) {
      $breadcrumb_url_active = './?stage=oldEnquiryDetails';
      $breadcrumb_title_active = 'Past Enquiry Details';
    }
  }
?>

<?php if (checkForEquality(checkLoginStatus($db1), true, 'strict')): // User Logged In ?>
  <?php if(checkForEquality(getUserRoleUsingUserCode($_SESSION['usercode']), 'admin', 'strict')): // For Admins ?>
    <?php if(checkForEquality($enquiryFormStatus, 'selectManagerTask', 'strict')): ?>
      <!-- MAIN MENU OF ENQUIRY MANAGER -->
      <section class="section-border border-primary min-vh-100">
        <div class="container d-flex flex-column">
          <div class="row justify-content-center align-items-center gx-0">
            <div class="col-12 col-lg-9 col-md-10 py-8 py-md-8">
              <p class="mb-7 display-4 fw-bold text-center">Select Enquiry Manager Task</p>

              <div class="d-flex flex-column justify-content-center align-items-center">
                <a class="col-auto btn btn-primary btn-lg mb-7 w-50" 
                  href="./?stage=submitEnquiryDetails">Submit Enquiry Details</a>
                <a class="col-auto btn btn-primary btn-lg mb-7 w-50" 
                  href="./?stage=viewEnquiryDetails">View Enquiry Details</a>
                <a class="col-auto btn btn-primary btn-lg mb-7 w-50" 
                  href="./?stage=oldEnquiryDetails">Past Enquiry Details</a>
              </div>
              
            </div>
          </div>
        </div>
      </section>

      <!-- ENQUIRY REQUEST FORM -->
      <section class="section-border border-primary min-vh-100">
        <div class="container d-flex flex-column">
          <div class="row justify-content-center gx-0">
            <div class="col-12 col-lg-9 col-md-10 py-8 py-md-8">
              <h1 class="mb-0 fw-bold text-center">
                Submit Enquiry Request
              </h1>
              <p class="lead mb-7 text-center text-body-secondary">
                Add Enquiry Requests.
              </p>
              <div class="d-flex row justify-content-center mb-10">
                <div class="col-auto form-group mb-7">
                  <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                      Choose Enquiry Role
                    </button>
                    <div class="dropdown-menu">
                      <a class="dropdown-item" href="#" data-target="role" data-value="student">Enquiry for Student</a>
                      <a class="dropdown-item" href="#" data-target="role" data-value="faculty">Enquiry for Faculty</a>
                    </div>
                  </div>
                </div>

                <div class="col-auto form-group mb-7">
                  <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                      Choose Enquiry Category
                    </button>
                    <div class="dropdown-menu">
                      <a class="dropdown-item" href="#" data-target="category" data-value="institute">Enquiry for Institute</a>
                      <a class="dropdown-item" href="#" data-target="category" data-value="tuition">Enquiry for Home Tuition</a>
                    </div>
                  </div>
                </div>
              </div>

              <form id="enquiryFormCard"
                    class="card card-border card-border-xl border-primary shadow-lg mb-6 px-8 px-md-8 py-8 py-md-8 d-none"
                    method="POST"
                    action="./?stage=submitEnquiryDetails">

                <input type="hidden" name="enquiry_type" id="enquiryType">
                <input type="hidden" name="enquiry_category" id="enquiryCategory">

                <!-- STUDENT + INSTITUTE -->
                <div id="student-institute" class="d-none">
                  <p class="mb-7 display-4 fw-bold text-center">
                    Student – Institute Enquiry
                  </p>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="student_name"
                          placeholder="Student Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the student's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="guardian_name"
                          placeholder="Guardian Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the student's guardian's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="batch_details"
                          placeholder="Batch (e.g., Grade 5 CBSE or V CBSE)"
                          required>
                    <div class="form-text">
                      Enter the student's batch details.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="subjects_interested"
                          placeholder="Subjects (e.g., Mathematics, Science, English)"
                          required>
                    <div class="form-text">
                      Specify the subjects the student is interested in.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="student_address"
                          placeholder="Student Address"
                          autocomplete="street-address"
                          required>
                    <div class="form-text">
                      Enter the student's full residential address.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="tel"
                          class="form-control mb-3"
                          name="student_mobile_number"
                          placeholder="Mobile Number"
                          inputmode="numeric"
                          pattern="[0-9]{10}"
                          maxlength="10"
                          autocomplete="tel"
                          required>
                    <div class="form-text">
                      Enter the student's contact number.
                    </div>
                  </div>
                </div>

                <!-- STUDENT + TUITION -->
                <div id="student-tuition" class="d-none">
                  <p class="mb-7 display-4 fw-bold text-center">
                    Student – Tuition Enquiry
                  </p>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="student_name"
                          placeholder="Student Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the student's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="guardian_name"
                          placeholder="Guardian Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the student's guardian's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="batch_details"
                          placeholder="Batch (e.g., Grade 5 CBSE or V CBSE)"
                          required>
                    <div class="form-text">
                      Enter the student's batch details.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="subjects_interested"
                          placeholder="Subjects (e.g., Mathematics, Science, English)"
                          required>
                    <div class="form-text">
                      Specify the subjects the student is interested in.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="preferred_timing_slots"
                          placeholder="Time Slots (e.g., 6–8 p.m. or 7–9 a.m.)"
                          required>
                    <div class="form-text">
                      Enter the preferred tuition time slots.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="tuition_fee_range"
                          placeholder="Tuition Fee Range (e.g., ₹3,000–₹10,000)"
                          required>
                    <div class="form-text">
                      Enter the preferred tuition fee range.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="teacher_gender_preference"
                          placeholder="Teacher Gender Preference (e.g., Male, Female)"
                          required>
                    <div class="form-text">
                      Specify the preferred gender of the teacher.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="student_address"
                          placeholder="Student Address"
                          autocomplete="street-address"
                          required>
                    <div class="form-text">
                      Enter the student's full residential address.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="tel"
                          class="form-control mb-3"
                          name="student_mobile_number"
                          placeholder="Mobile Number"
                          inputmode="numeric"
                          pattern="[0-9]{10}"
                          maxlength="10"
                          autocomplete="tel"
                          required>
                    <div class="form-text">
                      Enter the student's contact number.
                    </div>
                  </div>
                </div>

                <!-- FACULTY + INSTITUTE -->
                <div id="faculty-institute" class="d-none">
                  <p class="mb-7 display-4 fw-bold text-center">
                    Faculty – Tuition Enquiry
                  </p>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="faculty_name"
                          placeholder="Faculty Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the faculty member's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="faculty_last_qualification"
                          placeholder="Highest Qualification"
                          required>
                    <div class="form-text">
                      Enter the faculty member's highest qualification.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="number"
                          class="form-control mb-3"
                          name="faculty_experience"
                          placeholder="Experience (Years)"
                          min="0"
                          required>
                    <div class="form-text">
                      Enter the faculty member's teaching experience in years.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="classes_interested"
                          placeholder="Classes (e.g., Grade 6–8)"
                          required>
                    <div class="form-text">
                      Specify the classes the faculty member wishes to teach.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="subjects_interested"
                          placeholder="Subjects (e.g., Mathematics, Science)"
                          required>
                    <div class="form-text">
                      Specify the subjects the faculty member wishes to teach.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="preferred_timing_slots"
                          placeholder="Time Slots (e.g., 6–8 p.m. or 7–9 a.m.)"
                          required>
                    <div class="form-text">
                      Enter the preferred teaching time slots.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="number"
                          class="form-control mb-3"
                          name="minimum_tuition_fee"
                          placeholder="Minimum Fee (₹)"
                          min="0"
                          required>
                    <div class="form-text">
                      Enter the minimum tuition fee expected.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="teacher_gender"
                          placeholder="Gender (e.g., Male, Female)"
                          required>
                    <div class="form-text">
                      Enter the faculty member's gender.
                    </div>
                  </div>
                </div>

                <!-- FACULTY + TUITION -->
                <div id="faculty-tuition" class="d-none">
                  <p class="mb-7 display-4 fw-bold text-center">
                    Faculty – Tuition Enquiry
                  </p>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="faculty_name"
                          placeholder="Faculty Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the faculty member's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="faculty_last_qualification"
                          placeholder="Highest Qualification"
                          required>
                    <div class="form-text">
                      Enter the faculty member's highest qualification.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="number"
                          class="form-control mb-3"
                          name="faculty_experience"
                          placeholder="Experience (Years)"
                          min="0"
                          required>
                    <div class="form-text">
                      Enter the faculty member's teaching experience in years.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="classes_interested"
                          placeholder="Classes (e.g., Grade 6–8)"
                          required>
                    <div class="form-text">
                      Specify the classes the faculty member wishes to teach.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="subjects_interested"
                          placeholder="Subjects (e.g., Mathematics, Science)"
                          required>
                    <div class="form-text">
                      Specify the subjects the faculty member wishes to teach.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="preferred_timing_slots"
                          placeholder="Time Slots (e.g., 6–8 p.m. or 7–9 a.m.)"
                          required>
                    <div class="form-text">
                      Enter the preferred teaching time slots.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="number"
                          class="form-control mb-3"
                          name="minimum_tuition_fee"
                          placeholder="Minimum Fee (₹)"
                          min="0"
                          required>
                    <div class="form-text">
                      Enter the minimum tuition fee expected.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="teacher_gender"
                          placeholder="Gender (e.g., Male, Female)"
                          required>
                    <div class="form-text">
                      Enter the faculty member's gender.
                    </div>
                  </div>
                </div>

                <input type="hidden" 
                      name="csrf_token" 
                      value="<?php echo htmlspecialchars(generateCsrfToken()); ?>"
                />

                <button class="btn btn-primary btn-lg rounded-pill mt-7"
                        type="submit"
                        name="submitEnquiryDetails">
                  Submit Enquiry Details
                </button>
              </form>
            </div>
          </div>
        </div>
      </section>

      <script type="text/javascript">
        const enquiryTypeInput = document.getElementById('enquiryType');
        const enquiryCategoryInput = document.getElementById('enquiryCategory');

        const enquiryFormCard = document.getElementById('enquiryFormCard');

        const allForms = document.querySelectorAll(
          '#student-institute, #student-tuition, #faculty-institute, #faculty-tuition'
        );

        function updateFormVisibility() {
          const role = enquiryTypeInput.value;
          const category = enquiryCategoryInput.value;

          let targetForm = null;

          // Hide everything and disable all inputs
          enquiryFormCard.classList.add('d-none');
          allForms.forEach(form => {
            form.classList.add('d-none');
            form.querySelectorAll('input').forEach(input => input.disabled = true);
          });

          // Show only when BOTH values exist
          if (role && category) {
            const formId = `${role}-${category}`;
            targetForm = document.getElementById(formId);

            if (targetForm) {
              enquiryFormCard.classList.remove('d-none');
              targetForm.classList.remove('d-none');
              targetForm.querySelectorAll('input').forEach(input => input.disabled = false);
            }
          }
        }

        document.querySelectorAll('.dropdown-item').forEach(item => {
          item.addEventListener('click', function (e) {
            e.preventDefault();

            const target = this.dataset.target;
            const value = this.dataset.value;

            const button = this.closest('.dropdown')
                              .querySelector('.dropdown-toggle');
            button.textContent = this.textContent;

            if (target === 'role') {
              enquiryTypeInput.value = value;
            }

            if (target === 'category') {
              enquiryCategoryInput.value = value;
            }

            updateFormVisibility();
          });
        });
      </script>

    <?php elseif(checkForEquality($enquiryFormStatus, 'submitEnquiryDetails', 'strict')): ?>
      <!-- ENQUIRY REQUEST FORM -->
      <section class="section-border border-primary min-vh-100">
        <div class="container d-flex flex-column">
          <div class="row justify-content-center gx-0">
            <div class="col-12 col-lg-9 col-md-10 py-8 py-md-8">
              <h1 class="mb-0 fw-bold text-center">
                Submit Enquiry Request
              </h1>
              <p class="lead mb-7 text-center text-body-secondary">
                Add Enquiry Requests.
              </p>
              <div class="d-flex row justify-content-center mb-10">
                <div class="col-auto form-group mb-7">
                  <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                      Choose Enquiry Role
                    </button>
                    <div class="dropdown-menu">
                      <a class="dropdown-item" href="#" data-target="role" data-value="student">Enquiry for Student</a>
                      <a class="dropdown-item" href="#" data-target="role" data-value="faculty">Enquiry for Faculty</a>
                    </div>
                  </div>
                </div>

                <div class="col-auto form-group mb-7">
                  <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                      Choose Enquiry Category
                    </button>
                    <div class="dropdown-menu">
                      <a class="dropdown-item" href="#" data-target="category" data-value="institute">Enquiry for Institute</a>
                      <a class="dropdown-item" href="#" data-target="category" data-value="tuition">Enquiry for Home Tuition</a>
                    </div>
                  </div>
                </div>
              </div>

              <form id="enquiryFormCard"
                    class="card card-border card-border-xl border-primary shadow-lg mb-6 px-8 px-md-8 py-8 py-md-8 d-none"
                    method="POST"
                    action="./?stage=submitEnquiryDetails">

                <input type="hidden" name="enquiry_type" id="enquiryType">
                <input type="hidden" name="enquiry_category" id="enquiryCategory">

                <!-- STUDENT + INSTITUTE -->
                <div id="student-institute" class="d-none">
                  <p class="mb-7 display-4 fw-bold text-center">
                    Student – Institute Enquiry
                  </p>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="student_name"
                          placeholder="Student Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the student's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="guardian_name"
                          placeholder="Guardian Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the student's guardian's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="batch_details"
                          placeholder="Batch (e.g., Grade 5 CBSE or V CBSE)"
                          required>
                    <div class="form-text">
                      Enter the student's batch details.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="subjects_interested"
                          placeholder="Subjects (e.g., Mathematics, Science, English)"
                          required>
                    <div class="form-text">
                      Specify the subjects the student is interested in.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="student_address"
                          placeholder="Student Address"
                          autocomplete="street-address"
                          required>
                    <div class="form-text">
                      Enter the student's full residential address.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="tel"
                          class="form-control mb-3"
                          name="student_mobile_number"
                          placeholder="Mobile Number"
                          inputmode="numeric"
                          pattern="[0-9]{10}"
                          maxlength="10"
                          autocomplete="tel"
                          required>
                    <div class="form-text">
                      Enter the student's contact number.
                    </div>
                  </div>
                </div>

                <!-- STUDENT + TUITION -->
                <div id="student-tuition" class="d-none">
                  <p class="mb-7 display-4 fw-bold text-center">
                    Student – Tuition Enquiry
                  </p>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="student_name"
                          placeholder="Student Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the student's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="guardian_name"
                          placeholder="Guardian Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the student's guardian's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="batch_details"
                          placeholder="Batch (e.g., Grade 5 CBSE or V CBSE)"
                          required>
                    <div class="form-text">
                      Enter the student's batch details.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="subjects_interested"
                          placeholder="Subjects (e.g., Mathematics, Science, English)"
                          required>
                    <div class="form-text">
                      Specify the subjects the student is interested in.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="preferred_timing_slots"
                          placeholder="Time Slots (e.g., 6–8 p.m. or 7–9 a.m.)"
                          required>
                    <div class="form-text">
                      Enter the preferred tuition time slots.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="tuition_fee_range"
                          placeholder="Tuition Fee Range (e.g., ₹3,000–₹10,000)"
                          required>
                    <div class="form-text">
                      Enter the preferred tuition fee range.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="teacher_gender_preference"
                          placeholder="Teacher Gender Preference (e.g., Male, Female)"
                          required>
                    <div class="form-text">
                      Specify the preferred gender of the teacher.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="student_address"
                          placeholder="Student Address"
                          autocomplete="street-address"
                          required>
                    <div class="form-text">
                      Enter the student's full residential address.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="tel"
                          class="form-control mb-3"
                          name="student_mobile_number"
                          placeholder="Mobile Number"
                          inputmode="numeric"
                          pattern="[0-9]{10}"
                          maxlength="10"
                          autocomplete="tel"
                          required>
                    <div class="form-text">
                      Enter the student's contact number.
                    </div>
                  </div>
                </div>

                <!-- FACULTY + INSTITUTE -->
                <div id="faculty-institute" class="d-none">
                  <p class="mb-7 display-4 fw-bold text-center">
                    Faculty – Tuition Enquiry
                  </p>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="faculty_name"
                          placeholder="Faculty Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the faculty member's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="faculty_last_qualification"
                          placeholder="Highest Qualification"
                          required>
                    <div class="form-text">
                      Enter the faculty member's highest qualification.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="number"
                          class="form-control mb-3"
                          name="faculty_experience"
                          placeholder="Experience (Years)"
                          min="0"
                          required>
                    <div class="form-text">
                      Enter the faculty member's teaching experience in years.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="classes_interested"
                          placeholder="Classes (e.g., Grade 6–8)"
                          required>
                    <div class="form-text">
                      Specify the classes the faculty member wishes to teach.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="subjects_interested"
                          placeholder="Subjects (e.g., Mathematics, Science)"
                          required>
                    <div class="form-text">
                      Specify the subjects the faculty member wishes to teach.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="preferred_timing_slots"
                          placeholder="Time Slots (e.g., 6–8 p.m. or 7–9 a.m.)"
                          required>
                    <div class="form-text">
                      Enter the preferred teaching time slots.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="number"
                          class="form-control mb-3"
                          name="minimum_tuition_fee"
                          placeholder="Minimum Fee (₹)"
                          min="0"
                          required>
                    <div class="form-text">
                      Enter the minimum tuition fee expected.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="teacher_gender"
                          placeholder="Gender (e.g., Male, Female)"
                          required>
                    <div class="form-text">
                      Enter the faculty member's gender.
                    </div>
                  </div>
                </div>

                <!-- FACULTY + TUITION -->
                <div id="faculty-tuition" class="d-none">
                  <p class="mb-7 display-4 fw-bold text-center">
                    Faculty – Tuition Enquiry
                  </p>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="faculty_name"
                          placeholder="Faculty Full Name"
                          autocomplete="name"
                          required>
                    <div class="form-text">
                      Enter the faculty member's full name.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="faculty_last_qualification"
                          placeholder="Highest Qualification"
                          required>
                    <div class="form-text">
                      Enter the faculty member's highest qualification.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="number"
                          class="form-control mb-3"
                          name="faculty_experience"
                          placeholder="Experience (Years)"
                          min="0"
                          required>
                    <div class="form-text">
                      Enter the faculty member's teaching experience in years.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="classes_interested"
                          placeholder="Classes (e.g., Grade 6–8)"
                          required>
                    <div class="form-text">
                      Specify the classes the faculty member wishes to teach.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="subjects_interested"
                          placeholder="Subjects (e.g., Mathematics, Science)"
                          required>
                    <div class="form-text">
                      Specify the subjects the faculty member wishes to teach.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="preferred_timing_slots"
                          placeholder="Time Slots (e.g., 6–8 p.m. or 7–9 a.m.)"
                          required>
                    <div class="form-text">
                      Enter the preferred teaching time slots.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="number"
                          class="form-control mb-3"
                          name="minimum_tuition_fee"
                          placeholder="Minimum Fee (₹)"
                          min="0"
                          required>
                    <div class="form-text">
                      Enter the minimum tuition fee expected.
                    </div>
                  </div>

                  <div class="form-group mb-5">
                    <input type="text"
                          class="form-control mb-3"
                          name="teacher_gender"
                          placeholder="Gender (e.g., Male, Female)"
                          required>
                    <div class="form-text">
                      Enter the faculty member's gender.
                    </div>
                  </div>
                </div>

                <input type="hidden" 
                      name="csrf_token" 
                      value="<?php echo htmlspecialchars(generateCsrfToken()); ?>"
                />

                <button class="btn btn-primary btn-lg rounded-pill mt-7"
                        type="submit"
                        name="submitEnquiryDetails">
                  Submit Enquiry Details
                </button>
              </form>
            </div>
          </div>
        </div>
      </section>

    <?php elseif(checkForEquality($enquiryFormStatus, 'viewEnquiryDetails', 'strict')): ?>
      <!-- ENQUIRY VIEWING FORM -->
      <section class="section-border border-primary min-vh-100">
        <div class="container-xxl d-flex flex-column">
          <div class="row justify-content-center gx-0">
            <div class="col-12 py-8 px-8 py-md-8">

              <form method="POST" action="./?stage=viewEnquiryDetails" class="mb-8 px-lg-10 <?php echo ($selectedType && $selectedCategory) ? 'd-none' : 'd-block'; ?>">
                <div class="d-flex row justify-content-center mb-10">
                  <div class="col form-group mb-7">
                    <label class="form-label fw-semibold">Enquiry Role</label>
                    <select class="form-select form-select-lg"
                            name="enquiry_type"
                            required>
                      <option value="" selected disabled>Choose Enquiry Role</option>
                      <option value="student" <?= $selectedType === 'student' ? 'selected' : '' ?>>Enquiry for Students</option>
                      <option value="faculty" <?= $selectedType === 'faculty' ? 'selected' : '' ?>>Enquiry for Faculty</option>
                    </select>
                  </div>

                  <div class="col form-group mb-7">
                    <label class="form-label fw-semibold">Enquiry Category</label>
                    <select class="form-select form-select-lg"
                            name="enquiry_category"
                            required>
                      <option value="" selected disabled>Choose Enquiry Category</option>
                      <option value="institute" <?= $selectedCategory === 'institute' ? 'selected' : '' ?>>Enquiry for Institute</option>
                      <option value="tuition" <?= $selectedCategory === 'tuition' ? 'selected' : '' ?>>Enquiry for Tuition</option>
                    </select>
                  </div>
                </div>
                <button class="btn btn-primary btn-lg rounded-pill" 
                        type="submit" 
                        name="viewEnquiriesBtn">View Enquiry Details</button>
              </form>

              <?php if (!empty($fetchedRecords)): ?>
                <?php
                  $tableHeaders = [];

                  foreach ($fetchedRecords as $record) {
                    $meta = json_decode($record['enquiry_metadata'], true);
                    if (is_array($meta)) {
                      $tableHeaders = array_unique(
                        array_merge($tableHeaders, array_keys($meta))
                      );
                    }
                  }

                  $tableHeaders = array_diff(
                    $tableHeaders,
                    ['enquiry_type', 'enquiry_category']
                  );
                ?>

                <div class="mt-10 table-responsive">
                  <p class="mb-10 display-4 fw-bold text-center">Saved Enquiry Records</p>
                  <table class="table table-bordered table-striped table-sm align-middle">
                    <thead class="table-primary text-center">
                      <tr>
                        <th class="p-1">S. No.</th>
                        <th class="p-1">Submitted On</th>
                        <?php foreach ($tableHeaders as $header): ?>
                          <th class="p-1">
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $header))); ?>
                          </th>
                        <?php endforeach; ?>
                        <th class="p-1">Action</th>
                      </tr>
                    </thead>

                    <tbody>
                      <?php foreach ($fetchedRecords as $index => $record): ?>
                        <?php $meta = json_decode($record['enquiry_metadata'], true) ?? []; ?>
                        <tr id="<?php echo $record['enquiry_id'] ?? null; ?>">
                          <td class="text-center p-1">
                            <?php echo (($currentPage - 1) * $recordsPerPage) + $index + 1; ?>
                          </td>
                          <td class="p-1">
                            <?php echo htmlspecialchars($record['enquiry_timestamp'] ?? 'N/A'); ?>
                          </td>
                          <?php foreach ($tableHeaders as $key): ?>
                            <td class="p-1">
                              <?php echo htmlspecialchars($meta[$key] ?? '—'); ?>
                            </td>
                          <?php endforeach; ?>
                          <td class="text-center p-1">
                            <form method="POST" action="./?stage=viewEnquiryDetails" class="d-inline">
                              <input type="hidden" name="record_id"
                                    value="<?php echo (int) $record['enquiry_id']; ?>">

                              <input type="hidden" name="enquiry_type"
                                    value="<?php echo htmlspecialchars($selectedType); ?>">

                              <input type="hidden" name="enquiry_category"
                                    value="<?php echo htmlspecialchars($selectedCategory); ?>">

                              <button type="submit"
                                      name="updateEnquiryStatus"
                                      class="btn btn-xs btn-warning px-2 py-0">
                                Move
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>

                  <?php if ($totalPages > 1): ?>
                    <nav class="mt-6">
                      <ul class="pagination justify-content-center">

                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                          <a class="page-link"
                            href="?stage=viewEnquiryDetails&type=<?= urlencode($selectedType) ?>&category=<?= urlencode($selectedCategory) ?>&page=<?= $currentPage - 1 ?>">
                            Previous
                          </a>
                        </li>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                          <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                            <a class="page-link"
                              href="?stage=viewEnquiryDetails&type=<?= urlencode($selectedType) ?>&category=<?= urlencode($selectedCategory) ?>&page=<?= $i ?>">
                              <?= $i ?>
                            </a>
                          </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                          <a class="page-link"
                            href="?stage=viewEnquiryDetails&type=<?= urlencode($selectedType) ?>&category=<?= urlencode($selectedCategory) ?>&page=<?= $currentPage + 1 ?>">
                            Next
                          </a>
                        </li>

                      </ul>
                    </nav>
                    <?php endif; ?>

                </div>

              <?php elseif (empty($fetchedRecords)): ?>
                <p class="text-center text-muted mt-8">
                  No enquiries found for the selected filters.
                </p>

              <?php endif; ?>

            </div>
          </div>
        </div>
      </section>

    <?php elseif(checkForEquality($enquiryFormStatus, 'oldEnquiryDetails', 'strict')): ?>
      <!-- OLD ENQUIRY VIEWING FORM -->
      <section class="section-border border-primary min-vh-100">
        <div class="container-xxl d-flex flex-column">
          <div class="row justify-content-center gx-0">
            <div class="col-12 py-8 px-8 py-md-8">
              <?php 
                $currentAttemptForFetchingOldEnquiryRecords = 0;
                $maxRetriesForFetchingOldEnquiryRecords = 3;

                while ($currentAttemptForFetchingOldEnquiryRecords < $maxRetriesForFetchingOldEnquiryRecords) {
                  try {
                    $STMT_fetchOldEnquiryRecords = "SELECT * FROM enquiry_details
                                                    WHERE enquiry_has_been_previewed = 1";

                    $fetchOldEnquiryRecords = $db1->prepare($STMT_fetchOldEnquiryRecords);

                    if ($fetchOldEnquiryRecords->execute()) {
                      $fetchedOldRecords = $fetchOldEnquiryRecords->fetchAll(PDO::FETCH_ASSOC);
                      break;
                    }
                  }
                  catch (PDOException $ex) {
                    if (!isRetryablePdoException($ex)) {
                      setToast('Error occured while Fetching Old Enquiry Records. Contact Admin.', 'danger', 7000);

                      logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while Fetching Old Enquiry Records: ' . $ex->getMessage());

                      break;
                    }

                    $currentAttemptForFetchingOldEnquiryRecords++;
                    sleep(5);
                  }
                }
              ?>
              <?php if (!empty($fetchedOldRecords)): ?>
                <?php
                  $tableHeaders = [];

                  foreach ($fetchedOldRecords as $record) {
                    $meta = json_decode($record['enquiry_metadata'], true);
                    if (is_array($meta)) {
                      $tableHeaders = array_unique(
                        array_merge($tableHeaders, array_keys($meta))
                      );
                    }
                  }

                  $tableHeaders = array_diff($tableHeaders, ['enquiry_type', 'enquiry_category']);
                ?>

                <div class="mt-10 table-responsive">
                  <p class="mb-10 display-4 fw-bold text-center">Old Enquiry Records</p>
                  <table class="table table-bordered table-striped table-sm align-middle">
                    <thead class="table-primary text-center">
                      <tr>
                        <th class="p-1">S. No.</th>
                        <th class="p-1">Submitted On</th>
                        <?php foreach ($tableHeaders as $header): ?>
                          <th class="p-1">
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $header))); ?>
                          </th>
                        <?php endforeach; ?>
                      </tr>
                    </thead>

                    <tbody>
                      <?php foreach ($fetchedOldRecords as $index => $record): ?>
                        <?php $meta = json_decode($record['enquiry_metadata'], true) ?? []; ?>
                        <tr>
                          <td class="text-center p-1"><?php echo $index + 1; ?></td>
                          <td class="p-1">
                            <?php echo htmlspecialchars($record['enquiry_timestamp'] ?? 'N/A'); ?>
                          </td>
                          <?php foreach ($tableHeaders as $key): ?>
                            <td class="p-1">
                              <?php echo htmlspecialchars($meta[$key] ?? '—'); ?>
                            </td>
                          <?php endforeach; ?>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

              <?php elseif (empty($fetchedOldRecords)): ?>
                <p class="text-center text-muted mt-8">
                  No enquiries found for the selected filters.
                </p>

              <?php endif; ?>

            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>
  
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