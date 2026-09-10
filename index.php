<?php
session_start();
$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Evercove Inn & Suites — Check In. Turn Off.</title>
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
    <a href="index.php" class="logo">
      <img src="images/logo-1.png" alt="Evercove Inn & Suites">
    </a>
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

<!-- Hero Section -->
<section class="hero">
  <div class="container hero-container">
    <div class="hero-content">
      <span class="eyebrow">A Boutique Inn in the Hills Above Dumaguete</span>
      <h1>Disconnect from the world.<br>Reconnect with <span>each other.</span></h1>
      <p>Moody, romantic warmth meets premium comfort — built for late-night talks, good music, and effortless connection.</p>
      <div class="hero-actions">
        <a href="rooms.php" class="btn btn-gold">Explore Rooms</a>
        <a href="about.php" class="btn btn-outline-cream">Our Story</a>
      </div>
    </div>
    <div class="season-card">
      <div class="thumb"></div>
      <div class="season-text">
        <span class="eyebrow">This Season</span>
        <h4>The Fireside Weekend Package is here</h4>
        <a href="experience.php">See what's included &rsaquo;</a>
      </div>
    </div>
  </div>
</section>

<!-- Floating Availability Bar -->
<div class="photo-band">
  <div class="booking-bar">
    <div class="container">
      <form class="booking-form" method="GET" action="booking.php">
        <div class="booking-field">
          <label for="checkin">Check In</label>
          <input type="date" id="checkin" name="checkin" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="booking-field">
          <label for="checkout">Check Out</label>
          <input type="date" id="checkout" name="checkout" value="<?= date('Y-m-d', strtotime('+2 days')) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
        </div>
        <div class="booking-field">
          <label for="guests">Guests</label>
          <input type="number" id="guests" name="guests" value="2" min="1" max="6" required>
        </div>
        <button type="submit" class="btn btn-gold">Find Stays</button>
      </form>
    </div>
  </div>
  <div class="divider-mountain"></div>
</div>

<div class="pattern-band"></div>

<!-- Call to Action Banner -->
<section class="cta-banner">
  <div class="container">
    <span class="eyebrow">Ready When You Are</span>
    <h2>A weekend away is closer than it feels.</h2>
    <p>Two nights, no plans required. Just a fire, good company, and the woods.</p>
    <a href="rooms.php" class="btn btn-gold">View Rooms</a>
  </div>
</section>

<!-- Information Split Panels -->
<div class="split-photo"></div>
<div class="split-panels">
  <div class="panel panel-gold">
    <span class="eyebrow">Membership</span>
    <h3>Join the Evercove Circle</h3>
    <p>Early access, late checkouts, and first pick of the lounge — free to join.</p>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="index.php?status=success&message=<?= urlencode('You are already an active member of the Evercove Circle!') ?>" class="btn btn-green">Join for Free</a>
    <?php else: ?>
      <a href="register.php" class="btn btn-green">Join for Free</a>
    <?php endif; ?>
  </div>
  <div class="panel panel-green">
    <span class="eyebrow">Book Direct</span>
    <h3>Reserve Your Room</h3>
    <p>Best rate guaranteed when you book directly with us — no middlemen, no markup.</p>
    <a href="rooms.php" class="btn btn-gold">View Rooms</a>
  </div>
</div>

<!-- Footer -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-top">
      <div class="footer-brand">
        <a href="index.php" class="logo"><img src="images/logo-2.png" alt="Evercove Inn & Suites"></a>
        <p class="tagline">A boutique retreat deep in nature — built for disconnecting from the world and reconnecting with each other.</p>
        <div class="addr">
          Barangay Camp Lookout,<br>
          Valencia, Negros Oriental, 6200<br>
          Philippines
        </div>
        <div class="contact-line">+63 917 000 1234</div>
        <div class="contact-line">hello@evercoveinn.ph</div>
      </div>
      <div class="footer-col">
        <h5>Explore</h5>
        <ul>
          <li><a href="rooms.php">Rooms &amp; Suites</a></li>
          <li><a href="experience.php">Experience</a></li>
          <li><a href="reviews.php">Reviews</a></li>
          <li><a href="booking.php">Reservations</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Inn Info</h5>
        <ul>
          <li><a href="experience.php">Directions</a></li>
          <li><a href="about.php">Policies</a></li>
          <li><a href="about.php">FAQ</a></li>
          <li><a href="about.php">Contact</a></li>
        </ul>
      </div>
      <div class="footer-col footer-news">
        <h5>Stay in the Loop</h5>
        <p>Quiet updates, no noise.</p>
        <form class="news-form" method="POST" action="function.php">
          <input type="email" name="subscriber_email" placeholder="Email address" required>
          <button type="submit" name="newsletter-submit">Join</button>
        </form>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?php echo date("Y"); ?> Evercove Inn &amp; Suites. All rights reserved.</span>
      <span>Dumaguete City, Negros Oriental, Philippines</span>
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