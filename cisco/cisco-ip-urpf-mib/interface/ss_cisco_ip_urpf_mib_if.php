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
    print call_user_func_array("ss_cisco_ip_urpf_if", $_SERVER["argv"]);
}

# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_cisco_urpf_table.php <host_or_ip> public 2 index
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_cisco_urpf_table.php <host_or_ip> public 2 query index|addressfamily
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_cisco_urpf_table.php <host_or_ip> public 2 get drops|droprate <index>

function ss_cisco_ip_urpf_if($hostname, $snmp_community, $snmp_version, $cmd, $query_field = "", $query_index = "") {

    $oids = array (
        "ifDescr"                     => ".1.3.6.1.2.1.2.2.1.2",
        "ifName"                      => ".1.3.6.1.2.1.31.1.1.1.1",
        "ifAlias"                     => ".1.3.6.1.2.1.31.1.1.1.18",
        "ifAdminStatus"               => ".1.3.6.1.2.1.2.2.1.7",
        "ifOperStatus"                => ".1.3.6.1.2.1.2.2.1.8",
        "cipUrpfIfDrops"              => ".1.3.6.1.4.1.9.9.451.1.2.2.1.2",
        "cipUrpfIfSuppressedDrops"    => ".1.3.6.1.4.1.9.9.451.1.2.2.1.3",
        "cipUrpfIfDropRate"           => ".1.3.6.1.4.1.9.9.451.1.2.2.1.4",
        "cipUrpfIfDiscontinuityTime"  => ".1.3.6.1.4.1.9.9.451.1.2.2.1.5",
    );

    $var = ss_cisco_ip_urpf_if_walk($hostname, $snmp_community, $oids["cipUrpfIfDrops"], $snmp_version);
#    print_r ($var);
    if ($cmd == "index" || $cmd == "query") {
        for ($i=0;$i<(count($var));$i++) {
            preg_match("/([0-9]+).([0-9]+)$/", $var[$i]["oid"], $matches);
            $ifPlusIpIndex = $matches[0];
            $ifIndex = $matches[1];
            $ipVersion = $matches[2];

            if ($cmd == "index") {
                $result = $ifPlusIpIndex;

            } elseif ($cmd == "query") {
                switch ($query_field) {

                case "index":
                    $result = $ifPlusIpIndex . "!" . $ifPlusIpIndex;
                    break;

                case "name":
                    $value = ss_cisco_ip_urpf_if_get($hostname, $snmp_community, $oids["ifName"] . ".$ifIndex", $snmp_version);
                    $result = $ifPlusIpIndex . "!" . $value;
                    break;

                case "descr":
                    $value = ss_cisco_ip_urpf_if_get($hostname, $snmp_community, $oids["ifDescr"] . ".$ifIndex", $snmp_version);
                    $result = $ifPlusIpIndex . "!" . $value;
                    break;

                case "alias":
                    $value = ss_cisco_ip_urpf_if_get($hostname, $snmp_community, $oids["ifAlias"] . ".$ifIndex", $snmp_version);
                    $result = $ifPlusIpIndex . "!" . $value;
                    break;

                case "addressfamily":
                    if ($ipVersion = 1) {
                        $value = "IPv4";
                    }
                    elseif ($ipVersion = 2) {
                        $value = "IPv6";
                    }
                    $result = $ifPlusIpIndex . "!" . $value;
                    break;

                case "adminstatus":
                    $value = ss_cisco_ip_urpf_if_get($hostname, $snmp_community, $oids["ifAdminStatus"] . ".$ifIndex", $snmp_version);
                    $result = $ifPlusIpIndex . "!" . $value;
                    break;

                case "operstatus":
                    $value = ss_cisco_ip_urpf_if_get($hostname, $snmp_community, $oids["ifOperStatus"] . ".$ifIndex", $snmp_version);
                    $result = $ifPlusIpIndex . "!" . $value;
                    break;

                default:
                    $result = "\n" . "Invalid query argument. Valid arguments are \"index\", \"name\", \"descr\", \"alias\" " . 
                              "\"adressfamily\", \"adminstatus\" and \"operstatus\" " .  "\n";
                    exit($result);
                }
            }
            print $result . "\n";
        }
    } 
    elseif ($cmd == "get") {
        $ifPlusIpIndex = $query_index;

        switch ($query_field) {

        case "drops":
            $result = ss_cisco_ip_urpf_if_get($hostname, $snmp_community, $oids["cipUrpfIfDrops"] . ".$ifPlusIpIndex", $snmp_version);
            break;

        case "dropssuppressed":
            $result = ss_cisco_ip_urpf_if_get($hostname, $snmp_community, $oids["cipUrpfIfSuppressedDrops"] . ".$ifPlusIpIndex", $snmp_version);
            break;

        case "droprate":
            $result = ss_cisco_ip_urpf_if_get($hostname, $snmp_community, $oids["cipUrpfIfDropRate"] . ".$ifPlusIpIndex", $snmp_version);
            break;

        default:
            $result = "\n" . "Invalid query argument. Valid arguments are \"drops\", \"dropssupressed\" and \"droprate\"" . "\n";
            exit($result);

        }
        return trim($result);
    }
    else {
        print "Invalid command. Valid commands are \"index\", \"query\" and \"get\"" . "\n";
    }
}

function ss_cisco_ip_urpf_if_walk ($hostname, $snmp_community, $oid, $snmp_version) {

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

function ss_cisco_ip_urpf_if_get ($hostname, $snmp_community, $oid, $snmp_version) {

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
