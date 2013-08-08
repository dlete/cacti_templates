<?php
# vim:ts=2:set noai:
/*
 +-------------------------------------------------------------------------+
 | name    : ss_cisco_ip_urpf_device                                       |
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
    print call_user_func_array("ss_cisco_ip_urpf_device", $_SERVER["argv"]);
}

# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_cisco_urpf_device.php <host_or_ip> public 2 index
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_cisco_urpf_device.php <host_or_ip> public 2 query index|addressfamily
# /usr/bin/php -q /usr/share/cacti/site/scripts/ss_cisco_urpf_device.php <host_or_ip> public 2 get drops|droprate <index>

function ss_cisco_ip_urpf_device($hostname, $snmp_community, $snmp_version, $cmd, $query_field = "", $query_index = "") {

    $oids = array (
        "ifDescr"                     => ".1.3.6.1.2.1.2.2.1.2",
        "ifAlias"                     => ".1.3.6.1.2.1.31.1.1.1.18",
        "ifAdminStatus"               => ".1.3.6.1.2.1.2.2.1.7",
        "ifOperStatus"                => ".1.3.6.1.2.1.2.2.1.8",
        "cipUrpfDrops"                => ".1.3.6.1.4.1.9.9.451.1.2.1.1.2",
        "cipUrpfDropRate"             => ".1.3.6.1.4.1.9.9.451.1.2.1.1.3",
    );

    $var = ss_cisco_ip_urpf_device_walk($hostname, $snmp_community, $oids["cipUrpfDrops"], $snmp_version);
#    print_r ($var);
    if ($cmd == "index" || $cmd == "query") {
        for ($i=0;$i<(count($var));$i++) {
            preg_match("/[0-9]+$/", $var[$i]["oid"], $index);
            $cipUrpfIpVersion = $index[0];

            if ($cmd == "index") {
                $result = $cipUrpfIpVersion;

            } elseif ($cmd == "query") {
                switch ($query_field) {

                case "index":
                    $result = $cipUrpfIpVersion . "!" . $cipUrpfIpVersion;
                    break;

                case "addressfamily":
                    if ($cipUrpfIpVersion = 1) {
                        $value = "IPv4";
                    }
                    elseif ($cipUrpfIpVersion = 2) {
                        $value = "IPv6";
                    }
                    $result = $cipUrpfIpVersion . "!" . $value;
                    break;

                    default:
                        $result = "\n" . "Invalid query argument. Valid arguments are \"index\" and \"addressfamily\" " . "\n";
                        exit($result);
                    }
                }
                print $result . "\n";
#            }
        }
    } 
    elseif ($cmd == "get") {
        $cipUrpfIpVersion = $query_index;

        switch ($query_field) {

        case "drops":
            $result = ss_cisco_ip_urpf_device_get($hostname, $snmp_community, $oids["cipUrpfDrops"] . ".$cipUrpfIpVersion", $snmp_version);
            break;

        case "droprate":
            $result = ss_cisco_ip_urpf_device_get($hostname, $snmp_community, $oids["cipUrpfDropRate"] . ".$cipUrpfIpVersion", $snmp_version);
#            settype($result, "integer");
#            echo gettype($result);
            break;

        default:
            $result = "\n" . "Invalid query argument. Valid arguments are \"drops\" and \"droprate\"" . "\n";
            exit($result);

        }
        settype($result, "integer");
#        echo gettype($result);
        return $result;
    }
    else {
        print "Invalid command. Valid commands are \"index\", \"query\" and \"get\"" . "\n";
    }
}

function ss_cisco_ip_urpf_device_walk ($hostname, $snmp_community, $oid, $snmp_version) {

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

function ss_cisco_ip_urpf_device_get ($hostname, $snmp_community, $oid, $snmp_version) {

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
