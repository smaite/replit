-- Add vendor_status column to order_items table
ALTER TABLE `order_items`
ADD COLUMN `vendor_status` enum('pending','processing','ready','shipped','delivered','cancelled') DEFAULT 'pending';
