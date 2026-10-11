<?php
/**
 * Quick Reference: How to Use Manual Auth in Your Pages
 *
 * No .env. No Laravel. No APP_KEY dependency.
 * 100% standalone PHP with token-based sessions.
 */

// ============================================================================
// EXAMPLE 1: PROTECT A PAGE (Require authentication)
// ============================================================================

// At the TOP of any protected page, include this:
// require_once __DIR__ . '/manual-auth-check.php';
// requireAuth(); // Redirects to login if not authenticated
// $user = auth(); // Get current user array

// ============================================================================
// EXAMPLE 2: OPTIONAL AUTH (Page works for both logged-in and guests)
// ============================================================================

// require_once __DIR__ . '/manual-auth-check.php';
// $user = auth(); // Returns user array or null
//
// if ($user) {
//     echo 'Welcome, ' . htmlspecialchars($user['name']);
// } else {
//     echo 'Please log in';
// }

// ============================================================================
// EXAMPLE 3: LOGOUT (Destroy session)
// ============================================================================

// Redirect to:
// /manual-logout.php

// ============================================================================
// EXAMPLE 4: CREATE A NEW PROTECTED PAGE
// ============================================================================

// File: /public/my-dashboard.php
//
// <?php
// require_once __DIR__ . '/manual-auth-check.php';
// requireAuth();
// $user = auth();
// ?>
// <html>
// <body>
//   <h1>Dashboard for <?php echo $user['name']; ?></h1>
//   <a href="/manual-logout.php">Logout</a>
// </body>
// </html>

// ============================================================================
// DATABASE TABLE STRUCTURE
// ============================================================================

// The system automatically creates this table on first login:
//
// CREATE TABLE manual_sessions (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     user_id INT NOT NULL,
//     token_hash VARCHAR(64) NOT NULL UNIQUE,
//     expires_at DATETIME NOT NULL,
//     ip_address VARCHAR(45),
//     user_agent TEXT,
//     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//     INDEX idx_token_hash (token_hash),
//     INDEX idx_expires_at (expires_at),
//     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
// );

// ============================================================================
// SECURITY FEATURES
// ============================================================================

// 1. Token: 32 random bytes (64 hex chars)
// 2. Storage: Only token HASH stored in DB (not raw token)
// 3. Cookie: HttpOnly flag (can't be accessed by JavaScript)
// 4. Cookie: Secure flag (HTTPS only when available)
// 5. Cookie: SameSite=Lax (CSRF protection)
// 6. Expiry: 24-hour automatic expiration
// 7. IP Tracking: Logged (optional enforcement)
// 8. User-Agent: Logged (can be verified)
// 9. Database validation on EVERY request

// ============================================================================
// FILES CREATED
// ============================================================================

// manual-db-helper.php           - Database connection & helper functions
// manual-login.php               - Login form & handler
// manual-auth-check.php          - Auth verification (include in protected pages)
// manual-logout.php              - Session destruction
// manual-protected-page.php      - Example protected page
// MANUAL_AUTH_REFERENCE.php      - This file

// ============================================================================
// HOW TO USE
// ============================================================================

// 1. Navigate to: http://yoursite.com/manual-login.php
// 2. Login with any existing user from the database
// 3. Token stored in secure cookie
// 4. Access protected pages
// 5. Session validated on every request
// 6. Logout to destroy session

// ============================================================================
// NO DEPENDENCIES
// ============================================================================

// ✓ No Laravel
// ✓ No .env file
// ✓ No APP_KEY
// ✓ No APP_ENV
// ✓ No APP_DEBUG
// ✓ Just plain PHP + PDO + MySQL

echo "Manual Auth System - Quick Reference\n";
echo "====================================\n";
echo "Login: /manual-login.php\n";
echo "Protected Example: /manual-protected-page.php\n";
echo "Logout: /manual-logout.php\n";
?>
