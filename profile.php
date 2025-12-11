<?php $pageTitle = 'SAS - Profile'; require_once __DIR__.'/db.php'; require_login('student'); verify_csrf();
$user = $_SESSION['user'];
$errors = [];
// Load existing student profile (if any)
$stmtP = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id=?');
$stmtP->execute([$user['id']]);
$profile = $stmtP->fetch() ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'update_name') {
    $name = trim($_POST['name'] ?? '');
    if (!$name) { $errors[] = 'Name is required'; }
    else {
      $stmt = $pdo->prepare('UPDATE users SET name=? WHERE id=?');
      $stmt->execute([$name, $user['id']]);
      $_SESSION['user']['name'] = $name;
      set_flash('success','Profile updated');
      header('Location: /student-appraisal/profile.php'); exit;
    }
  }
  if ($action === 'change_password') {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id=?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($old, $row['password_hash'])) { $errors[] = 'Current password is incorrect'; }
    elseif (strlen($new) < 6) { $errors[] = 'New password must be at least 6 characters'; }
    else {
      $stmt = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
      $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
      set_flash('success','Password changed');
      header('Location: /student-appraisal/profile.php'); exit;
    }
  }
  if ($action === 'save_profile') {
    $roll_no = trim($_POST['roll_no'] ?? '');
    $program = trim($_POST['program'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $year_of_study = (int)($_POST['year_of_study'] ?? 0);
    $section = trim($_POST['section'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $address_line = trim($_POST['address_line'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_phone = trim($_POST['guardian_phone'] ?? '');
    try {
      $sql = 'INSERT INTO student_profiles(user_id, roll_no, program, department, year_of_study, section, phone, dob, address_line, city, state, pincode, guardian_name, guardian_phone) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)
              ON DUPLICATE KEY UPDATE roll_no=VALUES(roll_no), program=VALUES(program), department=VALUES(department), year_of_study=VALUES(year_of_study), section=VALUES(section), phone=VALUES(phone), dob=VALUES(dob), address_line=VALUES(address_line), city=VALUES(city), state=VALUES(state), pincode=VALUES(pincode), guardian_name=VALUES(guardian_name), guardian_phone=VALUES(guardian_phone)';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$user['id'],$roll_no,$program,$department,$year_of_study?:null,$section,$phone,$dob?:null,$address_line,$city,$state,$pincode,$guardian_name,$guardian_phone]);
      set_flash('success','Personal details saved');
      header('Location: /student-appraisal/profile.php'); exit;
    } catch (Throwable $e) {
      $errors[] = 'Failed to save personal details';
    }
  }
}
?>
<?php include __DIR__.'/includes/header.php'; ?>
  <?php if($errors): ?><div class="alert alert-danger"><?php echo htmlspecialchars(implode("\n", $errors)); ?></div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-bold">Profile</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="update_name">
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input class="form-control" name="name" value="<?php echo htmlspecialchars($_SESSION['user']['name']); ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email (read-only)</label>
              <input class="form-control" value="<?php echo htmlspecialchars($_SESSION['user']['email']); ?>" disabled>
            </div>
            <button class="btn btn-primary">Save</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-bold">Change Password</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="mb-3">
              <label class="form-label">Current Password</label>
              <input type="password" class="form-control" name="old_password" required>
            </div>
            <div class="mb-3">
              <label class="form-label">New Password</label>
              <input type="password" class="form-control" name="new_password" required>
            </div>
            <button class="btn btn-warning">Change Password</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header fw-bold">Personal Details</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="save_profile">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Roll No</label>
                <input class="form-control" name="roll_no" value="<?php echo htmlspecialchars($profile['roll_no'] ?? ''); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Program</label>
                <input class="form-control" name="program" value="<?php echo htmlspecialchars($profile['program'] ?? ''); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Department</label>
                <input class="form-control" name="department" value="<?php echo htmlspecialchars($profile['department'] ?? ''); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Year of Study</label>
                <input type="number" min="1" max="8" class="form-control" name="year_of_study" value="<?php echo htmlspecialchars($profile['year_of_study'] ?? ''); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Section</label>
                <input class="form-control" name="section" value="<?php echo htmlspecialchars($profile['section'] ?? ''); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Date of Birth</label>
                <input type="date" class="form-control" name="dob" value="<?php echo htmlspecialchars($profile['dob'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Address</label>
                <input class="form-control" name="address_line" value="<?php echo htmlspecialchars($profile['address_line'] ?? ''); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">City</label>
                <input class="form-control" name="city" value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">State</label>
                <input class="form-control" name="state" value="<?php echo htmlspecialchars($profile['state'] ?? ''); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Pincode</label>
                <input class="form-control" name="pincode" value="<?php echo htmlspecialchars($profile['pincode'] ?? ''); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Guardian Name</label>
                <input class="form-control" name="guardian_name" value="<?php echo htmlspecialchars($profile['guardian_name'] ?? ''); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Guardian Phone</label>
                <input class="form-control" name="guardian_phone" value="<?php echo htmlspecialchars($profile['guardian_phone'] ?? ''); ?>">
              </div>
            </div>
            <div class="mt-3">
              <button class="btn btn-success">Save Details</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php include __DIR__.'/includes/footer.php'; ?>
