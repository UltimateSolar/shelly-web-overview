#!/bin/bash
# shelly data poller v1.0 (2024-01)
# define a cron job that will run every 10min */10  * * * * /scripts/shelly.sh
# tested with: plug s, firmware 1.1

# === config start ===

# list of shellies (fixed ip address would be good for reliability)
declare -A shelly
shelly[1]=192.168.0.134
shelly[2]=192.168.0.136
# shelly[3]=192.168.0.136
# shelly[4]=192.168.0.137

# where the data should be stored (note that there is NO / at the end)
data=/var/www/html/shelly/data

# it is assumed all shellies are configured with the same password
# shellies can not do HTTPS so please use a password that is not used anywhere else, as it is send in clear text over the network in use
PASSWORD=(auth currently does not work)

# === config end ===

# === start polling ===
for key in "${!shelly[@]}"
do
	IP=${shelly[$key]}
	LOGFILENAME="$data/shelly.$(date '+%Y-%m-%d').log"
	printf "\n$(date '+%Y-%m-%d===%H:%M:%S')===PollingShelly===$IP===" >> $LOGFILENAME

	# curl -s --tlsv1.2 http://$IP/rpc/Shelly.GetDeviceInfo
	curl -s --tlsv1.2 http://$IP/rpc/Shelly.GetStatus >> $LOGFILENAME
done

# change ownership so that apache2+php are allowed read access
# chown -R www-data: $data
