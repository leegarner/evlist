<?php
/**
 * Class to manage ticket types.
 * Ticket types are meant to represent the type of admission purchased,
 * such as "General Admission", "VIP Pass", "Balcony", "Orchestra", etc.
 * Each ticket type can also be set to be an Event Pass allowing admission
 * to all occurrences of an event.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2015-2019 Lee Garner <lee@leegarner.com>
 * @package     evlist
 * @version     v1.4.6
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Evlist;


/**
 * Class for ticket types.
 * @package evlist
 */
class TicketType
{
    /** Flag to indicate a new record.
     * @var boolean */
    private $isNew = true;

    /** Ticket type record ID.
     * @var integer */
    private $tt_id = 0;

    /** Flag indicating this is an event pass vs. one occurance.
     * @var boolean */
    private $event_pass = 0;

    /** Flag indicating this ticket type is enabled.
     * @var boolean */
    private $enabled = 1;

    /** Description of the ticket type.
     * @var string */
    private $dscp = '';


    /**
     * Constructor.
     * Create an empty ticket type object, or read an existing one.
     *
     * @param   integer $id     Ticket Type ID to read
     */
    public function __construct($id = 0)
    {
        $this->tt_id = (int)$id;
        if ($this->tt_id > 0) {
            $this->Read($this->tt_id);
        }
    }


    /**
     * Read an existing ticket type record into this object.
     *
     * @param   integer $id Optional type ID, $this->tt_id used if 0
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        if ($id > 0)
            $this->tt_id = $id;

        $sql = "SELECT * FROM {$_TABLES['evlist_tickettypes']}
            WHERE tt_id='{$this->tt_id}'";
        //echo $sql;
        $result = DB_query($sql);

        if (!$result || DB_numRows($result) == 0) {
            $this->tt_id = 0;
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->SetVars($row, true);
            return true;
        }
    }


    /**
     * Set the value of all variables from an array, either DB or a form.
     *
     * @param   array   $A      Array of fields
     */
    public function SetVars($A)
    {
        $this->tt_id = isset($A['tt_id']) ? (int)$A['tt_id'] : 0;
        $this->dscp = $A['dscp'];
        $this->event_pass = isset($A['event_pass']) && $A['event_pass'] ? 1 : 0;
        $this->enabled = isset($A['enabled']) && $A['enabled'] ? 1 : 0;
    }


    /**
     * Check if this ticket type is an event pass or for a single instance.
     *
     * @return  boolean     True if it is a pass, False for single
     */
    public function isEventPass()
    {
        return $this->event_pass;
    }


    /**
     * Get the description for this ticket type.
     *
     * @return  string      Description value
     */
    public function getDscp()
    {
        return $this->dscp;
    }


    /**
     * Provide the form to create or edit a ticket type.
     *
     * @return  string  HTML for editing form
     */
    public function Edit()
    {
        $T = new \Template(EVLIST_PI_PATH . '/templates');
        $T->set_file(array(
            'modify'    => 'ticketForm.thtml',
            'tips'      => 'tooltipster.thtml',
        ) );
        $T->set_var(array(
            'tt_id'             => $this->tt_id,
            'dscp'              => $this->dscp,
            'event_pass_chk'    => $this->event_pass == 1 ? EVCHECKED : '',
            'enabled_chk'       => $this->enabled == 1 ? EVCHECKED : '',
            'doc_url'           => EVLIST_getDocURL('tickettype'),
        ) );
        $T->parse('tooltipster_js', 'tips');
        $T->parse('output','modify');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Insert or update a ticket type.
     *
     * @param   array    $A  Array of data to save, typically from form
     */
    public function Save($A=array())
    {
        global $_TABLES, $_EV_CONF;

        if (is_array($A) && !empty($A))
            $this->SetVars($A);

        if ($this->tt_id > 0) {
            $this->isNew = false;
        } else {
            $this->isNew = true;
        }

        $fld_sql = "dscp = '" . DB_escapeString($this->dscp) ."',
            enabled = '{$this->enabled}',
            event_pass = '{$this->event_pass}'";

        if ($this->isNew) {
            $sql = "INSERT INTO {$_TABLES['evlist_tickettypes']} SET
                    $fld_sql";
        } else {
            $sql = "UPDATE {$_TABLES['evlist_tickettypes']} SET
                    $fld_sql
                    WHERE tt_id='{$this->tt_id}'";
        }

        //echo $sql;die;
        DB_query($sql, 1);
        if (!DB_error()) {
            if ($this->isNew) {
                $this->tt_id = DB_insertId();
            }
            return true;
        } else {
            COM_errorLog("Evist\\TicketType::Save SQL Error: $sql");
            return false;
        }
    }   // function Save()


    /**
     * Deletes the current ticket type.
     * First checks that the type isn't in use, and don't delete
     * the default ticket type (id == 1).
     *
     * @param   integer $id     ID of ticket type to delete
     */
    public static function Delete($id)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id < 2 || self::isUsed($id)) {
            // Can't delete the default type, or one that has been used.
            return false;
        } else {
            DB_delete($_TABLES['evlist_tickettypes'], 'tt_id', $id);
            return true;
        }
    }


    /**
     * Determine if the ticket type is in use by any tickets.
     *
     * @param   integer $id     Ticket type ID
     * @return  boolean     False if unused, True if used
     */
    public static function isUsed($id=0)
    {
        global $_TABLES;

        $id = (int)$id;
        $count = DB_count($_TABLES['evlist_tickets'], 'tic_type', $id);
        return $count == 0 ? false : true;
    }


    /**
     * Sets the field to the opposite of the specified value.
     *
     * @param   string  $fld        DB Field to toggle
     * @param   integer $oldvalue   Old (current) value of field
     * @param   integer $id         ID number of element to modify
     * @return          New value, or old value upon failure
     */
    public static function Toggle($fld, $oldvalue, $id)
    {
        global $_TABLES;

        // Validate $item - only toggle-able fields
        switch ($fld) {
        case 'event_pass':
        case 'enabled':
            break;
        default:
            return $oldval;
            break;
        }

        $id = (int)$id;
        if ($id == 0) return $oldvalue;
        $newvalue = $oldvalue == 0 ? 1 : 0;
        $sql = "UPDATE {$_TABLES['evlist_tickettypes']}
                SET $fld = $newvalue
                WHERE tt_id = '$id'";
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("Evlist\\TicketType::Toggle SQL Error: $sql");
            return $oldvalue;
        } else {
            return $newvalue;
        }
    }


    /**
     * Get all the ticket types into objects.
     *
     * @param   boolean $enabled    True to get only enabled, false for all
     * @return  array       Array of TicketType objects, indexed by ID
     */
    public static function GetTicketTypes($enabled = true)
    {
        global $_TABLES;

        static $types = array();
        $key = $enabled ? 1 : 0;

        if (!isset($types[$key])) {
            $types[$key] = array();
            $sql = "SELECT * FROM {$_TABLES['evlist_tickettypes']}";
            if ($enabled) $sql .= " WHERE enabled = 1";
            $res = DB_query($sql, 1);
            while ($A = DB_fetchArray($res, false)) {
                // create empty objects and use SetVars to save DB lookups
                $types[$key][$A['tt_id']] = new TicketType();
                $types[$key][$A['tt_id']]->SetVars($A);
            }
        }
        return $types[$key];
    }


    /**
     * Get the admin list of ticket types.
     *
     * @return  string      HTML for admin list
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $LANG_EVLIST, $LANG_EVLIST_HELP, $LANG_ADMIN;

        USES_lib_admin();
        EVLIST_setReturn('admintickettypes');

        $retval = '';

        $header_arr = array(
            array(
                'text' => $LANG_EVLIST['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_EVLIST['id'],
                'field' => 'id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_EVLIST['description'],
                'field' => 'dscp',
                'sort' => true,
            ),
            array(
                'text' => $LANG_EVLIST['enabled'],
                'field' => 'enabled',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_EVLIST['event_pass'] .
                    '&nbsp;' . Icon::getHTML(
                        'question-circle',
                        'tooltip',
                        array('title' => $LANG_EVLIST_HELP['event_pass'])
                    ),
                'field' => 'event_pass',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADMIN['delete'] .
                    '&nbsp;' . Icon::getHTML(
                        'question-circle',
                        'tooltip',
                        array('title' => $LANG_EVLIST_HELP['del_hdr1'])
                    ),
                'field' => 'delete',
                'sort' => false,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'tt_id',
            'direction' => 'ASC',
        );
        $text_arr = array(
            'has_menu'     => false,
            'has_extras'   => false,
            'form_url'     => EVLIST_ADMIN_URL . '/index.php?view=tickettypes',
            'help_url'     => ''
        );
        $sql = "SELECT * FROM {$_TABLES['evlist_tickettypes']} WHERE 1=1 ";
        $query_arr = array(
            'table' => 'evlist_tickettypes',
            'sql' => $sql,
            'query_fields' => array('dscp'),
        );

        $retval .= COM_createLink(
            $LANG_EVLIST['new_ticket_type'],
            EVLIST_ADMIN_URL . '/index.php?editticket=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );

        $retval .= ADMIN_list(
            'evlist_tickettype_admin',
            array(__CLASS__, 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr
        );
        return $retval;
    }


    /**
     * Return the display value for a ticket type field.
     *
     * @param   string  $fieldname  Name of the field
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Name-value pairs for all fields
     * @param   array   $icon_arr   Array of system icons
     * @return  string      HTML to display for the field
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ADMIN, $LANG_EVLIST, $_EV_CONF;

        $retval = '';
        switch($fieldname) {
        case 'edit':
            $retval = COM_createLInk(
                Icon::getHTML('edit'),
                EVLIST_ADMIN_URL . '/index.php?editticket=' . $A['tt_id'],
                array(
                    'title' => $LANG_ADMIN['edit'],
                )
            );
            break;

        case 'enabled':
        case 'event_pass':
            if ($fieldvalue == '1') {
                $switch = EVCHECKED;
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval = "<input type=\"checkbox\" $switch value=\"1\"
                name=\"cat_check\"
                tt_id=\"tog{$fieldname}{$A['tt_id']}\"
                onclick='EVLIST_toggle(this,\"{$A['tt_id']}\",\"{$fieldname}\",".
                "\"tickettype\",\"".EVLIST_ADMIN_URL."\");' />".LB;
            break;

        case 'delete':
            if (!self::isUsed($A['tt_id'])) {
                $retval = COM_createLink(
                    Icon::getHTML('delete'),
                    EVLIST_ADMIN_URL. '/index.php?deltickettype=' . $A['tt_id'],
                    array(
                        'class' => 'tooltip',
                        'onclick'=>"return confirm('{$LANG_EVLIST['conf_del_item']}');",
                        'title' => $LANG_ADMIN['delete'],
                    )
                );
            }
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}   // class TicketType

?>
