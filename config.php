<?php

##################################################################################
#### pgm3 -- a PHP-webfrontend for fhz1000.pl 


###### required settings
	$fhz1000="localhost"; 			# on which machine is fhz1000 runnning??
						# it possible to run it over the network
						# be sure that the fhz1000 is running with "port 7025 global"
	$fhz1000port="7072";			# port of fhz1000.pl
	$logpath="/tmp";			# where are your logs?
						# if is running over the network the install nfs-shares

	$gnuplot='/usr/local/bin/gnuplot';		# location of gnuplot
##################################################################################
###### nice to have


###### showgnuplot 
	# Gnuplot will automatically show the pictures.
	# There is no reason any more to deactivate Gnuplot. Values: 0/1
	$showgnuplot=1;
	$pictype='png';  	



## Kioskmode. Only show but don't switch anything. Values: on/off
	$kioskmode='off';


## HMS-Devices
	$imgmaxxhms=620;  #Size of the pictures
        $imgmaxyhms=52;

## FHT-Devices
	$imgmaxxfht=550;  #Size of the pictures
        $imgmaxyfht=80;
	$fht_ttf_fonts=false;
	$fht_fixed_limits=true;
	$fht_grid_lines=true;
	$fht_upper_limit=20;
	$fht_lower_limit=10;


## FS20-Device, adjust it if you have e.g. long titles
	$imgmaxxfs20=85;  		#Size of the pictures, default=85
        $imgmaxyfs20=85; 		# default=85 
	$fs20fontsizetitel=10;  	# default=10 
	$fs20maxiconperline=10; 	# default=9
	#room. Write e.g. "attr rolowz room wzo" into your fhz1000.cfg and restart fhz1000.pl
	# this will be marked on the FS20-Button. In future there will be the possibility to view only the rooms.
	$txtroom=""; 			# default=""; example: $txtroom="room: ";
	# room hidden will not be shown


## ROOMS adjust it if you have e.g. long titles
	$showroombuttons=1; 		#default 1  Values 0/1
	$imgmaxxroom=$imgmaxxfs20;  	#Size of the pictures, default=$imgmaxxfs20
        $imgmaxyroom=30; 		# default=30 
	$roomfontsizetitel=10;  	# default=10 
	$roommaxiconperline=$fs20maxiconperline; # default=$fs20maxiconperline




## KS300-Device
	$imgmaxxks=620;  		#Size of the pictures
        $imgmaxyks=52;


## FHZ-DEVICES
	$show_general=1; 		#field to type FHZ1000-orders 0/1 Default:1
	$show_fs20pulldown=1; 		#Pull-Down for the FS20 Devices 0/1 Default:1

## misc
	$taillog=1; 			#make shure to have the correct rights. Values: 0/1
	$taillogorder="/usr/bin/tail -30 /tmp/fhz1000.log"; 

	$urlreload=60;			# Automatic reloading page [sec].
	$titel="PHP-Webmachine for fhz1000.pl :-)";
	$timeformat="Y-m-d H:i:s";
	$bodybg="bgcolor='#F5F5F5'"; 
	$bg1="bgcolor='#6E94B7'";
	$bg2="bgcolor='#AFC6DB'";
	$bg3="bgcolor='#F8F8F8'";
	$bg4="bgcolor='#6394BD'";
	$fontcolor1="color='#FFFFFF'";
	$fontcolor2="color='#D4AD00'";
	
	$fontcolor3="color='143554'";
	$fontcol_grap_R=20;
	$fontcol_grap_G=53;
	$fontcol_grap_B=84;
	$fontttf="Vera";
	$fontttfb="VeraBd"; 		##copyright of the fonts: docs/copyright_font
	   ## if there is now graphic try the following:
	    #	$fontttf="Vera.ttf";
	    #	$fontttfb="VeraBd.ttf";
	    # or absolut:
	    #	$fontttf="/var/www/htdocs/fhz1000/include/Vera.ttf";
	    #	$fontttfb="/var/www/htdocs/fhz1000/include/VeraBd.ttf";

###############################   end of settings
	putenv('GDFONTPATH=' . realpath('.')); 

?>
