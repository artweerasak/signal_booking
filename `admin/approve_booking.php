<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: ../login.php');
    exit;
}

if (isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    
    // Update the booking status to 'approved'
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'approved' WHERE id = :id");
    $stmt->execute(['id' => $booking_id]);
    
    header('Location: dashboard.php');
    exit;
} else {
    echo "Invalid request.";
}
