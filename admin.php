<?php
session_start();
require 'database/config.php';

// Check if user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?status=error&message=' . urlencode('Administrator privileges required. Please sign in as staff.'));
    exit;
}

$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;

$pdo = getConnection();

// 1. Fetch dashboard overview stats
$revStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total_rev FROM bookings WHERE status = 'confirmed'");
$totalRevenue = (float)$revStmt->fetchColumn();

$activeStmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND CURRENT_DATE BETWEEN checkin_date AND checkout_date");
$activeStays = (int)$activeStmt->fetchColumn();

$confirmedCountStmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
$totalBookings = (int)$confirmedCountStmt->fetchColumn();

$ratingStmt = $pdo->query("SELECT AVG(rating) AS avg_score, COUNT(*) AS count FROM reviews");
$ratingData = $ratingStmt->fetch();
$avgRating = ($ratingData && $ratingData['count'] > 0) ? number_format((float)$ratingData['avg_score'], 1) : '5.0';

// 2. Fetch users, rooms, bookings, and reviews
$users = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY id ASC")->fetchAll();
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY id ASC")->fetchAll();
$bookings = $pdo->query("
    SELECT b.*, r.name AS room_name 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    ORDER BY b.id DESC
")->fetchAll();
$reviews = $pdo->query("SELECT * FROM reviews ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Management Portal — Evercove</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="admin-wrap">

  <?php if ($status && $message): ?>
    <div class="system-alert <?= $status === 'success' ? 'alert-success' : 'alert-error' ?>" id="alert-banner">
      <span><?= htmlspecialchars($message) ?></span>
      <button type="button" class="alert-close" onclick="dismissAlert()">&times;</button>
    </div>
  <?php endif; ?>

  <!-- Admin Top Header -->
  <div class="admin-header">
    <div>
      <span class="eyebrow">Executive Console</span>
      <h2 style="color: var(--emerald-green); margin-top: 0.2rem;">Evercove Hotel Operations</h2>
    </div>
    <div class="user-badge">
      <a href="index.php" class="btn btn-green btn-sm">View Website</a>
      <a href="function.php?action=logout" class="btn btn-sage btn-sm">Sign Out</a>
    </div>
  </div>

  <!-- Metric Overview Cards -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <span class="kpi-label">Gross Revenue</span>
      <div class="kpi-number">&#8369;<?= number_format($totalRevenue, 2) ?></div>
      <small>Confirmed reservations</small>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Active In-House Stays</span>
      <div class="kpi-number"><?= $activeStays ?></div>
      <small>Currently checked in</small>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Confirmed Bookings</span>
      <div class="kpi-number"><?= $totalBookings ?></div>
      <small>All-time total</small>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Guest Satisfaction</span>
      <div class="kpi-number"><?= $avgRating ?> / 5.0</div>
      <small><?= (int)($ratingData['count'] ?? 0) ?> verified reviews</small>
    </div>
  </div>

  <!-- Reservations Header with Instant Search Bar -->
  <div class="admin-section-head">
    <div>
      <h3 style="color: var(--emerald-green);">Guest Reservations</h3>
      <p style="color: var(--gray); font-size: 0.85rem;">Manage upcoming arrivals, guest cancellations, and payment settlements.</p>
    </div>
    <div class="admin-toolbar">
      <input type="text" id="bookingSearch" class="admin-search-input" placeholder="Search reference or guest..." onkeyup="filterBookingsTable()">
    </div>
  </div>

  <?php if (empty($bookings)): ?>
    <p style="font-size: 0.85rem; color: var(--gray); margin-bottom: 2rem;">No reservations yet.</p>
  <?php else: ?>
    <table class="admin-table" id="bookingsTable">
      <thead>
        <tr>
          <th>Ref</th>
          <th>Guest</th>
          <th>Room</th>
          <th>Dates</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $b): ?>
          <?php 
            $today = date('Y-m-d');
            $isPastStay = ($b['checkout_date'] < $today);
            $payStatus = $b['payment_status'] ?? 'Pending (Due at Check-in)';
            $isPaid = (strpos($payStatus, 'Paid (Verified)') !== false || strpos($payStatus, 'Paid (Front Desk)') !== false);
          ?>
          <tr>
            <td class="cell-nowrap">
              <a href="booking-success.php?id=<?= $b['id'] ?>" target="_blank" style="color: var(--emerald-green); text-decoration: underline;" title="View Voucher Receipt">
                <strong>#EVR-<?= str_pad($b['id'], 5, '0', STR_PAD_LEFT) ?></strong>
              </a>
            </td>
            <td>
              <strong><?= htmlspecialchars($b['guest_name']) ?></strong><br>
              <small style="color: var(--gray); font-size: 0.75rem;"><?= htmlspecialchars($b['guest_email']) ?></small>
            </td>
            <td class="cell-nowrap">
              <strong><?= htmlspecialchars($b['room_name']) ?></strong>
            </td>
            <td class="cell-nowrap" style="font-size: 0.8rem; color: #3d3a30;">
              <?= date('M j', strtotime($b['checkin_date'])) ?> &ndash; <?= date('M j, Y', strtotime($b['checkout_date'])) ?>
            </td>
            <td class="cell-nowrap">
              <strong>&#8369;<?= number_format($b['total_amount'], 2) ?></strong>
            </td>
            <td>
              <small style="color: var(--gray); display: block; font-weight: 600; white-space: nowrap; margin-bottom: 2px;">
                <?= htmlspecialchars($b['payment_method'] ?? 'Pay on Check-in') ?>
              </small>
              <span class="badge" style="background: <?= $isPaid ? 'var(--forest-green)' : 'var(--warm-gold)' ?>; color: #fff;">
                <?= htmlspecialchars($payStatus) ?>
              </span>
              <?php if (!empty($b['payment_ref'])): ?>
                <small style="display: block; color: var(--emerald-green); font-family: monospace; font-size: 0.72rem; margin-top: 3px; white-space: nowrap;">
                  Ref: <?= htmlspecialchars($b['payment_ref']) ?>
                </small>
              <?php endif; ?>
              <?php if (!empty($b['payment_proof'])): ?>
                <a href="<?= htmlspecialchars($b['payment_proof']) ?>" target="_blank" style="display: inline-block; font-size: 0.68rem; font-weight: 700; color: var(--gold-dark); text-decoration: underline; margin-top: 3px; white-space: nowrap;">
                  🔍 View Screenshot
                </a>
              <?php endif; ?>
            </td>
            <td class="cell-nowrap">
              <?php if ($b['status'] === 'cancelled'): ?>
                <span class="badge badge-cancelled">cancelled</span>
              <?php elseif ($isPastStay): ?>
                <span class="badge badge-completed">completed</span>
              <?php else: ?>
                <span class="badge badge-confirmed">confirmed</span>
              <?php endif; ?>
            </td>
            <td class="cell-nowrap" style="text-align: right;">
              <a href="booking-success.php?id=<?= $b['id'] ?>" target="_blank" class="btn-action-view" style="margin-right: 4px;">Receipt</a>

              <?php if ($b['status'] === 'confirmed' && !$isPaid): ?>
                <a href="function.php?action=confirm-payment&id=<?= $b['id'] ?>" 
                   class="btn btn-green btn-sm" 
                   style="padding: 0.35rem 0.6rem; font-size: 0.68rem; margin-right: 4px;"
                   onclick="return confirm('Confirm verified payment for this reservation?');">
                  Mark Paid
                </a>
              <?php endif; ?>

              <?php if ($b['status'] === 'confirmed' && !$isPastStay): ?>
                <a href="function.php?action=cancel-booking&id=<?= $b['id'] ?>" 
                   class="btn-action-cancel" 
                   onclick="return confirm('Cancel this reservation? This reopens the dates immediately.');">
                  Cancel
                </a>
              <?php elseif ($isPastStay && $b['status'] === 'confirmed'): ?>
                <span style="color: var(--gray); font-size: 0.75rem;">Completed</span>
              <?php else: ?>
                <span style="color: var(--gray); font-size: 0.75rem;">None</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- Room Rates Management -->
  <h3 style="color: var(--emerald-green); margin-bottom: 0.8rem; margin-top: 2rem;">Room Rates &amp; Catalog Management</h3>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Room Name</th>
        <th>Category</th>
        <th>Current Rate / Night</th>
        <th>Update Rate</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rooms as $room): ?>
        <tr>
          <td><strong><?= htmlspecialchars($room['name']) ?></strong></td>
          <td><?= htmlspecialchars($room['category']) ?></td>
          <td><strong>&#8369;<?= number_format($room['price_per_night'], 2) ?></strong></td>
          <td>
            <form method="POST" action="function.php" style="display: flex; gap: 0.5rem; align-items: center;">
              <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
              <input type="number" step="50" min="500" name="price_per_night" value="<?= (int)$room['price_per_night'] ?>" class="rate-inline-input" required>
              <button type="submit" name="update-room-rate" class="btn btn-green btn-sm" style="padding: 0.4rem 0.8rem;">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Registered User Accounts -->
  <h3 style="color: var(--emerald-green); margin-bottom: 0.8rem; margin-top: 2rem;">Registered Accounts</h3>
  <table class="admin-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Created</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['id']) ?></td>
          <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <span class="badge <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
              <?= htmlspecialchars($u['role']) ?>
            </span>
          </td>
          <td><?= htmlspecialchars($u['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Guest Reviews Moderation -->
  <h3 style="color: var(--emerald-green); margin-bottom: 0.8rem; margin-top: 2rem;">Guest Feedback Moderation</h3>
  <?php if (empty($reviews)): ?>
    <p style="font-size: 0.85rem; color: var(--gray);">No guest reviews recorded yet.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Attribution</th>
          <th>Room Stayed</th>
          <th>Rating</th>
          <th>Feedback</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reviews as $rev): ?>
          <tr>
            <td><strong><?= htmlspecialchars($rev['guest_name']) ?></strong></td>
            <td><?= htmlspecialchars($rev['room_type']) ?></td>
            <td><?= htmlspecialchars($rev['rating']) ?> / 5 &#9733;</td>
            <td><?= htmlspecialchars($rev['comment']) ?></td>
            <td><?= htmlspecialchars($rev['created_at']) ?></td>
            <td>
              <a href="function.php?action=delete-review&id=<?= $rev['id'] ?>" 
                 class="btn-action-cancel" 
                 onclick="return confirm('Permanently remove this review from the public site?');">
                Delete
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>

<script>
  function filterBookingsTable() {
    const input = document.getElementById('bookingSearch');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('bookingsTable');
    if (!table) return;

    const tr = table.getElementsByTagName('tr');
    for (let i = 1; i < tr.length; i++) {
      const rowText = tr[i].textContent || tr[i].innerText;
      tr[i].style.display = rowText.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
    }
  }

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