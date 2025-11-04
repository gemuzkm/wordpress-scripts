# Alt Text Synchronization Script for WordPress

A powerful WordPress script that automatically synchronizes alt text attributes for images from your media library to posts and pages. The script intelligently finds attachment IDs by image URLs and applies alt text with detailed logging.

## Features

✅ **URL-Based Image Detection** - Finds images by their src URLs instead of relying on wp-image classes
✅ **Intelligent Attachment Lookup** - Uses multiple methods to find attachment IDs:
   - Search by `_wp_attached_file` metadata
   - Search by `guid` in posts table
   - WordPress built-in `attachment_url_to_postid()` function

✅ **Smart Caching** - Caches URL→ID mappings to speed up processing of duplicate images
✅ **Selective Updates** - Only updates images that need it:
   - Images with empty alt attributes
   - Images with different alt text from media library
   - Skips images that already match

✅ **Detailed Logging** - Complete log file showing:
   - Each image processed (URL, attachment ID, file name)
   - Current vs new alt text
   - Before/after HTML snippets
   - Processing status (updated/skipped/error)
   - Summary statistics

✅ **Multiple Execution Methods**:
   - WordPress admin URL (simple, web-based)
   - WP-CLI command (for large deployments)

✅ **Safe Operation** - Can be tested with detailed logs before production use

## Requirements

- WordPress 4.0+
- PHP 5.6+
- Database access (for attachment lookups)
- Admin privileges for execution
- Write permissions to wp-content directory (for log files)

## Installation

### Option 1: Add to Theme's functions.php

1. Copy the script code to your active theme's `functions.php`:
```php
wp-content/themes/your-theme/functions.php
```

2. Add the complete script at the end of the file

3. Save the file

### Option 2: Create a Must-Use Plugin

1. Create a new directory in `wp-content/mu-plugins/` (if it doesn't exist)

2. Create a new file: `wp-content/mu-plugins/sync-alt-text.php`

3. Add this header at the top:
```php
<?php
/**
 * Plugin Name: Alt Text Synchronization
 * Description: Sync alt text from media library to posts
 * Version: 1.0
 * Author: Your Name
 */
```

4. Add the complete script code below the header

5. Save the file

### Option 3: Create a Dedicated Plugin

1. Create directory: `wp-content/plugins/sync-alt-text/`

2. Create `wp-content/plugins/sync-alt-text/sync-alt-text.php`:
```php
<?php
/**
 * Plugin Name: Alt Text Synchronizer
 * Plugin URI: https://example.com
 * Description: Synchronize alt text from media library to post images
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Insert the complete script code here
?>
```

3. Upload the plugin folder to your server

4. Activate from WordPress admin: Plugins → Installed Plugins

## Usage

### Method 1: Via Admin URL (Easiest)

1. Log in to your WordPress admin panel

2. Ensure you have admin privileges

3. Navigate to any admin page and modify the URL:
```
https://your-site.com/wp-admin/?sync_alt_by_url=1
```

4. Press Enter - the script will start processing

5. Watch the real-time log output as images are processed

6. Wait for "SYNCHRONIZATION COMPLETED" message

7. Check the log file location displayed in the output

### Method 2: Via WP-CLI (Recommended for Large Sites)

1. Connect to your server via SSH/command line

2. Navigate to your WordPress root directory:
```bash
cd /path/to/your/wordpress
```

3. Run the command:
```bash
wp sync-alt-by-url
```

4. Monitor the real-time output

5. Log file will be created and path displayed when complete

### Method 3: Via WordPress Admin Menu (if using plugin)

If installed as a plugin, you can add a menu item to WordPress admin for easier access. You would need to modify the script to add:

```php
add_action('admin_menu', function() {
    add_management_page(
        'Sync Alt Text',
        'Sync Alt Text',
        'manage_options',
        'sync-alt-text',
        'display_sync_page'
    );
});
```

## Log Files

Log files are automatically created in: `wp-content/sync-alt-by-url-YYYY-MM-DD-HH-MM-SS.txt`

### Log File Contents

Each log file contains:

```
[2025-11-04 10:27:45] === START ALT TEXT SYNCHRONIZATION ===
[2025-11-04 10:27:45] Log file: /var/www/wp-content/sync-alt-by-url-2025-11-04-10-27-45.txt
[2025-11-04 10:27:45] Found posts to process: 156
[2025-11-04 10:27:46] 
[2025-11-04 10:27:46] --- PROCESSING POST ID: 42 | TITLE: My Blog Post ---
[2025-11-04 10:27:46] Found <img> tags: 3
[2025-11-04 10:27:46]   [IMG #1] URL: https://example.com/uploads/2025/01/image-001.jpg
[2025-11-04 10:27:46]     🔍 Searching attachment by URL: example.com/uploads/2025/01/image-001.jpg
[2025-11-04 10:27:46]     ✅ Found by _wp_attached_file: ID 1847
[2025-11-04 10:27:46]     📎 File name: Blog Featured Image
[2025-11-04 10:27:46]     Alt in media library: "Beautiful landscape photo"
[2025-11-04 10:27:46]     Current alt in post: ""
[2025-11-04 10:27:46]     ✅ UPDATING: empty alt → "Beautiful landscape photo"
[2025-11-04 10:27:46]     WAS: <img src="https://example.com/uploads/2025/01/image-001.jpg" alt="" ...>...
[2025-11-04 10:27:46]     NOW: <img src="https://example.com/uploads/2025/01/image-001.jpg" alt="Beautiful landscape photo" ...>...
```

### Understanding Log Status Symbols

- ✅ **Successfully updated** - Alt text was added or changed
- ❌ **Skipped** - Image was not updated (see reason)
- ⚠️  **Warning** - Potential issue (missing data, etc.)
- ℹ️  **Info** - General information about processing
- 🔍 **Searching** - Looking for attachment by URL
- 📎 **File reference** - Found attachment information
- 📊 **Progress** - Current processing statistics

## How It Works

### 1. Post Discovery
The script finds all published posts and pages in your WordPress site.

### 2. Image Detection
For each post, it extracts all `<img>` tags with `src` attributes.

### 3. Attachment ID Lookup
For each image URL, it searches the media library using three methods:
- **Method 1**: Search WordPress postmeta table for `_wp_attached_file` matching the filename
- **Method 2**: Search WordPress posts table for `guid` matching the filename
- **Method 3**: Use WordPress built-in `attachment_url_to_postid()` function

The first matching method returns the attachment ID.

### 4. URL Caching
Found URL→ID mappings are cached to speed up processing if the same image appears in multiple posts.

### 5. Alt Text Retrieval
Once attachment ID is found, the script retrieves the alt text from post metadata: `_wp_attachment_image_alt`

### 6. Conditional Update
The script only updates images if:
- Alt attribute is currently empty, OR
- Alt text differs from media library version

### 7. Content Update
Updated image HTML is saved back to the post content using `wp_update_post()`.

### 8. Logging
Every action is logged with timestamp, details, and status indicators.

## Configuration

### Modify Post Types

Edit this section to include other post types (CPTs):

```php
$args = array(
    'post_type'      => array('post', 'page', 'custom_post_type'),
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids'
);
```

### Change Log Directory

Modify the log file path:

```php
$log_file = WP_CONTENT_DIR . '/my-custom-logs/sync-alt-' . date('Y-m-d-H-i-s') . '.txt';
```

Make sure the directory is writable.

### Adjust Progress Reporting

Change progress report frequency (currently every 5 posts):

```php
if ($processed_posts % 10 == 0) {  // Change 10 to any number
    write_log("📊 PROGRESS: ...", $log_file);
}
```

## Safety Considerations

### Before Running

1. **Backup Your Database**
```bash
# Using mysqldump
mysqldump -u username -p database_name > backup.sql

# Or use WordPress backup plugin
```

2. **Test on Staging**
Run the script on a staging environment first to verify behavior.

3. **Check Logs**
Always review logs before running on production.

### Best Practices

- Run during off-peak hours to minimize server load
- Monitor server resources during execution for large sites
- Keep backup copies of log files for audit trail
- Don't interrupt the script while it's running
- Review one full log before running again

## Troubleshooting

### Script Not Running

**Issue**: Admin URL doesn't work or WP-CLI command not recognized

**Solution**:
1. Verify you're logged in as admin
2. Check the script is properly added to functions.php or activated as plugin
3. Verify `manage_options` capability
4. Try WP-CLI directly: `wp plugin list` to verify WordPress CLI works

### No Changes Detected

**Issue**: Script runs but doesn't update any alt text

**Check**:
1. Review the log file for skipped images and reasons
2. Verify alt texts exist in media library (check admin → Media)
3. Verify images are actually embedded in posts (search for `<img>` tags)
4. Check if images are using direct URLs or via other methods

### Attachment Not Found Errors

**Issue**: Log shows "Attachment not found in media library"

**Reasons**:
1. Image URL is from external source or CDN
2. Image filename doesn't match any attachment
3. Image was uploaded outside standard WordPress upload structure

**Solutions**:
- Only URLs matching `wp-content/uploads/` are processed
- Consider re-uploading images through WordPress media library
- Verify the image URL format matches upload directory structure

### Database Errors

**Issue**: Error messages about database queries

**Check**:
1. Database user has proper SELECT permissions
2. No other database operations running concurrently
3. Database connection is stable

## Performance Tips

### For Large Sites (10,000+ posts)

1. **Use WP-CLI** - More efficient than admin URL
2. **Run During Maintenance Window** - Off-peak hours
3. **Monitor Server Resources** - Check CPU and memory usage
4. **Run in Batches** - Modify the script to process X posts per run
5. **Disable Plugins Temporarily** - Reduces server load

### Example Batch Processing

Modify the post query to process only recent posts first:

```php
$args = array(
    'post_type'      => array('post', 'page'),
    'post_status'    => 'publish',
    'posts_per_page' => 100,  // Process 100 at a time
    'paged'          => 1,     // Change this to process different batches
    'orderby'        => 'date',
    'order'          => 'DESC'
);
```

## Compatibility

### WordPress Versions
- ✅ WordPress 4.0+
- ✅ WordPress 5.x
- ✅ WordPress 6.x

### PHP Versions
- ✅ PHP 5.6+
- ✅ PHP 7.x
- ✅ PHP 8.x

### Database
- ✅ MySQL 5.5+
- ✅ MySQL 5.7+
- ✅ MySQL 8.0+
- ✅ MariaDB 10.0+

## Uninstallation

### If Added to functions.php
1. Edit `wp-content/themes/your-theme/functions.php`
2. Delete the entire script code
3. Save the file

### If Installed as Plugin
1. WordPress Admin → Plugins
2. Find "Alt Text Synchronizer"
3. Click "Deactivate"
4. Click "Delete"
5. Confirm deletion

## Support & Troubleshooting

### Check WordPress Health
```bash
wp plugin verify-checksums
wp core verify-checksums
wp db check
```

### Verify Post Content Integrity
The script only updates `post_content` field. Check integrity:

```bash
wp post list --post_type=post --fields=ID,post_content
```

### Manual Log Review
Log files are plain text. You can:
- Download and open in text editor
- Search for specific image IDs or URLs
- Check update counts and statistics

## Advanced Usage

### Custom Logging Function

Extend logging with email notifications:

```php
function write_log_extended($message, $log_file, $email = null) {
    $timestamp = '[' . date('Y-m-d H:i:s') . '] ';
    file_put_contents($log_file, $timestamp . $message . "\n", FILE_APPEND);
    echo $timestamp . $message . "\n";
    
    // Email every N posts
    static $count = 0;
    $count++;
    if ($count % 50 == 0 && $email) {
        wp_mail($email, "Alt Text Sync Progress", "Processed: " . $count . " posts");
    }
}
```

### Scheduled Execution

Create a cron job to run periodically:

```php
add_action('init', function() {
    if (!wp_next_scheduled('sync_alt_text_hook')) {
        wp_schedule_event(time(), 'weekly', 'sync_alt_text_hook');
    }
});

add_action('sync_alt_text_hook', 'sync_image_alt_by_url_with_logs');
```

## Limitations

1. **External Images Only** - Only processes images with URLs matching your WordPress uploads directory
2. **URL-Based Only** - Cannot process images added via other methods (direct post_content editing, etc.)
3. **Attachment Dependency** - Requires images to exist in media library
4. **Plain Alt Text** - Doesn't generate alt text, only transfers existing alt text

## License

This script is provided as-is for WordPress site maintenance. Use at your own discretion.

## Credits

Created for WordPress site administrators to automate image alt text management and improve site accessibility and SEO.

## Changelog

### Version 1.0.0
- Initial release
- URL-based image detection
- Multiple attachment lookup methods
- URL→ID caching
- Detailed logging
- Admin URL and WP-CLI support

---

**Last Updated**: November 4, 2025
**Compatibility**: WordPress 4.0+ | PHP 5.6+
