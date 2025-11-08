-- Users
CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       username VARCHAR(50) NOT NULL,
                       email VARCHAR(100) NOT NULL,
                       password_hash VARCHAR(255) NOT NULL,
                       role ENUM('user', 'admin') DEFAULT 'user',
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
                          available_from DATETIME,
                          available_to DATETIME,
                          status ENUM('pending','approved','rejected') DEFAULT 'approved',
                          FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- Sample Users (password: "password")
INSERT INTO users (username, email, password_hash, role) VALUES
                                                             ('admin', 'admin@parkshare.local', '$2y$10$b5aZ4Cqos0HZg3CCW.MmyOn0uIdrUc09llz3pUEaINHvj10JB.siK', 'admin'),
                                                             ('alice', 'alice@test.com', '$2y$10$b5aZ4Cqos0HZg3CCW.MmyOn0uIdrUc09llz3pUEaINHvj10JB.siK', 'user'),
                                                             ('bob', 'bob@test.com', '$2y$10$b5aZ4Cqos0HZg3CCW.MmyOn0uIdrUc09llz3pUEaINHvj10JB.siK', 'user');


-- Sample Parking Spots
INSERT INTO parkings (owner_id, title, description, location, price, available_from, available_to, status) VALUES
                                                                                                               (2, 'City Center Parking', 'Secure parking in downtown', 'Berlin Mitte', 10.00, '2025-11-10 08:00:00', '2025-11-10 20:00:00', 'approved'),
                                                                                                               (3, 'Office Garage', 'Covered parking near office', 'Berlin Friedrichshain', 8.50, '2025-11-10 09:00:00', '2025-11-10 18:00:00', 'approved'),
                                                                                                               (2, 'Weekend Parking', 'Cheap weekend parking', 'Berlin Kreuzberg', 5.00, '2025-11-15 08:00:00', '2025-11-15 22:00:00', 'approved');
