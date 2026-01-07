-- Users
CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       username VARCHAR(50) NOT NULL,
                       email VARCHAR(100) NOT NULL,
                       password_hash VARCHAR(255) NOT NULL,
                       role ENUM('user', 'admin') DEFAULT 'user',
                       active TINYINT(1) DEFAULT 1,     -- 1 = aktiv, 0 = gesperrt
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Parkings
CREATE TABLE parkings (
                          id INT AUTO_INCREMENT PRIMARY KEY,
                          owner_id INT NOT NULL,
                          title VARCHAR(100),
                          description TEXT,
                          location VARCHAR(255),
                          price DECIMAL(10,2),
                          status ENUM('pending','approved','rejected') DEFAULT 'pending',
                          main_image VARCHAR(255) NULL,
                          available_from DATETIME,
                          available_to DATETIME,
                          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                          modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          FOREIGN KEY (owner_id) REFERENCES users(id)
);

CREATE TABLE parking_availability (
      id INT AUTO_INCREMENT PRIMARY KEY,
      parking_id INT NOT NULL,
      available_from DATE NOT NULL,
      available_to DATE NOT NULL,
      FOREIGN KEY (parking_id) REFERENCES parkings(id) ON DELETE CASCADE
);

CREATE TABLE bookings (
      id INT AUTO_INCREMENT PRIMARY KEY,
      parking_id INT NOT NULL,
      user_id INT NOT NULL,
      booking_start DATE NOT NULL,
      booking_end DATE NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

      FOREIGN KEY (parking_id) REFERENCES parkings(id) ON DELETE CASCADE,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sample Users (password: "password")
INSERT INTO users (username, email, password_hash, role) VALUES
     ('admin', 'admin@parkshare.local', '$2y$10$b5aZ4Cqos0HZg3CCW.MmyOn0uIdrUc09llz3pUEaINHvj10JB.siK', 'admin'),
     ('alice', 'alice@test.com', '$2y$10$b5aZ4Cqos0HZg3CCW.MmyOn0uIdrUc09llz3pUEaINHvj10JB.siK', 'user'),
     ('bob', 'bob@test.com', '$2y$10$b5aZ4Cqos0HZg3CCW.MmyOn0uIdrUc09llz3pUEaINHvj10JB.siK', 'user');


INSERT INTO parkings (owner_id, title, description, location, price, main_image, available_from, available_to, status) VALUES
   (2, 'City Center Parking', 'Secure parking in downtown', 'Berlin Mitte', 10.00, 'city_center.jpg', '2025-11-10 08:00:00', '2025-11-10 20:00:00', 'approved'),
   (3, 'Office Garage', 'Covered parking near office', 'Berlin Friedrichshain', 8.50, 'office_garage.jpg', '2025-11-10 09:00:00', '2025-11-10 18:00:00', 'approved'),
   (2, 'Weekend Parking', 'Cheap weekend parking', 'Berlin Kreuzberg', 5.00, 'weekend_parking.jpg', '2025-11-15 08:00:00', '2025-11-15 22:00:00', 'approved');

INSERT INTO parking_availability (parking_id, available_from, available_to) VALUES
    (1, '2026-01-01 12:00:00', '2026-06-30 20:00:00'),
    (1, '2026-07-15 14:00:00', '2026-07-31 20:00:00'),

    (2, '2026-06-01 12:00:00', '2026-06-30 20:00:00'),
    (2, '2026-07-15 14:00:00', '2026-07-31 20:00:00'),

    (3, '2026-02-01 12:00:00', '2026-03-30 20:00:00'),
    (3, '2026-05-15 14:00:00', '2026-07-31 20:00:00');
