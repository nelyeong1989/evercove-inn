<?php
session_start();
require 'database/config.php';

// Protect receipt route behind active login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php');
    exit;
}

$pdo = getConnection();
$sql = "SELECT b.*, r.name AS room_name, r.category 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$booking = $stmt->fetch();

// Ensure reservation exists and belongs to the authenticated user (or an admin)
if (!$booking || ($booking['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin')) {
    header('Location: my-bookings.php?status=error&message=' . urlencode('Reservation receipt not found or access denied.'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reservation Confirmed — Evercove Inn & Suites</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

<!-- Printable Receipt Box -->
<div class="receipt-box">
  <span class="eyebrow eyebrow-green">Reservation Confirmed</span>
  <h2 style="color: var(--emerald-green); margin-top: 0.4rem;">We'll have the fire ready.</h2>
  <p style="font-size: 0.9rem; color: var(--gray);">Thank you, <?= htmlspecialchars($booking['guest_name']) ?>. Here is your reservation receipt:</p>

  <table class="receipt-table">
    <tr>
      <th>Booking Reference</th>
      <td>#EVR-<?= str_pad($booking['id'], 5, '0', STR_PAD_LEFT) ?></td>
    </tr>
    <tr>
      <th>Reserved Room</th>
      <td><?= htmlspecialchars($booking['room_name']) ?> (<?= htmlspecialchars($booking['category']) ?>)</td>
    </tr>
    <tr>
      <th>Check-in Date</th>
      <td><?= date('F j, Y', strtotime($booking['checkin_date'])) ?></td>
    </tr>
    <tr>
      <th>Check-out Date</th>
      <td><?= date('F j, Y', strtotime($booking['checkout_date'])) ?></td>
    </tr>
    <tr>
      <th>Guests</th>
      <td><?= htmlspecialchars($booking['guests_count']) ?> Guest(s)</td>
    </tr>
    <tr>
      <th>Total Amount Due</th>
      <td>₱<?= number_format($booking['total_amount'], 2) ?></td>
    </tr>
  </table>

  <!-- Receipt Action Buttons -->
  <div class="user-badge" style="justify-content: center; flex-wrap: wrap; gap: 0.6rem;">
    <a href="my-bookings.php" class="btn btn-gold">View My Bookings</a>
    <button type="button" onclick="window.print()" class="btn btn-sage">Print Receipt</button>
    <a href="index.php" class="btn btn-green">Return to Home</a>
  </div>
</div>

</body>
</html>