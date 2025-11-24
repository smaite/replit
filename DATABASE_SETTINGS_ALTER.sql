-- SQL ALTER CODE TO ADD SETTINGS TABLE
-- Run these commands to add settings to your database

-- Create Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    setting_type ENUM('text', 'color', 'url', 'textarea', 'image') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('website_name', 'SASTO Hub', 'text'),
('website_tagline', 'Your Online Marketplace', 'text'),
('header_logo', '/assets/images/logo.png', 'image'),
('footer_logo', '/assets/images/logo.png', 'image'),
('footer_name', 'SASTO Hub', 'text'),
('copyright_text', '© 2025 SASTO Hub. All rights reserved.', 'text'),
('primary_color', '#4f46e5', 'color'),
('contact_email', 'info@sastohub.com', 'text'),
('contact_phone', '+977 1234567890', 'text'),
('address', 'Kathmandu, Nepal', 'textarea'),
('facebook_url', '', 'url'),
('twitter_url', '', 'url'),
('instagram_url', '', 'url'),
('youtube_url', '', 'url')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Add rejection_reason column to vendors table if it doesn't exist
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS verified_documents LONGTEXT DEFAULT NULL;
