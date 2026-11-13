# Slim WordPress

Slim WordPress is WordPress 7.1.4 with a SQLite database, a smaller admin, and updates that come from this GitHub repository instead of WordPress.org.

Current Slim release: **1.0.15**  
Repository: [codelyfe/slimword](https://github.com/codelyfe/slimword)

Plugins and themes still install the normal way. Core files stay in place so WordPress APIs keep working.

## Requirements

- PHP 7.2 or newer, with the PDO SQLite extension
- SQLite 3.37.0 or newer
- A web server pointed at this folder, or PHP’s built-in server

MySQL and MariaDB are not used.

## Install

1. Copy `wp-config-sample.php` to `wp-config.php`.
2. Replace the eight salt lines. You can generate new ones at <https://api.wordpress.org/secret-key/1.1/salt/>.
3. Create a `private` directory next to `wp-config.php`. The database file is created there on first run: `private/database.sqlite`.
4. Open the site. WordPress shows the usual install screen at `/wp-admin/install.php`.
5. Finish that screen. Tables are created in the SQLite file.

`DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `DB_HOST` are still defined because WordPress expects them. They are not a MySQL login. `DB_ENGINE` is `sqlite`, and `DB_PATH` is the real database file.

Keep `private` out of the web root if you can. This copy also denies web access to that folder with `.htaccess` and `web.config`. Do not publish `wp-config.php` or the SQLite file.

## What changed from stock WordPress

### Database

- The official [SQLite Database Integration](https://wordpress.org/plugins/sqlite-database-integration/) plugin (3.1.3) is included.
- `wp-content/db.php` is the drop-in that loads that plugin, so WordPress talks to SQLite through the normal `wpdb` API.
- Posts, users, options, and plugin data live in `private/database.sqlite`.
- Turning this off does not import or export MySQL. It is a separate database.

### Removed extras

These bundled pieces are not in this copy:

- Akismet
- Hello Dolly
- Themes Twenty Twenty-Three and Twenty Twenty-Four
- The stock WordPress `readme.html` text. A short `readme.html` stays, because the upgrader refuses a package that does not contain one.

Twenty Twenty-Five remains so a new site has a working theme. You can install any other theme.

These features are turned off in `wp-content/mu-plugins/slimword.php` without deleting the core code plugins may call:

- Emoji detection script and styles
- The `wp-embed` script and oEmbed discovery links
- jQuery Migrate
- XML-RPC
- Generator, Really Simple Discovery, Windows Live Writer, shortlink, and adjacent-post tags in the HTML head
- The “WordPress” capital-P filter
- Front-end Heartbeat
- Dashicons for visitors who are not logged in
- DNS prefetch to `w.org`
- Default pingbacks and self-pings
- The WordPress news, Quick Draft, and Site Health dashboard boxes

`wp-config-sample.php` also sets:

- `WP_POST_REVISIONS` to 3
- `AUTOSAVE_INTERVAL` to 120 seconds
- `EMPTY_TRASH_DAYS` to 7

### HTTPS

`wp-content/slimword-https.php` runs before WordPress loads.

- If this host already answered on HTTPS, or port 443 accepts TLS, HTTP requests redirect to HTTPS and admin cookies are marked secure.
- If nothing is listening on 443, the site stays on HTTP.
- The check is cached for 10 minutes in `private/https-available.txt`.
- A self-signed certificate still counts as available. The check only asks whether TLS answers.

### Dashboard

The stock “Welcome to WordPress” panel is replaced. The new panel shows:

- This is Slim WordPress on one SQLite file
- The database path
- Links to add a plugin and choose a theme
- Whether HTTPS is on for the current request

### Cache

WordPress object cache is stored as files in `private/cache/object` by `wp-content/object-cache.php`. Plugins use the normal cache functions. Settings → Cache lists the group, key, size, and expiry, and **Clear cache** deletes those files and the saved pages.

Public pages for visitors who are not logged in are also saved as HTML in `private/cache/pages` for 10 minutes. `wp-content/advanced-cache.php` serves a saved page before WordPress boots. Logged-in visits, searches, and addresses with a query string are not saved. Saving a post, switching the theme, or clicking **Clear cache** deletes the saved pages. `WP_CACHE` is on in `wp-config-sample.php` so that file is loaded.

### Cookies

Settings → Cookies controls the notice: on or off, message, button, link, placement, and colors. Clicking the button stores a `slimword_consent` cookie for 6 months. The notice stays hidden while that cookie is present.

`/cookies/` explains every cookie this site sets: the consent cookie, the login cookies, and the comment cookies. It says there are no advertising or analytics cookies. If the link address in the setting is blank, the notice points at that page. A published page with the slug `cookies` is left as written.

### Reading

Public pages load the accessibility panel from [A11y Widget Place Anywhere](https://github.com/codelyfe/A11y-Widget-Place-Anywhere). The button is at the bottom left. Text size, contrast, spacing, links, grayscale, motion, and the rest of that panel stay in the browser. The panel moves up while the cookie notice is open, and those choices still apply on block themes. **Powered by** links to that repository.

### Favicon

If the site has a logo and no site icon, the logo is used as the browser icon.

### Comments

Settings → Slim comments is off until an admin turns it on. The switch is stored as `slimword_disable_comments`. While it is on, `wp-content/mu-plugins/slimword-comments.php` closes comments and pingbacks everywhere, hides existing comments, removes the comments screens, and refuses new comments.

### Scheduled tasks

Settings → Tasks is on by default. While it is on, WordPress does not call `wp-cron.php`, and `wp-content/mu-plugins/slimword-cron.php` runs tasks that are already due at the end of a normal visit. Turn the setting off to use WordPress cron again. The GitHub update check uses that same visit: if the last check is older than 15 minutes, the site asks GitHub then. No separate cron service is required.

### Salts

Settings → Salts has **Get new salts**. It loads eight new keys from <https://api.wordpress.org/secret-key/1.1/salt/>, writes them into `wp-config.php`, and signs everyone out.

### Protection

Settings → Protection is on until an admin turns it off. The switch is stored as `slimword_protect`. While it is on, `wp-content/mu-plugins/slimword-guard.php` does only this:

- Hides author archives and the public user list from people who are not logged in
- Uses one login error instead of saying whether the user exists
- Stops an address for 15 minutes after 8 failed logins
- Asks a new addition question on every login page load, and ignores a login that fills the hidden company field
- Turns off the theme and plugin file editor and application passwords
- Refuses requests for `wp-config.php`, `.env`, and `../` paths
- Sends headers that turn off the camera, microphone, and location, and on HTTPS keeps later visits on HTTPS for one year

A wrong math answer does not count toward the login lock. The hidden field is off screen, so people do not see it.

It does not scan every request body or keep a bot database.

### Discovery

Every public page gets a canonical URL, a description, Open Graph and Twitter tags, and schema.org data for the site and the current page. A page or post can set its own SEO title and description in the Discovery box. Blank fields use the page title, the excerpt, or the start of the content. The home page uses the tagline when it has no custom description. Pages and posts also publish a breadcrumb trail, and a details/summary question becomes FAQ schema. Images without alt text use the page title.

Settings → Discovery lists the values this site is already publishing and shows a share preview of the home page. Search engines use the normal WordPress sitemap. Answer and generative engines get `/llms.txt` and `/llms-full.txt`, linked from the page and from `robots.txt`. Training crawlers are limited to those two files. Search crawlers may read the public site. Author archives and search results stay out of the index. The files use this site’s name, description, pages, and posts.

### Site Health

Site Health is removed from Tools and the dashboard. Opening `site-health.php` or `site-health-info.php` returns you to the dashboard. The weekly Site Health cron event is cleared, and the `view_site_health_checks` capability is denied so its remote tests do not run.

### Updates

WordPress core updates come from WordPress.org, so the version number stays current for plugins and themes. Slim WordPress updates come from GitHub and copy the Slim files only, unless that package contains a newer WordPress than the one installed. `wp-config.php`, uploads, and the SQLite database stay in place. Automatic core updates stay off. Dashboard → Updates is where a newer WordPress is installed.

### About

The WordPress About screen stays the current core page. Slim WordPress is shown above it.

## Layout

| Path | Role |
| --- | --- |
| `wp-config-sample.php` | SQLite config to copy |
| `wp-content/db.php` | SQLite drop-in |
| `wp-content/plugins/sqlite-database-integration/` | MySQL-to-SQLite driver |
| `wp-content/mu-plugins/slimword.php` | Bloat removal, welcome panel, salts, Site Health removal |
| `wp-content/mu-plugins/slimword-guard.php` | Protection switch, login check, response headers |
| `wp-content/mu-plugins/slimword-visitor.php` | Cookie notice and favicon |
| `wp-content/mu-plugins/slimword-reading.php` | Accessibility panel |
| `wp-content/mu-plugins/slimword-reading/a11y-widget.min.js` | Panel script |
| `wp-content/advanced-cache.php` | Serves a saved public page |
| `wp-content/mu-plugins/slimword-page-cache.php` | Writes and clears saved pages |
| `wp-content/mu-plugins/slimword-discovery.php` | SEO, answer-engine, and generative-engine tags, plus Settings → Discovery |
| `wp-content/mu-plugins/slimword-updates.php` | GitHub update check on a normal visit |
| `wp-content/mu-plugins/slimword-cron.php` | Runs due tasks without `wp-cron.php` |
| `wp-content/mu-plugins/slimword-comments.php` | Switch that disables comments |
| `wp-content/mu-plugins/slimword-ajax.php` | Plugin Ajax without loading the admin |
| `wp-content/object-cache.php` | File object cache |
| `wp-content/mu-plugins/slimword-cache.php` | Cache list and clear button |
| `wp-content/slimword-https.php` | HTTPS only when it is available |
| `wp-content/slimword-version.php` | Release number and GitHub repo |
| `private/` | SQLite database, not in git |

## License

WordPress and this Slim layer are GPL v2 or later. See `license.txt`. The SQLite plugin keeps its own license file in its plugin directory.
