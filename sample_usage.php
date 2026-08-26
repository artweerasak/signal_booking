<?php

require_once 'config/database.php';

try {
    // Example query to fetch data from the database
    $stmt = $conn->query("SELECT * FROM bookings");
    $bookings = $stmt->fetchAll();

    foreach ($bookings as $booking) {
        echo "Booking ID: " . $booking['id'] . "<br>";
        echo "Customer Name: " . $booking['customer_name'] . "<br>";
        echo "Date: " . $booking['date'] . "<br><br>";
    }
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

?>
