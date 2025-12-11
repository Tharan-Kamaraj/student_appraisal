<?php $pageTitle = 'SAS - Student Login / Register'; require_once __DIR__.'/db.php'; verify_csrf();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = 'student';
    if (!$name || !$email || !$password) {
      $errors[] = 'All fields are required';
    } else {
      try {
        $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        $userId = $pdo->lastInsertId();
        $_SESSION['user'] = ['id'=>$userId,'name'=>$name,'email'=>$email,'role'=>$role];
        set_flash('success','Registration successful');
        header('Location: /student-appraisal/dashboard.php'); exit;
      } catch (Throwable $e) {
        $errors[] = 'Registration failed (email may already exist)';
      }
    }
  }
  if ($action === 'login') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
      if ($user['role'] !== 'student') {
        set_flash('error','This login is for students. Use Mentor/Admin login.');
        header('Location: /student-appraisal/staff-login.php'); exit;
      }
      $_SESSION['user'] = ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']];
      set_flash('success','Welcome');
      header('Location: /student-appraisal/dashboard.php'); exit;
    } else {
      $errors[] = 'Invalid credentials';
    }
  }
}
include __DIR__.'/includes/header.php'; ?>
<div class="py-3">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">Login</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">Register</button>
            </li>
          </ul>
          <div class="tab-content pt-3">
            <?php if ($errors): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars(implode("\n", $errors)); ?></div>
            <?php endif; ?>
            <div class="tab-pane fade show active" id="login" role="tabpanel">
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
            </div>
            <div class="tab-pane fade" id="register" role="tabpanel">
              <form method="post">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="register">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="name" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                  </div>
                </div>
                <div class="mt-3">
                  <button class="btn btn-success">Register</button>
                </div>
              </form>
            </div>
          </div>
          <div class="text-muted small mt-3">This page is only for students. Mentors/Admins use their login page.</div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
