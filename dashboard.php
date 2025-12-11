<?php $pageTitle = 'SAS - Student Dashboard'; require_once __DIR__.'/db.php'; require_login('student'); verify_csrf();

$user = $_SESSION['user'];
$message = null; $error = null;

function clamp($v,$max){ $n=(int)($v??0); if($n<0)$n=0; if($n>$max)$n=$max; return $n; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $data = [
            'academic_gpa' => clamp($_POST['academic_gpa']??0,10),
            'academic_project' => clamp($_POST['academic_project']??0,10),
            'academic_certifications' => clamp($_POST['academic_certifications']??0,5),
            'academic_language' => clamp($_POST['academic_language']??0,5),
            'academic_attendance' => clamp($_POST['academic_attendance']??0,10),
            'cocurricular_events' => clamp($_POST['cocurricular_events']??0,15),
            'cocurricular_innovation' => clamp($_POST['cocurricular_innovation']??0,10),
            'cocurricular_membership' => clamp($_POST['cocurricular_membership']??0,5),
            'cocurricular_community' => clamp($_POST['cocurricular_community']??0,5),
            'cocurricular_competitive' => clamp($_POST['cocurricular_competitive']??0,5),
            'personality_leadership' => clamp($_POST['personality_leadership']??0,5),
            'personality_softskills' => clamp($_POST['personality_softskills']??0,5),
            'personality_feedback' => clamp($_POST['personality_feedback']??0,5),
            'personality_awards' => clamp($_POST['personality_awards']??0,5),
        ];
        $total = array_sum($data);
        $grade = $total>=90?'O':($total>=80?'A+':($total>=70?'A':($total>=60?'B+':($total>=50?'B':'C'))));
        $status = $_POST['submitForReview'] ? 'submitted' : 'draft';

        // Handle evidence file uploads (init vars; actual move handled below)
        $uploadDir = __DIR__.'/assets/uploads';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
        $allowed = ['pdf','jpg','jpeg','png','webp'];
        $maxSize = 5*1024*1024; // 5MB
        $newFiles = [];

        if (!empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            // Merge details JSON and append any new files
            $stmtD = $pdo->prepare('SELECT details FROM appraisals WHERE id=? AND student_id=?');
            $stmtD->execute([$id, $user['id']]);
            $existing = $stmtD->fetch();
            $details = $existing && $existing['details'] ? json_decode($existing['details'], true) : [];
            if (!is_array($details)) $details = [];
            // Re-run upload loop now that we know correct base path
            if (!empty($_FILES['evidence']) && is_array($_FILES['evidence']['name'])) {
                for ($i=0; $i<count($_FILES['evidence']['name']); $i++) {
                    $name = $_FILES['evidence']['name'][$i] ?? '';
                    $tmp = $_FILES['evidence']['tmp_name'][$i] ?? '';
                    $err = $_FILES['evidence']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                    $size = $_FILES['evidence']['size'][$i] ?? 0;
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if ($err===UPLOAD_ERR_OK && $name && in_array($ext,$allowed) && $size>0 && $size<=$maxSize) {
                        $safe = preg_replace('/[^a-zA-Z0-9._-]/','_', $name);
                        $rel = '/student-appraisal/assets/uploads/'.time().'_'.bin2hex(random_bytes(3)).'_'.$safe;
                        $abs = $_SERVER['DOCUMENT_ROOT'].$rel;
                        if (@move_uploaded_file($tmp, $abs)) { $newFiles[] = $rel; }
                    }
                }
            }
            if (!empty($newFiles)) { $details['files'] = array_values(array_unique(array_merge($details['files'] ?? [], $newFiles))); }
            $placeholders = implode(', ', array_map(fn($k)=>"$k=?", array_keys($data)));
            $stmt = $pdo->prepare("UPDATE appraisals SET $placeholders, total=?, grade=?, status=?, details=? WHERE id=? AND student_id=?");
            $stmt->execute([...array_values($data), $total, $grade, $status, $details?json_encode($details):null, $id, $user['id']]);
            $message = 'Appraisal updated';
        } else {
            // Handle uploads on create
            $details = [];
            if (!empty($_FILES['evidence']) && is_array($_FILES['evidence']['name'])) {
                for ($i=0; $i<count($_FILES['evidence']['name']); $i++) {
                    $name = $_FILES['evidence']['name'][$i] ?? '';
                    $tmp = $_FILES['evidence']['tmp_name'][$i] ?? '';
                    $err = $_FILES['evidence']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                    $size = $_FILES['evidence']['size'][$i] ?? 0;
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if ($err===UPLOAD_ERR_OK && $name && in_array($ext,$allowed) && $size>0 && $size<=$maxSize) {
                        $safe = preg_replace('/[^a-zA-Z0-9._-]/','_', $name);
                        $rel = '/student-appraisal/assets/uploads/'.time().'_'.bin2hex(random_bytes(3)).'_'.$safe;
                        $abs = $_SERVER['DOCUMENT_ROOT'].$rel;
                        if (@move_uploaded_file($tmp, $abs)) { $newFiles[] = $rel; }
                    }
                }
                if (!empty($newFiles)) $details['files'] = $newFiles;
            }
            $columns = 'student_id,'.implode(',', array_keys($data)).',total,grade,status,details';
            $placeholders = implode(',', array_fill(0, count($data) + 5, '?')); // 1 student + n fields + 4 extra
            $sql = "INSERT INTO appraisals($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user['id'], ...array_values($data), $total, $grade, $status, $details?json_encode($details):null]);
            $message = 'Appraisal saved';
        }
        // Audit log when submitted
        if ($status==='submitted') {
            $appraisalId = !empty($id) ? $id : (int)$pdo->lastInsertId();
            try {
                $stmt = $pdo->prepare('INSERT INTO audit_logs(appraisal_id, actor_id, action, remarks) VALUES(?,?,"submitted",?)');
                $stmt->execute([$appraisalId, $user['id'], 'Student submitted for review']);
            } catch (Throwable $ignore) { /* audit table may not exist yet; ignore */ }
        }
    }
    if ($action === 'delete' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare('DELETE FROM appraisals WHERE id=? AND student_id=? AND status IN ("draft","rejected")');
        $stmt->execute([(int)$_POST['id'], $user['id']]);
        $message = 'Deleted';
    }
}

$stmt = $pdo->prepare('SELECT * FROM appraisals WHERE student_id=? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$appraisals = $stmt->fetchAll();
?>
<?php include __DIR__.'/includes/header.php'; ?>
  <?php if($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
  <?php if($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">New / Edit Appraisal</div>
    <div class="card-body">
      <form method="post" id="appraisalForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="editId">

        <div class="row g-3">
          <div class="col-12"><h6>Academic Performance (40)</h6></div>
          <div class="col-md-4"><label class="form-label">Semester GPA / Result (10)</label><input type="number" max="10" min="0" step="1" class="form-control score" name="academic_gpa" required></div>
          <div class="col-md-4"><label class="form-label">Project / Internship (10)</label><input type="number" max="10" min="0" step="1" class="form-control score" name="academic_project" required></div>
          <div class="col-md-4"><label class="form-label">Online Certifications (5)</label><input type="number" max="5" min="0" step="1" class="form-control score" name="academic_certifications" required></div>
          <div class="col-md-4"><label class="form-label">Language / Communication (5)</label><input type="number" max="5" min="0" step="1" class="form-control score" name="academic_language" required></div>
          <div class="col-md-4"><label class="form-label">Attendance & Discipline (10)</label><input type="number" max="10" min="0" step="1" class="form-control score" name="academic_attendance" required></div>

          <div class="col-12 pt-2"><h6>Co/Extra Curricular (40)</h6></div>
          <div class="col-md-4"><label class="form-label">Events / Hackathons (15)</label><input type="number" max="15" min="0" step="1" class="form-control score" name="cocurricular_events" required></div>
          <div class="col-md-4"><label class="form-label">Product / Startup / Innovation (10)</label><input type="number" max="10" min="0" step="1" class="form-control score" name="cocurricular_innovation" required></div>
          <div class="col-md-4"><label class="form-label">Membership in Clubs / Bodies (5)</label><input type="number" max="5" min="0" step="1" class="form-control score" name="cocurricular_membership" required></div>
          <div class="col-md-4"><label class="form-label">Community / NSS / YRC (5)</label><input type="number" max="5" min="0" step="1" class="form-control score" name="cocurricular_community" required></div>
          <div class="col-md-4"><label class="form-label">Competitive Exams & Placement (5)</label><input type="number" max="5" min="0" step="1" class="form-control score" name="cocurricular_competitive" required></div>

          <div class="col-12 pt-2"><h6>Personality & Extension (20)</h6></div>
          <div class="col-md-4"><label class="form-label">Leadership / Teamwork (5)</label><input type="number" max="5" min="0" step="1" class="form-control score" name="personality_leadership" required></div>
          <div class="col-md-4"><label class="form-label">Soft Skills / Communication (5)</label><input type="number" max="5" min="0" step="1" class="form-control score" name="personality_softskills" required></div>
          <div class="col-md-4"><label class="form-label">Mentor Feedback (5)</label><input type="number" max="5" min="0" step="1" class="form-control score" name="personality_feedback" required></div>
          <div class="col-md-4"><label class="form-label">Awards / Recognition (5)</label><input type="number" max="5" min="0" step="1" class="form-control score" name="personality_awards" required></div>

          <div class="col-md-3 ms-auto">
            <label class="form-label">Total (100)</label>
            <input type="text" class="form-control" id="total" readonly value="0">
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label">Evidence (PDF/JPG/PNG, up to 5MB each)</label>
          <input type="file" class="form-control" name="evidence[]" multiple>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-primary">Save Draft</button>
          <button class="btn btn-success" onclick="document.getElementById('submitForReview').value='1'">Submit for Mentor</button>
          <input type="hidden" id="submitForReview" name="submitForReview" value="">
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">Score Breakdown</div>
    <div class="card-body">
      <canvas id="scoreChart" height="140"></canvas>
      <div class="text-muted small mt-2">This chart updates as you edit scores.</div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
      <span>My Appraisals</span>
      <button class="btn btn-sm btn-outline-success" id="exportExcel">Export Excel</button>
    </div>
    <div class="card-body table-responsive">
      <table class="table table-striped align-middle" id="myAppraisalsTable">
        <thead><tr>
          <th>ID</th><th>Total</th><th>Grade</th><th>Status</th><th>Mentor Remarks</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach($appraisals as $a): ?>
          <tr>
            <td><?php echo (int)$a['id']; ?></td>
            <td><?php echo (int)$a['total']; ?></td>
            <td><?php echo htmlspecialchars($a['grade']); ?></td>
            <td><span class="badge bg-<?php echo $a['status']==='approved'?'success':($a['status']==='submitted'?'warning text-dark':($a['status']==='rejected'?'danger':'secondary')); ?>"><?php echo htmlspecialchars($a['status']); ?></span></td>
            <td><?php echo htmlspecialchars($a['mentor_remarks'] ?? ''); ?></td>
            <td class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-primary" href="/student-appraisal/report.php?id=<?php echo (int)$a['id']; ?>" target="_blank">Report</a>
              <?php if(in_array($a['status'],['draft','rejected'])): ?>
                <button class="btn btn-sm btn-outline-secondary" onclick='prefill(<?php echo json_encode($a, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>Edit</button>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                  <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this draft?')">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include __DIR__.'/includes/footer.php'; ?>
