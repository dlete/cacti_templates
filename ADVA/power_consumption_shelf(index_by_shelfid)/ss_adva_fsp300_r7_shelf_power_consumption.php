<?php
# vim:ts=2:set noai:
/*
 +-------------------------------------------------------------------------+
 | name    : ss_adva_fsp300_r7_shelf_power_consumption.php                 |
 | version : 0.1.1                                                         |
 | date    : 20130731                                                      |
 +-------------------------------------------------------------------------+
*/
/*
YOU HAVE TO LOOK INTO REINDEX
can you simplyfy $snmp_version?
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
    print call_user_func_array("ss_adva_fsp300r7_chassis_power", $_SERVER["argv"]);
}

# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_adva_fsp300r7_chassis_power <host_or_ip> public 2 index
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_adva_fsp300r7_chassis_power <host_or_ip> public 2 query index|entityindex|power_con|power_out
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_adva_fsp300r7_chassis_power <host_or_ip> public 2 get rx|tx <interface_name>

function ss_adva_fsp300r7_chassis_power($hostname, $snmp_community, $snmp_version, $cmd, $query_field = "", $query_index = "") {

    $oids = array (
        "dependenciesAid"             => ".1.3.6.1.4.1.2544.1.11.2.4.1.5.1.2",
        "shelfPowerConsumption"       => ".1.3.6.1.4.1.2544.1.11.2.4.2.12.1.1",
        "shelfDiagnosticsPowerOutput" => ".1.3.6.1.4.1.2544.1.11.2.4.2.12.1.2",
#        "shelfDiagnosticsMaxPowerConsumption"    => ".1.3.6.1.4.1.2544.1.11.2.4.2.12.1.3",
    );

    $var = ss_adva_fsp300r7_chassis_power_walk($hostname, $snmp_community, $oids["shelfPowerConsumption"], $snmp_version);
#    print_r ($var);   // this will help you see what is being returned, the structure
    if ($cmd == "index" || $cmd == "query") {
        // we run through all the values  where the MIB reports power consumption and with a loop we 
        // select only the ones we want, the relevant ones for us
        // in the components relevant for us, we parse the oid and get the entityIndex (last number in the full oid)
        // with that entityIndex we will find out all the values we want, i.e. ifAlias, ifAdminStatus, etc.
        for ($i=0;$i<(count($var));$i++) {
            if ( (int) $var[$i]["value"] < 10000 ) {
                // get the ifIndex
                preg_match("/[0-9]+$/", $var[$i]["oid"], $matches);
                $entityIndex = $matches[0];

                // get the shelf number, this will be our index
                $fan = ss_adva_fsp300r7_chassis_power_get($hostname, $snmp_community, $oids["dependenciesAid"] . ".$entityIndex" .".1", $snmp_version);
                preg_match("/[0-9]+$/", $fan, $hits);
                $shelf_number = $hits[0];
                

                if ($cmd == "index") {
                    $result = $shelf_number;

                } elseif ($cmd == "query") {
                    switch ($query_field) {

                    case "index":
                        $result = $shelf_number . "!" . $shelf_number;
                        break;

                    case "entityindex":
                        $result = $shelf_number . "!" . $entityIndex;
                        break;

                    case "power_con":
                        $power_con = ss_adva_fsp300r7_chassis_power_get($hostname, $snmp_community, $oids["shelfPowerConsumption"] . ".$entityIndex", $snmp_version);
                        $result = $shelf_number . "!" . $power_con;
                        break;

                    case "power_out":
                        $power_out = ss_adva_fsp300r7_chassis_power_get($hostname, $snmp_community, $oids["shelfDiagnosticsPowerOutput"] . ".$entityIndex", $snmp_version);
                        $result = $shelf_number . "!" . $power_out;
                        break;

                    default:
                        $result = "\n" . "Invalid query argument. Valid arguments are \"index\", \"entityindex\", " .
                                  "\"power_con\" and \"power_out\"" . "\n";
                        exit($result);
                    }
                }
                print $result . "\n";
            }
        }
    } 
    elseif ($cmd == "get") {
        //This is the argument the user passes to the script, i.e. "SC-1-3NE"
        $shelf = $query_index;

        // now we will find the entityIndex of the the interface (a string) the user passes to the script
        // we run through all the interface names reported by the MIB
        // in each pass of the loop, compare the name of the interface given by the user (it is a string) with the value of the oid (a string as well)
        // and when we find the match of the two strings, parse the entityIndex as the last number string in the oid
        for ($i=0;$i<(count($var));$i++) {
            // get the entityIndex of all modules reporting power
            preg_match("/[0-9]+$/", $var[$i]["oid"], $matches);
            $entityIndex = $matches[0];

            // the first dependency is a fan, type FSU-<shelf_number>
            $fan = ss_adva_fsp300r7_chassis_power_get($hostname, $snmp_community, $oids["dependenciesAid"] . ".$entityIndex" . ".1", $snmp_version);
            preg_match("/[0-9]+$/", $fan, $hits);
            $shelf_number = $hits[0];

#print $shelf . gettype($shelf) . "\n"; 
#print $entityIndex . gettype($entityIndex) . "\n";
#print $shelf_number . gettype($shelf_number) . "\n";
           
            if (strstr($shelf_number, $shelf)) {

                switch ($query_field) {

                    case "pw_con":
                        $power_con = ss_adva_fsp300r7_chassis_power_get($hostname, $snmp_community, $oids["shelfPowerConsumption"] . ".$entityIndex", $snmp_version);
                        $result = $power_con;
                        break;

                    case "pw_out":
                        $power_out = ss_adva_fsp300r7_chassis_power_get($hostname, $snmp_community, $oids["shelfDiagnosticsPowerOutput"] . ".$entityIndex", $snmp_version);
                        $result = $power_out;
                        break;

                    default:
                        $result = "\n" . "Invalid query argument. Valid arguments are \"pw_con\" and \"pw_out\"" . "\n";
                        exit($result);

                }
            return trim($result);
            }
        }
    }
    else {
        print "Invalid command. Valid commands are \"index\", \"query\" and \"get\"" . "\n";
    }
}

function ss_adva_fsp300r7_chassis_power_walk ($hostname, $snmp_community, $oid, $snmp_version) {

    $snmp_auth_username         = "";
    $snmp_auth_password         = "";
    $snmp_auth_protocol         = "";
    $snmp_priv_passphrase       = "";
    $snmp_priv_protocol         = "";
    $snmp_context               = "";
    $snmp_port                  = 161;                              # snmp port
    $snmp_timeout               = 500;                              # snmp timeout
    $snmp_retries               = read_config_option("snmp_retries");
    $environ                    = SNMP_POLLER; 

    $walk = cacti_snmp_walk($hostname, $snmp_community, $oid, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $environ);

    return $walk;
}

function ss_adva_fsp300r7_chassis_power_get ($hostname, $snmp_community, $oid, $snmp_version) {

    $snmp_auth_username         = "";
    $snmp_auth_password         = "";
    $snmp_auth_protocol         = "";
    $snmp_priv_passphrase       = "";
    $snmp_priv_protocol         = "";
    $snmp_context               = "";
    $snmp_port                  = 161;                              # snmp port
    $snmp_timeout               = 500;                              # snmp timeout
    $snmp_retries               = read_config_option("snmp_retries");
    $environ                    = SNMP_POLLER;

    $get = cacti_snmp_get($hostname, $snmp_community, $oid, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $environ);

    return $get;
}

?>
