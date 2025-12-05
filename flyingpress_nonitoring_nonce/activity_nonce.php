// Добавь в functions.php (опционально):
add_action('init', function() {
    if (isset($_GET['debug_mlcm_nonce']) && current_user_can('manage_options')) {
        $cookie_nonce = $_COOKIE['mlcm_nonce'] ?? 'not set';
        $is_valid = wp_verify_nonce($cookie_nonce, 'mlcm_nonce') ? 'VALID' : 'INVALID';
        
        echo '<pre>';
        echo "Cookie nonce: " . $cookie_nonce . "\n";
        echo "Status: " . $is_valid . "\n";
        echo "Expires in: " . (isset($_COOKIE['mlcm_nonce']) ? 'check browser DevTools' : 'N/A') . "\n";
        echo '</pre>';
        exit;
    }
});


