<?php
# vim:ts=2:set noai:
/*
 +-------------------------------------------------------------------------+
 | name    : ss_adva_fsp300_r7_optical_line_attenuation.php                |
 | version : 0.1.1                                                         |
 | date    : 20130731                                                      |
 +-------------------------------------------------------------------------+
*/
/*
YOU HAVE TO LOOK INTO REINDEX
can you simplyfy $snmp_version?
can you simplyfy the for loop? it is twice one in query and another in get
along same lines, can you simplify the for loop? i.e. comparison of two arrays? one array with entityIndex, the other with ifDescr and compare oid?
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
    print call_user_func_array("ss_adva_op_li_att", $_SERVER["argv"]);
}

# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_adva_op_li_att <host_or_ip> public 2 index
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_adva_op_li_att <host_or_ip> public 2 query index|entityindex|ifalias|adminstatus|operstatus|rxatt|txatt|farendhost|farendport|farendtransport
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_adva_op_li_att <host_or_ip> public 2 get rx|tx|farendthreshold <interface_name>

function ss_adva_op_li_att($hostname, $snmp_community, $snmp_version, $cmd, $query_field = "", $query_index = "") {

    $oids = array (
        "ifDescr"                                          => ".1.3.6.1.2.1.2.2.1.2",
        "ifAlias"                                          => ".1.3.6.1.2.1.31.1.1.1.18",
        "ifAdminStatus"                                    => ".1.3.6.1.2.1.2.2.1.7",
        "ifOperStatus"                                     => ".1.3.6.1.2.1.2.2.1.8",
        "neighborDiscoveryDataFarEndTid"                   => ".1.3.6.1.4.1.2544.1.11.2.4.3.41.1.3",
        "neighborDiscoveryDataFarEndPortAid"               => ".1.3.6.1.4.1.2544.1.11.2.4.3.41.1.5",
        "neighborDiscoveryDataFarEndTranLayerTermPointAid" => ".1.3.6.1.4.1.2544.1.11.2.4.3.41.1.6",
        "opticalIfOlmRxLineAttenuation"                    => ".1.3.6.1.4.1.2544.1.11.2.4.3.15.1.1",
        "opticalIfOlmTxLineAttenuation"                    => ".1.3.6.1.4.1.2544.1.11.2.4.3.15.1.2",
        "opticalIfOlmFarEndSigDegThres"                    => ".1.3.6.1.4.1.2544.1.11.2.4.3.15.1.3",
    );

    $var = ss_adva_op_li_att_walk($hostname, $snmp_community, $oids["opticalIfOlmRxLineAttenuation"], $snmp_version);
#    print_r ($var);   // this will help you see what is being returned, the structure
    if ($cmd == "index" || $cmd == "query") {
        // we run through all the interfaces where the MIB reports attenuation and extract their entityIndex
        // with that entityIndex we will find out all the values we want, i.e. ifAlias, ifAdminStatus, etc.
        for ($i=0;$i<(count($var));$i++) {
            preg_match("/[0-9]+$/", $var[$i]["oid"], $index);
            $entityIndex = $index[0];
            $ifDescr = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["ifDescr"] . ".$entityIndex", $snmp_version);

            if ($cmd == "index") {
                $result = $ifDescr;

            } elseif ($cmd == "query") {
                switch ($query_field) {

                case "index":
                    $result = $ifDescr . "!" . $ifDescr;
                    break;

                case "entityindex":
                    $value = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["ifDescr"] . ".$entityIndex", $snmp_version);
                    $result = $ifDescr . "!" . $entityIndex;
                    break;

                case "ifalias":
                    $value = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["ifAlias"] . ".$entityIndex", $snmp_version);
                    $result = $ifDescr . "!" . $value;
                    break;

                case "adminstatus":
                    $value = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["ifAdminStatus"] . ".$entityIndex", $snmp_version);
                    preg_match("/^[a-z]+/", $value, $trimmed_value);
                    $result = $ifDescr . "!" . $trimmed_value[0];
                    break;

                case "operstatus":
                    $value = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["ifOperStatus"] . ".$entityIndex", $snmp_version);
                    preg_match("/^[a-z]+/", $value, $trimmed_value);
                    $result = $ifDescr . "!" . $trimmed_value[0];
                    break;

                case "rxatt":
                    $value = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["opticalIfOlmRxLineAttenuation"] . ".$entityIndex", $snmp_version);
                    $result = $ifDescr . "!" . $value/10;
                    break;

                case "txatt":
                    $value = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["opticalIfOlmTxLineAttenuation"] . ".$entityIndex", $snmp_version);
                    $result = $ifDescr . "!" . $value/10;
                    break;

                case "farendhost":
                    $value = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["neighborDiscoveryDataFarEndTid"] . ".$entityIndex", $snmp_version);
                    $result = $ifDescr . "!" . $value;
                    break;

                case "farendport":
                    $value = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["neighborDiscoveryDataFarEndPortAid"] . ".$entityIndex", $snmp_version);
                    $result = $ifDescr . "!" . $value;
                    break;

                case "farendtransport":
                    $value = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["neighborDiscoveryDataFarEndTranLayerTermPointAid"] . ".$entityIndex", $snmp_version);
                    $result = $ifDescr . "!" . $value;
                    break;

                    default:
                        $result = "\n" . "Invalid query argument. Valid arguments are \"index\", \"entityindex\", \"ifalias\" " .
                                  "\"adminstatus\", \"operstatus\", \"rxatt\", \"txatt\", \"farendhost\", \"farendport\" and \"farendtransport\"" . "\n";
                        exit($result);
                    }
                }
                print $result . "\n";
#            }
        }
    } 
    elseif ($cmd == "get") {
        //This is the argument the user passes to the script, i.e. "SC-1-3NE"
        $interface = $query_index;

        // now we will find the entityIndex of the the interface (a string) the user passes to the script
        // run through all the interfaces where the MIB does report attenuation
        // in each pass of the loop, parse the entityIndex as the last number string in the oid
        // with that entityIndex, find out the value of the ifDescr oid and compare the two strings
        for ($i=0;$i<(count($var));$i++) {
            preg_match("/[0-9]+$/", $var[$i]["oid"], $index);
            $entityIndex = $index[0];
            $ifDescr = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["ifDescr"] . ".$entityIndex", $snmp_version);

            if (strstr($ifDescr, $interface)) {
        
                switch ($query_field) {

                    case "rx":
                        $result = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["opticalIfOlmRxLineAttenuation"] . ".$entityIndex", $snmp_version);
                        break;

                    case "tx":
                        $result = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["opticalIfOlmTxLineAttenuation"] . ".$entityIndex", $snmp_version);
                        break;

                    case "farendthreshold":
                        $result = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["opticalIfOlmFarEndSigDegThres"] . ".$entityIndex", $snmp_version);
                        break;

                    default:
                        $result = "\n" . "Invalid query argument. Valid arguments are \"rx\", \"tx\" and \"farendthreshold\"" . "\n";
                        exit($result);

                }
            return trim($result);
            }
        }
        print "No interface matches the name you have given. This is the list of all the interfaces: "  . "\n";
        for ($i=0;$i<(count($var));$i++) {
            $ifDescr = ss_adva_op_li_att_get($hostname, $snmp_community, $oids["ifDescr"] . ".$entityIndex", $snmp_version);
            print $ifDescr . "\n";
        }
        exit;
    }
    else {
        print "Invalid command. Valid commands are \"index\", \"query\" and \"get\"" . "\n";
    }
}

function ss_adva_op_li_att_walk ($hostname, $snmp_community, $oid, $snmp_version) {

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

function ss_adva_op_li_att_get ($hostname, $snmp_community, $oid, $snmp_version) {

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
