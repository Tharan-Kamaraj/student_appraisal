<?php require_once __DIR__.'/db.php'; verify_csrf(); session_destroy(); header('Location: /student-appraisal/index.php');
