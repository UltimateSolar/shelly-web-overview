=== shelly-web-overview v1.1 ===

tested 2024-01-05 with STOCK firmware Shelly Plug S Plus (firmware v1.1.0 web build 8372d8c8)

NO NEED TO FLASH A CUSTOM ROM!

![Screenshot](./shelly-web-overview-v1-0.png?raw=true "Screenshot")


what is it?
the (probably) easiest way to monitor and collect shelly's data locally
nice graphical web view chart of shelly logs created by bash script that polls it DIRECTLY from the shellies via curl via http /scripts/shelly.sh
requirements: a (embedded) PC with Apache2+PHP

setup:
1) edit the shelly.sh 
1.1) and put in all IPs of all shellies
1.2) modify /path/to/log/files (default is: /var/www/html/shelly/data)
3) then autorun script every 1min  https://crontab.guru/every-1-minute = finer resolution = more correct kWh values, or every 2min https://crontab.guru/every-2-minutes via crontab -e like 
4) open browser localhost/shelly/index.php
