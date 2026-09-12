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

    <form method="POST" action="function.php" enctype="multipart/form-data">
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
            
            <!-- Pay on Check-in -->
            <label class="payment-card active" onclick="selectPayment(this, 'checkin')">
              <input type="radio" name="payment_method" value="Pay on Check-in" checked>
              <div class="payment-icon-wrap">
                <span class="payment-emoji">🏨</span>
              </div>
              <div class="payment-title">Pay on Check-in</div>
              <div class="payment-sub">Cash / Card at front desk</div>
            </label>

            <!-- GCash -->
            <label class="payment-card" onclick="selectPayment(this, 'gcash')">
              <input type="radio" name="payment_method" value="GCash (Online)">
              <div class="payment-icon-wrap">
                <img src="images/gcash-logo.png" alt="GCash" onerror="this.onerror=null;this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/5/52/GCash_logo.svg/320px-GCash_logo.svg.png';">
              </div>
              <div class="payment-title">GCash Express</div>
              <div class="payment-sub">Scan QR or Send Money</div>
            </label>

            <!-- Credit / Debit Card -->
            <label class="payment-card" onclick="selectPayment(this, 'card')">
              <input type="radio" name="payment_method" value="Card (Online)">
              <div class="payment-icon-wrap">
                <span class="payment-emoji">💳</span>
              </div>
              <div class="payment-title">Bank / Card</div>
              <div class="payment-sub">Direct Bank Deposit</div>
            </label>
          </div>

          <!-- Pay on Check-in Details Box -->
          <div class="payment-details-box active" id="box-checkin">
            <div style="display: flex; align-items: center; gap: 0.8rem;">
              <span style="font-size: 1.5rem;">🛎️</span>
              <div>
                <strong style="color: var(--emerald-green); font-size: 0.95rem;">Pay Upon Arrival</strong>
                <p style="font-size: 0.82rem; color: var(--gray); margin-top: 0.2rem;">
                  No advance payment required today. Your reservation will be confirmed and settled at our Valencia front desk via cash, credit, or debit card upon check-in.
                </p>
              </div>
            </div>
          </div>

          <!-- GCash Details & Verification Upload Box -->
          <div class="payment-details-box" id="box-gcash">
            <div class="company-bank-badge">
              <div style="display:flex; align-items:center; gap: 0.6rem;">
                <img src="images/gcash-logo.png" alt="GCash" style="height: 22px;" onerror="this.onerror=null;this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/5/52/GCash_logo.svg/320px-GCash_logo.svg.png';">
                <strong style="color: var(--emerald-green); font-size: 0.92rem;">Official Evercove GCash Account</strong>
              </div>
              <span class="badge badge-confirmed">Verified Merchant</span>
            </div>

            <div class="company-info-row">
              <div>
                <span>Account Name</span>
                <strong>Evercove Inn &amp; Suites</strong>
              </div>
              <div>
                <span>GCash Mobile Number</span>
                <strong>0928 855 7646</strong>
              </div>
            </div>

            <p style="font-size: 0.78rem; color: var(--gray); line-height: 1.4;">
              <strong>Step 1:</strong> Send payment using your GCash app to <strong>0928 855 7646</strong>.<br>
              <strong>Step 2:</strong> Enter your 13-digit Reference Number and attach a screenshot of your payment receipt below.
            </p>

            <div class="proof-upload-zone">
              <div class="booking-input-group" style="margin-bottom: 0.9rem;">
                <label for="gcash_ref">GCash Reference Number</label>
                <input type="text" id="gcash_ref" name="gcash_ref" placeholder="e.g. 901234567890" maxlength="30">
              </div>

              <label for="gcash_proof">Upload GCash Payment Screenshot</label>
              <input type="file" id="gcash_proof" name="gcash_proof" class="file-input-custom" accept="image/png, image/jpeg, image/jpg, image/webp" onchange="previewImage(this, 'preview-gcash')">
              <img id="preview-gcash" class="preview-thumbnail" alt="Receipt Preview">
            </div>
          </div>

          <!-- Bank / Card Details & Verification Box -->
          <div class="payment-details-box" id="box-card">
            <div class="company-bank-badge">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 1.3rem;">💳</span>
                <strong style="color: var(--emerald-green); font-size: 0.92rem;">Official Corporate Bank Account (BPI)</strong>
              </div>
              <span class="badge badge-confirmed">Online Banking</span>
            </div>

            <div class="company-info-row">
              <div>
                <span>Bank Partner</span>
                <strong>BPI (Bank of the Philippine Islands)</strong>
              </div>
              <div>
                <span>Account Name</span>
                <strong>Evercove Inn &amp; Suites Inc.</strong>
              </div>
              <div>
                <span>Account Number</span>
                <strong>9219-4214-16</strong>
              </div>
              <div>
                <span>Account Type</span>
                <strong>Corporate Savings</strong>
              </div>
            </div>

            <p style="font-size: 0.78rem; color: var(--gray); line-height: 1.4;">
              Transfer funds to BPI Account <strong>9219-4214-16</strong> via BPI Online, Vybe, InstaPay, or Pesonet. Provide the transaction number and confirmation screenshot below.
            </p>

            <div class="proof-upload-zone">
              <div class="booking-input-group" style="margin-bottom: 0.9rem;">
                <label for="card_ref">BPI / Transfer Reference Number</label>
                <input type="text" id="card_ref" name="card_ref" placeholder="e.g. FT-982341908" maxlength="40">
              </div>

              <label for="card_proof">Upload Bank Transfer Slip / Confirmation</label>
              <input type="file" id="card_proof" name="card_proof" class="file-input-custom" accept="image/png, image/jpeg, image/jpg, image/webp" onchange="previewImage(this, 'preview-card')">
              <img id="preview-card" class="preview-thumbnail" alt="Deposit Slip Preview">
            </div>
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

    document.querySelectorAll('.payment-details-box').forEach(b => b.classList.remove('active'));
    const targetBox = document.getElementById('box-' + mode);
    if (targetBox) targetBox.classList.add('active');

    const gcashRef = document.getElementById('gcash_ref');
    const gcashProof = document.getElementById('gcash_proof');
    const cardRef = document.getElementById('card_ref');
    const cardProof = document.getElementById('card_proof');

    if (mode === 'gcash') {
      if (gcashRef) gcashRef.required = true;
      if (gcashProof) gcashProof.required = true;
      if (cardRef) cardRef.required = false;
      if (cardProof) cardProof.required = false;
    } else if (mode === 'card') {
      if (cardRef) cardRef.required = true;
      if (cardProof) cardProof.required = true;
      if (gcashRef) gcashRef.required = false;
      if (gcashProof) gcashProof.required = false;
    } else {
      if (gcashRef) gcashRef.required = false;
      if (gcashProof) gcashProof.required = false;
      if (cardRef) cardRef.required = false;
      if (cardProof) cardProof.required = false;
    }
  }

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