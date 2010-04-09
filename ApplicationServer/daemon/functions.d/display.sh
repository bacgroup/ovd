# Copyright (C) 2009 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com>
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation, version 2
# of the License.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

display_vnc_opts="-caching -caching_ent 1500 -caching_malg LRU -caching_minsize 5000000"

display_init() {
    local sessid=$1
    local rfb_port=$2
    local xauth_file=/tmp/.tmp${VNC_UID}.Xauthority

    # Install the MIT_MAGIC_COOKIE
    su -s "/bin/bash" $VNC_USER -c "xauth -f $xauth_file add :$rfb_port . $(/usr/bin/mcookie)" 2>/dev/null

    # Install the MIT_MAGIC_COOKIE into the real user env
    cp $xauth_file $SPOOL_USERS/$sessid/.Xauthority
    chown ${USER_LOGIN}      $SPOOL_USERS/$sessid/.Xauthority

}


display_start() {
    local rfb_port=$1
    local geometry=$2
    local pid_file=$3

    local vnc_tmp=/tmp/.tmp${VNC_UID} 

    # If previous X11 socket still exist for this display
    if [ -e /tmp/.X11-unix/X${rfb_port} ]; then
        local owner=$(stat -c "%U" /tmp/.X11-unix/X${rfb_port})
        if [ "$owner" = "UNKNOWN" ]; then
            log_INFO "Clean old X11 socket (X${rfb_port})"
            rm -f $VNC_USER /tmp/.X11-unix/X${rfb_port}
        else
            log_WARN "Cannot start Xserver, file '/tmp/.X11-unix/X${rfb_port}' exists"
            return 1
        fi
    fi

    # Start the VNC server
    /bin/su -s "/bin/bash" $VNC_USER -c "XAUTHORITY=${vnc_tmp}.Xauthority /usr/bin/Xtightvnc ${display_vnc_opts} :${rfb_port} -desktop X${rfb_port} -nolock -once -interface 127.0.0.1 -localhost -lf 1024 -geometry ${geometry} -depth 24 -rfbwait 240000 -rfbauth ${vnc_tmp}encvncpasswd -rfbport ${rfb_port} -fp /usr/share/fonts/X11/Type1/,/usr/share/fonts/X11/misc/,/usr/share/fonts/X11/75dpi/,/usr/share/fonts/X11/100dpi/ -co /etc/X11/rgb -ac -auth ${vnc_tmp}.Xauthority" >/dev/null 2>&1 &
    echo $! >$pid_file

    sleep 1

    # Xvnc accept connection only from MIT_MAGIC_COOKIEs
    su -s "/bin/bash" $VNC_USER -c "DISPLAY=:$rfb_port XAUTHORITY=${vnc_tmp}.Xauthority /usr/bin/xhost +" >/dev/null 2>&1
}

display_stop() {
    local rfb_port=$1
    local pid_file=$2

    tightvncserver -kill :$rfb_port 2>/dev/null
    [ $? -eq 0 ] && return 0

    if [ -f $pid_file ]; then
        local pid=$(head -n 1 $pid_file)
    else
        local pid=$(ps ax |grep Xtightvnc |grep ":$rfb_port" |cut -d ' ' -f1)
    fi

    if [ -z "$pid" ]; then
        log_WARN "Unable to find PID for display $rfb_port"
        return 1
    fi

    log_INFO "display_stop: kill $pid"
    kill $pid || kill -s 9 $pid
}

display_alive() {
    local rfb_port=$1
    local pid_file=$2

    if [ -f $pid_file ]; then
        local pid=$(head -n 1 $pid_file)

        if [ -n "$pid" ]; then
            pid_alive $pid
            return $?
        fi
    fi

    return 1
}

display_clean() {
    local user=$1

    # remove X11 sockets still exists
    for file in $(find /tmp/.X11-unix -user $user); do
        local name=$(basename "$file")
        log_INFO "Clean old X11 socket ($name)"
        rm -f "$file"
    done
}
