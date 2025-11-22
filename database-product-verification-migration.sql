-- Product Verification System for SASTO Hub
-- Products uploaded by vendors require admin approval before visibility

-- Add verification columns to products table
ALTER TABLE products ADD COLUMN IF NOT EXISTS verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER status;
ALTER TABLE products ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) DEFAULT NULL AFTER verification_status;
ALTER TABLE products ADD COLUMN IF NOT EXISTS verified_by INT DEFAULT NULL AFTER rejection_reason;
ALTER TABLE products ADD COLUMN IF NOT EXISTS verified_at TIMESTAMP NULL DEFAULT NULL AFTER verified_by;

-- Add foreign key for verified_by admin
ALTER TABLE products ADD CONSTRAINT fk_products_verified_by 
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;

-- Add index for product verification queries
CREATE INDEX idx_products_verification ON products(vendor_id, verification_status);
CREATE INDEX idx_products_status ON products(status, verification_status);

-- Create product audit table for tracking changes
CREATE TABLE IF NOT EXISTS product_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    action VARCHAR(50) NOT NULL, -- 'created', 'updated', 'approved', 'rejected', 'revoked'
    admin_id INT DEFAULT NULL,
    reason VARCHAR(500),
    old_data JSON,
    new_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create index for audit log
CREATE INDEX idx_audit_product ON product_audit_log(product_id);
CREATE INDEX idx_audit_action ON product_audit_log(action);
