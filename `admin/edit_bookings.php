<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $date = htmlspecialchars(trim($_POST['date']));
    $court = intval($_POST['court']);
    $time_slot = htmlspecialchars(trim($_POST['time_slot']));

    // Insert the booking into the database with status 'approved'
    $stmt = $pdo->prepare("INSERT INTO bookings (name, email, phone, date, court, time_slot, status) VALUES (:name, :email, :phone, :date, :court, :time_slot, 'approved')");
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'date' => $date,
        'court' => $court,
        'time_slot' => $time_slot
    ]);

    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Free Booking for Internal Staff</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h1>Add Free Booking for Internal Staff</h1>
    <a href="dashboard.php">Back to Dashboard</a>
    <form method="post">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required><br>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br>

        <label for="phone">Phone:</label>
        <input type="tel" id="phone" name="phone" required><br>

        <label for="date">Date:</label>
        <input type="date" id="date" name="date" required><br>

        <label for="court">Court:</label>
        <select id="court" name="court" required>
            <option value="1">Court 1</option>
            <option value="2">Court 2</option>
            <!-- Add more courts as needed -->
        </select><br>

        <label for="time_slot">Time Slot:</label>
        <input type="text" id="time_slot" name="time_slot" required><br>

        <button type="submit">Add Booking</button>
    </form>
</body>
</html>
