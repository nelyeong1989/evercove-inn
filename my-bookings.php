<?php
session_start();
require_once 'database/config.php';

// Must be signed in to see personal bookings
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?status=error&message=' . urlencode('Please sign in to view your reservations.'));
    exit;
}

$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;
$userId  = $_SESSION['user_id'];

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT b.*, r.name AS room_name, r.category 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.user_id = :uid 
        ORDER BY b.id DESC
    ");
    $stmt->execute([':uid' => $userId]);
    $myBookings = $stmt->fetchAll();
} catch (Exception $e) {
    $myBookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Reservations — Evercove Inn & Suites</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

<?php if ($status && $message): ?>
  <div class="system-alert <?= $status === 'success' ? 'alert-success' : 'alert-error' ?>" id="alert-banner">
    <span><?= htmlspecialchars($message) ?></span>
    <button type="button" class="alert-close" onclick="dismissAlert()">&times;</button>
  </div>
<?php endif; ?>

<!-- Header Navigation -->
<header class="site-header">
  <div class="container header-container">
    <a href="index.php" class="logo"><img src="images/logo-1.png" alt="Evercove Inn & Suites"></a>
    <nav class="nav-links">
      <a href="index.php">Home</a>
      <a href="about.php">About</a>
      <a href="rooms.php">Rooms</a>
      <a href="experience.php">Experience</a>
      <a href="reviews.php">Reviews</a>
    </nav>
    <div class="user-badge">
      <span class="user-greeting">Hi, <?= htmlspecialchars($_SESSION['username']) ?></span>

      <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a href="admin.php" class="btn btn-green">Management Console</a>
      <?php else: ?>
        <a href="my-bookings.php" class="btn btn-gold btn-sm">My Bookings</a>
        <a href="booking.php" class="btn btn-green">Book Your Stay</a>
      <?php endif; ?>

      <a href="function.php?action=logout" class="btn btn-sage btn-sm">Log Out</a>
    </div>
  </div>
</header>

<!-- Guest Reservations Portal -->
<div class="admin-wrap" style="margin-top: 3.5rem;">
  <div class="admin-header">
    <div>
      <span class="eyebrow eyebrow-green">Guest Portal</span>
      <h2 style="color: var(--emerald-green); margin-top: 0.2rem;">Your Stay History &amp; Orders</h2>
      <p style="color: var(--gray); font-size: 0.9rem; margin-top: 0.2rem;">Review your upcoming escapes, download receipts, or update your reservation dates.</p>
    </div>
    <a href="booking.php" class="btn btn-gold btn-sm">+ New Reservation</a>
  </div>

  <?php if (empty($myBookings)): ?>
    <div class="booking-card-expanded" style="text-align: center; padding: 3rem 2rem;">
      <h3 style="color: var(--emerald-green); margin-bottom: 0.5rem;">No bookings found</h3>
      <p style="color: var(--gray); font-size: 0.95rem; margin-bottom: 1.5rem;">You haven't reserved any stays with us yet.</p>
      <a href="rooms.php" class="btn btn-green">Find Your Room</a>
    </div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Ref</th>
          <th>Room</th>
          <th>Dates</th>
          <th>Guests</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th style="text-align: right;">Manage</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($myBookings as $b): ?>
          <?php 
            $today = date('Y-m-d');
            $isPastStay = ($b['checkout_date'] < $today);
            $payStatus = $b['payment_status'] ?? 'Pending (Due at Check-in)';
            $isPaid = (strpos($payStatus, 'Paid') !== false);
          ?>
          <tr>
            <td><strong>#EVR-<?= str_pad($b['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
            <td>
              <?= htmlspecialchars($b['room_name']) ?><br>
              <small style="color: var(--gray);"><?= htmlspecialchars($b['category']) ?></small>
            </td>
            <td>
              <?= date('M j, Y', strtotime($b['checkin_date'])) ?> &rarr; 
              <?= date('M j, Y', strtotime($b['checkout_date'])) ?>
            </td>
            <td><?= htmlspecialchars($b['guests_count']) ?></td>
            <td><strong>₱<?= number_format($b['total_amount'], 2) ?></strong></td>
            <td>
              <small style="color: var(--gray); display: block;"><?= htmlspecialchars($b['payment_method'] ?? 'Pay on Check-in') ?></small>
              <span class="badge" style="background: <?= $isPaid ? 'var(--forest-green)' : 'var(--warm-gold)' ?>; color: #fff; font-size: 0.65rem;">
                <?= htmlspecialchars($payStatus) ?>
              </span>
            </td>
            <td>
              <?php if ($b['status'] === 'cancelled'): ?>
                <span class="badge badge-cancelled">Cancelled</span>
              <?php elseif ($isPastStay): ?>
                <span class="badge badge-completed">Completed</span>
              <?php else: ?>
                <span class="badge badge-confirmed">Confirmed</span>
              <?php endif; ?>
            </td>
            <td style="text-align: right; white-space: nowrap;">
              <a href="booking-success.php?id=<?= $b['id'] ?>" class="btn-action-view" style="margin-right: 4px;">Receipt</a>
              <?php if ($b['status'] === 'confirmed' && !$isPastStay): ?>
                <a href="edit-booking.php?id=<?= $b['id'] ?>" class="btn-action-edit" style="margin-right: 4px;">Modify</a>
                <a href="function.php?action=user-cancel-booking&id=<?= $b['id'] ?>" 
                   class="btn-action-cancel" 
                   onclick="return confirm('Are you sure you want to cancel this reservation?');">
                  Cancel
                </a>
              <?php elseif ($isPastStay && $b['status'] === 'confirmed'): ?>
                <a href="reviews.php" class="btn btn-sage btn-sm" style="padding: 0.35rem 0.65rem; font-size: 0.7rem;">Leave Review</a>
              <?php else: ?>
                <span style="color: var(--gray); font-size: 0.75rem;">None</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Footer -->
<footer class="site-footer" style="margin-top: 6rem;">
  <div class="container">
    <div class="footer-bottom">
      <span>&copy; <?php echo date("Y"); ?> Evercove Inn &amp; Suites. All rights reserved.</span>
    </div>
  </div>
</footer>

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