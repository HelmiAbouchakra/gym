-- Create the additional tables needed for membership registration
USE gym;

-- Add missing columns to user_memberships table if they don't exist
ALTER TABLE user_memberships 
ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) AFTER status,
ADD COLUMN IF NOT EXISTS total_amount DECIMAL(10, 2) AFTER payment_method;

-- Create table for membership add-ons
CREATE TABLE IF NOT EXISTS membership_addons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membership_id INT NOT NULL,
    addon_name VARCHAR(100) NOT NULL,
    addon_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (membership_id) REFERENCES user_memberships(id) ON DELETE CASCADE
);

-- Create table for customer details
CREATE TABLE IF NOT EXISTS customer_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membership_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    zip VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (membership_id) REFERENCES user_memberships(id) ON DELETE CASCADE
);
