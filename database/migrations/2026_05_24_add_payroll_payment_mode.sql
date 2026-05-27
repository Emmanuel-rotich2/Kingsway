-- Add payment mode tracking to staff payroll records
-- Required for accurate payslip audit details (Bank/Cash/M-Pesa/Airtel Money)

ALTER TABLE staff_payroll
    ADD COLUMN IF NOT EXISTS payment_mode ENUM('bank', 'cash', 'mpesa', 'airtel_money') NULL AFTER payment_date;
