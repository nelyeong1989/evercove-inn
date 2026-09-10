<?php
// Get max guests allowed by room category
function getRoomMaxGuests(string $category): int
{
    switch (trim($category)) {
        case 'Single Room':
        case 'Single':
            return 2;
        case 'Cabin Room':
        case 'Cabin':
            return 4;
        case 'Suite':
        default:
            return 6;
    }
}

// Basic validation helpers
function validateEmailFormat(string $value): ?string
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "Enter a valid email address.";
}

function validateRequired(string $value, string $label): ?string
{
    return trim($value) === '' ? "$label is required." : null;
}

function validateMinLength(string $value, string $label, int $min): ?string
{
    return strlen($value) < $min ? "$label must be at least $min characters." : null;
}

function validateIntRange(string $value, string $label, int $min, int $max): ?string
{
    $ok = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);
    return $ok !== false ? null : "$label must be a whole number between $min and $max.";
}

// Validate registration inputs with confirmation check
function validateRegisterInput(array $post): array
{
    $username = trim($post['username'] ?? '');
    $email    = trim($post['email'] ?? '');
    $password = $post['password'] ?? '';
    $confirm  = $post['confirm_password'] ?? '';

    $errors = array_filter([
        validateRequired($username, 'Username'),
        validateMinLength($username, 'Username', 3),
        validateRequired($email, 'Email'),
        validateEmailFormat($email),
        validateRequired($password, 'Password'),
        validateMinLength($password, 'Password', 6),
        ($password !== $confirm) ? "Passwords do not match." : null,
    ]);

    return [
        'errors' => array_values($errors),
        'data'   => [
            'username' => htmlspecialchars($username),
            'email'    => $email,
            'password' => $password,
        ],
    ];
}

// Validate sign in inputs
function validateLoginInput(array $post): array
{
    $username = trim($post['username'] ?? '');
    $password = $post['password'] ?? '';

    $errors = array_filter([
        validateRequired($username, 'Username'),
        validateRequired($password, 'Password'),
    ]);

    return [
        'errors' => array_values($errors),
        'data'   => [
            'username' => htmlspecialchars($username),
            'password' => $password,
        ],
    ];
}

// Validate reservation inputs
function validateBookingInput(array $post): array
{
    $room_id  = trim($post['room_id'] ?? '');
    $checkin  = trim($post['checkin'] ?? '');
    $checkout = trim($post['checkout'] ?? '');
    $guests   = trim($post['guests'] ?? '');
    $name     = trim($post['guest_name'] ?? '');
    $email    = trim($post['guest_email'] ?? '');

    $errors = array_filter([
        validateRequired($name, 'Full Name'),
        validateRequired($email, 'Email Address'),
        validateEmailFormat($email),
        validateRequired($room_id, 'Room Selection'),
        validateRequired($checkin, 'Check-in Date'),
        validateRequired($checkout, 'Check-out Date'),
        validateIntRange($guests, 'Guest Count', 1, 6),
    ]);

    if ($checkin && $checkout && empty($errors)) {
        if (strtotime($checkout) <= strtotime($checkin)) {
            $errors[] = "Check-out date must be after check-in date.";
        }
    }

    return [
        'errors' => array_values($errors),
        'data'   => [
            'room_id'     => (int) $room_id,
            'checkin'     => $checkin,
            'checkout'    => $checkout,
            'guests'      => (int) $guests,
            'guest_name'  => htmlspecialchars($name),
            'guest_email' => $email,
        ],
    ];
}

// Validate review inputs
function validateReviewInput(array $post): array
{
    $room    = trim($post['room_type'] ?? '');
    $rating  = trim($post['rating'] ?? '');
    $comment = trim($post['comment'] ?? '');

    $errors = array_filter([
        validateRequired($room, 'Room Stayed In'),
        validateIntRange($rating, 'Rating', 1, 5),
        validateRequired($comment, 'Review Comment'),
        validateMinLength($comment, 'Review Comment', 8),
    ]);

    return [
        'errors' => array_values($errors),
        'data'   => [
            'room_type'  => htmlspecialchars($room),
            'rating'     => (int) $rating,
            'comment'    => htmlspecialchars($comment),
        ],
    ];
}