// Secyurity measure to prevent CSRF attacks on long-lived pages
// The solution for this is to extend the nonce life for specific actions
add_filter('nonce_life', function() {
    return 48 * HOUR_IN_SECONDS; // 48 hours instead of 24
});