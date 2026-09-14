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
$today = date('Y-m-d');

// 1. Fetch dashboard overview stats
$revStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total_rev FROM bookings WHERE status = 'confirmed'");
$totalRevenue = (float)$revStmt->fetchColumn();

$activeStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND :today BETWEEN checkin_date AND checkout_date");
$activeStmt->execute([':today' => $today]);
$activeStays = (int)$activeStmt->fetchColumn();

$confirmedCountStmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
$totalBookings = (int)$confirmedCountStmt->fetchColumn();

$ratingStmt = $pdo->query("SELECT AVG(rating) AS avg_score, COUNT(*) AS count FROM reviews");
$ratingData = $ratingStmt->fetch();
$avgRating = ($ratingData && $ratingData['count'] > 0) ? number_format((float)$ratingData['avg_score'], 1) : '5.0';

// 2. Occupancy & Operations
$totalRoomsCount = (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occupancyRate = $totalRoomsCount > 0 ? round(($activeStays / $totalRoomsCount) * 100) : 0;

$todayCheckins = $pdo->prepare("
    SELECT b.*, r.name AS room_name 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.checkin_date = :today AND b.status = 'confirmed'
");
$todayCheckins->execute([':today' => $today]);
$arrivals = $todayCheckins->fetchAll();

$todayCheckouts = $pdo->prepare("
    SELECT b.*, r.name AS room_name 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.checkout_date = :today AND b.status = 'confirmed'
");
$todayCheckouts->execute([':today' => $today]);
$departures = $todayCheckouts->fetchAll();

// 3. Needs Verification Count
$needsVerificationCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND payment_status = 'Paid (Under Verification)'")->fetchColumn();

// 4. Fetch users, rooms, bookings, and reviews
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
      <span class="kpi-label">Occupancy Rate</span>
      <div class="kpi-number"><?= $occupancyRate ?>%</div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill" style="width: <?= min(100, $occupancyRate) ?>%;"></div>
      </div>
      <small><?= $activeStays ?> of <?= $totalRoomsCount ?> rooms occupied</small>
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

  <!-- Operations Today Panel -->
  <div class="ops-panel">
    <div class="ops-col">
      <div class="ops-title">
        <span class="ops-dot arrival-dot"></span>
        <h4>Today's Expected Arrivals (<?= count($arrivals) ?>)</h4>
      </div>
      <?php if (empty($arrivals)): ?>
        <p class="ops-empty">No scheduled check-ins for today.</p>
      <?php else: ?>
        <div class="ops-list">
          <?php foreach ($arrivals as $arr): ?>
            <div class="ops-item">
              <div>
                <strong><?= htmlspecialchars($arr['guest_name']) ?></strong>
                <small><?= htmlspecialchars($arr['room_name']) ?> &bull; #EVR-<?= str_pad($arr['id'], 5, '0', STR_PAD_LEFT) ?></small>
              </div>
              <span class="badge badge-confirmed">Arriving</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="ops-col">
      <div class="ops-title">
        <span class="ops-dot departure-dot"></span>
        <h4>Today's Expected Departures (<?= count($departures) ?>)</h4>
      </div>
      <?php if (empty($departures)): ?>
        <p class="ops-empty">No scheduled check-outs for today.</p>
      <?php else: ?>
        <div class="ops-list">
          <?php foreach ($departures as $dep): ?>
            <div class="ops-item">
              <div>
                <strong><?= htmlspecialchars($dep['guest_name']) ?></strong>
                <small><?= htmlspecialchars($dep['room_name']) ?> &bull; #EVR-<?= str_pad($dep['id'], 5, '0', STR_PAD_LEFT) ?></small>
              </div>
              <span class="badge" style="background: var(--warm-gold); color: #fff;">Check Out</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Reservations Header with Toolbar & Filter Pills -->
  <div class="admin-section-head" style="margin-top: 2rem;">
    <div>
      <h3 style="color: var(--emerald-green);">Guest Reservations</h3>
      <p style="color: var(--gray); font-size: 0.85rem;">Manage upcoming arrivals, guest cancellations, and payment settlements.</p>
    </div>
    <div class="admin-toolbar">
      <input type="text" id="bookingSearch" class="admin-search-input" placeholder="Search reference or guest..." onkeyup="filterBookingsTable()">
    </div>
  </div>

  <!-- Filter Pills -->
  <div class="admin-filter-bar">
    <button type="button" class="admin-filter-pill active" onclick="setBookingFilter('all', this)">All</button>
    <button type="button" class="admin-filter-pill" onclick="setBookingFilter('verify', this)">
      Needs Verification <?php if ($needsVerificationCount > 0): ?><span class="filter-count-badge"><?= $needsVerificationCount ?></span><?php endif; ?>
    </button>
    <button type="button" class="admin-filter-pill" onclick="setBookingFilter('today', this)">Arriving Today</button>
    <button type="button" class="admin-filter-pill" onclick="setBookingFilter('inhouse', this)">In-House</button>
    <button type="button" class="admin-filter-pill" onclick="setBookingFilter('completed', this)">Completed</button>
    <button type="button" class="admin-filter-pill" onclick="setBookingFilter('cancelled', this)">Cancelled</button>
  </div>

  <?php if (empty($bookings)): ?>
    <p style="font-size: 0.85rem; color: var(--gray); margin-bottom: 2rem;">No reservations yet.</p>
  <?php else: ?>
    <div class="table-responsive">
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
              $isPastStay = ($b['checkout_date'] < $today);
              $isArrivingToday = ($b['checkin_date'] === $today && $b['status'] === 'confirmed');
              $isInHouse = ($b['status'] === 'confirmed' && $today >= $b['checkin_date'] && $today <= $b['checkout_date']);
              $payStatus = $b['payment_status'] ?? 'Pending (Due at Check-in)';
              $isPaid = in_array($payStatus, ['Paid (Online)', 'Paid (Verified)', 'Paid (Front Desk)'], true);
              $needsVerify = ($b['status'] === 'confirmed' && $payStatus === 'Paid (Under Verification)');

              // Tag for JS filtering
              $filterTags = ['all'];
              if ($needsVerify) $filterTags[] = 'verify';
              if ($isArrivingToday) $filterTags[] = 'today';
              if ($isInHouse) $filterTags[] = 'inhouse';
              if ($isPastStay && $b['status'] === 'confirmed') $filterTags[] = 'completed';
              if ($b['status'] === 'cancelled') $filterTags[] = 'cancelled';
            ?>
            <tr data-filter="<?= implode(' ', $filterTags) ?>">
              <td class="cell-nowrap">
                <a href="booking-success.php?id=<?= $b['id'] ?>" target="_blank" style="color: var(--emerald-green); text-decoration: underline;" title="View Voucher Receipt">
                  <strong>#EVR-<?= str_pad($b['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                </a>
              </td>
              <td>
                <strong style="font-size: 0.8rem;"><?= htmlspecialchars($b['guest_name']) ?></strong><br>
                <small style="color: var(--gray); font-size: 0.72rem;"><?= htmlspecialchars($b['guest_email']) ?></small>
              </td>
              <td class="cell-nowrap">
                <strong><?= htmlspecialchars($b['room_name']) ?></strong>
              </td>
              <td class="cell-nowrap" style="font-size: 0.76rem; color: #3d3a30;">
                <?= date('M j', strtotime($b['checkin_date'])) ?> &ndash; <?= date('M j, Y', strtotime($b['checkout_date'])) ?>
              </td>
              <td class="cell-nowrap">
                <strong>&#8369;<?= number_format($b['total_amount'], 2) ?></strong>
              </td>
              <td>
                <small style="color: var(--gray); display: block; font-weight: 600; white-space: nowrap; font-size: 0.7rem; margin-bottom: 2px;">
                  <?= htmlspecialchars($b['payment_method'] ?? 'Pay on Check-in') ?>
                </small>
                <span class="badge" style="background: <?= $isPaid ? 'var(--forest-green)' : 'var(--warm-gold)' ?>; color: #fff;">
                  <?= htmlspecialchars($payStatus) ?>
                </span>
                <?php if (!empty($b['payment_ref'])): ?>
                  <small style="display: block; color: var(--emerald-green); font-family: monospace; font-size: 0.68rem; margin-top: 2px; white-space: nowrap;">
                    Ref: <?= htmlspecialchars($b['payment_ref']) ?>
                  </small>
                <?php endif; ?>
                <?php if (!empty($b['payment_proof'])): ?>
                  <a href="javascript:void(0)" 
                     onclick="openProofModal('<?= htmlspecialchars($b['payment_proof']) ?>', '#EVR-<?= str_pad($b['id'], 5, '0', STR_PAD_LEFT) ?>', '<?= htmlspecialchars($b['payment_ref'] ?? 'N/A') ?>', <?= $b['id'] ?>, <?= $isPaid ? 'true' : 'false' ?>)" 
                     style="display: inline-block; font-size: 0.66rem; font-weight: 700; color: var(--gold-dark); text-decoration: underline; margin-top: 2px; white-space: nowrap;">
                    🔍 Screenshot
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
                <a href="booking-success.php?id=<?= $b['id'] ?>" target="_blank" class="btn-action-view" style="margin-right: 3px;">Receipt</a>

                <?php if ($b['status'] === 'confirmed' && !$isPaid): ?>
                  <a href="function.php?action=confirm-payment&id=<?= $b['id'] ?>" 
                     class="btn btn-green btn-sm" 
                     style="padding: 0.32rem 0.5rem; font-size: 0.66rem; margin-right: 3px;"
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
                  <span style="color: var(--gray); font-size: 0.72rem;">Completed</span>
                <?php else: ?>
                  <span style="color: var(--gray); font-size: 0.72rem;">None</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- Room Rates & Availability Management -->
  <h3 style="color: var(--emerald-green); margin-bottom: 0.8rem; margin-top: 2.5rem;">Room Rates &amp; Catalog Management</h3>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Room Name</th>
          <th>Category</th>
          <th>Status</th>
          <th>Current Rate / Night</th>
          <th>Update Rate</th>
          <th style="text-align: right;">Availability Toggle</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rooms as $room): ?>
          <?php $isAvail = ($room['is_available'] ?? 1) == 1; ?>
          <tr>
            <td><strong><?= htmlspecialchars($room['name']) ?></strong></td>
            <td><?= htmlspecialchars($room['category']) ?></td>
            <td class="cell-nowrap">
              <span class="badge <?= $isAvail ? 'badge-confirmed' : 'badge-cancelled' ?>">
                <?= $isAvail ? 'Available' : 'Maintenance' ?>
              </span>
            </td>
            <td class="cell-nowrap"><strong>&#8369;<?= number_format($room['price_per_night'], 2) ?></strong></td>
            <td>
              <form method="POST" action="function.php" style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                <input type="number" step="50" min="500" name="price_per_night" value="<?= (int)$room['price_per_night'] ?>" class="rate-inline-input" required>
                <button type="submit" name="update-room-rate" class="btn btn-green btn-sm" style="padding: 0.35rem 0.7rem;">Save</button>
              </form>
            </td>
            <td class="cell-nowrap" style="text-align: right;">
              <a href="function.php?action=toggle-room-status&id=<?= $room['id'] ?>" 
                 class="btn <?= $isAvail ? 'btn-sage' : 'btn-gold' ?> btn-sm"
                 style="font-size: 0.68rem; padding: 0.35rem 0.65rem;">
                <?= $isAvail ? 'Set to Maintenance' : 'Set Available' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Registered User Accounts -->
  <h3 style="color: var(--emerald-green); margin-bottom: 0.8rem; margin-top: 2rem;">Registered Accounts</h3>
  <div class="table-responsive">
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
  </div>

  <!-- Guest Reviews Moderation -->
  <h3 style="color: var(--emerald-green); margin-bottom: 0.8rem; margin-top: 2rem;">Guest Feedback Moderation</h3>
  <?php if (empty($reviews)): ?>
    <p style="font-size: 0.85rem; color: var(--gray);">No guest reviews recorded yet.</p>
  <?php else: ?>
    <div class="table-responsive">
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
    </div>
  <?php endif; ?>

</div>

<!-- In-App Payment Proof Lightbox Modal -->
<div id="proofModal" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-head">
      <div>
        <h4 id="modalBookingRef" style="color: var(--emerald-green); font-size: 1.05rem;">Payment Proof Verification</h4>
        <small id="modalPayRef" style="color: var(--gray); font-family: monospace; font-size: 0.78rem;"></small>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeProofModal()">&times;</button>
    </div>
    <div class="modal-body">
      <img id="modalProofImg" src="" alt="Payment Receipt Proof">
    </div>
    <div class="modal-footer" id="modalFooterActions">
      <a href="" id="modalMarkPaidBtn" class="btn btn-green btn-sm" onclick="return confirm('Confirm verified payment for this reservation?');">
        Confirm &amp; Mark Paid
      </a>
      <a href="" id="modalOpenTabBtn" target="_blank" class="btn btn-sage btn-sm">Open Full Image</a>
    </div>
  </div>
</div>

<script>
  let currentActiveFilter = 'all';

  function setBookingFilter(filterKey, element) {
    currentActiveFilter = filterKey;
    document.querySelectorAll('.admin-filter-pill').forEach(btn => btn.classList.remove('active'));
    if (element) element.classList.add('active');
    applyFilters();
  }

  function filterBookingsTable() {
    applyFilters();
  }

  function applyFilters() {
    const searchVal = document.getElementById('bookingSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#bookingsTable tbody tr');

    rows.forEach(row => {
      const rowText = (row.textContent || row.innerText).toLowerCase();
      const rowTags = (row.getAttribute('data-filter') || '').split(' ');

      const matchesFilter = (currentActiveFilter === 'all') || rowTags.includes(currentActiveFilter);
      const matchesSearch = rowText.indexOf(searchVal) > -1;

      row.style.display = (matchesFilter && matchesSearch) ? '' : 'none';
    });
  }

  // Lightbox Modal Handlers
  function openProofModal(imgSrc, bookingRef, payRef, bookingId, isPaid) {
    document.getElementById('modalProofImg').src = imgSrc;
    document.getElementById('modalBookingRef').textContent = 'Audit Proof: ' + bookingRef;
    document.getElementById('modalPayRef').textContent = 'Transaction Ref: ' + payRef;
    document.getElementById('modalOpenTabBtn').href = imgSrc;

    const markPaidBtn = document.getElementById('modalMarkPaidBtn');
    if (isPaid) {
      markPaidBtn.style.display = 'none';
    } else {
      markPaidBtn.style.display = 'inline-flex';
      markPaidBtn.href = 'function.php?action=confirm-payment&id=' + bookingId;
    }

    document.getElementById('proofModal').classList.add('active');
  }

  function closeProofModal() {
    document.getElementById('proofModal').classList.remove('active');
    document.getElementById('modalProofImg').src = '';
  }

  // Close modal on escape or background click
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeProofModal();
  });
  document.getElementById('proofModal').addEventListener('click', (e) => {
    if (e.target.id === 'proofModal') closeProofModal();
  });

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