# WordPress Primary Category Updater

A PHP script for bulk assignment and management of primary categories in WordPress using SEOPress plugin.

## Description

This script automatically sets the primary category for WordPress posts by finding the deepest (most specific) category assigned to each post. It's designed to work with the SEOPress plugin's primary category functionality, which is important for SEO and breadcrumb navigation.

## Features

- **Bulk Processing**: Processes posts in batches to handle large websites efficiently
- **Smart Category Detection**: Automatically finds the deepest category in the category hierarchy
- **Memory Management**: Includes memory cleanup and server load reduction
- **Progress Tracking**: Shows detailed progress during execution
- **Test Mode**: Allows testing on a small sample before full execution
- **WP-CLI Integration**: Designed to work seamlessly with WP-CLI
- **Safe Execution**: Skips posts that already have the correct primary category set

## Requirements

- WordPress installation
- SEOPress plugin installed and activated
- WP-CLI (recommended)
- PHP 7.0 or higher

## Installation

1. Download the `update-primary-categories.php` file
2. Upload it to your WordPress root directory or any accessible location
3. Ensure you have WP-CLI installed for optimal usage

## Usage

### With WP-CLI (Recommended)

```bash
# Test mode - process 10 posts (default)
wp eval-file update-primary-categories.php test

# Test mode - process specific number of posts
wp eval-file update-primary-categories.php test 25

# Full update - process all posts
wp eval-file update-primary-categories.php update
```

### Direct PHP Execution

```bash
# Run the script directly (will execute full update by default)
php update-primary-categories.php
```

## How It Works

1. **Category Analysis**: For each post, the script analyzes all assigned categories
2. **Depth Calculation**: Determines the hierarchy level of each category
3. **Primary Selection**: Selects the category with the deepest nesting level
4. **Meta Update**: Updates the `_seopress_robots_primary_cat` meta field
5. **Progress Reporting**: Provides detailed logging of all operations

## Configuration

The script includes several configurable parameters:

- **Batch Size**: Default 100 posts per batch (adjustable in code)
- **Memory Management**: Automatic cache flushing every 100 posts
- **Server Load**: 1-second pause between batches (configurable)

## Output Examples

### Test Mode Output
```
Test mode: processing 10 posts

Post 123: 'Sample Post Title'
 Categories:
  - Cars [ID: 5, Level: 0]
    - BMW [ID: 15, Level: 1]
      - BMW X5 [ID: 25, Level: 2]
 Recommended primary: BMW X5 [ID: 25]
 Current primary: not set
```

### Update Mode Output
```
Starting bulk update of primary categories...
Batch size: 100
Total published posts: 1500
Processing posts 1 - 100 of 1500
 Post 123: updated (primary category: BMW X5 [ID: 25])
 Post 124: skipped (no categories)
Progress: 6.7% (100/1500)
=== COMPLETED ===
Total posts processed: 1500
Updated: 1200
Skipped: 300
=================
```

## Safety Features

- **Duplicate Prevention**: Skips posts that already have the correct primary category
- **Error Handling**: Gracefully handles missing categories and WordPress errors
- **Batch Processing**: Prevents memory exhaustion on large sites
- **Logging**: Comprehensive logging for troubleshooting

## Troubleshooting

### Common Issues

1. **Script stops unexpectedly**: Check PHP memory limit and execution time
2. **No categories found**: Ensure posts have categories assigned
3. **Permission errors**: Verify file permissions and WordPress access

### Performance Tips

- Run during low-traffic periods
- Increase PHP memory limit for large sites
- Use test mode first to estimate execution time

## Meta Field Details

The script works with the SEOPress meta field:
- **Field Name**: `_seopress_robots_primary_cat`
- **Field Type**: Category ID (integer)
- **Purpose**: Defines the primary category for SEO and breadcrumbs

## Compatibility

- **WordPress**: 5.0+
- **SEOPress**: All versions
- **PHP**: 7.0+
- **WP-CLI**: 2.0+

## Support

This script is provided as-is for educational and practical use. Always test on a staging environment before running on production sites.

## License

Open source - feel free to modify and distribute according to your needs.