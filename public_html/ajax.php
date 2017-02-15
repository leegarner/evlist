<?php
/**
*   Common AJAX functions.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011-2017 Lee Garner <lee@leegarner.com>
*   @package    evlist
*   @version    1.4.2
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/** Include required glFusion common functions */
require_once dirname(__FILE__) . '/../lib-common.php';

$content = '';

switch ($_GET['action']) {
case 'getloc':
    // Create an array to return so the javascript won't choke.
    $B = array(
        'id'        => '',
        'title'     => '',
        'street'    => '',
        'city'      => '',
        'province'  => '',
        'country'   => '',
        'postal'    => '',
        'lat'       => '',
        'lng'       => '',
    );

    if ($_EV_CONF['use_locator'] && function_exists('GEO_getInfo')) {

        $id = isset($_GET['id']) && !empty($_GET['id']) ?
                    COM_sanitizeID($_GET['id']) : '';
        $A = GEO_getInfo($id);
        if (!$A) {
            $A = $B;        // Use the default, empty array
            $A['id'] = $id;
        }
        // Now form the XML return
        foreach ($B as $name=>$value) {
            if (isset($A[$name])) {
                $value = $A[$name];
            }
            $content .= "<{$name}>" .
                htmlspecialchars($value) .
                "</{$name}>\n";
        }
    }
    break;

case 'addreminder':
    $rp_id = (int)$_GET['rp_id'];
    $status = array();
    USES_evlist_class_repeat();
    $Ev = new evRepeat($rp_id);
    if (!COM_isAnonUser() && $Ev->rp_id > 0 && $Ev->Event->hasAccess(2)) {
        $username = COM_getDisplayName($_GET['uid']);
        $sql = "INSERT INTO {$_TABLES['evlist_remlookup']}
            (eid, rp_id, uid, name, email, days_notice)
        VALUES (
            '{$Ev->Event->id}',
            '{$Ev->rp_id}',
            '" . (int)$_USER['uid']. "',
            '" . DB_escapeString($username) . "',
            '" . DB_escapeString($_GET['rem_email']) . "',
            '" . (int)$_GET['notice']. "')";
        //COM_errorLog($sql);
        DB_query($sql, 1);
        if (!DB_error()) {
            $status['reminder_set'] = true;
        } else {
            $status['reminder_set'] = false;
        }
    }
    echo json_encode($status);
    exit;
    break;

case 'delreminder':
    $rp_id = (int)$_GET['rp_id'];
    $uid = (int)$_USER['uid'];
    if (!COM_isAnonUser() && $rp_id > 0) {
        USES_evlist_class_repeat();
        $Ev = new evRepeat($rp_id);
        DB_delete($_TABLES['evlist_remlookup'],
            array('eid', 'uid', 'rp_id'),
            array($Ev->Event->id, $uid, $rp_id) );
    }
    echo json_encode(array('reminder_set' => false));
    exit;
    break;

case 'getCalDay':
    $month = (int)$_GET['month'];
    $day = (int)$_GET['day'];
    $year = (int)$_GET['year'];
    $cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
    $cal = isset($_GET['cal']) ? (int)$_GET['cal'] : 0;
    $opt = isset($_GET['opt']) ? $_GET['opt'] : '';
    USES_evlist_class_view();
    $V = new evView_day($year, $month, $day, $cat, $cal, $opt);
    echo $V->Content();
    exit;
    break;

case 'getCalWeek':
    $month = (int)$_GET['month'];
    $day = (int)$_GET['day'];
    $year = (int)$_GET['year'];
    $cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
    $cal = isset($_GET['cal']) ? (int)$_GET['cal'] : 0;
    $opt = isset($_GET['opt']) ? $_GET['opt'] : '';
    USES_evlist_class_view();
    $V = new evView_week($year, $month, $day, $cat, $cal, $opt);
    echo $V->Content();
    exit;
    break;

case 'getCalMonth':
    $month = (int)$_GET['month'];
    $year = (int)$_GET['year'];
    $cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
    $cal = isset($_GET['cal']) ? (int)$_GET['cal'] : 0;
    $opt = isset($_GET['opt']) ? $_GET['opt'] : '';
    USES_evlist_class_view();
    $V = new evView_month($year, $month, 1, $cat, $cal, $opt);
    echo $V->Content();
    exit;
    break;

case 'getCalYear':
    $year = (int)$_GET['year'];
    $cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
    $cal = isset($_GET['cal']) ? (int)$_GET['cal'] : 0;
    $opt = isset($_GET['opt']) ? $_GET['opt'] : '';
    USES_evlist_class_view();
    $V = new evView_year($year, 1, 1, $cat, $cal, $opt);
    echo $V->Content();
    exit;
    break;

}

if (!empty($content)) {
    $content = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n" .
        '<location>' . $content . "</location>\n";
    header('Content-Type: text/xml');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo $content;
}
?>
