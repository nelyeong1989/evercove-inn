<?php
session_start();

require 'database/config.php';
require 'validation.php';

// 1. User Registration
if (isset($_POST['register-user'])) {
    $result = validateRegisterInput($_POST);
    if (!empty($result['errors'])) {
        header('Location: register.php?status=error&message=' . urlencode(implode(' ', $result['errors'])));
        exit;
    }

    try {
        $pdo = getConnection();
        $check = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e");
        $check->execute([':u' => $result['data']['username'], ':e' => $result['data']['email']]);
        if ($check->fetch()) {
            header('Location: register.php?status=error&message=' . urlencode('Username or Email is already registered.'));
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (:u, :e, :p, 'user')");
        $stmt->execute([
            ':u' => $result['data']['username'],
            ':e' => $result['data']['email'],
            ':p' => password_hash($result['data']['password'], PASSWORD_DEFAULT),
        ]);

        header('Location: login.php?status=success&message=' . urlencode('Account registered! Please sign in.'));
        exit;
    } catch (PDOException $e) {
        header('Location: register.php?status=error&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// 2. Unified Sign In (Customers -> index.php, Admins -> admin.php)
if (isset($_POST['login-user'])) {
    $result = validateLoginInput($_POST);
    if (!empty($result['errors'])) {
        header('Location: login.php?status=error&message=' . urlencode(implode(' ', $result['errors'])));
        exit;
    }

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $result['data']['username']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($result['data']['password'], $user['password'])) {
            header('Location: login.php?status=error&message=' . urlencode('Invalid username or password.'));
            exit;
        }

        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email']    = $user['email'];
        $_SESSION['role']     = $user['role'];

        // Dynamic Role-Based Redirection
        if ($user['role'] === 'admin') {
            header('Location: admin.php');
        } else {
            $isNewUser = (time() - strtotime($user['created_at'])) < 900;
            $welcomeText = $isNewUser ? 'Welcome to Evercove, ' : 'Welcome back, ';
            header('Location: index.php?status=success&message=' . urlencode($welcomeText . $user['username'] . '!'));
        }
        exit;
    } catch (PDOException $e) {
        header('Location: login.php?status=error&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// 3. Password Reset Handler
if (isset($_POST['reset-password'])) {
    $username    = trim($_POST['username'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if (empty($username) || empty($email) || empty($newPassword)) {
        header('Location: forgot-password.php?status=error&message=' . urlencode('All fields are required.'));
        exit;
    }

    if (strlen($newPassword) < 6) {
        header('Location: forgot-password.php?status=error&message=' . urlencode('New password must be at least 6 characters.'));
        exit;
    }

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u AND email = :e LIMIT 1");
        $stmt->execute([':u' => $username, ':e' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            header('Location: forgot-password.php?status=error&message=' . urlencode('No account found matching those credentials.'));
            exit;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
        $updateStmt->execute([':p' => $hashedPassword, ':id' => $user['id']]);

        header('Location: login.php?status=success&message=' . urlencode('Password updated successfully! Please sign in.'));
        exit;
    } catch (PDOException $e) {
        header('Location: forgot-password.php?status=error&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// 4. Make a Reservation with Payment Verification & Proof Screenshot
if (isset($_POST['book-stay'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?status=error&message=' . urlencode('Please sign in or create an account to book a stay.'));
        exit;
    }

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: admin.php?status=error&message=' . urlencode('Administrator accounts cannot make room reservations.'));
        exit;
    }

    $result = validateBookingInput($_POST);
    if (!empty($result['errors'])) {
        header('Location: booking.php?status=error&message=' . urlencode(implode(' ', $result['errors'])));
        exit;
    }

    try {
        $pdo = getConnection();

        $roomStmt = $pdo->prepare("SELECT name, category, price_per_night FROM rooms WHERE id = :id");
        $roomStmt->execute([':id' => $result['data']['room_id']]);
        $room = $roomStmt->fetch();

        if (!$room) {
            header('Location: booking.php?status=error&message=' . urlencode('Selected room does not exist.'));
            exit;
        }

        $maxGuests = getRoomMaxGuests($room['category'] ?? '');
        if ($result['data']['guests'] > $maxGuests) {
            header('Location: booking.php?status=error&message=' . urlencode("{$room['name']} can only accommodate up to {$maxGuests} guest(s)."));
            exit;
        }

        $conflictQuery = "SELECT COUNT(*) FROM bookings 
                          WHERE room_id = :room_id 
                            AND LOWER(TRIM(status)) = 'confirmed' 
                            AND checkin_date < :checkout 
                            AND checkout_date > :checkin";
        $conflictStmt = $pdo->prepare($conflictQuery);
        $conflictStmt->execute([
            ':room_id'  => $result['data']['room_id'],
            ':checkout' => $result['data']['checkout'],
            ':checkin'  => $result['data']['checkin'],
        ]);

        if ($conflictStmt->fetchColumn() > 0) {
            header('Location: booking.php?room_id=' . $result['data']['room_id'] . '&status=error&message=' . urlencode($room['name'] . ' is already booked for these dates. Please choose different dates or another room.'));
            exit;
        }

        $nights = (strtotime($result['data']['checkout']) - strtotime($result['data']['checkin'])) / 86400;
        $total  = $nights * (float) $room['price_per_night'];
        $userId = $_SESSION['user_id'];

        // Determine payment option, reference, and uploaded proof screenshot
        $rawPayment = trim($_POST['payment_method'] ?? 'Pay on Check-in');
        $validMethods = ['Pay on Check-in', 'GCash (Online)', 'Card (Online)'];
        $paymentMethod = in_array($rawPayment, $validMethods, true) ? $rawPayment : 'Pay on Check-in';

        $paymentRef   = null;
        $paymentProof = null;

        if ($paymentMethod === 'GCash (Online)') {
            $paymentRef = trim($_POST['gcash_ref'] ?? '');
            $fileUpload = $_FILES['gcash_proof'] ?? null;
            $paymentStatus = 'Paid (Under Verification)';
        } elseif ($paymentMethod === 'Card (Online)') {
            $paymentRef = trim($_POST['card_ref'] ?? '');
            $fileUpload = $_FILES['card_proof'] ?? null;
            $paymentStatus = 'Paid (Under Verification)';
        } else {
            $paymentStatus = 'Pending (Due at Check-in)';
            $fileUpload = null;
        }

        // Process proof screenshot upload
        if ($fileUpload && $fileUpload['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $fileInfo    = pathinfo($fileUpload['name']);
            $extension   = strtolower($fileInfo['extension'] ?? '');

            if (!in_array($extension, $allowedExts, true)) {
                header('Location: booking.php?status=error&message=' . urlencode('Payment proof must be an image (JPG, PNG, or WEBP).'));
                exit;
            }

            $uploadDir = __DIR__ . '/uploads/payments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName  = 'proof_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
            $targetPath   = $uploadDir . $newFileName;

            if (move_uploaded_file($fileUpload['tmp_name'], $targetPath)) {
                $paymentProof = 'uploads/payments/' . $newFileName;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO bookings (user_id, room_id, guest_name, guest_email, checkin_date, checkout_date, guests_count, total_amount, payment_method, payment_status, payment_ref, payment_proof, status) 
            VALUES (:uid, :rid, :name, :email, :cin, :cout, :guests, :total, :pay_method, :pay_status, :pay_ref, :pay_proof, 'confirmed')
        ");
        $stmt->execute([
            ':uid'        => $userId,
            ':rid'        => $result['data']['room_id'],
            ':name'       => $_SESSION['username'],
            ':email'      => $_SESSION['email'],
            ':cin'        => $result['data']['checkin'],
            ':cout'       => $result['data']['checkout'],
            ':guests'     => $result['data']['guests'],
            ':total'      => $total,
            ':pay_method' => $paymentMethod,
            ':pay_status' => $paymentStatus,
            ':pay_ref'    => $paymentRef,
            ':pay_proof'  => $paymentProof,
        ]);

        $bookingId = $pdo->lastInsertId();
        header('Location: booking-success.php?id=' . $bookingId);
        exit;
    } catch (PDOException $e) {
        header('Location: booking.php?status=error&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// 5. Update Existing Reservation
if (isset($_POST['update-booking'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    if (!$bookingId) {
        header('Location: my-bookings.php');
        exit;
    }

    $result = validateBookingInput([
        'room_id'     => $_POST['room_id'] ?? '',
        'checkin'     => $_POST['checkin'] ?? '',
        'checkout'    => $_POST['checkout'] ?? '',
        'guests'      => $_POST['guests'] ?? '',
        'guest_name'  => $_SESSION['username'],
        'guest_email' => $_SESSION['email'],
    ]);

    if (!empty($result['errors'])) {
        header('Location: edit-booking.php?id=' . $bookingId . '&status=error&message=' . urlencode(implode(' ', $result['errors'])));
        exit;
    }

    try {
        $pdo = getConnection();

        $chk = $pdo->prepare("SELECT id, checkin_date FROM bookings WHERE id = :id AND user_id = :uid AND status = 'confirmed'");
        $chk->execute([':id' => $bookingId, ':uid' => $_SESSION['user_id']]);
        $existingBooking = $chk->fetch();

        if (!$existingBooking) {
            header('Location: my-bookings.php?status=error&message=' . urlencode('Reservation cannot be modified.'));
            exit;
        }

        if ($existingBooking['checkin_date'] <= date('Y-m-d')) {
            header('Location: my-bookings.php?status=error&message=' . urlencode('Active or past stays cannot be modified.'));
            exit;
        }

        $roomStmt = $pdo->prepare("SELECT name, category, price_per_night FROM rooms WHERE id = :id");
        $roomStmt->execute([':id' => $result['data']['room_id']]);
        $room = $roomStmt->fetch();

        $maxGuests = getRoomMaxGuests($room['category'] ?? '');
        if ($result['data']['guests'] > $maxGuests) {
            header('Location: edit-booking.php?id=' . $bookingId . '&status=error&message=' . urlencode("{$room['name']} can only accommodate up to {$maxGuests} guest(s)."));
            exit;
        }

        $conflictQuery = "SELECT COUNT(*) FROM bookings 
                          WHERE room_id = :room_id 
                            AND id != :bid
                            AND LOWER(TRIM(status)) = 'confirmed' 
                            AND checkin_date < :checkout 
                            AND checkout_date > :checkin";
        $conflictStmt = $pdo->prepare($conflictQuery);
        $conflictStmt->execute([
            ':room_id'  => $result['data']['room_id'],
            ':bid'      => $bookingId,
            ':checkout' => $result['data']['checkout'],
            ':checkin'  => $result['data']['checkin'],
        ]);

        if ($conflictStmt->fetchColumn() > 0) {
            header('Location: edit-booking.php?id=' . $bookingId . '&status=error&message=' . urlencode($room['name'] . ' is already reserved for these dates.'));
            exit;
        }

        $nights = (strtotime($result['data']['checkout']) - strtotime($result['data']['checkin'])) / 86400;
        $total  = $nights * (float) $room['price_per_night'];

        $upd = $pdo->prepare("
            UPDATE bookings 
            SET room_id = :rid, 
                checkin_date = :cin, 
                checkout_date = :cout, 
                guests_count = :guests, 
                total_amount = :total 
            WHERE id = :bid AND user_id = :uid
        ");
        $upd->execute([
            ':rid'    => $result['data']['room_id'],
            ':cin'    => $result['data']['checkin'],
            ':cout'   => $result['data']['checkout'],
            ':guests' => $result['data']['guests'],
            ':total'  => $total,
            ':bid'    => $bookingId,
            ':uid'    => $_SESSION['user_id'],
        ]);

        header('Location: my-bookings.php?status=success&message=' . urlencode('Reservation #EVR-' . str_pad($bookingId, 5, '0', STR_PAD_LEFT) . ' updated successfully!'));
        exit;
    } catch (PDOException $e) {
        header('Location: edit-booking.php?id=' . $bookingId . '&status=error&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// 6. Guest Cancels Own Booking
if (isset($_GET['action']) && $_GET['action'] === 'user-cancel-booking') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($bookingId) {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = :id AND user_id = :uid AND checkin_date > CURRENT_DATE AND status = 'confirmed'");
            $stmt->execute([':id' => $bookingId, ':uid' => $_SESSION['user_id']]);

            if ($stmt->rowCount() > 0) {
                header('Location: my-bookings.php?status=success&message=' . urlencode('Reservation #EVR-' . str_pad($bookingId, 5, '0', STR_PAD_LEFT) . ' has been cancelled.'));
            } else {
                header('Location: my-bookings.php?status=error&message=' . urlencode('Active, completed, or already cancelled stays cannot be cancelled.'));
            }
            exit;
        } catch (PDOException $e) {
            header('Location: my-bookings.php?status=error&message=' . urlencode($e->getMessage()));
            exit;
        }
    }

    header('Location: my-bookings.php');
    exit;
}

// 7. Admin Cancels Booking
if (isset($_GET['action']) && $_GET['action'] === 'cancel-booking') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php?status=error&message=' . urlencode('Unauthorized access.'));
        exit;
    }

    $bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($bookingId) {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = :id AND checkout_date >= CURRENT_DATE");
            $stmt->execute([':id' => $bookingId]);

            if ($stmt->rowCount() > 0) {
                header('Location: admin.php?status=success&message=' . urlencode('Booking #EVR-' . str_pad($bookingId, 5, '0', STR_PAD_LEFT) . ' marked as cancelled. Room dates reopened.'));
            } else {
                header('Location: admin.php?status=error&message=' . urlencode('Past completed stays cannot be cancelled.'));
            }
            exit;
        } catch (PDOException $e) {
            header('Location: admin.php?status=error&message=' . urlencode($e->getMessage()));
            exit;
        }
    }

    header('Location: admin.php');
    exit;
}

// 8. Admin Confirms Payment Settlement
if (isset($_GET['action']) && $_GET['action'] === 'confirm-payment') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php?status=error&message=' . urlencode('Unauthorized access.'));
        exit;
    }

    $bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($bookingId) {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'Paid (Verified)' WHERE id = :id AND status = 'confirmed'");
            $stmt->execute([':id' => $bookingId]);

            header('Location: admin.php?status=success&message=' . urlencode('Payment confirmed for booking #EVR-' . str_pad($bookingId, 5, '0', STR_PAD_LEFT) . '.'));
            exit;
        } catch (PDOException $e) {
            header('Location: admin.php?status=error&message=' . urlencode($e->getMessage()));
            exit;
        }
    }

    header('Location: admin.php');
    exit;
}

// 9. Admin Updates Room Price
if (isset($_POST['update-room-rate'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php');
        exit;
    }

    $roomId = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $newRate = filter_input(INPUT_POST, 'price_per_night', FILTER_VALIDATE_FLOAT);

    if ($roomId && $newRate !== false && $newRate > 0) {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("UPDATE rooms SET price_per_night = :rate WHERE id = :id");
            $stmt->execute([':rate' => $newRate, ':id' => $roomId]);

            header('Location: admin.php?status=success&message=' . urlencode('Room rate updated successfully.'));
            exit;
        } catch (PDOException $e) {
            header('Location: admin.php?status=error&message=' . urlencode($e->getMessage()));
            exit;
        }
    }

    header('Location: admin.php?status=error&message=' . urlencode('Invalid rate value submitted.'));
    exit;
}

// 10. Admin Moderation: Remove Review
if (isset($_GET['action']) && $_GET['action'] === 'delete-review') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php');
        exit;
    }

    $reviewId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($reviewId) {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = :id");
            $stmt->execute([':id' => $reviewId]);

            header('Location: admin.php?status=success&message=' . urlencode('Review removed from public site.'));
            exit;
        } catch (PDOException $e) {
            header('Location: admin.php?status=error&message=' . urlencode($e->getMessage()));
            exit;
        }
    }

    header('Location: admin.php');
    exit;
}

// 11. Post Anonymous Review
if (isset($_POST['submit-review'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?status=error&message=' . urlencode('Please sign in to leave a review.'));
        exit;
    }

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: reviews.php?status=error&message=' . urlencode('Administrator accounts cannot submit guest reviews.'));
        exit;
    }

    $result = validateReviewInput($_POST);
    if (!empty($result['errors'])) {
        header('Location: reviews.php?status=error&message=' . urlencode(implode(' ', $result['errors'])));
        exit;
    }

    try {
        $pdo = getConnection();
        $userId = $_SESSION['user_id'];

        $stmt = $pdo->prepare("INSERT INTO reviews (user_id, guest_name, room_type, rating, comment) VALUES (:uid, :name, :room, :rating, :comment)");
        $stmt->execute([
            ':uid'     => $userId,
            ':name'    => 'A Recent Guest',
            ':room'    => $result['data']['room_type'],
            ':rating'  => $result['data']['rating'],
            ':comment' => $result['data']['comment'],
        ]);

        header('Location: reviews.php?status=success&message=' . urlencode('Thank you! Your anonymous review has been recorded.'));
        exit;
    } catch (PDOException $e) {
        header('Location: reviews.php?status=error&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// 12. Newsletter Subscription
if (isset($_POST['newsletter-submit'])) {
    $email = trim($_POST['subscriber_email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: index.php?status=error&message=' . urlencode('Please provide a valid email address.'));
        exit;
    }

    header('Location: index.php?status=success&message=' . urlencode('Thank you for subscribing to quiet updates.'));
    exit;
}

// 13. Sign Out
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.php?status=success&message=' . urlencode('Logged out successfully.'));
    exit;
}

header('Location: index.php');
exit;