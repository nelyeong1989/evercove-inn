<?php
session_start();
require_once 'database/config.php';

$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;

try {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM rooms ORDER BY id ASC");
    $rooms = $stmt->fetchAll();
} catch (Exception $e) {
    $rooms = [];
}

// Room inclusions
function getRoomInclusions(string $category): array {
    switch (trim($category)) {
        case 'Single Room':
        case 'Single':
            return [
                'Queen-size plush bed',
                'Complimentary breakfast',
                'Nightly firepit lounge',
                'High-speed Wi-Fi desk'
            ];
        case 'Cabin Room':
        case 'Cabin':
            return [
                'Timber King-size bed',
                'Complimentary breakfast',
                'Private forest balcony',
                'Vinyl room access'
            ];
        case 'Suite':
        default:
            return [
                'Expansive master King suite',
                'Complimentary breakfast',
                'Soaking tub & double vanity',
                'Panoramic private terrace'
            ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Rooms &amp; Suites — Evercove Inn & Suites</title>
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
      <?php if (isset($_SESSION['username'])): ?>
        <span class="user-greeting">Hi, <?= htmlspecialchars($_SESSION['username']) ?></span>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <a href="admin.php" class="btn btn-green">Management Console</a>
        <?php else: ?>
          <a href="my-bookings.php" class="btn btn-gold btn-sm">My Bookings</a>
          <a href="booking.php" class="btn btn-green">Book Your Stay</a>
        <?php endif; ?>

        <a href="function.php?action=logout" class="btn btn-sage btn-sm">Log Out</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-green btn-sm">Sign In</a>
        <a href="register.php" class="btn btn-gold btn-sm">Register</a>
        <a href="booking.php" class="btn btn-green">Book Your Stay</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- Rooms Catalog Section -->
<section class="section section-rooms">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Where You'll Stay</span>
      <h2>Our Most-Loved Rooms</h2>
      <p>Eight intentional room layouts, each built around fewer distractions and better company.</p>
    </div>

    <!-- Category Filters -->
    <div class="room-filters">
      <button type="button" class="filter-pill active" onclick="filterCategory('All', this)">All Rooms</button>
      <button type="button" class="filter-pill" onclick="filterCategory('Suite', this)">Suites</button>
      <button type="button" class="filter-pill" onclick="filterCategory('Cabin Room', this)">Cabin Rooms</button>
      <button type="button" class="filter-pill" onclick="filterCategory('Single Room', this)">Single</button>
    </div>

    <!-- Dynamic Room Grid -->
    <div class="room-grid" id="room-grid">
      <?php foreach ($rooms as $room): ?>
        <?php $isAvail = ($room['is_available'] ?? 1) == 1; ?>
        <div class="room-card" data-category="<?= htmlspecialchars($room['category']) ?>" style="<?= !$isAvail ? 'opacity: 0.82;' : '' ?>">
          
          <!-- Photo Container with Inclusions Overlay -->
          <div class="room-card-media">
            <img src="<?= htmlspecialchars($room['image_path']) ?>" alt="<?= htmlspecialchars($room['name']) ?>">
            
            <?php if (!$isAvail): ?>
              <div style="position: absolute; top: 10px; right: 10px; z-index: 3;">
                <span class="badge badge-cancelled" style="font-size: 0.65rem; box-shadow: 0 2px 8px rgba(0,0,0,0.35);">Maintenance</span>
              </div>
            <?php endif; ?>

            <div class="room-inclusions-sheet" id="sheet-<?= $room['id'] ?>">
              <button type="button" class="sheet-close" onclick="toggleSheet(<?= $room['id'] ?>)">&times;</button>
              <span class="sheet-eyebrow">Included with stay</span>
              <ul class="sheet-list">
                <?php foreach (getRoomInclusions($room['category']) as $item): ?>
                  <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>

          <div class="room-card-body">
            <div class="room-cat">
              <?= htmlspecialchars($room['category']) ?>
              <?php if (!$isAvail): ?>
                <span style="color: var(--danger); font-size: 0.65rem; font-weight: 700; margin-left: 4px;">(Unavailable)</span>
              <?php endif; ?>
            </div>
            <h3><?= htmlspecialchars($room['name']) ?></h3>
            <p class="room-price"><strong>&#8369;<?= number_format($room['price_per_night']) ?></strong> / night</p>
            
            <!-- Action Buttons: Solid Sage & Booking State -->
            <div class="room-actions-dual">
              <button type="button" class="btn btn-sage btn-sm" onclick="toggleSheet(<?= $room['id'] ?>)">Inclusions</button>
              <?php if ($isAvail): ?>
                <a href="booking.php?room_id=<?= $room['id'] ?>" class="btn btn-green btn-sm">Book Room</a>
              <?php else: ?>
                <button type="button" class="btn btn-sm" style="background: #b5ada3; color: #fff; cursor: not-allowed;" disabled title="Room is currently undergoing maintenance">Maintenance</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="pattern-band" style="margin-top: 4rem;"></div>

<!-- Footer -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-bottom">
      <span>&copy; <?php echo date("Y"); ?> Evercove Inn &amp; Suites. All rights reserved.</span>
    </div>
  </div>
</footer>

<script>
  function filterCategory(category, element) {
    document.querySelectorAll('.filter-pill').forEach(btn => btn.classList.remove('active'));
    if (element) element.classList.add('active');

    const cards = document.querySelectorAll('.room-card');
    cards.forEach(card => {
      if (category === 'All' || card.getAttribute('data-category') === category) {
        card.style.display = 'flex';
      } else {
        card.style.display = 'none';
      }
    });
  }

  function toggleSheet(id) {
    const targetSheet = document.getElementById('sheet-' + id);
    const allSheets = document.querySelectorAll('.room-inclusions-sheet');

    allSheets.forEach(sheet => {
      if (sheet !== targetSheet) sheet.classList.remove('active');
    });

    if (targetSheet) {
      targetSheet.classList.toggle('active');
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