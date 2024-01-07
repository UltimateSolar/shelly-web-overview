<?php
// reads a file config.php which contain user-adjustable settings 
function config_read()
{
    $config = array();
    $config_php_lines = file("config.php") or die("can't open config.php file, does webserver-user (www-data) have file-access-rights to web-root?");

    foreach($config_php_lines as $key => $line)
    {
        $line = str_replace("//", "",$line); // remove all //
        $line = clean_string($line);
        if($line == "<?php") continue; // if line is empty, skip it
        if($line == "?>") continue; // if line is empty, skip it
        if(empty($line)) continue; // if line is empty, skip it
        
        $line_temp = explode("#",$line); // remove comment #comment
        if(empty($line_temp[0]))
        {
            continue;
        }
        else 
        {
            $line = $line_temp[0];
        }

        $line_temp = explode("=",$line); // key=value#comment

        $key = $line_temp[0];
        $value = $line_temp[1];
        
        $config[$key] = $value;
    }
    
    return $config;
}

// stores last values the user set during runtime (by checkboxes and buttons in the program)
// it is basically like cookies BUT with the distinctive advantage of NO EXPIRATION DATE) (as it is not possible to set a cookie to "forever" https://stackoverflow.com/questions/3290424/set-a-cookie-to-never-expire)
function config_save($key,$value)
{
    $config = array();
    $config_php_lines = file("config.php") or die("can't open config.php file, does webserver-user (www-data) have file-access-rights to web-root?");

    foreach($config_php_lines as $key => $line)
    {
        if(empty($line)) continue; // if line is empty, skip it
        $line_segments = explode("=",$line); // key=value#comment
    }
    // file_put_contents('config.php', $config_php_lines) or die("can't open config.php file for writing, does webserver-user (www-data) have file-access-rights to web-root?");
}

?>