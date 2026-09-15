<?php
session_start();
require_once 'database/config.php';
require_once 'validation.php';

// Must be signed in to edit booking
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$status    = $_GET['status'] ?? null;
$message   = $_GET['message'] ?? null;
$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userId    = $_SESSION['user_id'];

if (!$bookingId) {
    header('Location: my-bookings.php');
    exit;
}

$pdo = getConnection();

// Verify booking belongs to this user and is active
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = :id AND user_id = :uid AND status = 'confirmed'");
$stmt->execute([':id' => $bookingId, ':uid' => $userId]);
$booking = $stmt->fetch();

// Block access if reservation doesn't exist, is cancelled, or has already started/completed
if (!$booking || $booking['checkin_date'] <= date('Y-m-d')) {
    header('Location: my-bookings.php?status=error&message=' . urlencode('Past or in-progress stays cannot be modified.'));
    exit;
}

$rooms = $pdo->query("SELECT * FROM rooms ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Modify Reservation #EVR-<?= str_pad($booking['id'], 5, '0', STR_PAD_LEFT) ?> — Evercove</title>
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
      <a href="my-bookings.php" class="btn btn-gold btn-sm">My Bookings</a>
      <a href="function.php?action=logout" class="btn btn-sage btn-sm">Log Out</a>
    </div>
  </div>
</header>

<!-- Modify Booking Card -->
<div class="booking-page-wrap">
  <div class="booking-card-expanded">
    <div style="text-align: center; margin-bottom: 2rem;">
      <span class="eyebrow eyebrow-green">Modify Stay</span>
      <h2 style="color: var(--emerald-green); font-size: 2rem; margin-top: 0.3rem;">
        Reservation #EVR-<?= str_pad($booking['id'], 5, '0', STR_PAD_LEFT) ?>
      </h2>
      <p style="color: var(--gray); font-size: 0.95rem; margin-top: 0.4rem;">
        Update your room, dates, or party size, and upload updated payment proof.
      </p>
    </div>

    <form method="POST" action="function.php" enctype="multipart/form-data">
      <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">

      <div class="booking-form-grid">
        <div class="booking-input-group full-width">
          <label for="room_id">Select Room / Suite</label>
          <select id="room_id" name="room_id" onchange="updateGuestLimit()" required>
            <?php foreach ($rooms as $r): ?>
              <?php $roomMax = getRoomMaxGuests($r['category']); ?>
              <option value="<?= $r['id'] ?>" 
                      data-max="<?= $roomMax ?>" 
                      <?= (int)$booking['room_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['category']) ?> — Max <?= $roomMax ?>) — ₱<?= number_format($r['price_per_night']) ?> / night
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="booking-input-group">
          <label for="checkin">Check In Date</label>
          <input type="date" id="checkin" name="checkin" value="<?= htmlspecialchars($booking['checkin_date']) ?>" min="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="booking-input-group">
          <label for="checkout">Check Out Date</label>
          <input type="date" id="checkout" name="checkout" value="<?= htmlspecialchars($booking['checkout_date']) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
        </div>

        <div class="booking-input-group full-width">
          <label for="guests" id="guest-label">Number of Guests</label>
          <input type="number" id="guests" name="guests" value="<?= htmlspecialchars($booking['guests_count']) ?>" min="1" max="6" required>
        </div>

        <!-- Payment Method Selection -->
        <div class="booking-input-group">
          <label for="payment_method">Payment Method</label>
          <select id="payment_method" name="payment_method" required>
            <option value="GCash (Online)" <?= ($booking['payment_method'] === 'GCash (Online)') ? 'selected' : '' ?>>GCash (Online)</option>
            <option value="Card (Online)" <?= ($booking['payment_method'] === 'Card (Online)') ? 'selected' : '' ?>>Card / Bank Transfer</option>
            <option value="Pay on Check-in" <?= ($booking['payment_method'] === 'Pay on Check-in') ? 'selected' : '' ?>>Pay on Check-in</option>
          </select>
        </div>

        <!-- Payment Reference Number -->
        <div class="booking-input-group">
          <label for="payment_ref">Payment Reference Number</label>
          <input type="text" id="payment_ref" name="payment_ref" value="<?= htmlspecialchars($booking['payment_ref'] ?? '') ?>" placeholder="e.g. 901234567890">
        </div>

        <!-- Mandatory Screenshot Upload on Modification -->
        <div class="booking-input-group full-width proof-upload-zone" style="margin-top: 0.5rem;">
          <label for="payment_proof">Upload Updated Payment Screenshot *</label>
          <?php if (!empty($booking['payment_proof'])): ?>
            <small style="display: block; margin-bottom: 0.5rem; color: var(--gray); font-size: 0.76rem;">
              Current file on record: <a href="<?= htmlspecialchars($booking['payment_proof']) ?>" target="_blank" style="color: var(--gold-dark); text-decoration: underline; font-weight: 600;">View previous receipt</a>
            </small>
          <?php endif; ?>
          <input type="file" id="payment_proof" name="payment_proof" class="file-input-custom" accept="image/png, image/jpeg, image/jpg, image/webp" required onchange="previewImage(this, 'preview-edit-proof')">
          <img id="preview-edit-proof" class="preview-thumbnail" alt="Updated Receipt Preview">
        </div>
      </div>

      <div style="display: flex; gap: 1rem; margin-top: 2rem;">
        <button type="submit" name="update-booking" class="btn btn-gold" style="flex: 1; padding: 1rem;">
          Save &amp; Update Reservation
        </button>
        <a href="my-bookings.php" class="btn btn-green" style="padding: 1rem 1.8rem;">
          Back to Bookings
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Footer -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-bottom">
      <span>&copy; <?php echo date("Y"); ?> Evercove Inn &amp; Suites. All rights reserved.</span>
    </div>
  </div>
</footer>

<script>
  function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    } else {
      preview.style.display = 'none';
    }
  }

  function updateGuestLimit() {
    const roomSelect = document.getElementById('room_id');
    const guestInput = document.getElementById('guests');
    const guestLabel = document.getElementById('guest-label');
    if (!roomSelect || !guestInput) return;

    const selectedOption = roomSelect.options[roomSelect.selectedIndex];
    if (!selectedOption) return;

    const max = parseInt(selectedOption.getAttribute('data-max') || '6', 10);
    guestInput.max = max;

    if (guestLabel) {
      guestLabel.textContent = `Number of Guests (Max ${max})`;
    }

    if (parseInt(guestInput.value, 10) > max) {
      guestInput.value = max;
    }
  }

  function dismissAlert() {
    const alert = document.getElementById('alert-banner');
    if (alert) alert.style.display = 'none';
  }

  window.addEventListener('DOMContentLoaded', () => {
    updateGuestLimit();

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