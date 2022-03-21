# Containerized Development

These files help configure a containerized environment that's as similar to our production environment on platform.sh as possible.

Our [local macOS setups](https://gist.github.com/jeffam/cc8db1a9072a56808363447e6b829c53) are probably simpler and more performant, but this will allow us to test new PHP versions and keep our development configuration the same between developers.

## Requirements

- `podman` v4.0.2 or higher (`brew install podman` via homebrew)

## Initial setup

Install a podman machine, a small VM used to run the containers on a real linux host:

```
$ podman machine init -v $HOME/sites:$HOME/sites
$ podman machine start
```

This will create a CoreOS linux VM running via Qemu. Your $HOME/sites/ directory will be shared into the VM, and thus accessible to container volumes.

You can access the VM using `podman machine ssh`.

##

```
podman build -t php-fpm-caddy ./dev

podman run -d -p 8080:80 --restart=unless-stopped -v .:/app --name="www.ilr.test" php-fpm-caddy
