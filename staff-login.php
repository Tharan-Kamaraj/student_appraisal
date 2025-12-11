<?php $pageTitle = 'SAS - Mentor/Admin Login'; require_once __DIR__.'/db.php'; verify_csrf();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (($_POST['action'] ?? '') === 'login') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
      if (!in_array($user['role'], ['mentor','admin'])) {
        $errors[] = 'Access restricted to Mentor/Admin accounts.';
      } else {
        $_SESSION['user'] = ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']];
        set_flash('success','Welcome back');
        header('Location: /student-appraisal/mentor.php'); exit;
      }
    } else {
      $errors[] = 'Invalid credentials';
    }
  }
}
include __DIR__.'/includes/header.php'; ?>
<div class="py-3">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h5 class="mb-3">Mentor / Admin Login</h5>
          <?php if ($errors): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars(implode("\n", $errors)); ?></div>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" class="form-control" name="password" required>
            </div>
            <button class="btn btn-primary">Login</button>
          </form>
          <div class="text-muted small mt-3">Students should use the Student Login page.</div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
