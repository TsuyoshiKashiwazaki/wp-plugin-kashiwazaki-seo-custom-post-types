# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Kashiwazaki SEO Custom Post Types is a WordPress plugin that enables no-code creation and management of custom post types with advanced features like hierarchical URL structures and archive display control. The plugin is fully Japanese-localized.

**System Requirements:**
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+

## Architecture

### Core Components

1. **Database Layer** (`class-database.php`)
   - Manages custom table `wp_kstb_post_types` for storing post type configurations
   - Handles CRUD operations for post types
   - Schema includes archive display settings, parent directory relationships, and hierarchical structures

2. **Post Type Registration** (`class-post-type-registrar.php`)
   - Registers custom post types with WordPress using `register_post_type()`
   - Builds full hierarchical paths recursively for nested post types
   - Key method: `build_full_path()` - recursively constructs parent/child/grandchild path structures

3. **Archive Controller** (`class-archive-controller.php`)
   - Controls archive page display behavior via multiple WordPress hooks
   - Implements "best match" logic to find the longest matching path for nested post types
   - Three archive display modes:
     - `default`: Show static page if exists at same URL (when has_archive is false)
     - `none`: Force 404 for archive URLs
     - `custom_page`: Display specified page
   - Critical: Hooks into `parse_request`, `pre_get_posts`, and `template_redirect` to intercept requests

4. **Parent Selector** (`class-parent-selector.php`)
   - Adds metabox to post edit screens for selecting parent pages
   - Generates hierarchical URLs (e.g., `parent/post-type/post-name`)
   - Blocks old non-hierarchical URLs and prevents unwanted redirects
   - Contains extensive redirect prevention logic

5. **Admin Interface** (`class-admin.php`, `templates/admin-page.php`)
   - Provides UI for managing custom post types
   - Single-page admin interface with tabs for settings

6. **AJAX Handler** (`class-ajax-handler.php`)
   - Handles all AJAX requests from admin interface
   - Manages create, update, delete operations

### Key Concepts

**Hierarchical URL Structure:**
Post types can have parent directories creating paths like `company/member/john-doe`:
- Parent directories can be either static pages or other custom post types
- Path building is recursive - handles unlimited nesting depth
- URLs are validated using "best match" algorithm (longest path wins)

**Archive Display Control:**
Custom post types can control archive page behavior:
- `has_archive`: Controls if archive is enabled
- `archive_display_type`: Determines what displays at archive URL
- `archive_page_id`: Specifies custom page to show
- System intelligently falls back to static pages with matching URLs

**Rewrite Rules:**
Plugin manages WordPress rewrite rules carefully:
- Flushes rules on post type save/delete
- Filters rewrite rules array to remove/add archive routes
- Uses `with_front => false` to prevent WordPress prefix interference

## Development Commands

### Testing
No automated test suite currently exists. Manual testing in WordPress environment required.

### Debugging
Enable debug output by adding `?kstb_debug=1` query parameter (requires `manage_options` capability).

### Git Workflow
**CRITICAL:** Follow the workflow rules in `.claude/github-workflow-rules.md`:
1. Complete all development work locally
2. Update version numbers in 4 files:
   - `kashiwazaki-seo-custom-post-types.php` (2 locations)
   - `readme.txt` (3 locations)
   - `README.md` (version badge with `--dev`, version history)
   - `CHANGELOG.md` (add new version at top)
3. Commit and push everything in ONE commit with message format:
   ```
   Update version to X.X.X

   - Fix: description
   - Add: description
   - Improve: description
   ```
4. Create git tags: remove `-dev` from previous version, add `-dev` to new version

**DO NOT** make incremental commits during development and push them separately.

### Version Management
When updating versions:
- Main plugin file: Update both the header comment and `KSTB_VERSION` constant
- Keep README.md version badge with `-dev` suffix during development
- Update CHANGELOG.md following Keep a Changelog format
- Update readme.txt for WordPress.org compatibility

## Common Development Patterns

### Adding New Post Type Settings
1. Add column to database table in `class-database.php::create_tables()`
2. Update insert/update methods in `class-database.php`
3. Add UI field in `templates/admin-page.php`
4. Update AJAX handler in `class-ajax-handler.php`
5. Use new setting in `class-post-type-registrar.php::register_single_post_type()`

### Modifying URL Behavior
- Archive display logic: `class-archive-controller.php`
- Individual post URLs: `class-parent-selector.php::custom_post_link()`
- Rewrite rules: `class-archive-controller.php::filter_rewrite_rules()`

### Hook Priority Notes
The plugin uses careful hook priorities to control WordPress request flow:
- Archive controller initializes at priority 5 on `init`
- Parent selector adds rewrite rules at priority 1
- Multiple redirect prevention hooks at priority 1
- Template redirect hooks at priority -999 (extremely early)

### WordPress Integration Points
- Uses standard `register_post_type()` API
- Creates custom database table (not using post meta)
- Integrates with REST API (`show_in_rest: true`)
- Supports Gutenberg block editor
- Compatible with standard WordPress taxonomies

## File Structure

```
/includes/          - Core PHP classes
  class-database.php
  class-post-type-registrar.php
  class-archive-controller.php
  class-parent-selector.php
  class-admin.php
  class-ajax-handler.php
  class-post-type-force-register.php
  class-post-type-menu-fix.php
  class-post-mover.php
/templates/         - Admin UI templates
  admin-page.php
/assets/            - CSS and JavaScript
  admin.css, admin.js           - Plugin settings page
  admin-global.css, admin-global.js  - All admin pages
/.claude/           - Development guidelines
  github-workflow-rules.md
```

## Important Notes

- Plugin text domain: `kashiwazaki-seo-type-builder`
- Database table prefix: `kstb_` (full table: `wp_kstb_post_types`)
- All UI strings are in Japanese
- Plugin conflicts are possible with other permalink-modifying plugins
- Flush rewrite rules after any URL structure changes
- The `build_full_path()` methods are critical for nested post type functionality
