<?php require_once __DIR__.'/../db.php'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0d6efd">
  <meta name="description" content="Student Appraisal System (SAS) - Performance Based Appraisal adapted for students.">
  <title><?php echo htmlspecialchars($pageTitle ?? 'Student Appraisal System'); ?></title>
  <link rel="icon" href="/student-appraisal/assets/images/logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/student-appraisal/assets/css/style.css">
</head>
<body class="bg-light hero-bg">
<nav class="navbar navbar-expand-lg navbar-dark sas-navbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="/student-appraisal/index.php">
      <img src="/student-appraisal/assets/images/logo.png" alt="Logo" height="36" class="me-2" onerror="this.style.display='none'">
      <span>SAS</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <?php if (!empty($_SESSION['user'])): ?>
          <?php if ($_SESSION['user']['role'] === 'student'): ?>
            <li class="nav-item"><a class="nav-link" href="/student-appraisal/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="/student-appraisal/profile.php">Profile</a></li>
          <?php elseif (in_array($_SESSION['user']['role'], ['mentor','admin'])): ?>
            <li class="nav-item"><a class="nav-link" href="/student-appraisal/mentor.php">Mentor</a></li>
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
              <li class="nav-item"><a class="nav-link" href="/student-appraisal/admin.php">Admin</a></li>
              <li class="nav-item"><a class="nav-link" href="/student-appraisal/analytics.php">Analytics</a></li>
            <?php endif; ?>
          <?php endif; ?>
          <li class="nav-item ms-lg-3">
            <form method="post" action="/student-appraisal/logout.php" class="d-flex">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <button class="btn btn-sm btn-outline-light">Logout</button>
            </form>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/student-appraisal/index.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container py-4">
<?php $flashes = consume_flash(); if ($flashes): ?>
  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?php echo $f['type']==='error'?'danger':($f['type']==='success'?'success':'info'); ?>">
      <?php echo htmlspecialchars($f['msg']); ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
