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
$revStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total_rev FROM bookings WHERE status IN ('confirmed', 'completed')");
$totalRevenue = (float)$revStmt->fetchColumn();

// Only count paid stays as active occupancy
$activeStmt = $pdo->query("
    SELECT COUNT(*) FROM bookings 
    WHERE status = 'confirmed' 
      AND payment_status IN ('Paid (Online)', 'Paid (Verified)', 'Paid (Front Desk)')
      AND CURRENT_DATE BETWEEN checkin_date AND checkout_date
");
$activeStays = (int)$activeStmt->fetchColumn();

$confirmedCountStmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed', 'completed')");
$totalBookings = (int)$confirmedCountStmt->fetchColumn();

$ratingStmt = $pdo->query("SELECT AVG(rating) AS avg_score, COUNT(*) AS count FROM reviews");
$ratingData = $ratingStmt->fetch();
$avgRating = ($ratingData && $ratingData['count'] > 0) ? number_format((float)$ratingData['avg_score'], 1) : '5.0';

// 2. Occupancy & Operations
$totalRoomsCount = (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occupancyRate = $totalRoomsCount > 0 ? round(($activeStays / $totalRoomsCount) * 100) : 0;

// Box 1: Scheduled arrivals today
$todayCheckins = $pdo->query("
    SELECT b.*, r.name AS room_name 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.checkin_date = CURRENT_DATE AND b.status = 'confirmed'
    ORDER BY b.id DESC
");
$arrivals = $todayCheckins->fetchAll();

// Box 2: Scheduled departures today
$todayCheckouts = $pdo->query("
    SELECT b.*, r.name AS room_name 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.checkout_date = CURRENT_DATE AND b.status = 'confirmed'
    ORDER BY b.id DESC
");
$departures = $todayCheckouts->fetchAll();

// Box 3: Active in-house stays (STRICT: Must be PAID and staying past today)
$inHouseStmt = $pdo->query("
    SELECT b.*, r.name AS room_name 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.status = 'confirmed' 
      AND b.payment_status IN ('Paid (Online)', 'Paid (Verified)', 'Paid (Front Desk)')
      AND CURRENT_DATE >= b.checkin_date 
      AND CURRENT_DATE < b.checkout_date
    ORDER BY b.checkout_date ASC
");
$inHouseGuests = $inHouseStmt->fetchAll();

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
      <small>Confirmed &amp; completed stays</small>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Occupancy Rate</span>
      <div class="kpi-number"><?= $occupancyRate ?>%</div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill" style="width: <?= min(100, $occupancyRate) ?>%;"></div>
      </div>
      <small><?= $activeStays ?> of <?= $totalRoomsCount ?> rooms active (paid)</small>
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

  <!-- Operations Today Panel: 3 Columns -->
  <div class="ops-panel">
    <!-- Box 1: Arrivals Today (Clean without badge box) -->
    <div class="ops-col">
      <div class="ops-title">
        <span class="ops-dot arrival-dot"></span>
        <h4>Today's Arrivals (<?= count($arrivals) ?>)</h4>
      </div>
      <?php if (empty($arrivals)): ?>
        <p class="ops-empty">No scheduled check-ins today.</p>
      <?php else: ?>
        <div class="ops-list">
          <?php foreach ($arrivals as $arr): ?>
            <div class="ops-item">
              <div>
                <strong><?= htmlspecialchars($arr['guest_name']) ?></strong>
                <small><?= htmlspecialchars($arr['room_name']) ?> &bull; #EVR-<?= str_pad($arr['id'], 5, '0', STR_PAD_LEFT) ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Box 2: Departures Today -->
    <div class="ops-col">
      <div class="ops-title">
        <span class="ops-dot departure-dot"></span>
        <h4>Departures Today (<?= count($departures) ?>)</h4>
      </div>
      <?php if (empty($departures)): ?>
        <p class="ops-empty">No scheduled check-outs today.</p>
      <?php else: ?>
        <div class="ops-list">
          <?php foreach ($departures as $dep): ?>
            <div class="ops-item" id="dep-item-<?= $dep['id'] ?>">
              <div>
                <strong><?= htmlspecialchars($dep['guest_name']) ?></strong>
                <small><?= htmlspecialchars($dep['room_name']) ?> &bull; #EVR-<?= str_pad($dep['id'], 5, '0', STR_PAD_LEFT) ?></small>
              </div>
              <a href="function.php?action=checkout-booking&id=<?= $dep['id'] ?>" 
                 class="btn btn-gold btn-sm" 
                 style="font-size: 0.66rem; padding: 0.3rem 0.6rem;"
                 onclick="return confirm('Complete check-out for <?= htmlspecialchars(addslashes($dep['guest_name'])) ?>?');">
                Check Out
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Box 3: Active In-House Guests (Only Shows PAID Guests) -->
    <div class="ops-col">
      <div class="ops-title">
        <span class="ops-dot inhouse-dot"></span>
        <h4 id="inhouse-header-count">Active In-House (<?= count($inHouseGuests) ?>)</h4>
      </div>
      <div class="ops-list" id="inhouse-ops-list">
        <?php if (empty($inHouseGuests)): ?>
          <p class="ops-empty" id="inhouse-empty-msg">No active paid guests staying past today.</p>
        <?php else: ?>
          <?php foreach ($inHouseGuests as $ih): ?>
            <div class="ops-item" id="ih-item-<?= $ih['id'] ?>">
              <div>
                <strong><?= htmlspecialchars($ih['guest_name']) ?></strong>
                <small><?= htmlspecialchars($ih['room_name']) ?> &bull; Dep: <?= date('M j', strtotime($ih['checkout_date'])) ?></small>
              </div>
              <a href="function.php?action=early-checkout&id=<?= $ih['id'] ?>" 
                 class="btn btn-sage btn-sm" 
                 style="font-size: 0.66rem; padding: 0.3rem 0.6rem;"
                 onclick="return confirm('Process early check-out for <?= htmlspecialchars(addslashes($ih['guest_name'])) ?>? Room will be released today.');">
                Check Out
              </a>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
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
      Needs Verification <?php if ($needsVerificationCount > 0): ?><span class="filter-count-badge" id="verifyBadgeCount"><?= $needsVerificationCount ?></span><?php endif; ?>
    </button>
    <button type="button" class="admin-filter-pill" onclick="setBookingFilter('today', this)">Arriving Today</button>
    <button type="button" class="admin-filter-pill" onclick="setBookingFilter('inhouse', this)">In-House (Paid)</button>
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
              $payStatus = $b['payment_status'] ?? 'Pending (Due at Check-in)';
              $isPaid = in_array($payStatus, ['Paid (Online)', 'Paid (Verified)', 'Paid (Front Desk)'], true);
              $isPastStay = ($b['checkout_date'] < $today || $b['status'] === 'completed');
              $isArrivingToday = ($b['checkin_date'] === $today && $b['status'] === 'confirmed');
              $isInHouse = ($b['status'] === 'confirmed' && $isPaid && $today >= $b['checkin_date'] && $today <= $b['checkout_date']);
              $needsVerify = ($b['status'] === 'confirmed' && $payStatus === 'Paid (Under Verification)');

              $filterTags = ['all'];
              if ($needsVerify) $filterTags[] = 'verify';
              if ($isArrivingToday) $filterTags[] = 'today';
              if ($isInHouse) $filterTags[] = 'inhouse';
              if ($isPastStay) $filterTags[] = 'completed';
              if ($b['status'] === 'cancelled') $filterTags[] = 'cancelled';
            ?>
            <tr data-filter="<?= implode(' ', $filterTags) ?>" data-booking-id="<?= $b['id'] ?>" data-checkin="<?= $b['checkin_date'] ?>" data-checkout="<?= $b['checkout_date'] ?>">
              <td class="cell-nowrap">
                <a href="booking-success.php?id=<?= $b['id'] ?>" style="color: var(--emerald-green); text-decoration: underline;" title="View Voucher Receipt">
                  <strong>#EVR-<?= str_pad($b['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                </a>
              </td>
              <td>
                <strong style="font-size: 0.8rem;" class="guest-name-val"><?= htmlspecialchars($b['guest_name']) ?></strong><br>
                <small style="color: var(--gray); font-size: 0.72rem;"><?= htmlspecialchars($b['guest_email']) ?></small>
              </td>
              <td class="cell-nowrap">
                <strong class="room-name-val"><?= htmlspecialchars($b['room_name']) ?></strong>
              </td>
              <td class="cell-nowrap" style="font-size: 0.76rem; color: #3d3a30;">
                <span class="booking-dates-display"><?= date('M j', strtotime($b['checkin_date'])) ?> &ndash; <?= date('M j, Y', strtotime($b['checkout_date'])) ?></span>
              </td>
              <td class="cell-nowrap">
                <strong class="booking-total-display">&#8369;<?= number_format($b['total_amount'], 2) ?></strong>
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
                <a href="booking-success.php?id=<?= $b['id'] ?>" class="btn-action-view" style="margin-right: 3px;">Receipt</a>

                <?php if ($b['status'] === 'confirmed' && !$isPaid): ?>
                  <a href="function.php?action=confirm-payment&id=<?= $b['id'] ?>" 
                     class="btn btn-green btn-sm btn-mark-paid" 
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
                <?php elseif ($isPastStay && $b['status'] !== 'cancelled'): ?>
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

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeProofModal();
  });
  document.getElementById('proofModal').addEventListener('click', (e) => {
    if (e.target.id === 'proofModal') closeProofModal();
  });

  function showBanner(message, type = 'success') {
    let alert = document.getElementById('alert-banner');
    if (!alert) {
      alert = document.createElement('div');
      alert.id = 'alert-banner';
      alert.className = 'system-alert';
      alert.innerHTML = `<span></span><button type="button" class="alert-close" onclick="dismissAlert()">&times;</button>`;
      document.querySelector('.admin-wrap').prepend(alert);
    }
    alert.className = `system-alert ${type === 'success' ? 'alert-success' : 'alert-error'}`;
    alert.querySelector('span').textContent = message;
    alert.style.display = 'flex';
    setTimeout(dismissAlert, 3500);
  }

  function dismissAlert() {
    const alert = document.getElementById('alert-banner');
    if (alert) alert.style.display = 'none';
  }

  // Intercept Admin Clicks (Mark Paid, Check Out, Cancel, Toggle Room, Delete Review) without reload
  document.addEventListener('click', async function(e) {
    const link = e.target.closest('a[href*="function.php?action="]');
    if (!link) return;

    if (e.defaultPrevented) return;

    const href = link.getAttribute('href');
    if (href.includes('action=logout')) return;

    e.preventDefault();

    try {
      const response = await fetch(href + '&ajax=1', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const result = await response.json();

      if (result.status === 'success') {
        showBanner(result.message, 'success');
        const row = link.closest('tr');

        // 1. Confirm Payment UI update: Transitions Guest to Paid & In-House
        if (href.includes('action=confirm-payment')) {
          link.remove();
          closeProofModal();

          const bookingIdMatch = href.match(/id=(\d+)/);
          const bookingId = bookingIdMatch ? bookingIdMatch[1] : null;
          const targetRow = row || (bookingId ? document.querySelector(`tr[data-booking-id="${bookingId}"]`) : null);

          if (targetRow) {
            const payBadge = targetRow.querySelector('td:nth-child(6) .badge');
            if (payBadge) {
              payBadge.textContent = 'Paid (Verified)';
              payBadge.style.background = 'var(--forest-green)';
            }
            
            const cin = targetRow.getAttribute('data-checkin');
            const cout = targetRow.getAttribute('data-checkout');
            const todayStr = '<?= $today ?>';

            // Tag row as inhouse if stay is active
            let tags = (targetRow.getAttribute('data-filter') || '').split(' ');
            tags = tags.filter(t => t !== 'verify');
            if (todayStr >= cin && todayStr <= cout) {
              if (!tags.includes('inhouse')) tags.push('inhouse');
            }
            targetRow.setAttribute('data-filter', tags.join(' '));

            // Decrement Needs Verification badge
            const countBadge = document.getElementById('verifyBadgeCount');
            if (countBadge) {
              const currentVal = parseInt(countBadge.textContent, 10) - 1;
              if (currentVal <= 0) countBadge.remove();
              else countBadge.textContent = currentVal;
            }

            // Dynamically inject into Active In-House Box if staying past today
            if (todayStr >= cin && todayStr < cout) {
              const ihList = document.getElementById('inhouse-ops-list');
              const emptyMsg = document.getElementById('inhouse-empty-msg');
              if (emptyMsg) emptyMsg.remove();

              if (ihList && !document.getElementById(`ih-item-${bookingId}`)) {
                const guestName = targetRow.querySelector('.guest-name-val')?.textContent || 'Guest';
                const roomName = targetRow.querySelector('.room-name-val')?.textContent || 'Room';
                const depDateFormatted = targetRow.querySelector('.booking-dates-display')?.textContent.split('–')[1] || cout;

                const newIhItem = document.createElement('div');
                newIhItem.className = 'ops-item';
                newIhItem.id = `ih-item-${bookingId}`;
                newIhItem.innerHTML = `
                  <div>
                    <strong>${guestName}</strong>
                    <small>${roomName} &bull; Dep: ${depDateFormatted.trim()}</small>
                  </div>
                  <a href="function.php?action=early-checkout&id=${bookingId}" 
                     class="btn btn-sage btn-sm" 
                     style="font-size: 0.66rem; padding: 0.3rem 0.6rem;"
                     onclick="return confirm('Process early check-out for ${guestName}? Room will be released today.');">
                    Check Out
                  </a>
                `;
                ihList.appendChild(newIhItem);

                const ihHeader = document.getElementById('inhouse-header-count');
                if (ihHeader) {
                  const currentCount = ihList.querySelectorAll('.ops-item').length;
                  ihHeader.textContent = `Active In-House (${currentCount})`;
                }
              }
            }
          }
        }

        // 2. Check Out & Early Check-Out UI update
        if (href.includes('action=checkout-booking') || href.includes('action=early-checkout')) {
          const opsItem = link.closest('.ops-item');
          if (opsItem) {
            opsItem.remove();
            
            const ihList = document.getElementById('inhouse-ops-list');
            if (ihList && ihList.querySelectorAll('.ops-item').length === 0) {
              ihList.innerHTML = '<p class="ops-empty" id="inhouse-empty-msg">No active paid guests staying past today.</p>';
            }
            const ihHeader = document.getElementById('inhouse-header-count');
            if (ihHeader && ihList) {
              ihHeader.textContent = `Active In-House (${ihList.querySelectorAll('.ops-item').length})`;
            }
          }

          const bookingIdMatch = href.match(/id=(\d+)/);
          const targetRow = bookingIdMatch ? document.querySelector(`tr[data-booking-id="${bookingIdMatch[1]}"]`) : null;

          if (targetRow) {
            const statusCell = targetRow.querySelector('td:nth-child(7)');
            if (statusCell) statusCell.innerHTML = '<span class="badge badge-completed">completed</span>';
            targetRow.setAttribute('data-filter', 'all completed');

            const cancelBtn = targetRow.querySelector('.btn-action-cancel');
            if (cancelBtn) cancelBtn.remove();
          }
        }

        // 3. Cancel Reservation UI update
        if (href.includes('action=cancel-booking')) {
          link.remove();
          if (row) {
            const statusCell = row.querySelector('td:nth-child(7)');
            if (statusCell) statusCell.innerHTML = '<span class="badge badge-cancelled">cancelled</span>';
            row.setAttribute('data-filter', 'all cancelled');
          }
        }

        // 4. Toggle Room Availability UI update
        if (href.includes('action=toggle-room-status')) {
          if (row) {
            const statusBadge = row.querySelector('td:nth-child(3) .badge');
            const isCurrentlyAvail = statusBadge && statusBadge.textContent.trim().toLowerCase() === 'available';

            if (isCurrentlyAvail) {
              statusBadge.className = 'badge badge-cancelled';
              statusBadge.textContent = 'Maintenance';
              link.className = 'btn btn-gold btn-sm';
              link.textContent = 'Set Available';
            } else {
              statusBadge.className = 'badge badge-confirmed';
              statusBadge.textContent = 'Available';
              link.className = 'btn btn-sage btn-sm';
              link.textContent = 'Set to Maintenance';
            }
          }
        }

        // 5. Delete Review UI update
        if (href.includes('action=delete-review')) {
          if (row) row.remove();
        }
      } else {
        showBanner(result.message, 'error');
      }
    } catch (err) {
      showBanner('Failed to process request. Please try again.', 'error');
    }
  });

  // Intercept Inline Room Rate Form Submissions without page reload
  document.addEventListener('submit', async function(e) {
    const form = e.target.closest('form[action="function.php"]');
    if (!form || !form.querySelector('button[name="update-room-rate"]')) return;

    e.preventDefault();
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('update-room-rate', '1');

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const result = await response.json();

      if (result.status === 'success') {
        showBanner(result.message, 'success');
        const row = form.closest('tr');
        const newRate = parseFloat(form.querySelector('input[name="price_per_night"]').value);
        if (row && !isNaN(newRate)) {
          const rateCell = row.querySelector('td:nth-child(4) strong');
          if (rateCell) {
            rateCell.innerHTML = '&#8369;' + newRate.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          }
        }
      } else {
        showBanner(result.message, 'error');
      }
    } catch (err) {
      showBanner('Could not save room rate. Please try again.', 'error');
    }
  });

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