<?php
session_start();
$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;

// Route already logged in users appropriately
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sign In — Evercove Inn & Suites</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

<?php if ($status && $message): ?>
  <div class="system-alert <?= $status === 'success' ? 'alert-success' : 'alert-error' ?>" id="alert-banner">
    <span><?= htmlspecialchars($message) ?></span>
    <button type="button" class="alert-close" onclick="dismissAlert()">&times;</button>
  </div>
<?php endif; ?>

<!-- Unified Sign In Card (Both Guest & Admin) -->
<div class="auth-box">
  <a href="index.php" class="logo-link">
    <img src="images/logo-1.png" alt="Evercove Inn & Suites">
  </a>
  <h2>Sign In</h2>
  <p class="auth-subtitle">Access your Evercove account</p>

  <form method="POST" action="function.php">
    <div class="form-group">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>

    <!-- Forgot Password Link -->
    <div style="text-align: right; margin-top: -0.4rem; margin-bottom: 0.9rem;">
      <a href="forgot-password.php" style="font-size: 0.76rem; color: var(--gray); text-decoration: none;">Forgot password?</a>
    </div>

    <button type="submit" name="login-user" class="btn btn-green" style="width: 100%; margin-top: 0.2rem;">Sign In</button>
  </form>

  <p class="auth-footer-text">
    No account yet? <a href="register.php" style="color: var(--gold-dark);">Create one</a>
  </p>
</div>

<script>
  function dismissAlert() {
    const alert = document.getElementById('alert-banner');
    if (alert) alert.style.display = 'none';
  }
  window.addEventListener('DOMContentLoaded', () => {
    const alert = document.getElementById('alert-banner');
    if (alert) {
      setTimeout(dismissAlert, 4000);
      if (window.history.replaceState) {
        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
      }
    }
  });
</script>

</body>
</html>