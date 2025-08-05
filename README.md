# Cat Redirector - A WP Plugin

Version: 1.2  
Author: impshum  
Author URI: https://recycledrobot.co.uk

---

## Description

Creates a URL that redirects to the most recent post in a specified category.

---

## Features

- Redirects a custom slug (default: /latest) to the newest post in a chosen category.
- Admin settings page to select category, redirect slug, and cache duration.
- Caches redirect URL for performance (configurable cache duration).
- Adds a settings link on the plugins page.

---

## Installation

1. Upload the plugin folder to `/wp-content/plugins/cat-redirector/`.
2. Activate the plugin via the WordPress admin plugins page.
3. Go to **Cat Redirector** settings page in the admin menu.
4. Choose a category, set your redirect slug, and cache duration.
5. Visit `yoursite.com/{redirect-slug}` to be sent to the latest post.

---

## Configuration

- **Category:** Select the category to redirect from.  
- **Redirect Slug:** The URL slug to use for redirect (e.g., `latest`).  
- **Cache Duration:** How long (in seconds) to cache the redirect URL. Set 0 to disable caching.

---

## Development

- Flushes rewrite rules on activation and deactivation.
- Adds a rewrite rule for the redirect slug.
- Uses a transient to cache the latest post URL.
- Redirects with 301 status to the latest post or homepage if none found.
