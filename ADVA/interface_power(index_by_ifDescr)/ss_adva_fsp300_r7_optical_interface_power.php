<?php
# vim:ts=2:set noai:
/*
 +-------------------------------------------------------------------------+
 | name    : ss_adva_fsp300_r7_optical_interface_power.php                 |
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
    print call_user_func_array("ss_adva_op_if_power", $_SERVER["argv"]);
}

# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_adva_op_if_power <host_or_ip> public 2 index
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_adva_op_if_power <host_or_ip> public 2 query index|entityindex|ifalias|adminstatus|operstatus|inputpower|outputpower
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_adva_op_if_power <host_or_ip> public 2 get rx|tx <interface_name>

function ss_adva_op_if_power($hostname, $snmp_community, $snmp_version, $cmd, $query_field = "", $query_index = "") {

    $oids = array (
        "ifDescr"                     => ".1.3.6.1.2.1.2.2.1.2",
        "ifAlias"                     => ".1.3.6.1.2.1.31.1.1.1.18",
        "ifAdminStatus"               => ".1.3.6.1.2.1.2.2.1.7",
        "ifOperStatus"                => ".1.3.6.1.2.1.2.2.1.8",
        "opticalIfDiagInputPower"     => ".1.3.6.1.4.1.2544.1.11.2.4.3.5.1.3",
        "opticalIfDiagOutputPower"    => ".1.3.6.1.4.1.2544.1.11.2.4.3.5.1.4",
    );

    $var = ss_adva_op_if_power_walk($hostname, $snmp_community, $oids["ifDescr"], $snmp_version);
#    print_r ($var);   // this will help you see what is being returned, the structure
    if ($cmd == "index" || $cmd == "query") {
        // we run through all the possible interfaces and with a loop we select only the ones we want, the relevant ones for us
        // in the interfaces relevant for us, we parse the oid and get the ifIndex (last number in the full oid)
        // with that ifIndex we will find out all the values we want, i.e. ifAlias, ifAdminStatus, etc.
        for ($i=0;$i<(count($var));$i++) {
            if ( preg_match("/\bCH-[0-9]{1}-.*|\bOM./", $var[$i]["value"]) ) {
                // get the ifIndex
                preg_match("/[0-9]+$/", $var[$i]["oid"], $ifIndex);
                $entityIndex = $ifIndex[0];

                // get the interface name, this will be our index
                $ifDescr = $var[$i]["value"];

                if ($cmd == "index") {
                    $result = $ifDescr;

                } elseif ($cmd == "query") {
                    switch ($query_field) {

                    case "index":
                        $result = $ifDescr . "!" . $ifDescr;
                        break;

                    case "entityindex":
                        $ifDescr = $var[$i]["value"];
                        $result = $ifDescr . "!" . $entityIndex;
                        break;

                    case "ifalias":
                        $ifAlias = ss_adva_op_if_power_get($hostname, $snmp_community, $oids["ifAlias"] . ".$entityIndex", $snmp_version);
                        $result = $ifDescr . "!" . $ifAlias;
                        break;

                    case "adminstatus":
                        $ifAdminStatus = ss_adva_op_if_power_get($hostname, $snmp_community, $oids["ifAdminStatus"] . ".$entityIndex", $snmp_version);
                        preg_match("/^[a-z]+/", $ifAdminStatus, $trimmed_ifAdminStatus);
                        $result = $ifDescr . "!" . $trimmed_ifAdminStatus[0];
                        break;

                    case "operstatus":
                        $ifOperStatus = ss_adva_op_if_power_get($hostname, $snmp_community, $oids["ifOperStatus"] . ".$entityIndex", $snmp_version);
                        preg_match("/^[a-z]+/", $ifOperStatus, $trimmed_ifOperStatus);
                        $result = $ifDescr . "!" . $trimmed_ifOperStatus[0];
                        break;

                    case "inputpower":
                        $opticalIfDiagInputPower = ss_adva_op_if_power_get($hostname, $snmp_community, $oids["opticalIfDiagInputPower"] . ".$entityIndex", $snmp_version);
                        $result = $ifDescr . "!" . $opticalIfDiagInputPower/10;
                        break;

                    case "outputpower":
                        $opticalIfDiagOutputPower = ss_adva_op_if_power_get($hostname, $snmp_community, $oids["opticalIfDiagOutputPower"] . ".$entityIndex", $snmp_version);
                        $result = $ifDescr . "!" . $opticalIfDiagOutputPower/10;
                        break;

                    default:
                        $result = "\n" . "Invalid query argument. Valid arguments are \"index\", \"entityindex\", \"ifalias\" " .
                                  "\"adminstatus\", \"operstatus\", \"inputpower\" and \"outputpower\"" . "\n";
                        exit($result);
                    }
                }
                print $result . "\n";
            }
        }
    } 
    elseif ($cmd == "get") {
        //This is the argument the user passes to the script, i.e. "SC-1-3NE"
        $interface = $query_index;

        // now we will find the entityIndex of the the interface (a string) the user passes to the script
        // we run through all the interface names reported by the MIB
        // in each pass of the loop, compare the name of the interface given by the user (it is a string) with the value of the oid (a string as well)
        // and when we find the match of the two strings, parse the entityIndex as the last number string in the oid
        for ($i=0;$i<(count($var));$i++) {
            $ifDescr = $var[$i]["value"];

            if (strstr($ifDescr, $interface)) {

                preg_match("/[0-9]+$/", $var[$i]["oid"], $index);
                $entityIndex = $index[0];

                switch ($query_field) {

                    case "rx":
                        $result = ss_adva_op_if_power_get($hostname, $snmp_community, $oids["opticalIfDiagInputPower"] . ".$entityIndex", $snmp_version);
                        break;

                    case "tx":
                        $result = ss_adva_op_if_power_get($hostname, $snmp_community, $oids["opticalIfDiagOutputPower"] . ".$entityIndex", $snmp_version);
                        break;

                    default:
                        $result = "\n" . "Invalid query argument. Valid arguments are \"rx\" and \"tx\"" . "\n";
                        exit($result);

                }
            return trim($result);
            }
        }
        print "No interface matches the name you have given. This is the list of all the interfaces: "  . "\n";
        for ($i=0;$i<(count($var));$i++) {
            $ifDescr = $var[$i]["value"];
            print $ifDescr . "\n";
        }
        exit;
    }
    else {
        print "Invalid command. Valid commands are \"index\", \"query\" and \"get\"" . "\n";
    }
}

function ss_adva_op_if_power_walk ($hostname, $snmp_community, $oid, $snmp_version) {

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

function ss_adva_op_if_power_get ($hostname, $snmp_community, $oid, $snmp_version) {

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
