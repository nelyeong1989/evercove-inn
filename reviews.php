<?php
session_start();
require_once 'database/config.php';

$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;

$rooms = [];
$reviews = [];
$avgRating = '5.0';
$totalReviews = 0;

try {
    $pdo = getConnection();
    $rooms = $pdo->query("SELECT name FROM rooms ORDER BY id ASC")->fetchAll();
    $reviews = $pdo->query("SELECT * FROM reviews ORDER BY id DESC LIMIT 10")->fetchAll();

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
  <title>Guest Reviews — Evercove Inn & Suites</title>
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

<!-- Reviews Section -->
<section class="testimonials">
  <svg class="testi-sun" viewBox="0 0 260 260">
    <defs>
      <filter id="sun-glow" x="-50%" y="-50%" width="200%" height="200%">
        <feGaussianBlur stdDeviation="25" result="blur" />
        <feComposite in="SourceGraphic" in2="blur" operator="over" />
      </filter>
    </defs>
    <circle cx="130" cy="130" r="95" fill="#f5be42" opacity="0.35" filter="url(#sun-glow)" />
    <circle cx="130" cy="130" r="48" fill="#f7c852" />
  </svg>

  <svg class="testi-vector-backdrop" viewBox="0 0 1440 540" preserveAspectRatio="none">
    <rect x="610" y="200" width="220" height="340" fill="#caa07a" />
    <rect x="645" y="240" width="48" height="68" fill="#755135" rx="2" />
    <rect x="745" y="240" width="48" height="68" fill="#755135" rx="2" />
    <polygon points="830,540 985,225 1105,335 1255,130 1440,320 1440,540" fill="#274d3a" />
    <polygon points="0,540 0,480 120,455 270,490 430,460 590,490 730,460 880,495 1040,460 1200,495 1330,460 1440,480 1440,540" fill="#274d3a" />
  </svg>

  <div class="container">
    <div class="section-head">
      <span class="eyebrow eyebrow-green">Our Guests</span>
      <h2>Loyal Guests, Honest Words</h2>
      <p>Live Rating: <?= $avgRating ?> / 5.0 (<?= $totalReviews ?> verified reviews)</p>
    </div>

    <!-- Review Grid -->
    <div class="testi-grid">
      <?php if (empty($reviews)): ?>
        <p style="text-align:center; grid-column:span 2; color:#fff;">No reviews posted yet.</p>
      <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
          <div class="testi-card">
            <div class="stars"><?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?></div>
            <p class="quote">"<?= htmlspecialchars($rev['comment']) ?>"</p>
            <div class="testi-attr">&mdash; A Recent Guest, <?= htmlspecialchars($rev['room_type']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Review Submission Form -->
    <?php if (isset($_SESSION['user_id']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')): ?>
      <div class="review-form-box">
        <h3>Leave a Review</h3>
        <p style="font-size:0.82rem; color:var(--gray); margin-bottom:1rem;">Your feedback is shared anonymously as "A Recent Guest".</p>
        <form method="POST" action="function.php">
          <div class="review-form-grid">
            <div>
              <select name="room_type" required>
                <option value="">Select Room Stayed In</option>
                <?php foreach ($rooms as $r): ?>
                  <option value="<?= htmlspecialchars($r['name']) ?>"><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <select name="rating" required>
                <option value="5">★★★★★ (5 Stars)</option>
                <option value="4">★★★★☆ (4 Stars)</option>
                <option value="3">★★★☆☆ (3 Stars)</option>
                <option value="2">★★☆☆☆ (2 Stars)</option>
                <option value="1">★☆☆☆☆ (1 Star)</option>
              </select>
            </div>
          </div>
          <textarea name="comment" rows="3" placeholder="Tell future guests about your stay..." required></textarea>
          <button type="submit" name="submit-review" class="btn btn-green" style="margin-top:0.8rem;">Submit Review</button>
        </form>
      </div>
    <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
      <div class="review-form-box" style="text-align: center;">
        <h3>Staff Notice</h3>
        <p style="font-size: 0.88rem; color: var(--gray); margin: 0.6rem 0 1.2rem;">Administrator accounts cannot submit guest reviews. You can moderate reviews from your console.</p>
        <a href="admin.php" class="btn btn-green">Open Management Console</a>
      </div>
    <?php else: ?>
      <div class="review-form-box" style="text-align: center;">
        <h3>Share Your Experience</h3>
        <p style="font-size: 0.88rem; color: var(--gray); margin: 0.6rem 0 1.2rem;">Reviews are exclusive to verified guest accounts. Please sign in to submit a review.</p>
        <a href="login.php" class="btn btn-green">Sign In to Leave a Review</a>
      </div>
    <?php endif; ?>

  </div>
</section>

<!-- Footer -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-bottom">
      <span>&copy; <?php echo date("Y"); ?> Evercove Inn &amp; Suites. All rights reserved.</span>
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