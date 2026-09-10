<?php
session_start();
require_once 'database/config.php';
require_once 'validation.php';

// Must be signed in to book
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?status=error&message=' . urlencode('Please sign in or create an account to book a stay.'));
    exit;
}

// Admin cannot make guest bookings
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin.php?status=error&message=' . urlencode('Administrator accounts cannot make room reservations. Please sign in with a guest account.'));
    exit;
}

$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;

// Read query values from homepage search bar
$selectedRoomId = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);
$checkinParam   = filter_input(INPUT_GET, 'checkin', FILTER_DEFAULT);
$checkoutParam  = filter_input(INPUT_GET, 'checkout', FILTER_DEFAULT);
$guestsParam    = filter_input(INPUT_GET, 'guests', FILTER_VALIDATE_INT);

try {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM rooms ORDER BY id ASC");
    $rooms = $stmt->fetchAll();
} catch (Exception $e) {
    $rooms = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reserve Your Stay — Evercove Inn & Suites</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
  <style>
    .payment-option-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.8rem;
      margin-top: 0.4rem;
    }
    .payment-card {
      border: 1.5px solid rgba(188, 148, 86, 0.35);
      border-radius: 8px;
      padding: 0.9rem;
      text-align: center;
      background: #ffffff;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .payment-card:hover {
      border-color: var(--emerald-green);
    }
    .payment-card.active {
      border-color: var(--emerald-green);
      background: rgba(39, 77, 58, 0.06);
      box-shadow: 0 0 0 2px var(--emerald-green);
    }
    .payment-card input[type="radio"] {
      display: none;
    }
    .payment-icon {
      font-size: 1.3rem;
      margin-bottom: 0.2rem;
    }
    .payment-title {
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--emerald-green);
    }
    .payment-sub {
      font-size: 0.68rem;
      color: var(--gray);
      margin-top: 0.15rem;
    }
    .payment-instructions {
      background: #fbf5ee;
      border: 1px dashed rgba(188, 148, 86, 0.5);
      border-radius: 6px;
      padding: 0.85rem 1rem;
      font-size: 0.8rem;
      color: var(--charcoal);
      margin-top: 0.8rem;
    }
  </style>
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
      <a href="booking.php" class="btn btn-green">Book Your Stay</a>
    </div>
  </div>
</header>

<!-- Expanded Booking Form Card -->
<div class="booking-page-wrap">
  <div class="booking-card-expanded">
    <div style="text-align: center; margin-bottom: 2rem;">
      <span class="eyebrow eyebrow-green">Direct Reservation</span>
      <h2 style="color: var(--emerald-green); font-size: 2.2rem; margin-top: 0.3rem;">Find Your Haven in the Hills</h2>
      <p style="color: var(--gray); font-size: 0.95rem; margin-top: 0.4rem;">Select your preferred dates, room, and payment method below.</p>
    </div>

    <form method="POST" action="function.php">
      <div class="booking-form-grid">
        <div class="booking-input-group full-width">
          <label for="room_id">Select Room / Suite</label>
          <select id="room_id" name="room_id" onchange="updateGuestLimit()" required>
            <?php foreach ($rooms as $r): ?>
              <?php $roomMax = getRoomMaxGuests($r['category']); ?>
              <option value="<?= $r['id'] ?>" 
                      data-max="<?= $roomMax ?>" 
                      <?= $selectedRoomId === (int)$r['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['category']) ?> — Max <?= $roomMax ?>) — ₱<?= number_format($r['price_per_night']) ?> / night
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="booking-input-group">
          <label for="checkin">Check In Date</label>
          <input type="date" id="checkin" name="checkin" value="<?= htmlspecialchars($checkinParam ?: date('Y-m-d')) ?>" min="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="booking-input-group">
          <label for="checkout">Check Out Date</label>
          <input type="date" id="checkout" name="checkout" value="<?= htmlspecialchars($checkoutParam ?: date('Y-m-d', strtotime('+2 days'))) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
        </div>

        <div class="booking-input-group full-width">
          <label for="guests" id="guest-label">Number of Guests</label>
          <input type="number" id="guests" name="guests" value="<?= $guestsParam ?: 2 ?>" min="1" max="6" required>
        </div>

        <!-- Payment Method Selector -->
        <div class="booking-input-group full-width">
          <label>Select Payment Option</label>
          <div class="payment-option-grid">
            <label class="payment-card active" onclick="selectPayment(this, 'checkin')">
              <input type="radio" name="payment_method" value="Pay on Check-in" checked>
              <div class="payment-icon">🏨</div>
              <div class="payment-title">Pay on Check-in</div>
              <div class="payment-sub">Cash / Card at desk</div>
            </label>

            <label class="payment-card" onclick="selectPayment(this, 'gcash')">
              <input type="radio" name="payment_method" value="GCash (Online)">
              <div class="payment-icon">📱</div>
              <div class="payment-title">GCash</div>
              <div class="payment-sub">Instant e-Wallet</div>
            </label>

            <label class="payment-card" onclick="selectPayment(this, 'card')">
              <input type="radio" name="payment_method" value="Card (Online)">
              <div class="payment-icon">💳</div>
              <div class="payment-title">Credit / Debit</div>
              <div class="payment-sub">Visa / Mastercard</div>
            </label>
          </div>

          <div class="payment-instructions" id="payment-note">
            Settle your bill directly at our Valencia front desk when you arrive. No advance payment required today.
          </div>
        </div>
      </div>

      <div class="booking-info-banner">
        <span>Reserving for: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> (<?= htmlspecialchars($_SESSION['email']) ?>)</span>
        <span>Best Rate Guaranteed</span>
      </div>

      <input type="hidden" name="guest_name" value="<?= htmlspecialchars($_SESSION['username']) ?>">
      <input type="hidden" name="guest_email" value="<?= htmlspecialchars($_SESSION['email']) ?>">

      <button type="submit" name="book-stay" class="btn btn-gold" style="width: 100%; margin-top: 1.8rem; padding: 1.1rem; font-size: 0.92rem;">
        Confirm &amp; Reserve Stay
      </button>
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
  function selectPayment(cardElement, mode) {
    document.querySelectorAll('.payment-card').forEach(c => c.classList.remove('active'));
    cardElement.classList.add('active');
    cardElement.querySelector('input[type="radio"]').checked = true;

    const note = document.getElementById('payment-note');
    if (mode === 'checkin') {
      note.textContent = "Settle your bill directly at our Valencia front desk when you arrive. No advance payment required today.";
    } else if (mode === 'gcash') {
      note.innerHTML = "<strong>GCash Online:</strong> Reservation will be marked <strong>Paid (Online)</strong>. Simulated transaction reference: <code>#GC-" + Math.floor(100000 + Math.random() * 900000) + "</code>.";
    } else if (mode === 'card') {
      note.innerHTML = "<strong>Card Online:</strong> Secured via encrypted mock gateway. Reservation will be confirmed as <strong>Paid (Online)</strong> immediately.";
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