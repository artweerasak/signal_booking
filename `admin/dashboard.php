<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Fetch pending bookings from the database
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE status = 'pending'");
$stmt->execute();
$pending_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h1>Admin Dashboard</h1>
    <a href="edit_bookings.php">Add Free Booking for Internal Staff</a>
    <h2>Pending Bookings</h2>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Date</th>
            <th>Court</th>
            <th>Time Slot</th>
            <th>Action</th>
        </tr>
        <?php foreach ($pending_bookings as $booking): ?>
        <tr>
            <td><?php echo htmlspecialchars($booking['id']); ?></td>
            <td><?php echo htmlspecialchars($booking['name']); ?></td>
            <td><?php echo htmlspecialchars($booking['email']); ?></td>
            <td><?php echo htmlspecialchars($booking['phone']); ?></td>
            <td><?php echo htmlspecialchars($booking['date']); ?></td>
            <td><?php echo htmlspecialchars($booking['court']); ?></td>
            <td><?php echo htmlspecialchars($booking['time_slot']); ?></td>
            <td>
                <a href="approve_booking.php?id=<?php echo $booking['id']; ?>">Approve</a> |
                <a href="reject_booking.php?id=<?php echo $booking['id']; ?>">Reject</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
