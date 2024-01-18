<?php
date_default_timezone_set('CET'); // modify if not Europe/Berlin

include "./lib/strings.php";
include "./lib/file.php";

$title = "=== shelly-web-overview v1.2 ===";
/* tested 2024-01-05 with stock firmware Shelly PlusPlugS firmware v1.1.0 web build 8372d8c8
 * 
 * what is it?
 * the (probably) easiest way to monitor and collect shelly's data locally
 * nice graphical web view chart of shelly logs created by bash script that polls it DIRECTLY from the shellies via curl via http /scripts/shelly.sh
 * requirements: a (embedded) PC with Apache2+PHP
 * 
 * setup:
 * 1) edit the shelly.sh 
 * 1.1) and put in all IPs of all shellies
 * 1.2) modify /path/to/log/files (default is: /var/www/html/shelly/data)
 * 3) then autorun script every 1min  https://crontab.guru/every-1-minute = finer resolution = more correct kWh values, or every 2min https://crontab.guru/every-2-minutes via crontab -e like 
 * 4) open browser localhost/shelly/index.php
 */

// === config === user can change settings in config.php
$config = config_read();

// === startup defaults values === not to be modified by user
$input_date = ""; // holds the date to show 2023-12-12 or all (all dates from all logs = can be A LOT OF DATAPOINTS (like millions) = heavy on CPU client side js)
$input_last_parameter = ""; // the last parameter used

$stats_kWh_used = 0;

/* hold the data */
$chart_data_string_date = "[";
$chart_data_string_current = "[";
$chart_data_string_used_watts = "[";
$chart_data_string_batt_volt = "[";
$chart_data_string_temp_celsius = "[";
$shellies = array(); // list of all found shelly ips
$shelly_to_show_ip = ""; // ip of selected shelly

$array_files_all = array();
if($handle = opendir($config["path2data"]))
{
    while (false !== ($filename = readdir($handle)))
    {
        if ('.' === $filename) continue;
        if ('..' === $filename) continue;
        // do something with the file
        $array_filename_segments = explode('.',$filename);
        
        if($array_filename_segments[0] == "shelly") // if filename starts with shelly
        {
            if($array_filename_segments[2] == "log") // if filename ending is .log proceed
            {
                array_push($array_files_all,$filename);
            }
        }
    }
    closedir($handle);
}

$input_date = "all";
// process user input
if(isset($_REQUEST["date"])) // dates to display "2024-01-05" "all" (all days)
{
    $input_date = htmlspecialchars($_REQUEST["date"]); // escape possibly malicious input
    $input_last_parameter = $input_last_parameter."&date=".$input_date;
}
if(empty($input_date)) $input_date = "all";

if(isset($_REQUEST["shelly_to_show_ip"])) // which shelly to display
{
    $shelly_to_show_ip = htmlspecialchars($_REQUEST["shelly_to_show_ip"]);
    $input_last_parameter = $input_last_parameter."&shelly_to_show_ip=".$shelly_to_show_ip;
}

if(isset($_REQUEST["auto_reload"]))
{
    $config["auto_reload_string"] = htmlspecialchars($_REQUEST["auto_reload"]);
    config_save("auto_reload_string", $auto_reload_string); // after every save
    $config = config_read(); // reload config
}

/* sort filenames by date  */
$order = array();

// extract ip from filename and prepare sorting
$target = count($array_files_all);
for($i=0;$i<$target;$i++)
{
    $filename = $array_files_all[$i];
    $array_filename_segments = explode(".", $filename);
    $logfile_date_string = $array_filename_segments[1];
    $array_files_all[$i] = $logfile_date_string;
    
    $logfile_date = date( 'Y-m-d', strtotime( $logfile_date_string ) );
    $order[] = strtotime($logfile_date);
}

array_multisort($order, SORT_ASC, $array_files_all); // sort array

// iterate over all lines and extract values
foreach($array_files_all as $key => $value)
{
    if($input_date != "all")
    {
        if($input_date != $value) // ignore all files, except that match $input_date
        {
            continue;
        }
    }
    
    /* extract the values from the json with unquoted values data (decode fails, says not valid json) */
    $filename = "shelly.".$value.".log";

    $filename_and_path = $config["path2data"]."/".$filename;
    $lines = file($filename_and_path) or die("can't open ".$filename_and_path." file"); // changes "2023-10-30" back to full filename "2023-10-30 USB-QPIGS.log"

    foreach($lines as $key => $line)
    {
        $line = clean_string($line); // remove trailing newline
        if(empty($line)) continue; // if line is empty, skip it

        $line = str_replace("[", "",$line); // remove all [
        $line = str_replace("]", "",$line); // remove all ]
        $line = str_replace("(", " ",$line); // remove all ( and replace with " " because it is needed as separator
        
        $line_segments = explode("===",$line);

        $ip = $line_segments[3]; // extract ip of shelly
        $data = $line_segments[4];

        if(!(empty($data)))
        {
            $values = array();
    
            $pattern1 = '/"apower":([\d.]+),"voltage":([\d.]+),"current":([\d.]+)/'; // extract apower, voltage, current
            preg_match($pattern1, $data, $matches1);
            // $values["apower"] = (float)$matches1[1]; // problem: these values seem to be incorrect
            if(isset($matches1[2]))
            {
                $values["voltage"] = (float)$matches1[2];
                // round to 2x digits behind the dot (123.12)
                $values["voltage"] = number_format($values["voltage"], 2, '.', '');
            }
            else
            {
                $values;
            }
            if(isset($matches1[3]))
            {
                $values["current"] = (float)$matches1[3];
                // round to 2x digits behind the dot (123.12)
                $values["current"] = number_format($values["current"], 2, '.', '');
            }
            else
            {
                $values;
            }
    
            if(empty($values["voltage"]))
            {
                $values["voltage"];
            }
            // recalc watts used
            $values["apower"] = $values["voltage"] * $values["current"]; // correct incorrect watt values by recalc via V and A values
            
            // round to full integer
            $values["apower"] = round($values["apower"], 0);
            
            // round to 2x digits behind the dot (123.12)
            // $values["apower"] = number_format($values["apower"], 2, '.', '');
    
            // extract timestamp
            $pattern2 = '/"unixtime":(\d+)/';
            preg_match($pattern2, $data, $matches2);
            $unixtime = $matches2[1];
            
            if(!empty($unixtime))
            {
                $shellies[$ip][$unixtime] = $values;
            }
            else 
            {
                $unixtime; // regex extracting unixtime from data failed :( WHY? WHY?
            }
        }
    }
}

if(empty($shelly_to_show_ip))
{
    $shelly_to_show_ip = array_key_first($shellies);
}

// build chart.js data strings + calc kWh stats
foreach($shellies as $ip => $shelly)
{
    if($shelly_to_show_ip == $ip)
    {
        $unixtime_previous = 0;
        foreach($shelly as $unixtime => $values)
        {
            // calc time difference since last datapoint, it is assumed that wattage stayed the same in this period
            if($unixtime_previous == 0)
            {
                $time_diff_ms = 0;
            }
            else
            {
                $time_diff_ms = $unixtime - $unixtime_previous;
            }
            
            $time_diff_h = $time_diff_ms / 3600000;

            if(isset($values["apower"]))
            {
                $watts = $values["apower"];
                $stats_kWh_used = $stats_kWh_used + ($watts * $time_diff_h); // calc kWh
            }

            $chart_data_string_date = $chart_data_string_date.$unixtime.", ";
            $chart_data_string_batt_volt = $chart_data_string_batt_volt.$values["voltage"].", ";
            $chart_data_string_current = $chart_data_string_current.$values["current"].", ";
            $chart_data_string_used_watts = $chart_data_string_used_watts.$values["apower"].", ";

            $unixtime_previous = $unixtime;
        }
    }
}

// remove last ,
$chart_data_string_date = substr($chart_data_string_date, 0, -1);
$chart_data_string_date = substr($chart_data_string_date, 0, -1);

$chart_data_string_used_watts = substr($chart_data_string_used_watts, 0, -1);
$chart_data_string_used_watts = substr($chart_data_string_used_watts, 0, -1);

$chart_data_string_current = substr($chart_data_string_current, 0, -1);
$chart_data_string_current = substr($chart_data_string_current, 0, -1);

$chart_data_string_batt_volt = substr($chart_data_string_batt_volt, 0, -1);
$chart_data_string_batt_volt = substr($chart_data_string_batt_volt, 0, -1);

// close the braket
$chart_data_string_date = $chart_data_string_date."]";
$chart_data_string_current = $chart_data_string_current."]";
$chart_data_string_used_watts = $chart_data_string_used_watts."]";
$chart_data_string_batt_volt = $chart_data_string_batt_volt."]";

$stats_kWh_used = number_format((float)$stats_kWh_used, 3, '.', '');

/* manual modifications

<canvas id="myChart" width="100%" height="100%" style="background-color: #444;"></canvas>


// Data
const data = [
<?php echo $chart_data_string_date.","; ?>
<?php echo $chart_data_string_used_watts; ?>
<?php echo $chart_data_string_current; ?>
];

/*
// Data
const data = [
[1702213297, 1702213308, 1702213317, 1702213327, 1699939996, 1699940001, 1699940016, 1699940021, 1698645904, 1698645917, 1698645926, 1698645936],
[122, 122, 123, 128, 159, 159, 158, 157, 35, 63, 68, 74],
[122, 122, 123, 128, 159, 159, 158, 157, 35, 63, 68, 74],
[122, 122, 123, 128, 159, 159, 158, 157, 35, 63, 68, 74],
[122, 122, 123, 128, 159, 159, 158, 157, 35, 63, 68, 74]
];
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <meta http-equiv="refresh" content="<?php if($config["auto_reload_string"] == "on"){ echo ($config["refresh_auto"]*60).";URL=index.php?".$input_last_parameter; } ?>"/>
  <title><?php echo $title; ?></title>
  <!-- Include Chart.js library -->
  <script src="js/chart.js"></script>
  <link rel="stylesheet" type="text/css" href="./css/style.css">
</head>
<body>
	<div id="div1" style="position: absolute; left: 0px; top: 0px; width: 100%; height: 100%;">
    	<div id="div2" style="position: relative; float: left; min-width: 100%;">
    		<?php echo "stats for selected dates: ".$stats_kWh_used." kWh used"; ?>
    	</div>
    	<div id="div3" style="position: relative; float: left; min-width: 100%;">
    		<?php
    		foreach($shellies as $key => $value)
    		{
    		    if($shelly_to_show_ip == $key)
    		    {
    		      echo '<a class="link_button_blue_active" href="./index.php?shelly_to_show_ip='.$key.'">'.$key.'</a>';
    		    }
    		    else
    		    {
    		      echo '<a class="link_button_blue" href="./index.php?shelly_to_show_ip='.$key.'">'.$key.'</a>';
    		    }
    		}
    		?>
    	</div>
    	<div id="div4" style="position: relative; float: left; min-width: 100%;">
    		<?php
    		if($input_date = "all")
    		{
    		    echo '<a class="link_button_orange_active" href="./index.php?date=all">ShowAll</a>';
    		}
    		else 
    		{
    		    echo '<a class="link_button_orange" href="./index.php?date=all">ShowAll</a>';
    		}

    		foreach($array_files_all as $key => $value)
    		{
    		    if($input_date == $value)
    		    {
    		        echo '<a class="link_button_orange_active" href="./index.php?date='.$value.'">'.$value.'</a>';
    		    }
    		    else
    		    {
    		        echo '<a class="link_button_orange" href="./index.php?date='.$value.'">'.$value.'</a>';
    		    }
    		}
    		?>
			<?php
                if($config["auto_reload_string"] == "on")
                {
                    echo '<a title="turn auto reload every '.$config["refresh_auto"].'min on or off" id="button_enabled" class="link_button_orange" href="./index.php?'.$input_last_parameter.'&auto_reload=off">auto_reload_on</a>';
                }
                else
                {
                    echo '<a title="turn auto reload every '.$config["refresh_auto"].'min on or off" id="button_disabled" class="link_button_orange" href="./index.php?'.$input_last_parameter.'&auto_reload=on">auto_reload_off</a>';
                }
			?>

		</div>
    <!-- Create a canvas element to render the chart -->
	<canvas id="myChart" width="2048" height="1024" style="background-color: #444;"></canvas>

  <script>
    // Data
    const data = [
    <?php echo $chart_data_string_date.",\n"; ?>
    <?php echo $chart_data_string_current.",\n"; ?>
    <?php echo $chart_data_string_used_watts.",\n"; ?>
    <?php echo $chart_data_string_batt_volt.",\n"; ?>
    ];

    // Extract x and y coordinates from the data
    const xValues = data[0];
    const yValues1 = data[1];
    const yValues2 = data[2];
    const yValues3 = data[3];
/*
    const yValues4 = data[4];
*/
    // Function to format timestamp to YYYY-MM-DD hh:mm:ss
    const formatTimestamp = (timestamp) => {
      const date = new Date(timestamp * 1000);
      const year = date.getFullYear();
      const month = (date.getMonth() + 1).toString().padStart(2, '0');
      const day = date.getDate().toString().padStart(2, '0');
      const hours = date.getHours().toString().padStart(2, '0');
      const minutes = date.getMinutes().toString().padStart(2, '0');
      // if user wants with seconds uncomment next 2 lines
      // const seconds = date.getSeconds().toString().padStart(2, '0');
      // return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
      return `${year}-${month}-${day} ${hours}:${minutes}`;
    };

    // Get the canvas element
    const ctx = document.getElementById('myChart').getContext('2d');

    // Create the chart
    const myChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: xValues.map(value => formatTimestamp(value)),
        datasets: [
          {
			label: 'current',
            data: yValues1,
            borderColor: '#ffa500', /* dark orange */
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#c27e00', /* orange */
          },
          {
            label: 'watts',
            data: yValues2,
            borderColor: 'red', /* 'rgba(255, 99, 132, 1)' */
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: 'darkred', /* red: rgba(54, 162, 235, 1) */
          },
          {
            label: 'volt',
            data: yValues3,
            borderColor: '#19ff00', /* dark green */
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#298f1f', /* bright green */
          },
/*
          {
            label: 'Line 4',
            data: yValues4,
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 2,
            pointRadius: 5,
            pointBackgroundColor: 'rgba(75, 192, 192, 1)',
          },
*/
        ]
      },
      options: {
    	  scales: {
    	    xAxes: [{
    	      type: 'linear',
    	      position: 'bottom'
    	    }],
    	    /*
    	    yAxes: [{
    	      ticks: {
    	        min: 0,
    	      }
    	    }]
    	    */
    	  },
    	  /* does not look better with many datapoints while slowing down render performance 
    	  elements: {
    	    line: {
				cubicInterpolationMode: 'monotone',
				tension: 0.8, // level of interpolation (between 0 and 1)
    	    }
    	  }
    	  */
    	}
    });
  </script>
  </div>
</body>
</html>