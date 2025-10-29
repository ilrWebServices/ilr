# ILR Drupal 10 Site

This is the Drupal 10 version of https://www.ilr.cornell.edu, AKA The Marketing Site.

It is based on the [Composer template for Drupal projects][].

## Requirements

- git
- PHP 8.2 or greater
- the PHP [ImageMagick extension][] (for PDF thumbnail generation)
- Composer
- Drush (included in this project; use the bash/zsh `drush` function below to use it)
- Node.js 18.x or greater (for theming; a `.tool-versions` file is included for use with [asdf][])

## Setup

1. Clone this repository
2. Open a terminal at the root of the repo
3. Run `composer install`
4. Copy `.env.example` to `.env` and update the database connection and other info.
5. Run `npm install && npm run build` to generate the CSS for the custom theme.

Setting up your local web server and database is left as an exercise for the developer. Please note when setting up your web server, though, that this project uses the `web` directory as the web root.

Our recommended `drush` setup uses the following function to use the version of drush included in this project. To use it, add the following to your `.zshrc` or `.bashrc` file:

```bash
function drush () {
  $(git rev-parse --show-toplevel)/vendor/bin/drush "$@"
}
```

### Development-only Settings

You may wish to configure some settings (cache, config splits, etc.) for local development. To do so, you may optionally add a `settings.local.php` file to `web/sites/default/`.

Here's a suggested example:

```
<?php

// Allow any domain to access the site.
$settings['trusted_host_patterns'] = [];

// Switch the salesforce auth provider to production if needed.
// Otherwise, we will use the default for dev.
// $config['salesforce.settings']['salesforce_auth_provider'] = 'ilr_marketing_jwt_oauth';

// Enable the config split for development-only modules, like field_ui.
$config['config_split.config_split.dev']['status'] = TRUE;

// Enable local development services.
$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/local_development.services.yml';

// Show all error messages, with backtrace information.
$config['system.logging']['error_level'] = 'verbose';

// Show more cron logging info, including in `drush cron`.
$config['system.cron']['logging'] = TRUE;

// Disable CSS and JS aggregation.
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// Skip file system permissions hardening.
$settings['skip_permissions_hardening'] = TRUE;

// Config ignore pattern debugging.
$settings['config_ignore_pattern_debug'] = FALSE;
```

### Clean install

To work on a blank slate of the codebase without syncing content and data from production, install Drupal like so:

```
$ drush si minimal --existing-config
```

Some configuration, mainly Webforms created on production, is ignored from sync. The `config_ignore_pattern` module is used. See $settings['config_ignore_patterns'].

## Managing Modules and Other Dependencies

Use standard composer commands to add, remove, and update project dependencies. To add the rules module, for example, run:

```
$ composer require drupal/rules:~1.0
```

To add a module for developer use only, which will prevent its installation on the production site, use the `--dev` paramater, like so:

```
$ composer require --dev drupal/devel:~1.0
```

Outdated Drupal modules can be listed with the following command:

```
$ composer outdated "drupal/*"
```

To update a specific module, run something like:

```
composer update drupal/rules
```

## Patching Contributed modules

If you need to apply patches (depending on the project being modified, a pull request is often a better solution), you can do so with the [composer-patches][] plugin.

To add a patch to drupal module foobar insert the patches section in the extra section of composer.json:

```json
"extra": {
  "patches": {
    "drupal/foobar": {
      "Patch description": "URL or local path to patch"
    }
  }
}
```

## Updating Drupal core

```
$ composer update drupal/core 'drupal/core-*' --with-all-dependencies
```

Then run `git diff` to determine if any of the scaffolding files have changed.

Review changes and restore any customizations to `.htaccess` or `robots.txt`. Commit everything together in a single commit (or merge), so `web` will remain in sync with `core` when checking out branches or running `git bisect`.

See https://www.drupal.org/node/2700999 for more information.

## Configuration import and export

As with many Drupal 8+ sites, configuration is managed in version control by exporting it to the filesystem. This project is configured, via `$settings['config_sync_directory']` in `settings.php`, to store configuration in the `./config/sync/` directory.

During development, configuration is exported via `drush cex`. For example, if a new content type were created on a local development site, the node type and field config would be exported, and the new config yml files would be committed to git.

During deployment, modified configuration is synchronized via a script that runs `drush cim` on the production site. In general, this means that any configuration added or modified on production will be reverted or removed during deployment. For example, if a new content type were added on production, it would be removed during deployment.

### Ignored configuration

While most configuration is created during local development and then deployed to production, some configuration is generated automatically directly on the production site.

Some examples:

- The `collection_subsites` module generates a menu and a block visibility group when a new subsite collection is created.
- The `collection_blogs` module generates category and tag taxonomy vocabularies when new blog collections are created.
- Some Webforms are manually created on production.

Since new subsites and blogs can be created by administrators on the production site, we don't want those menus, block visibility groups, and vocabularies accidentally deleted during a deployment. Same with on-the-fly Webforms that we don't want to have to build locally and deploy.

Additionally, we don't want this generated configuration exported to the `config/sync/` directory during local development.

Therefore, some configuration is ignored using the [Config ignore pattern module][].

The ignore patterns are manually maintained in `settings.php` via `$settings['config_ignore_patterns']`.

#### Forcing ignored configuration

On occasion, ignored configuration needs to be updated via deployment. For example, the display view mode for a blog category taxonomy page may be need to be updated.

For these cases, the [Config ignore pattern module][] checks for ignored configuration items in `config/sync/` and _doesn't ignore them if they are found_.

Continuing the view mode example above, imagine that a developer updates the default view display for a blog category taxonomy vocabulary with the machine name `blog_2_categories`. This vocabulary display view mode was generated on production when the collection blog with the id `2` was created.

Assuming that the developer is very clever and knows the exact name of the configuration item for that view mode, it can be exported, bypassing the ignore filters, with two commands:

```
$ touch config/sync/core.entity_view_display.taxonomy_term.blog_2_categories.default.yml
$ drush cex
```

You can get a bit of help finding the names of configuration items by using the single item export utility at `/admin/config/development/configuration/single/export`.

## Salesforce Integration

This site uses the Salesforce Suite module to synchronize some Salesforce objects to Drupal entities, mainly Professionional Programs courses, classes, and related items.

Authentication is done via OAuth JWT tokens - one for ILR Drupal sites to connect to the production instance and one for all development sites to connect to the `tiger` sandbox instance. See the [OAuth JWT Bearer Token flow documentation][] for more information.

### Configuration

The only required configuration is to set the `SALESFORCE_CONSUMER_JWT_X509_KEY` environment variable. For development, this is done by editing the `.env` file. On production, this is done via platform.sh environment variable settings.

The JWT x509 key is stored in the 'SalesForce prod key/secret for ILR Marketing D8 JWT' note in the shared 'ILR Webdev' folder in LastPass.

### Usage

You can see the status of the two authentication providers via drush:

```
$ drush sflp
```

...or by visiting `/admin/config/salesforce/authorize/list`.

You can then refresh the authentication tokens for one or both of the providers by either using the _Edit / Re-auth_ button in the web interface or via drush:

```
$ drush sfrt ilr_marketing_jwt_oauth_dev
```

If needed, the default provider can be overriden during local development (e.g. for testing with production data) by updating the configuration for the `salesforce_auth_provider`. See the "Development-only Settings" above for an example.

## Content Migration

External content can be migrated into Drupal using the Migrate module functionality. Migration config is stored in `web/modules/custom/ilr_migrate/migrations` rather than `config/` because it's faster to reload the migrations when testing.

As of 2024-10 the legacy D7 migrations have been removed and the `migrate_drupal` module disabled.

## Theme Development

This project uses a custom theme that includes shared components from the [Union Component Library][].

The custom theme is found in `web/themes/custom/union_marketing/`. The Sass CSS preprocessor is used for styles, and you can compile CSS either 1) manually via `npm run build` or 2) automatically by running `npm start` in a spare terminal.

## Easier Union Design System Development

After an initial install:

```
composer require cornell/union:master --prefer-source
```

This updates the composer install of `cornell/union` in `vendor/cornell/union` to a git checkout, which allows development of the design system and the main site at the same time.

### Including Union Components

Union Components are integrated into the theme using the [Union Organizer][] module. See the documentation for that module for more information.

### Livereload

If you set `LIVERELOAD=1` in your `.env` file and reload your browser while `npm start` is running, changes to stylesheets will reload automatically in your browser.


[Composer template for Drupal projects]: https://github.com/drupal-composer/drupal-project
[ImageMagick extension]: https://www.php.net/manual/en/book.imagick.php
[asdf]: https://asdf-vm.com/
[git submodules]: https://git-scm.com/book/en/v2/Git-Tools-Submodules
[Config ignore pattern module]: https://www.drupal.org/project/config_ignore_pattern
[OAuth JWT Bearer Token flow documentation]: https://www.drupal.org/docs/8/modules/salesforce-suite/create-a-oauth-jwt-bearer-token-flow-connected-app-4x
[composer-patches]: https://github.com/cweagans/composer-patches
[Union Component Library]: https://github.com/ilrWebServices/union
[Union Organizer]: https://github.com/ilrWebServices/union_organizer
