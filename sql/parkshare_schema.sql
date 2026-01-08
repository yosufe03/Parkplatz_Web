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

-- Districts (Stadtteile) and Neighborhoods
CREATE TABLE districts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE neighborhoods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  district_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE
);

-- Parkings
CREATE TABLE parkings (
              id INT AUTO_INCREMENT PRIMARY KEY,
              owner_id INT NOT NULL,
              title VARCHAR(100) NOT NULL,
              description TEXT,
              price DECIMAL(10,2) NOT NULL,
              status ENUM('pending','approved','rejected') DEFAULT 'pending',
              main_image VARCHAR(255) NULL,
              available_from DATETIME,
              available_to DATETIME,
              -- geographic references (required)
              district_id INT NOT NULL,
              neighborhood_id INT NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              FOREIGN KEY (owner_id) REFERENCES users(id),
              FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE RESTRICT,
              FOREIGN KEY (neighborhood_id) REFERENCES neighborhoods(id) ON DELETE RESTRICT,
              INDEX idx_parking_district (district_id),
              INDEX idx_parking_neighborhood (neighborhood_id)
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
      price_day DECIMAL(10,2) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

      FOREIGN KEY (parking_id) REFERENCES parkings(id) ON DELETE CASCADE,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE parking_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parking_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL, -- 1..5
  comment VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (parking_id) REFERENCES parkings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY ux_parking_user (parking_id, user_id)
);

CREATE TABLE favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parking_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parking_id) REFERENCES parkings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY ux_parking_user_fav (parking_id, user_id)
);

-- Sample Users (password: "password")
INSERT INTO users (username, email, password_hash, role) VALUES
     ('admin', 'admin@parkshare.local', '$2y$10$b5aZ4Cqos0HZg3CCW.MmyOn0uIdrUc09llz3pUEaINHvj10JB.siK', 'admin'),
     ('alice', 'alice@test.com', '$2y$10$b5aZ4Cqos0HZg3CCW.MmyOn0uIdrUc09llz3pUEaINHvj10JB.siK', 'user'),
     ('bob', 'bob@test.com', '$2y$10$b5aZ4Cqos0HZg3CCW.MmyOn0uIdrUc09llz3pUEaINHvj10JB.siK', 'user');


-- Sample Districts and Neighborhoods
INSERT INTO districts (name) VALUES
  ('Mitte'),
  ('Friedrichshain'),
  ('Kreuzberg');

INSERT INTO neighborhoods (district_id, name) VALUES
  (1, 'Mitte Zentrum'),
  (2, 'Boxhagener Kiez'),
  (3, 'SO36');

-- Sample Parkings (include district/neighborhood IDs)
INSERT INTO parkings (owner_id, title, description, price, main_image, available_from, available_to, status, district_id, neighborhood_id) VALUES
  (2, 'City Center Parking', 'Secure parking in downtown', 10.00, 'city_center.jpg', '2025-11-10 08:00:00', '2025-11-10 20:00:00', 'approved', 1, 1),
  (3, 'Office Garage', 'Covered parking near office', 8.50, 'office_garage.jpg', '2025-11-10 09:00:00', '2025-11-10 18:00:00', 'approved', 2, 2),
  (2, 'Weekend Parking', 'Cheap weekend parking', 5.00, 'weekend_parking.jpg', '2025-11-15 08:00:00', '2025-11-15 22:00:00', 'approved', 3, 3);

INSERT INTO parking_availability (parking_id, available_from, available_to) VALUES
    (1, '2026-01-01 12:00:00', '2026-06-30 20:00:00'),

    (2, '2026-06-01 12:00:00', '2026-06-30 20:00:00'),

    (3, '2026-05-15 14:00:00', '2026-07-31 20:00:00');
