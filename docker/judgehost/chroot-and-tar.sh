#!/bin/bash -e
/opt/domjudge/judgehost/bin/dj_make_chroot

cd /
tar -czpf /chroot.tar.gz /chroot
tar -czpf /judgehost.tar.gz /opt/domjudge/judgehost
