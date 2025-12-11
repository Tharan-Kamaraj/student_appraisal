<?php $pageTitle = 'SAS - Mentor Panel'; require_once __DIR__.'/db.php'; require_login(); verify_csrf();
if (!in_array($_SESSION['user']['role'], ['mentor','admin'])) { header('Location: /student-appraisal/index.php'); exit; }
$user = $_SESSION['user'];

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $decision = $_POST['decision'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    if (in_array($decision,['approved','rejected'])) {
        $stmt = $pdo->prepare('UPDATE appraisals SET status=?, mentor_remarks=? WHERE id=?');
        $stmt->execute([$decision, $remarks, $id]);
        set_flash('success','Updated.');
    }
}

// Fetch submissions
$filter = $_GET['filter'] ?? 'submitted';
if (!in_array($filter,['submitted','approved','rejected','all'])) $filter='submitted';
$sql = 'SELECT a.*, u.name as student_name, u.email as student_email FROM appraisals a JOIN users u ON a.student_id=u.id';
$sql .= $filter==='all' ? '' : ' WHERE a.status=?';
$sql .= ' ORDER BY a.updated_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($filter==='all'?[]:[$filter]);
$list = $stmt->fetchAll();
?>
<?php include __DIR__.'/includes/header.php'; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Appraisals</h5>
    <div>
      <a class="btn btn-outline-secondary btn-sm <?php echo $filter==='submitted'?'active':''; ?>" href="?filter=submitted">Submitted</a>
      <a class="btn btn-outline-secondary btn-sm <?php echo $filter==='approved'?'active':''; ?>" href="?filter=approved">Approved</a>
      <a class="btn btn-outline-secondary btn-sm <?php echo $filter==='rejected'?'active':''; ?>" href="?filter=rejected">Rejected</a>
      <a class="btn btn-outline-secondary btn-sm <?php echo $filter==='all'?'active':''; ?>" href="?filter=all">All</a>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th><th>Student</th><th>Email</th><th>Total</th><th>Grade</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($list as $a): ?>
          <tr>
            <td><?php echo (int)$a['id']; ?></td>
            <td><?php echo htmlspecialchars($a['student_name']); ?></td>
            <td><?php echo htmlspecialchars($a['student_email']); ?></td>
            <td><?php echo (int)$a['total']; ?></td>
            <td><?php echo htmlspecialchars($a['grade']); ?></td>
            <td><?php echo htmlspecialchars($a['status']); ?></td>
            <td class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-primary" target="_blank" href="/student-appraisal/report.php?id=<?php echo (int)$a['id']; ?>">View</a>
              <?php if($a['status']!=='approved'): ?>
              <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#actionModal" data-id="<?php echo (int)$a['id']; ?>" data-decision="approved">Approve</button>
              <?php endif; if($a['status']!=='rejected'): ?>
              <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#actionModal" data-id="<?php echo (int)$a['id']; ?>" data-decision="rejected">Reject</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<div class="modal fade" id="actionModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title">Mentor Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="id" id="appraisalId">
        <input type="hidden" name="decision" id="decision">
        <div class="mb-3">
          <label class="form-label">Remarks</label>
          <textarea class="form-control" name="remarks" rows="3" placeholder="Optional"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const actionModal = document.getElementById('actionModal');
actionModal.addEventListener('show.bs.modal', e => {
  const btn = e.relatedTarget;
  document.getElementById('appraisalId').value = btn.getAttribute('data-id');
  document.getElementById('decision').value = btn.getAttribute('data-decision');
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
