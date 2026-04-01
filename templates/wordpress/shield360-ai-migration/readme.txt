=== Shield360 AI Migration ===
Contributors: shield360
Tags: migration, backup, transfer, clone, move
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically migrate your entire WordPress site to another server with one click.

== Description ==

Shield360 AI Migration is a powerful, all-in-one WordPress migration plugin that lets you move your entire site – database, themes, plugins, media uploads, and settings – to a new server effortlessly.

**Features:**

* **One-Click Export** – Package your full site (database + files) into a single downloadable ZIP.
* **One-Click Import** – Upload a migration package to restore or clone any site.
* **Push Migration** – Directly push your site to a remote server over a secure REST API.
* **Pull Migration** – Pull a migration package from a remote site.
* **Serialization-Safe Search & Replace** – Automatically rewrites URLs and paths in the database, safely handling serialized data and JSON.
* **Selective Migration** – Choose which components to include: database, themes, plugins, media.
* **Secure Transfers** – All remote communication is authenticated with unique API keys.
* **No Size Limits** – Handles large sites by processing data in batches.
* **Clean UI** – Modern, tabbed admin interface with progress indicators.

== Installation ==

1. Upload the `shield360-ai-migration` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Shield360 Migration** in the admin sidebar.

== Frequently Asked Questions ==

= How do I migrate to another server? =

**Method 1 – Export / Import:**
1. On the source site, go to Shield360 Migration > Export, create a package, and download it.
2. On the destination site, install Shield360 AI Migration, go to Import, upload the package.

**Method 2 – Push:**
1. Install Shield360 AI Migration on both sites.
2. On the destination site, copy the API key from Settings.
3. On the source site, go to Push, enter the destination URL and API key, then push.

= Is it safe? =
Yes. All remote transfers use unique API keys. Migration packages are stored in a protected directory and automatically cleaned up after 24 hours.

== Changelog ==

= 1.0.0 =
* Initial release.
* Full site export and import.
* Push and pull remote migration.
* Serialization-safe search and replace.
* Modern admin UI.
