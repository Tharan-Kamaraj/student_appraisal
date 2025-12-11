<?php $pageTitle = 'SAS - Admin Panel'; require_once __DIR__.'/db.php'; require_login('admin'); verify_csrf();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'create_user') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'mentor';
    if (!$name || !$email || !$password || !in_array($role, ['mentor','admin','student'])) {
      $errors[] = 'Name, email, password, and valid role are required';
    } else {
      try {
        $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        set_flash('success','User created');
        header('Location: /student-appraisal/admin.php'); exit;
      } catch (Throwable $e) {
        $errors[] = 'Failed to create user (email may already exist)';
      }
    }
  }
  if ($action === 'change_role') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $role = $_POST['role'] ?? 'student';
    if (!in_array($role,['student','mentor','admin'])) { $errors[] = 'Invalid role'; }
    else {
      $stmt = $pdo->prepare('UPDATE users SET role=? WHERE id=?');
      $stmt->execute([$role, $uid]);
      set_flash('success','Role updated');
      header('Location: /student-appraisal/admin.php'); exit;
    }
  }
  if ($action === 'delete_user') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid === ($_SESSION['user']['id'] ?? 0)) { $errors[] = 'Cannot delete yourself'; }
    else {
      $stmt = $pdo->prepare('DELETE FROM users WHERE id=?');
      $stmt->execute([$uid]);
      set_flash('success','User deleted');
      header('Location: /student-appraisal/admin.php'); exit;
    }
  }
  if ($action === 'reset_password') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $new = $_POST['new_password'] ?? '';
    if (strlen($new) < 6) { $errors[] = 'New password must be at least 6 characters'; }
    else {
      $stmt = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
      $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
      set_flash('success','Password reset for user #'.$uid);
      header('Location: /student-appraisal/admin.php'); exit;
    }
  }
  if ($action === 'assign_best') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $appraisalId = (int)($_POST['appraisal_id'] ?? 0);
    $year = (int)($_POST['year'] ?? date('Y'));
    $notes = trim($_POST['notes'] ?? '');
    if ($studentId && $year) {
      // Ensure appraisal belongs to student and is approved (optional but safer)
      if ($appraisalId) {
        $chk = $pdo->prepare('SELECT 1 FROM appraisals WHERE id=? AND student_id=? AND status="approved"');
        $chk->execute([$appraisalId, $studentId]);
        if (!$chk->fetch()) { $appraisalId = null; }
      }
      $sql = 'INSERT INTO honors(student_id, appraisal_id, year, title, notes) VALUES(?,?,?,?,?)
              ON DUPLICATE KEY UPDATE student_id=VALUES(student_id), appraisal_id=VALUES(appraisal_id), notes=VALUES(notes)';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$studentId, $appraisalId ?: null, $year, 'best_outgoing', $notes ?: null]);
      set_flash('success','Best Outgoing Student recorded for '.$year);
      header('Location: /student-appraisal/admin.php#leaderboard'); exit;
    } else {
      $errors[] = 'Student and year are required';
    }
  }
}

$search = trim($_GET['q'] ?? '');
if ($search) {
  $stmt = $pdo->prepare('SELECT * FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY created_at DESC');
  $stmt->execute(['%'.$search.'%','%'.$search.'%']);
} else {
  $stmt = $pdo->query('SELECT * FROM users ORDER BY created_at DESC');
}
$users = $stmt->fetchAll();

// Leaderboard: fetch approved appraisals ordered by total desc
$stmt = $pdo->query('SELECT a.*, u.name, u.email FROM appraisals a JOIN users u ON u.id=a.student_id WHERE a.status="approved" ORDER BY a.total DESC, a.updated_at DESC LIMIT 200');
$approved = $stmt->fetchAll();
// Deduplicate to best per student (first occurrence is max total)
$bestByStudent = [];
foreach ($approved as $row) { if (!isset($bestByStudent[$row['student_id']])) { $bestByStudent[$row['student_id']] = $row; } }

// Existing honors list
$hon = $pdo->query("SELECT h.*, u.name, u.email FROM honors h JOIN users u ON u.id=h.student_id WHERE h.title='best_outgoing' ORDER BY year DESC")->fetchAll();
?>
<?php include __DIR__.'/includes/header.php'; ?>
  <?php if($errors): ?><div class="alert alert-danger"><?php echo htmlspecialchars(implode("\n", $errors)); ?></div><?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">Create User (Mentor/Admin/Student)</div>
    <div class="card-body">
      <form class="row g-3 align-items-end" method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="action" value="create_user">
        <div class="col-md-3">
          <label class="form-label">Full Name</label>
          <input class="form-control" name="name" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Password</label>
          <input type="text" class="form-control" name="password" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Role</label>
          <select class="form-select" name="role">
            <option value="mentor">Mentor</option>
            <option value="admin">Admin</option>
            <option value="student">Student</option>
          </select>
        </div>
        <div class="col-md-1 d-grid">
          <button class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Users</h5>
    <form class="d-flex" method="get">
      <input class="form-control form-control-sm me-2" name="q" placeholder="Search name/email" value="<?php echo htmlspecialchars($search); ?>">
      <button class="btn btn-sm btn-outline-primary">Search</button>
    </form>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($users as $u): ?>
          <tr>
            <td><?php echo (int)$u['id']; ?></td>
            <td><?php echo htmlspecialchars($u['name']); ?></td>
            <td><?php echo htmlspecialchars($u['email']); ?></td>
            <td>
              <form method="post" class="d-flex align-items-center gap-2">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                <select class="form-select form-select-sm" name="role">
                  <option value="student" <?php echo $u['role']==='student'?'selected':''; ?>>Student</option>
                  <option value="mentor" <?php echo $u['role']==='mentor'?'selected':''; ?>>Mentor</option>
                  <option value="admin" <?php echo $u['role']==='admin'?'selected':''; ?>>Admin</option>
                </select>
                <button class="btn btn-sm btn-outline-primary">Update</button>
              </form>
            </td>
            <td><?php echo htmlspecialchars($u['created_at']); ?></td>
            <td>
              <?php if ($u['id'] !== ($_SESSION['user']['id'] ?? 0)): ?>
              <form method="post" onsubmit="return confirm('Delete this user? This will remove their appraisals as well.');">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
              <form method="post" class="mt-2 d-flex align-items-center gap-2">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                <input type="text" class="form-control form-control-sm" name="new_password" placeholder="New password" required style="max-width:160px">
                <button class="btn btn-sm btn-outline-secondary">Reset</button>
              </form>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="leaderboard" class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
      <span>Leaderboard (Approved Appraisals)</span>
      <small class="text-muted">Top ranked by Total</small>
    </div>
    <div class="card-body">
      <div class="row g-3 mb-3">
        <div class="col-12">
          <canvas id="leaderboardChart" height="120"></canvas>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead><tr>
            <th>#</th><th>Student</th><th>Email</th><th>Total</th><th>Grade</th><th>Appraisal ID</th><th>Assign Best Outgoing</th>
          </tr></thead>
          <tbody>
            <?php $rank=1; foreach($bestByStudent as $sid=>$row): ?>
            <tr>
              <td><?php echo $rank++; ?></td>
              <td><?php echo htmlspecialchars($row['name']); ?></td>
              <td><?php echo htmlspecialchars($row['email']); ?></td>
              <td><strong><?php echo (int)$row['total']; ?></strong></td>
              <td><?php echo htmlspecialchars($row['grade']); ?></td>
              <td>#<?php echo (int)$row['id']; ?></td>
              <td>
                <form class="row g-2 align-items-center" method="post">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                  <input type="hidden" name="action" value="assign_best">
                  <input type="hidden" name="student_id" value="<?php echo (int)$row['student_id']; ?>">
                  <input type="hidden" name="appraisal_id" value="<?php echo (int)$row['id']; ?>">
                  <div class="col-auto"><input class="form-control form-control-sm" name="year" placeholder="Year" value="<?php echo date('Y'); ?>" style="width:100px"></div>
                  <div class="col-auto"><input class="form-control form-control-sm" name="notes" placeholder="Notes (optional)"></div>
                  <div class="col-auto"><button class="btn btn-sm btn-primary">Mark Best</button></div>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    window.adminLeaderboard = <?php echo json_encode(array_values(array_map(function($r){ return ['name'=>$r['name'], 'total'=>(int)$r['total']]; }, array_slice($bestByStudent, 0, 10))), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
  </script>

  <div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">Best Outgoing Student (History)</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead><tr><th>Year</th><th>Student</th><th>Email</th><th>Notes</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach($hon as $h): ?>
            <tr>
              <td><?php echo (int)$h['year']; ?></td>
              <td><?php echo htmlspecialchars($h['name']); ?></td>
              <td><?php echo htmlspecialchars($h['email']); ?></td>
              <td><?php echo htmlspecialchars($h['notes'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($h['created_at']); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php include __DIR__.'/includes/footer.php'; ?>
