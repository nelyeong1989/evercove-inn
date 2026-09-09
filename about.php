<?php
session_start();
require_once 'database/config.php';

$avgRating = '4.9';
$totalReviews = 0;

try {
    $pdo = getConnection();
    $statStmt = $pdo->query("SELECT AVG(rating) AS avg_score, COUNT(*) AS count FROM reviews");
    $statData = $statStmt->fetch();
    if ($statData && $statData['count'] > 0) {
        $avgRating = number_format((float)$statData['avg_score'], 1);
        $totalReviews = (int)$statData['count'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>About Us — Evercove Inn & Suites</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

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

<!-- About Section -->
<section class="section">
  <div class="container about">
    <div class="about-images">
      <img src="images/about-1.jpg" alt="Evercove suite interior" class="img-back">
      <img src="images/about-2.jpg" alt="Loft bedroom with spiral staircase" class="img-front">
    </div>
    <div class="about-text">
      <span class="eyebrow">About Evercove</span>
      <h2>A low-key sanctuary, built for the people you'd cross the woods for.</h2>
      <p>We are a boutique inn built for young adults to disconnect from the world and reconnect with each other — blending moody, romantic warmth with premium comfort.</p>
      <p>Every room, every trail, every shared breakfast is built around the same idea: strip away the noise, and let the people you're with be the whole point of the trip.</p>
      
      <div class="about-stats-wrapper">
        <div class="about-stats">
          <div class="stat">
            <div class="num">8</div>
            <div class="lbl">Rooms &amp; Suites</div>
          </div>
          <div class="stat">
            <div class="num">40</div>
            <div class="lbl">Acres of Trail</div>
          </div>
          <div class="stat">
            <div class="num"><?= $avgRating ?></div>
            <div class="lbl">Rating (<?= $totalReviews ?> reviews)</div>
          </div>
        </div>
        <img src="images/bed.png" alt="Bed Icon" class="bed-icon">
      </div>
    </div>
  </div>
</section>

<div class="pattern-band"></div>

<!-- Footer -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-top">
      <div class="footer-brand">
        <a href="index.php" class="logo"><img src="images/logo-2.png" alt="Evercove Inn & Suites"></a>
        <p class="tagline">A boutique retreat deep in nature.</p>
        <div class="addr">Barangay Camp Lookout, Valencia, Negros Oriental, 6200 Philippines</div>
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
    </div>
  </div>
</footer>

</body>
</html>