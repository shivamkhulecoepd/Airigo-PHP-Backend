-- FCM Tokens Table for Firebase Notifications
CREATE TABLE IF NOT EXISTS user_fcm_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(500) NOT NULL UNIQUE,
    device_type ENUM('mobile', 'web', 'tablet') DEFAULT 'mobile',
    device_info TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_is_active (is_active),
    INDEX idx_device_type (device_type)
) ENGINE=InnoDB;

-- Add FCM token column to users table (optional - for storing single token per user)
ALTER TABLE users ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(500) NULL;
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_fcm_token (fcm_token);