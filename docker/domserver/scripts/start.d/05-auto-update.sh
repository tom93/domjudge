#!/bin/sh
# if $AUTO_UPDATE is 1, install unattended-upgrades

set -eu

if ! [ "${AUTO_UPDATE:-0}" -eq 1 ]; then exit 0; fi

# install unattended-upgrades
apt-get update
apt-get install -y --no-install-recommends cron unattended-upgrades

# enable cron using supervisord
cat >/etc/supervisor/conf.d/cron.conf <<"EOF"
[program:cron]
command=cron -f
autostart=true
autorestart=true
EOF

# perform an update immediately to avoid waiting for the cron job
unattended-upgrade --verbose
