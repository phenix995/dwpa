<?php
// run this only via cli
if(php_sapi_name() !== 'cli') {
    die('Run this from cli');
}

require('conf.php');
require('db.php');
require('common.php');

function insert_rkg(& $mysql, & $ref) {
    if (count($ref) < 2) {
        return;
    }

    $bindvars = 'issi';
    $sql = 'INSERT IGNORE INTO rkg(net_id, algo, pass, n_state) VALUES'.implode(',', array_fill(0, (count($ref)-1)/strlen($bindvars), '('.implode(',',array_fill(0, strlen($bindvars), '?')).')'));
    $stmt = $mysql->stmt_init();
    $stmt->prepare($sql);

    $ref[0] = str_repeat($bindvars, (count($ref)-1)/strlen($bindvars));
    call_user_func_array(array($stmt, 'bind_param'), $ref);
    $stmt->execute();
    $stmt->close();
}

function update_nets_algo(& $mysql, & $stmt, $algo, $net_id) {
    if ($stmt == Null) {
        $stmt = $mysql->stmt_init();
        $stmt->prepare('UPDATE nets SET algo=? WHERE net_id=?');
    }

    $stmt->bind_param('si', $algo, $net_id);
    $stmt->execute();
}

$n_state0 = 0;
$n_state1 = 1;

$submit_stmt = Null;
$update_stmt = Null;

$regenerate_rkg_dict = False;

// fetch unchecked handshakes
$result = $mysql->query('SELECT net_id, hccapx, ssid, bssid, pass FROM nets WHERE algo IS NULL ORDER BY net_id LIMIT 100');
$nets = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

foreach ($nets as $netkey => $net) {
    $algo = '';
    $ref = array('');
    $candidates = array();
    $found = False;
    $cres = False;
    $res = '';
    $rc  = 0;
    $mac = long2mac($net['bssid']);
    exec(RKG." -q -k -m $mac -s ".escapeshellarg($net['ssid']), $res, $rc);

    if ($rc == 0) {
        // process rkg output
        foreach ($res as $line) {
            if (! ($candidates[] = explode(':', $line, 2)) || count(end($candidates)) != 2 ) {
                array_pop($candidates);
            } else {
                // fill reference array and verify if this handshake was cracked
                $key = key($candidates);
                $ref[] = & $nets[$netkey]['net_id'];
                $ref[] = & $candidates[$key][0];
                $ref[] = & $candidates[$key][1];
                if ($found) {
                    $ref[] = & $n_state0;
                } else {
                    // first verify if we've already cracked that handshake
                    if ($candidates[$key][1] == $net['pass'] or ($cres = check_key_hccapx($net['hccapx'], array($candidates[$key][1])))) {
                        $ref[] = & $n_state1;
                        $algo = $candidates[$key][0];
                        $found = True;
                    } else {
                        $ref[] = & $n_state0;
                    }
                }
            }
        }
        // update PSK found if cracked, submitter IP is 127.0.0.1
        if ($cres) {
            submit_by_net_id($mysql, $submit_stmt, $cres[0], $cres[3], $cres[1], $cres[2], 2130706433, $net['net_id']);
        }
        // fill rkg table with generated candidates
        insert_rkg($mysql, $ref);
    }
    // set algo name or just empty if not identified
    update_nets_algo($mysql, $update_stmt, $algo, $net['net_id']);
    if ($algo != '') {
        $regenerate_rkg_dict = True;
    }
}

// cleanup DB connections
if ($submit_stmt) {
    $submit_stmt->close();
}
if ($update_stmt) {
    $update_stmt->close();
}

// regenerate rkg.txt.gz if we have hit
if ($regenerate_rkg_dict) {
    //pull rkg cracked wordlist
    $stmt = $mysql->stmt_init();
    $stmt->prepare("SELECT DISTINCT pass FROM nets WHERE algo IS NOT NULL AND algo != ''");
    $stmt->execute();
    $stmt->bind_result($key);

    //write compressed rkg wordlist
    $wpakeys = tempnam(SHM, 'rkgkeys');
    chmod($wpakeys, 0644);
    $fd = gzopen($wpakeys, 'wb9');
    while ($stmt->fetch()) {
        gzwrite($fd, "$key\n");
    }
    $stmt->close();
    fflush($fd);
    gzclose($fd);

    rename($wpakeys, dirname(CRACKED).'/rkg.txt.gz');

    // update statistics
    $mysql->query("UPDATE stats SET pvalue = (SELECT count(net_id) FROM nets WHERE algo IS NOT NULL AND algo != '') WHERE pname='cracked_rkg'");
    $mysql->query("UPDATE stats SET pvalue = (SELECT count(DISTINCT bssid) FROM nets WHERE algo IS NOT NULL AND algo != '') WHERE pname='cracked_rkg_unc'");
}

$mysql->close();
exit(0);
?>