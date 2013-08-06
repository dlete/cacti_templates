<?php
# vim:ts=2:set noai:
/*
 +-------------------------------------------------------------------------+
 | name    : ss_adva_fsp300_r7_node_power_consumption.php                 |
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
    print call_user_func_array("ss_adva_fsp300r7_node_power", $_SERVER["argv"]);
}

# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_adva_fsp300_r7_node_power_consumption.php <host_or_ip> <snmp_community>

function ss_adva_fsp300r7_node_power($hostname, $snmp_community) {

    $oids = array (
        "shelfPowerConsumption"       => ".1.3.6.1.4.1.2544.1.11.2.4.2.12.1.1",
        "shelfDiagnosticsPowerOutput" => ".1.3.6.1.4.1.2544.1.11.2.4.2.12.1.2",
#        "shelfDiagnosticsMaxPowerConsumption"    => ".1.3.6.1.4.1.2544.1.11.2.4.2.12.1.3",
    );

    $var = ss_adva_fsp300r7_node_power_walk($hostname, $snmp_community, $oids["shelfPowerConsumption"]);

    $node_power_consumption = 0;
    $node_power_output = 0;

    for ($i=0;$i<(count($var));$i++) {
        if ( (int) $var[$i]["value"] < 10000 ) {
            // get the ifIndex
            preg_match("/[0-9]+$/", $var[$i]["oid"], $matches);
            $entityIndex = $matches[0];

            $node_power_consumption = $node_power_consumption + $var[$i]["value"];

            $shelf_power_output = ss_adva_fsp300r7_node_power_get($hostname, $snmp_community, $oids["shelfDiagnosticsPowerOutput"] . ".$entityIndex");
            $node_power_output = $node_power_output + $shelf_power_output;
            
        }

    }    
    return "node_power_con:".$node_power_consumption . " " . "node_power_out:".$node_power_output;
}


function ss_adva_fsp300r7_node_power_walk ($hostname, $snmp_community, $oid) {

    $snmp_version               = 2;
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

function ss_adva_fsp300r7_node_power_get ($hostname, $snmp_community, $oid) {

    $snmp_version               = 2;
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
