<?php $pageTitle = 'SAS - Appraisal Report'; require_once __DIR__.'/db.php'; require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT a.*, u.name, u.email, sp.roll_no, sp.program, sp.department, sp.year_of_study, sp.section, sp.phone, sp.dob, sp.address_line, sp.city, sp.state, sp.pincode, sp.guardian_name, sp.guardian_phone FROM appraisals a JOIN users u ON a.student_id=u.id LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE a.id=?');
$stmt->execute([$id]);
$app = $stmt->fetch();
if (!$app) { http_response_code(404); echo 'Not found'; exit; }
$canView = $_SESSION['user']['role'] !== 'student' || $_SESSION['user']['id'] === (int)$app['student_id'];
if (!$canView) { http_response_code(403); echo 'Forbidden'; exit; }
?>
<?php include __DIR__.'/includes/header.php'; ?>
<div class="py-2" id="reportRoot">
  <div class="d-flex justify-content-between align-items-center report-header pb-2 mb-3">
    <div>
      <h4 class="mb-0">Student Appraisal Report (2023–2024)</h4>
      <div class="text-muted small-text">M. Kumarasamy College of Engineering</div>
    </div>
    <div>
      <img src="/student-appraisal/assets/images/logo.png" alt="Logo" height="48" onerror="this.style.display='none'">
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-6"><strong>Student:</strong> <?php echo htmlspecialchars($app['name']); ?></div>
    <div class="col-md-6"><strong>Email:</strong> <?php echo htmlspecialchars($app['email']); ?></div>
    <div class="col-md-6"><strong>Status:</strong> <?php echo htmlspecialchars($app['status']); ?></div>
    <div class="col-md-6"><strong>Updated:</strong> <?php echo htmlspecialchars($app['updated_at']); ?></div>
  </div>

  <div class="card mb-3">
    <div class="card-header">Personal Details</div>
    <div class="card-body small-text">
      <div class="row">
        <div class="col-md-4"><strong>Roll No:</strong> <?php echo htmlspecialchars($app['roll_no'] ?? ''); ?></div>
        <div class="col-md-4"><strong>Program:</strong> <?php echo htmlspecialchars($app['program'] ?? ''); ?></div>
        <div class="col-md-4"><strong>Department:</strong> <?php echo htmlspecialchars($app['department'] ?? ''); ?></div>
        <div class="col-md-3"><strong>Year:</strong> <?php echo htmlspecialchars($app['year_of_study'] ?? ''); ?></div>
        <div class="col-md-3"><strong>Section:</strong> <?php echo htmlspecialchars($app['section'] ?? ''); ?></div>
        <div class="col-md-3"><strong>Phone:</strong> <?php echo htmlspecialchars($app['phone'] ?? ''); ?></div>
        <div class="col-md-3"><strong>DOB:</strong> <?php echo htmlspecialchars($app['dob'] ?? ''); ?></div>
        <div class="col-md-6"><strong>Address:</strong> <?php echo htmlspecialchars(trim(($app['address_line'] ?? '').' '.($app['city'] ?? '').' '.($app['state'] ?? '').' '.($app['pincode'] ?? ''))); ?></div>
        <div class="col-md-6"><strong>Guardian:</strong> <?php echo htmlspecialchars(trim(($app['guardian_name'] ?? '').' '.($app['guardian_phone'] ?? ''))); ?></div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">Scores</div>
    <div class="card-body">
      <div class="row small-text">
        <?php
        $fields = [
          'Academic Performance'=>[
            'academic_gpa'=>10,'academic_project'=>10,'academic_certifications'=>5,'academic_language'=>5,'academic_attendance'=>10
          ],
          'Co/Extra Curricular'=>[
            'cocurricular_events'=>15,'cocurricular_innovation'=>10,'cocurricular_membership'=>5,'cocurricular_community'=>5,'cocurricular_competitive'=>5
          ],
          'Personality & Extension'=>[
            'personality_leadership'=>5,'personality_softskills'=>5,'personality_feedback'=>5,'personality_awards'=>5
          ]
        ];
        foreach ($fields as $section=>$items): ?>
          <div class="col-12"><strong><?php echo $section; ?></strong></div>
          <?php foreach ($items as $k=>$max): ?>
            <div class="col-md-6"> <?php echo str_replace('_',' ',ucfirst($k)); ?>: <strong><?php echo (int)$app[$k]; ?></strong> / <?php echo $max; ?></div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
      <hr>
      <div class="d-flex justify-content-between">
        <div><strong>Total:</strong> <?php echo (int)$app['total']; ?> / 100</div>
        <div><strong>Grade:</strong> <?php echo htmlspecialchars($app['grade']); ?></div>
      </div>
      <?php if(!empty($app['mentor_remarks'])): ?>
      <div class="mt-2"><strong>Mentor Remarks:</strong> <?php echo htmlspecialchars($app['mentor_remarks']); ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="d-print-none">
    <button class="btn btn-outline-secondary" onclick="window.print()">Print</button>
    <button class="btn btn-primary" id="btnPdf">Download PDF</button>
    <a class="btn btn-success" href="/student-appraisal/index.php">Home</a>
  </div>
</div>
<script>
  document.getElementById('btnPdf').addEventListener('click', async () => {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p','pt','a4');
    await doc.html(document.getElementById('reportRoot'), { x: 20, y: 20, html2canvas: { scale: 0.8 } });
    doc.save('appraisal_<?php echo (int)$app['id']; ?>.pdf');
  });
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
