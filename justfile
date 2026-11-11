branch := `git branch --show-current`

sync-prod:
    #!/usr/bin/env zsh
    source ~/.zshrc
    time composer run sync-prod
    drush uli --browser="open -a 'Firefox Developer Edition'"
    mariadb-dump ilr > /tmp/ilr_{{ datetime('%Y-%m-%d-%H:%M') }}.sql
    time composer run sync-update

snapshot:
    mariadb-dump ilr > /tmp/ilr_{{branch}}_{{ datetime('%Y-%m-%d-%H:%M') }}.sql

restore-snapshot snapshot_file:
    #!/usr/bin/env zsh
    source ~/.zshrc
    drush sql:drop -y && mariadb ilr < {{snapshot_file}}
