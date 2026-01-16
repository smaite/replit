-- Add cancel_reason column to order_items table
ALTER TABLE `order_items`
ADD COLUMN `cancel_reason` text DEFAULT NULL;
