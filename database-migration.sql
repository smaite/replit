-- SASTO Hub Database Migration - Complete Setup for Nepal
-- Run this SQL to setup all tables and columns for the full platform

-- Add rejection_reason column to vendors table (if not exists)
ALTER TABLE vendors 
ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL DEFAULT NULL;

-- Create vendor_documents table for storing vendor verification documents
CREATE TABLE IF NOT EXISTS vendor_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    document_type ENUM('national_id_front', 'national_id_back', 'pan_vat_document', 'business_registration') NOT NULL,
    document_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX (vendor_id),
    INDEX (document_type)
);

-- Add new columns to vendors table for enhanced information (Nepal-specific)
ALTER TABLE vendors 
ADD COLUMN IF NOT EXISTS bank_account_name VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS bank_account_number VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS business_location VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS business_city VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS business_state VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS business_postal_code VARCHAR(10) NULL,
ADD COLUMN IF NOT EXISTS business_phone VARCHAR(15) NULL,
ADD COLUMN IF NOT EXISTS business_website VARCHAR(255) NULL;

-- Drop IFSC code column if it exists (Nepal doesn't use IFSC)
ALTER TABLE vendors 
DROP COLUMN IF EXISTS bank_ifsc_code;

-- Create website_settings table for admin customization
CREATE TABLE IF NOT EXISTS website_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add order_status_updates table columns if they don't exist
ALTER TABLE order_status_updates 
ADD COLUMN IF NOT EXISTS notes TEXT NULL DEFAULT NULL;

-- Add columns to orders table for shipping info
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(10, 2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS shipping_address TEXT NULL;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_vendors_status ON vendors(status);
CREATE INDEX IF NOT EXISTS idx_vendors_created ON vendors(created_at);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
CREATE INDEX IF NOT EXISTS idx_products_vendor ON products(vendor_id);
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
CREATE INDEX IF NOT EXISTS idx_website_settings_key ON website_settings(setting_key);

-- All migrations completed for Nepal setup!
