<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>The Experience — Evercove Inn & Suites</title>
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

<!-- Experience Showcase -->
<section class="section feature-section">
  <div class="container">
    <div class="feature-head">
      <span class="eyebrow">Why Guests Choose Evercove</span>
      <h2>Everything here earns its place.</h2>
      <p class="feature-hint">Click any card to preview the space.</p>
    </div>
    <div class="feature">
      <div class="feature-media">
        <img id="experience-display" src="images/firepit.jpg" alt="Evercove Feature Highlight">
      </div>
      <div class="feature-cards">
        <div class="feature-card active" onclick="switchExperience('images/firepit.jpg', this)">
          <div class="icon-circle">🔥</div>
          <h4>Firepit Lounge, Every Night</h4>
          <p>Low seating, warm light, and nowhere you need to be — open to every guest, every evening.</p>
        </div>
        <div class="feature-card" onclick="switchExperience('images/trail.jpg', this)">
          <div class="icon-circle">🥾</div>
          <h4>Private Trail Access</h4>
          <p>40 acres of marked trail, straight from the back door — no shuttle, no crowd.</p>
        </div>
        <div class="feature-card" onclick="switchExperience('images/vinyl.jpg', this)">
          <div class="icon-circle">🎵</div>
          <h4>Vinyl Listening Room</h4>
          <p>A curated collection and a good pair of speakers — reservable for a private hour.</p>
        </div>
        <div class="feature-card" onclick="switchExperience('images/breakfast.jpg', this)">
          <div class="icon-circle">🍳</div>
          <h4>Communal Breakfast, Included</h4>
          <p>Slow mornings around one long table — included with every stay, no upcharge.</p>
        </div>
      </div>
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
  function switchExperience(imagePath, cardElement) {
    const displayImg = document.getElementById('experience-display');
    if (displayImg) {
      displayImg.style.opacity = '0.2';
      setTimeout(() => {
        displayImg.src = imagePath;
        displayImg.style.opacity = '1';
      }, 150);
    }
    document.querySelectorAll('.feature-card').forEach(c => c.classList.remove('active'));
    if (cardElement) {
      cardElement.classList.add('active');
    }
  }
</script>

</body>
</html>