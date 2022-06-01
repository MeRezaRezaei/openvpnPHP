#!/usr/bin/expect

set path [lindex $argv 0]; # Grab the first command line parameter

set timeout -1

spawn openvpn3 config-remove --path  $path

expect "Are you sure you want to do this? (enter yes in upper case) "
send -- "YES\n"

expect eof