-- Add Razorpay payment fields to orders table
ALTER TABLE orders 
ADD COLUMN razorpay_payment_id VARCHAR(255) NULL,
ADD COLUMN razorpay_order_id VARCHAR(255) NULL,
ADD COLUMN razorpay_signature VARCHAR(255) NULL,
ADD COLUMN payment_currency VARCHAR(10) DEFAULT 'INR',
ADD COLUMN payment_amount DECIMAL(10,2) NULL;

-- Add index for better performance
CREATE INDEX idx_razorpay_payment_id ON orders(razorpay_payment_id);
CREATE INDEX idx_razorpay_order_id ON orders(razorpay_order_id);
