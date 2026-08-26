ALTER TABLE bookings ADD COLUMN status ENUM('pending', 'approved', 'cancelled') NOT NULL DEFAULT 'pending';

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL
);
