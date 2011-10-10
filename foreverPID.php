<?php

include "config.php";
include "coeficientsPID.php";
include "current_temperature.php";

$ACTUATOR_LIMIT = 5;		#minimum value of the actuator to allow the boiler to turn on
$DESIRED_OFFSET = 0.0;		#difference between the desired temperature and the actual temperature that the system will try to achieve
$HISTERESIS = 0.4;

$KP = 10;
$KI = 0;
$KD = 0;


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


function writeCoeficients($integral, $control_value, $vector)
{
	$s = "<?php\n";

	foreach($integral as $room => $fht) 
	{
			$a = $integral[$room];
			$s .= "\$integral[\"$room\"] = $a;";
			$s .= "\n";
	}
	$s .= "\n";
	foreach($control_value as $room => $fht) 
	{
			$a = $control_value[$room];
			$s .= "\$control_value[\"$room\"] = $a;";
			$s .= "\n";
	}
	$s .= "\n";
	foreach($vector as $room => $fht) 
	{
			$a = $fht["desired-temp"];
			$s .= "\$desired_temp[\"$room\"] = $a;";
			$s .= "\n";
	}
	$s .= "\n";
	foreach($vector as $room => $fht) 
	{
			$a = $fht["measured-time"];
			$s .= "\$measured_time[\"$room\"] = \"$a\";";
			$s .= "\n";
	}
	$s .= "\n";		
	$s .= "?>\n";
	
	$handle = fopen("/usr/local/bin/fhz1000/coeficientsPID.php", 'w'); 
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

echo "foreverPID - 2011 10 09<br>";
	
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
	
	$order = "set boiler off";
	$wkday = date('l');

$log_handle = fopen("/usr/local/bin/fhz1000/foreverPID.log", 'a'); 
###############################################fwrite($log_handle, $number);
	
	foreach($vector as $room => $fht) 
	{

 //if about to switch to night temperature than do not warm up

//                switch($wkday) {

//                    case 'Monday':    $c1 = strtotime($fht["mon-to1"]) - time();$c2 = strtotime($fht["mon-to2"]) - time();; break;
//                    case 'Tuesday':   $c1 = strtotime($fht["tue-to1"]) - time();$c2 = strtotime($fht["tue-to2"]) - time();; break;
//                    case 'Wednesday': $c1 = strtotime($fht["wed-to1"]) - time();$c2 = strtotime($fht["wed-to2"]) - time();; break;
//                    case 'Thursday':  $c1 = strtotime($fht["thu-to1"]) - time();$c2 = strtotime($fht["thu-to2"]) - time();; break;
//                    case 'Friday':    $c1 = strtotime($fht["fri-to1"]) - time();$c2 = strtotime($fht["fri-to2"]) - time();; break;
//                    case 'Saturday':  $c1 = strtotime($fht["sat-to1"]) - time();$c2 = strtotime($fht["sat-to2"]) - time();; break;
//                    case 'Sunday':    $c1 = strtotime($fht["sun-to1"]) - time();$c2 = strtotime($fht["sun-to2"]) - time();; break;
//                }

//                if (($c1 < 10) and ($c1 > 0))
//               {
//                       echo "do not turn on - 1 - $c1!!!";
//                }
//                if (($c2 < 10) and ($c2 > 0))
//                {
//                        echo "do not turn on - 2 - $c2!!!";
//                }



		$output_string = date('Y-m-d H:i:s');
		$output_string .= "\t" . $room . "  \t";
		


		$output_string .= "$outside_temp\t";
		
		$current_temp = $fht["measured-temp"];

		$output_string .= "$current_temp\t";
		
		$aa = $fht["desired-temp"];
		$output_string .= "$aa\t";
		$aa = $fht["actuator"];
		$output_string .= "$aa\t";
		
		//NEW STUFF
		
		
		if ($desired_temp["babyRoom"] <> $fht["desired-temp"])  //change in setpoint: reset the PID controller
		{
			$integral[$room] = 0;
		}
		
		
		$error = $fht["desired-temp"] - $fht["measured-temp"];

		//only update the integral term upon each measurement of the room temperature
		if ($fht["measured-time"] <> $measured_time[$room])
		{
		
			echo "udating the integral term!!\n";
			if (abs($error) < 1.0)
			{
				$integral[$room] = $integral[$room] + $error;
			}
			else
			{
				$integral[$room] = 0;
			}
		}
		
//based on the curve for the boiler: -(tout)^2/100 - (tout) + a constant linearly related to the desired temperature: 27 + desired = K

		$base_water_temperature = -($outside_temp^2)/100 - $outside_temp + 27 + $fht["desired-temp"];
		
		echo "base water temperature for $room: \t $base_water_temperature C\n";

		$control_value[$room] = $error*$KP + $integral[$room]*$KI + $base_water_temperature;

		$output_string .= "$base_water_temperature\t";
		$output_string .= "$error\t";
		$aa = $integral[$room];
		$output_string .= "$aa\t";
		$aa = $control_value[$room];
		$output_string .= "$aa\n";
	
		

fwrite($log_handle, $output_string);


		//END OF NEW STUFF	
}

fclose($log_handle);

$max_control = 0;

	foreach($control_value as $room => $fht) 
	{
			$a = $control_value[$room];
			echo "\$control[\"$room\"] = $a;\n";
			if ($a > $max_control)
			{
				$max_control = $a;
			}
	}

$max_control = round($max_control);
echo "Control value to send: $max_control";

//check if the boiler pump should be disconnected and do it if so

//COMMENTED SINCE This is in test and should be adapted for JeeNodes
//execFHZ($order,$fhz1000,$fhz1000port);

writeCoeficients($integral, $control_value, $vector);

?> 
