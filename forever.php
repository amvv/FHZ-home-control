<?php

include "config.php";
include "coeficients.php";
include "current_temperature.php";

$ACTUATOR_LIMIT = 5;		#minimum value of the actuator to allow the boiler to turn on
$DESIRED_OFFSET = 1.0;		#difference between the desired temperature and the actual temperature that the system will try to achieve
$HISTERESIS = 0.4;

$KP = 15;
$KI = 5;
$KD = 5;


#writes coeficients to a file

function get_time_difference( $start, $end )
{
    $uts['start']      =    strtotime( $start );
    $uts['end']        =    strtotime( $end );
    if( $uts['start']!==-1 && $uts['end']!==-1 )
    {
        if( $uts['end'] >= $uts['start'] )
        {
            $diff    =    $uts['end'] - $uts['start'];
            if( $days=intval((floor($diff/86400))) )
                $diff = $diff % 86400;
            if( $hours=intval((floor($diff/3600))) )
                $diff = $diff % 3600;
            if( $minutes=intval((floor($diff/60))) )
                $diff = $diff % 60;
            $diff    =    intval( $diff );            
            return( $days * 86400 + $hours * 3600 + $minutes * 60 + $diff );
        }
        else
        {
            trigger_error( "Ending date/time is earlier than the start date/time", E_USER_WARNING );
        }
    }
    else
    {
        trigger_error( "Invalid date/time data detected", E_USER_WARNING );
    }
    return( false );
}

function update_boiler_time($boiler_time, $on)
{
	for ($i = 30; $i>1; $i--)
	{
		$a = $i - 1;
		$boiler_time[$i] = $boiler_time[$a];
	}
	if ($on == true)
	{
		$boiler_time[1] = 1;
	}
	else
	{
		$boiler_time[1] = 0;
	}
	return $boiler_time;
}


function writeCoeficients($warmup, $cool, $boiler_time, $boiler_pump_on, $time_last_on, $date_last_oni, $error, $integral, $control_value)
{
	$s = "<?php\n";

	foreach($warmup as $room => $fht) 
	{
		for ($i = 1; $i < 41; $i++)
		{
			$a = $warmup[$room][$i];
			$s .= "\$warmup[\"$room\"][$i] = $a;\n";
		}
		$s .= "\n";
	}

	foreach($cool as $room => $fht) 
	{
		for ($i = 1; $i < 41; $i++)
		{
			$a = $cool[$room][$i];
			$s .= "\$cool[\"$room\"][$i] = $a;";
			$s .= "\n";
		}
		$s .= "\n";
	}
	for ($i = 1; $i < 31; $i++)
	{
		$a = $boiler_time[$i];
		$s .= "\$boiler_time[$i] = $a;";
		$s .= "\n";
	}
	$s .= "\n";
	foreach($integral as $room => $fht) 
	{
			$a = $integral[$room];
			$s .= "\$integral[\"$room\"] = $a;";
			$s .= "\n";
	}
	$s .= "\n";
	
	//$boiler_pump_on
	
	$a = $boiler_pump_on;
	$s .= "\$boiler_pump_on = $a;";
	$s .= "\n";
	
	//$time_last_on
	
	$a = $time_last_on;
	$s .= "\$time_last_on = $a;";
	$s .= "\n";
	
	//$date_last_on
	
	$a = $date_last_on;
	$s .= "\$date_last_on = \"$a\";";
	$s .= "\n";
	//$error
	
	$a = $error;
	$s .= "\$previous_error = $a;";
	$s .= "\n";
	//$control_value
	
	$a = $control_value;
	$s .= "\$control_value = $a;";
	$s .= "\n";
	
	$s .= "?>\n";
	
	$handle = fopen("/usr/local/bin/fhz1000/coeficients.php", 'w'); 
	fwrite($handle, $s);
	fclose($handle);
}



#executes over the network to the fhz1000.pl (or localhost)
	function execFHZ($order,$machine,$port)
	{
		$fp = stream_socket_client("tcp://$machine:$port", $errno, $errstr, 30);
        	if (!$fp) {
        		echo "$errstr ($errno)<br />\n";
        		} else {
        		fwrite($fp, "$order\n;quit\n");
        		fclose($fp);
        		}
	}

###### make an array from the xmllist
	unset($output);
	$vector = array();
	$stack = array();
	$output=array();
	$fp = stream_socket_client("tcp://$fhz1000:$fhz1000port", $errno, $errstr, 30);
	if (!$fp) {
	   echo "$errstr ($errno)<br />\n";
	} else {
	   fwrite($fp, "xmllist\r\n;quit\r\n");
	   while (!feof($fp)) {
	       $outputvar = fgets($fp, 1024);
		array_push($output,$outputvar);

	   }
	   fclose($fp);
	}

#  start_element_handler ( resource parser, string name, array attribs )
	function startElement($parser, $name, $attribs)
	{											
	   global $stack;
	   $tag=array("name"=>$name,"attrs"=>$attribs);
	   array_push($stack,$tag);
	}
	
	#  end_element_handler ( resource parser, string name )
	function endElement($parser, $name)
	{
	   global $stack;
	   $stack[count($stack)-2]['children'][] = $stack[count($stack)-1];
	   array_pop($stack);
	}//
	
	
	function new_xml_parser($live)
	{
	   global $parser_live;
	   $xml_parser = xml_parser_create();
	   xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, 0);
	   xml_set_element_handler($xml_parser, "startElement", "endElement");
	 
	   if (!is_array($parser_live)) {
	       settype($parser_live, "array");
	   }
	   $parser_live[$xml_parser] = $live;
	   return array($xml_parser, $live);
	}
	
	# go parsing
	$live=0;
	if (!(list($xml_parser, $live) = new_xml_parser($live))) {
	   die("could not parse XML input");
	}

	
	foreach($output as $data) { 
	  if (!xml_parse($xml_parser, $data)) {
	       die(sprintf("XML error: %s at line %d\n",
	                   xml_error_string(xml_get_error_code($xml_parser)),
	                   xml_get_current_line_number($xml_parser)));
	   }
	}
	
	$all_data = array();
	
	for($i=0; $i < count($stack[0]['children']); $i++) 
	{
	
		if ($stack[0]['children'][$i]['name']=='FHT_DEVICES')
		{
	 		for($j=0; $j < count($stack[0]['children'][$i]['children']); $j++)
			{
				$fhtdevxml=$stack[0]['children'][$i]['children'][$j]['attrs']['name'];
		 		for($k=0; $k < count($stack[0]['children'][$i]['children'][$j]['children']); $k++)
				{
				   $check=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['name'];
				   switch ($check):
				   	Case "measured-temp":
						$temp=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
					        //echo "$fhtdevxml measured-temp: $temp\n";
						$vector[$fhtdevxml]["measured-temp"] = $temp;
						$temp=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['measured'];
					        //echo "$fhtdevxml measured-temp: $temp\n";
						$vector[$fhtdevxml]["measured-time"] = $temp;
						break;
				   	Case "desired-temp":
						$temp=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
					        //echo "$fhtdevxml desired-temp: $temp\n";
						$vector[$fhtdevxml]["desired-temp"] = $temp - $DESIRED_OFFSET;
						break;
				   	Case "actuator":
						$act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
					        //echo "$fhtdevxml actuator: $act\n";
						$vector[$fhtdevxml]["actuator"] = $act;
						$act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['measured'];
						$vector[$fhtdevxml]["actuator-time"] = $act;
						break;
                                        Case "mon-to1":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["mon-to1"] = $act;
                                                break;
                                        Case "mon-to2":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["mon-to2"] = $act;
                                                break;
                                        Case "tue-to1":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["tue-to1"] = $act;
                                                break;
                                        Case "tue-to2":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["tue-to2"] = $act;
                                                break;
                                        Case "wed-to1":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["wed-to1"] = $act;
                                                break;
                                        Case "wed-to2":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["wed-to2"] = $act;
                                                break;
                                        Case "thu-to1":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["thu-to1"] = $act;
                                                break;
                                        Case "thu-to2":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["thu-to2"] = $act;
                                                break;
                                        Case "fri-to1":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["fri-to1"] = $act;
                                                break;
                                        Case "fri-to2":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["fri-to2"] = $act;
                                                break;
                                        Case "sat-to1":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["sat-to1"] = $act;
                                                break;
                                        Case "sat-to2":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["sat-to2"] = $act;
                                                break;
                                        Case "sun-to1":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["sun-to1"] = $act;
                                                break;
                                        Case "sun-to2":
                                                $act=$stack[0]['children'][$i]['children'][$j]['children'][$k]['attrs']['value'];
                                                //echo "$fhtdevxml actuator: $act\n";
                                                $vector[$fhtdevxml]["sun-to2"] = $act;
                                                break;

					default:
				   endswitch;
				}
				//echo "ROOM CHANGE\n";
				//$all_data
			 }
		} #FHT
	}
	
	//echo $vector["kitchen"]["actuator"] . "\n";
	//echo $vector["kitchen"]["measured-temp"] . "\n";
	//echo $vector["kitchen"]["actuator-time"] . "\n";
	//echo $vector["kitchen"]["measured-time"] . "\n";
	//echo $vector["kitchen"]["desired-temp"] . "\n";
	//echo $vector["livingRoom"]["actuator"] . "\n";
	$vector["livingRoom"]["actuator"]  = 75;
	$vector["sleepRoom"]["actuator"]  = 75;
	$vector["babyRoom"]["actuator"]  = 75;
	$vector["kitchen"]["actuator"]  = 75;
	//$vector["livingRoom"]["actuator"]  = 0;
	//$vector["sleepRoom"]["actuator"]  = 0;
	//$vector["babyRoom"]["actuator"]  = 0;
	//$vector["kitchen"]["actuator"]  = 0;
	//echo $vector["livingRoom"]["measured-temp"] . "\n";
	//echo $vector["livingRoom"]["actuator-time"] . "\n";
	//echo $vector["livingRoom"]["measured-time"] . "\n";
	//echo $vector["livingRoom"]["desired-temp"] . "\n";
	//echo $vector["sleepRoom"]["actuator"] . "\n";
	//echo $vector["sleepRoom"]["measured-temp"] . "\n";
	//echo $vector["sleepRoom"]["actuator-time"] . "\n";
	//echo $vector["sleepRoom"]["measured-time"] . "\n";
	//echo $vector["sleepRoom"]["desired-temp"] . "\n";

if (!isset($vector["kitchen"]["actuator"]))
{
	$vector["kitchen"]["actuator"] = 0;
}
if (!isset($vector["livingRoom"]["actuator"]))
{
	$vector["livingRoom"]["actuator"] = 0;
}
if (!isset($vector["sleepRoom"]["actuator"]))
{
	$vector["sleepRoom"]["actuator"] = 0;
}
if (!isset($vector["babyRoom"]["actuator"]))
{
	$vector["babyRoom"]["actuator"] = 0;
}

echo "forever - 2010 08 18<br>";
	
$outside_temp = $current_temperature;

echo "outside temp = $outside_temp<br>\n";
	
	foreach($vector as $room => $fht) 
	{
		$parts = explode ("%", $fht["actuator"]);
		$vector[$room]["actuator"] = $parts[0];
	
		$parts = explode ("(", $fht["measured-temp"]);
		$vector[$room]["measured-temp"] = $parts[0];
	
		$parts = explode ("(", $fht["desired-temp"]);
		$vector[$room]["desired-temp"] = $parts[0];
	}
	
	$boiler_on = false;
	$order = "set boiler off";
	$wkday = date('l');

$log_handle = fopen("/usr/local/bin/fhz1000/forever.log", 'a'); 
###############################################fwrite($log_handle, $number);
	
	foreach($vector as $room => $fht) 
	{

//if about to switch to night temperature than do not warm up

                switch($wkday) {

                    case 'Monday':    $c1 = strtotime($fht["mon-to1"]) - time();$c2 = strtotime($fht["mon-to2"]) - time();; break;
                    case 'Tuesday':   $c1 = strtotime($fht["tue-to1"]) - time();$c2 = strtotime($fht["tue-to2"]) - time();; break;
                    case 'Wednesday': $c1 = strtotime($fht["wed-to1"]) - time();$c2 = strtotime($fht["wed-to2"]) - time();; break;
                    case 'Thursday':  $c1 = strtotime($fht["thu-to1"]) - time();$c2 = strtotime($fht["thu-to2"]) - time();; break;
                    case 'Friday':    $c1 = strtotime($fht["fri-to1"]) - time();$c2 = strtotime($fht["fri-to2"]) - time();; break;
                    case 'Saturday':  $c1 = strtotime($fht["sat-to1"]) - time();$c2 = strtotime($fht["sat-to2"]) - time();; break;
                    case 'Sunday':    $c1 = strtotime($fht["sun-to1"]) - time();$c2 = strtotime($fht["sun-to2"]) - time();; break;
                }

                if (($c1 < 10) and ($c1 > 0))
                {
                        echo "do not turn on - 1 - $c1!!!";
                }
                if (($c2 < 10) and ($c2 > 0))
                {
                        echo "do not turn on - 2 - $c2!!!";
                }




		$output_string = date('Y-m-d H:i:s');
		$output_string .= "\t" . $room . "  \t";
		
		$c = time() - strtotime($fht["measured-time"]);
		$c = $c / 60;

		if ($c > 21)
		{
			$c = 21;
		}

		$short_num = sprintf("%5.1f", $c); 

		$output_string .= "$short_num\t";
		echo "<b>$room</b> - time elapsed $short_num\n";
		
		$k = 0;//boiler warming in the last minutes
	//CHANGES NEWBOILER
		//for ($i = 1; $i<$c+9; $i++)
		for ($i = 1; $i<11; $i++)
		{
			$k += $boiler_time[$i];
		}
		$output_string .= "$k\t";
		//Old calculation:
		$k = $k * 1.3; //after switching off still warms up for a bit
		//New calculation
		//$tttt = pow($k, 1.4537);
		//$k = 0.4092 * $tttt;


		$temp_delta=$fht["measured-temp"] - $outside_temp;
		$temp_delta = round($temp_delta);
		echo "delta $temp_delta\n";
		if ($temp_delta <= 0)
		{
			$temp_delta = 1;
		}
		$output_string .= "$outside_temp\t";
		
		$current_temp = $fht["measured-temp"];

		$output_string .= "$current_temp\t";
		
		$warm_c = $warmup["$room"][$temp_delta];
		$cool_c = $cool["$room"][$temp_delta];

		$estimated_temp = $k * $warm_c - ($c - $k) * $cool_c;
		$estimated_temp = $estimated_temp * $fht["actuator"]/100;
		$estimated_temp = $estimated_temp + $current_temp;
		
		$short_num = sprintf("%5.2f", $estimated_temp); 

		$output_string .= "$short_num\t";
		$aa = $fht["desired-temp"];
		$output_string .= "$aa\t";
		$aa = $fht["actuator"];
		$output_string .= "$aa\t";
		
		//NEW STUFF
		$error = $fht["desired-temp"] - $fht["measured-temp"];
//tw = (desired - measured)Kheating + (measured - outside)Kloss 
		if (abs($error) < 1.0)
		{
			$integral[$room] = $integral[$room] + $error;
		}
		else
		{
			$integral[$room] = 0;
		}
		$control_value = $error*$KP + $integral[$room]*$KI;




		//END OF NEW STUFF	
		echo "estimated $estimated_temp °C ";
		echo "actuator $aa% ";
		echo "current $current_temp °C<br>\n";
		echo "\n";

		//attemp at histeresis
		if ($boiler_time[1] == 1)
		{
			$estimated_temp = $estimated_temp - $HISTERESIS;
		}



		$p = 0;
		for ($i = 1; $i<6; $i++)
		{
			$p += $boiler_time[$i];
		}

		if (($boiler_time[1] == 0) AND ($p > 0))
		{
			//boiler has not been off for 5 minutes
			//wait until that is the case
			echo "waiting for boiler resting time - $p ";
		}
		else
		{


			if ($fht["actuator"] > $ACTUATOR_LIMIT and $estimated_temp < ($fht["desired-temp"]))
			{
				//$time_needed = ($fht["desired-temp"] - $fht["measured-temp"] * 15);
				$date = date('Y-m-d H:i:s');
				printf ("$date");
				printf ("-on for $room act:%s mea:%s des:%s est:%2.2f<br>\n",
				$fht["actuator"], $fht["measured-temp"],
				$fht["desired-temp"], $estimated_temp);
				$boiler_on = true;
			}
			else
			{
				//do nothing...
			}
		}
		if ($boiler_on == true)
		{
			$output_string .= "ON\t";
		}
		else
		{
			$output_string .= "OFF\t";
		}
		$output_string .= "\n";
		fwrite($log_handle, $output_string);
	}
	if ($boiler_on == true)
	{
		$order = "set boiler on-for-timer 65";
		//$order = "set boiler on";
		$time_last_on = time();
	}

fclose($log_handle);
$boiler_time = update_boiler_time($boiler_time, $boiler_on);


//check if the boiler pump should be disconnected and do it if so

$time_now = time();
$boiler_elapsed = $time_now-$time_last_on;


//COMMENTED SINCE RADIATORS ARE OFF COMMENT IN WHEN SYSTEM READY
execFHZ($order,$fhz1000,$fhz1000port);

writeCoeficients($warmup, $cool, $boiler_time, $boiler_pump_on, $time_last_on, $date_last_on, $error, $integral, $control_value);

?> 
