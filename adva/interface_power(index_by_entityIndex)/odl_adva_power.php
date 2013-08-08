<?php
# vim:ts=2:set noai:
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 sodium                                               |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | name    : ss_65xx_sfp.php                                               |
 | version : 0.2.2                                                         |
 | date    : 20080429                                                      |
 +-------------------------------------------------------------------------+
*/
$no_http_headers = true;

/* display No errors */
error_reporting(E_ERROR);

include_once(dirname(__FILE__) . "/../include/config.php");
include_once(dirname(__FILE__) . "/../lib/snmp.php");

if (!isset($called_by_script_server)) {
    include_once(dirname(__FILE__) . "/../include/config.php");
    include_once(dirname(__FILE__) . "/../include/global.php");
    array_shift($_SERVER["argv"]);
    print call_user_func_array("ss_old_adva_psu", $_SERVER["argv"]);
}

# /usr/bin/php -q /usr/share/cacti/scripts/ss_65xx_sfp.php 192.168.1.11 public 2 index
# /usr/bin/php -q /usr/share/cacti/scripts/ss_65xx_sfp.php 192.168.1.11 public 2 query index|status|descr
# /usr/bin/php -q /usr/share/cacti/scripts/ss_65xx_sfp.php 192.168.1.11 public 2 get rx|tx TenGigabitEthernet1/1

function ss_old_adva_psu($hostname, $snmp_community, $snmp_version, $cmd, $direction = "", $interface = "") {

    $snmp_auth_username   	= "";
    $snmp_auth_password   	= "";
    $snmp_auth_protocol  	= "";
    $snmp_priv_passphrase 	= "";
    $snmp_priv_protocol   	= "";
    $snmp_context         	= "";
    $snmp_port      = 161;				# snmp port
    $snmp_timeout   = 500;				# snmp timeout
    $snmp_retries   = 3;				# snmp retries
    $max_oids	= 1;				# max oids for V2/V3 hosts

    $oids = array (
        "shelfPowerConsumption"       => ".1.3.6.1.4.1.2544.1.11.2.4.2.12.1.1",
        "dependenciesAid"             => ".1.3.6.1.4.1.2544.1.11.2.4.1.5.1.2",
#        "ifDescr"                     => ".1.3.6.1.2.1.2.2.1.2",
#        "ifAlias"                     => ".1.3.6.1.2.1.31.1.1.1.18",
#        "ifAdminStatus"               => ".1.3.6.1.2.1.2.2.1.7",
#        "ifOperStatus"                => ".1.3.6.1.2.1.2.2.1.8",
#        "opticalIfDiagInputPower"     => ".1.3.6.1.4.1.2544.1.11.2.4.3.5.1.3",
#        "opticalIfDiagOutputPower"    => ".1.3.6.1.4.1.2544.1.11.2.4.3.5.1.4",
    );

    $result = "";
    $oid_name = "";
    $sensor_name = "";
    $sensor_status = "";
    $sensor_string = "";
    $status_string = "";
    $tx_status = 0;
    $int = "";
    $snmp_retries = read_config_option("snmp_retries");
#    $var = (cacti_snmp_walk($hostname, $snmp_community, ".1.3.6.1.2.1.2.2.1.2", $snmp_version, "", "", 161, 5000, $snmp_retries, SNMP_POLLER));
    $var = (cacti_snmp_walk($hostname, $snmp_community, $oids["shelfPowerConsumption"], $snmp_version, "", "", 161, 5000, $snmp_retries, SNMP_POLLER));

    if ($cmd == "index" || $cmd == "query") {
        for ($i=0;$i<(count($var));$i++) {
#$aa = (int) $var[$i]["value"];
#print $var[$i]["oid"] . " blah blah " . gettype($aa) . "\n";

         #   if ( preg_match("/\bCH.*|\bOM.*/", $var[$i]["value"]) ) {
            if ( (int) $var[$i]["value"] < 10000 ) {
                preg_match("/[0-9]+$/", $var[$i]["oid"], $ifIndex);
                $entityIndex = $ifIndex[0];
#                $ifDescr = $var[$i]["value"];

                if ($cmd == "index") {
                    print $entityIndex . "\n";

                } elseif ($cmd == "query") {
                    switch ($direction) {

                    case "index":
                        print $entityIndex . "!" . $entityIndex."\n";
                        break;

                    case "partnumber":
                        $partNumber = cacti_snmp_get($hostname, $snmp_community,
                                                        $oids["dependenciesAid"] . ".$entityIndex" . ".1", $snmp_version, $snmp_auth_username,
                                                        $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
                                                        $snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER);
                        print $entityIndex . "!" . $partNumber . "\n";
                        break;

                    }
                }
            }
        }
    } 
    elseif ($cmd == "get") {
        $entityIndex = $interface;

        switch ($direction) {

            case "pw":
                $watts = cacti_snmp_get($hostname, $snmp_community,
                                      $oids["shelfPowerConsumption"] . ".$entityIndex", $snmp_version, $snmp_auth_username,
                                      $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
                                      $snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER);
                 return trim($watts);
                 break;
         #       print $entityIndex . "!" . $rx ."\n";
         #       break;

        }

/*
        for ($i=0;$i<(count($var));$i++) {
            if ($var[$i]["value"] == "14") {                // found a dBm entry


                $sensor_name = (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.2.1.47.1.1.1.1.2.\\1', $var[$i]["oid"]), $snmp_version, "", "", "", 5000, $snmp_retries, SNMP_POLLER));

                if ($direction == "tx") { 
                    $int=$interface." Transmit Power Sensor"; 
                } elseif ($direction == "rx") { 
                    $int=$interface." Receive Power Sensor"; 
                }

                preg_match("/[^\ ]+/", $sensor_name, $oid_name);

                if (strstr($int, $sensor_name)) {
                    if (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.5.\\1', $var[$i]["oid"]), $snmp_version, "", "", 161, 5000, $snmp_retries, SNMP_POLLER) == "1") {
                    $result = (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.4.\\1', $var[$i]["oid"]), $snmp_version, "", "", 161, 5000, $snmp_retries, SNMP_POLLER))/10;
                    }
                    else { $result = "-40"; // lights are off
                    }
                }
            }
        }
*/
    }
#    print $direction . "\n";
#    return trim($result);
}
?>
