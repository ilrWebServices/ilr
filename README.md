# ILR Drupal 8 Site

This is the Drupal 8 version of https://www.ilr.cornell.edu, AKA The Marketing Site.

It is based on the [Composer template for Drupal projects][].

## Requirements

- git
- PHP 7.2 or greater
- Composer
- Drush ([Drush launcher][] is recommended, since a copy of Drush is included in this project)
- Node.js 8.x or greater (for theming)

## Setup

1. Clone this repository
2. Open a terminal at the root of the repo
3. Run `composer install`
4. Copy `.env.example` to `.env` and update the database connection and other info.
5. Run `npm install && npm run build` to generate the CSS for the custom theme.

Setting up your local web server and database is left as an excercise for the developer. Please note when setting up your web server, though, that this project uses the `web` directory as the web root.

### Development-only Settings

You may wish to configure some settings (cache, config splits, etc.) for local development. To do so, you may optionally add a `settings.local.php` file to `web/sites/default/`.

Here's a suggested example:

```
<?php

// Allow any domain to access the site.
$settings['trusted_host_patterns'] = [];

// Enable the config split for development-only modules, like field_ui.
$config['config_split.config_split.dev']['status'] = TRUE;

// Enable local development services.
$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/local_development.services.yml';

// Show all error messages, with backtrace information.
$config['system.logging']['error_level'] = 'verbose';

// Disable CSS and JS aggregation.
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// Skip file system permissions hardening.
$settings['skip_permissions_hardening'] = TRUE;
```

### Clean install

To work on a blank slate of the codebase without syncing content and data from production, install Drupal like so:

```
$ drush si minimal --existing-config
```

## Adding and Updating Modules and Other Dependencies

Use standard composer commands to add, remove, and update project dependencies. To add the rules module, for example, run:

```
$ composer require drupal/rules:~1.0
```

To add a module for developer use only, which will prevent its installation on the production site, use the `--dev` paramater, like so:

```
$ composer require --dev drupal/devel:~1.0
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
$ composer update --with-dependencies drupal/core webflo/drupal-core-require-dev symfony/*
```

Then run `git diff` to determine if any of the scaffolding files have changed.

Review changes and restore any customizations to `.htaccess` or `robots.txt`. Commit everything together in a single commit (or merge), so `web` will remain in sync with `core` when checking out branches or running `git bisect`.

## Salesforce Integration

When installing the site from scratch, you'll need to configure two groups of settings to connect to Salesforce.

As of this writing (June 2019), you can get values for these settings from the production instance of the Drupal 7 site. Search the `variables` table for the following keys:

- `salesforce_consumer_key`
- `salesforce_consumer_secret`
- `salesforce_endpoint` (AKA `login_url`)
- `salesforce_instance_url`
- `salesforce_refresh_token`

### 1. Remote/Connected App Settings

These are set in `settings.php` via environment variables. For developers, this means adding them to your `.env` file. See the Setup section above.

### 2. Authorization Settings

These are set using the Drupal 8 State API, so they cannot be added via config or config overrides. Once you have the value for the refresh token from the production site, you can add it to your local state using drush:

```
$ drush state-set salesforce.refresh_token [VALUE_FROM_PROD_SITE]
```

## Content Migration

Several migrations are defined in `config/migrations/migrate_plus.migration.*.yml`.

Most of these migrations require a connection to the Drupal 7 database, which, as of this writing, should be defined in `settings.local.php`. Here's an example (note the `drupal7` key):

```
// D7 database connection.
$databases['drupal7']['default'] = [
  'database' => 'ilr',
  'username' => 'root',
  'password' => '',
  'prefix' => '',
  'host' => '127.0.0.1',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
];
```

Additionally, some custom migrate code, including source plugins, can be found in the custom `ilr_migrate` module.

### Viewing Migration Status

```
$ drush ms --group=drupal_7
```

### Running Migrations

All Drupal 7 content can be migrated via:

```
$ drush mim --group=drupal_7
```

Individual migrations can be run like so:

```
$ drush mim d7_node_course
```

## Theme Development

This project uses a custom theme that includes shared components from the [Union Component Library][].

The custom theme is found in `web/themes/custom/union_marketing/`. The Sass CSS preprocessor is used for styles, and you can compile CSS either 1) manually via `npm run build` or 2) automatically by running `npm start` in a spare terminal.

### Including Union Components

Union Components are integrated into the theme using the [Union Organizer][] module. See the documentation for that module for more information.

### Livereload

If you set `LIVERELOAD=1` in your `.env` file and reload your browser while `npm start` is running, changes to stylesheets will reload automatically in your browser.


[Composer template for Drupal projects]: https://github.com/drupal-composer/drupal-project
[Drush launcher]: https://github.com/drush-ops/drush-launcher
[git submodules]: https://git-scm.com/book/en/v2/Git-Tools-Submodules
[composer-patches]: https://github.com/cweagans/composer-patches
[Union Component Library]: https://github.com/ilrWebServices/union
[Union Organizer]: https://github.com/ilrWebServices/union_organizer
