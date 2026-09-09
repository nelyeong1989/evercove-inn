<?php
session_start();
$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password — Evercove Inn & Suites</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

<?php if ($status && $message): ?>
  <div class="system-alert <?= $status === 'success' ? 'alert-success' : 'alert-error' ?>" id="alert-banner">
    <span><?= htmlspecialchars($message) ?></span>
    <button type="button" class="alert-close" onclick="dismissAlert()">&times;</button>
  </div>
<?php endif; ?>

<div class="auth-box">
  <a href="index.php" class="logo-link">
    <img src="images/logo-1.png" alt="Evercove Inn & Suites">
  </a>
  <h2>Reset Password</h2>
  <p class="auth-subtitle">Verify your account details to update your password.</p>

  <form method="POST" action="function.php">
    <div class="form-group">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required>
    </div>
    <div class="form-group">
      <label for="email">Registered Email</label>
      <input type="email" id="email" name="email" required>
    </div>
    <div class="form-group">
      <label for="new_password">New Password (min. 6 characters)</label>
      <input type="password" id="new_password" name="new_password" required>
    </div>
    <button type="submit" name="reset-password" class="btn btn-gold" style="width: 100%; margin-top: 0.5rem;">
      Update Password
    </button>
  </form>

  <p class="auth-footer-text">
    Remembered your password? <a href="login.php">Sign In</a>
  </p>
</div>

<script>
  function dismissAlert() {
    const alert = document.getElementById('alert-banner');
    if (alert) alert.style.display = 'none';
  }
  window.addEventListener('DOMContentLoaded', () => {
    const alert = document.getElementById('alert-banner');
    if (alert) setTimeout(dismissAlert, 4000);
  });
</script>

</body>
</html>