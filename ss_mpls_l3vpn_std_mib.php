<?php
# vim:ts=2:set noai:
/*
 +-------------------------------------------------------------------------+
 | name    : ss_cisco_ip_urpf_if                                           |
 | version : 0.1.1                                                         |
 | date    : 20130731                                                      |
 +-------------------------------------------------------------------------+
*/
/*
YOU HAVE TO LOOK INTO REINDEX
can you simplyfy $snmp_version?
can you use php snmp instead of cacti functions?
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
    print call_user_func_array("ss_mpls_l3vpn_std_mib", $_SERVER["argv"]);
}

# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_mpls_l3vpn_std_mib.php <host_or_ip> public 2 index
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_mpls_l3vpn_std_mib.php <host_or_ip> public 2 query index|rd|descr|oper_status
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_mpls_l3vpn_std_mib.php <host_or_ip> public 2 get routesAdded|routesDeleted|routesCurrent|routesDropped <index>

function ss_mpls_l3vpn_std_mib($hostname, $snmp_community, $snmp_version, $cmd, $query_field = "", $query_index = "") {

    $oids = array (
        "mplsL3VpnVrfDescription"          => "1.3.6.1.2.1.10.166.11.1.2.2.1.3",
        "mplsL3VpnVrfRD"                   => "1.3.6.1.2.1.10.166.11.1.2.2.1.4",
        "mplsL3VpnVrfOperStatus"           => "1.3.6.1.2.1.10.166.11.1.2.2.1.6",
        "mplsL3VpnVrfActiveInterfaces"     => "1.3.6.1.2.1.10.166.11.1.2.2.1.7",
        "mplsL3VpnVrfAssociatedInterfaces" => "1.3.6.1.2.1.10.166.11.1.2.2.1.8",
        "mplsL3VpnVrfConfMidRteThresh"     => "1.3.6.1.2.1.10.166.11.1.2.2.1.9",
        "mplsL3VpnVrfConfHighRteThresh"    => "1.3.6.1.2.1.10.166.11.1.2.2.1.10",
        "mplsL3VpnVrfConfMaxRoutes"        => "1.3.6.1.2.1.10.166.11.1.2.2.1.11",
        "mplsL3VpnVrfConfAdminStatus"      => "1.3.6.1.2.1.10.166.11.1.2.2.1.14",

        "mplsL3VpnVrfPerfRoutesAdded"      => "1.3.6.1.2.1.10.166.11.1.3.1.1.1",
        "mplsL3VpnVrfPerfRoutesDeleted"    => "1.3.6.1.2.1.10.166.11.1.3.1.1.2",
        "mplsL3VpnVrfPerfCurrNumRoutes"    => "1.3.6.1.2.1.10.166.11.1.3.1.1.3",
        "mplsL3VpnVrfPerfRoutesDropped"    => "1.3.6.1.2.1.10.166.11.1.3.1.1.4",
    );

# to extract the indexes: walk any OID (I choose "mplsL3VpnVrfRD" like any other one) and parse the last part of the OID
    $var = ss_mpls_l3vpn_std_mib_walk($hostname, $snmp_community, $oids["mplsL3VpnVrfRD"], $snmp_version);
#    print_r ($var);
    if ($cmd == "index" || $cmd == "query") {
        # go through each line of the walk
        for ($i=0;$i<(count($var));$i++) {
            # get anything trailing the OID string we are walking
            preg_match("/1\.3\.6\.1\.2\.1\.10\.166\.11\.1\.2\.2\.1\.4\.(.*)$/", $var[$i]["oid"], $matches);
            # this is the whole OID: the base OID we are walking + the index
            $wholeOid = $matches[0];

            #this is the index we want
            $compIndex = $matches[1];

            if ($cmd == "index") {
                $result = $compIndex;

            } elseif ($cmd == "query") {
                switch ($query_field) {

                case "index":
                    $result = $compIndex . "!" . $compIndex;
                    break;

                case "rd":
                    $value = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfRD"] . ".$compIndex", $snmp_version);
                    $result = $compIndex . "!" . $value;
                    break;

                case "descr":
                    $value = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfDescription"] . ".$compIndex", $snmp_version);
                    $result = $compIndex . "!" . $value;
                    break;

                case "operstatus":
                    $value = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfOperStatus"] . ".$compIndex", $snmp_version);
                    if ($value = 1) {
                        $value = "Up";
                    }
                    elseif ($value = 2) {
                        $value = "Down";
                    }
                    $result = $compIndex . "!" . $value;
                    break;

                case "adminstatus":
                    $value = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfConfAdminStatus"] . ".$compIndex", $snmp_version);
                    if ($value = 1) {
                        $value = "Up";
                    }
                    elseif ($value = 2) {
                        $value = "Down";
                    }
                    elseif ($value = 3) {
                        $value = "Testing";
                    }
                    $result = $compIndex . "!" . $value;
                    break;

                case "activeifs":
                    $value = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfActiveInterfaces"] . ".$compIndex", $snmp_version);
                    $result = $compIndex . "!" . $value;
                    break;

                case "associatedifs":
                    $value = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfAssociatedInterfaces"] . ".$compIndex", $snmp_version);
                    $result = $compIndex . "!" . $value;
                    break;

                case "midThreshold":
                    $value = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfConfMidRouteThreshold"] . ".$compIndex", $snmp_version);
                    $result = $compIndex . "!" . $value;
                    break;

                case "highThreshold":
                    $value = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfConfHighRouteThreshold"] . ".$compIndex", $snmp_version);
                    $result = $compIndex . "!" . $value;
                    break;

                case "routeMax":
                    $value = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfConfMaxRoutes"] . ".$compIndex", $snmp_version);
                    $result = $compIndex . "!" . $value;
                    break;

                case "routesCurrent":
                    $value = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfPerfCurrNumRoutes"] . ".$compIndex", $snmp_version);
                    $result = $compIndex . "!" . $value;
                    break;

                default:
                    $result = "\n" . "Invalid query argument. Valid arguments are \"index\", \"rd\", \"descr\", \"operstatus\" " .
                              "\"adminstatus\", \"activeifs\" and \"associatedifs\" " .  "\n";
                    exit($result);
                }
            }
            print $result . "\n";
        }
    }
    elseif ($cmd == "get") {
        $compIndex = $query_index;

        switch ($query_field) {

        case "routesAdded":
            $result = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfPerfRoutesAdded"] . ".$query_index", $snmp_version);
            break;

        case "routesDeleted":
            $result = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfPerfRoutesDeleted"] . ".$query_index", $snmp_version);
            break;

        case "routesCurrent":
            $result = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfPerfCurrNumRoutes"] . ".$query_index", $snmp_version);
            break;

        case "routesDropped":
            $result = ss_mpls_l3vpn_std_mib_get($hostname, $snmp_community, $oids["mplsL3VpnVrfPerfRoutesDropped"] . ".$query_index", $snmp_version);
            break;


        default:
            $result = "\n" . "Invalid query argument. Valid arguments are \"routesAdded\", \"routesDeleted\", \"routesCurrent\" and \"routesDropped\"" . "\n";
            exit($result);

        }
        return trim($result);
    }
    else {
        print "Invalid command. Valid commands are \"index\", \"query\" and \"get\"" . "\n";
    }
}

function ss_mpls_l3vpn_std_mib_walk ($hostname, $snmp_community, $oid, $snmp_version) {

    $snmp_auth_username         = "";
    $snmp_auth_password         = "";
    $snmp_auth_protocol         = "";
    $snmp_priv_passphrase       = "";
    $snmp_priv_protocol         = "";
    $snmp_context               = "";^M
    $snmp_port                  = 161;                              # snmp port
    $snmp_timeout               = 500;                              # snmp timeout
    $snmp_retries               = read_config_option("snmp_retries");
    $environ                    = SNMP_POLLER;

    $walk = cacti_snmp_walk($hostname, $snmp_community, $oid, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $environ);

    return $walk;
}

function ss_mpls_l3vpn_std_mib_get ($hostname, $snmp_community, $oid, $snmp_version) {

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
