#!/usr/bin/expect

set ovpnname [lindex $argv 0]; # Grab the first command line parameter
set username [lindex $argv 1];
set password [lindex $argv 2];

set timeout -1

spawn openvpn3 session-start --config $ovpnname

expect "Auth User name: "
send -- "$username\n"
expect "Auth Password: "
send -- "$password\n"

expect eof



