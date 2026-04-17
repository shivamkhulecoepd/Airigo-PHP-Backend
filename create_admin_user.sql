-- =====================================================
-- Admin User Setup Script
-- Run this in your MySQL database to create admin user
-- =====================================================

-- 1. Create Admin User
-- Password: password (change the hash if you want a different password)
INSERT INTO users (
    email, 
    password_hash, 
    user_type, 
    status, 
    email_verified,
    phone,
    created_at,
    updated_at
) VALUES (
    'admin@airigo.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'active',
    1,
    NULL,
    NOW(),
    NOW()
);

-- 2. Verify the admin user was created
SELECT id, email, user_type, status, email_verified, created_at 
FROM users 
WHERE user_type = 'admin';

-- 3. (Optional) Create additional admin users
-- Uncomment and modify as needed
/*
INSERT INTO users (
    email, 
    password_hash, 
    user_type, 
    status, 
    email_verified,
    created_at,
    updated_at
) VALUES (
    'superadmin@airigo.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'active',
    1,
    NOW(),
    NOW()
);
*/

-- 4. (Optional) Convert existing user to admin
-- Uncomment and modify the email
/*
UPDATE users 
SET user_type = 'admin' 
WHERE email = 'youremail@example.com';
*/

-- 5. View all admin users
SELECT 
    id,
    email,
    user_type,
    status,
    email_verified,
    created_at,
    updated_at
FROM users 
WHERE user_type = 'admin'
ORDER BY created_at DESC;

-- =====================================================
-- DEFAULT CREDENTIALS
-- =====================================================
-- Email: admin@airigo.com
-- Password: password
-- =====================================================

-- =====================================================
-- TO GENERATE CUSTOM PASSWORD HASH:
-- =====================================================
-- 1. Create a file named 'hash.php' with this content:
--    <?php
--    echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);
--    ?>
-- 2. Run: php hash.php
-- 3. Copy the hash and use it in password_hash field
-- =====================================================
