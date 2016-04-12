<?php
/**
 *  Class to manage event repeats or single instances for the EvList plugin
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2011 Lee Garner <lee@leegarner.com>
 *  @package    evlist
 *  @version    1.3.0
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 *  @filesource
 */

USES_evlist_class_event();
USES_evlist_class_detail();

/**
 *  Class for event
 *  @package evlist
 */
class evRepeat
{
    /** Property fields.  Accessed via Set() and Get()
    *   @var array */
    var $properties = array();

    var $Event;
    var $Detail;
    var $isAdmin;   // indicates if the current user can admin this event

    /**
     *  Constructor.
     *  Reads in the specified event repeat, if $rp_id is set.  
     *  If $id is zero, then a new entry is being created.
     *
     *  @param integer $id Optional type ID
     */
    public function __construct($rp_id=0)
    {
        global $_USER;

        if ($rp_id == 0) {
            $this->rp_id = 0;
            $this->ev_id = '';
            $this->det_id = 0;
            $this->date_start = '';
            $this->date_end = '';
            $this->time_start1 = '';
            $this->time_end1 = ''; 
            $this->time_start2 = '';
            $this->time_end2 = ''; 
        } else {
            $this->rp_id = $rp_id;
            if (!$this->Read()) {
                $this->rp_id = '';
            } else {
                // This gets used a few places, so save on function calls.
                $this->isAdmin = $this->Event->hasAccess(3);
            }
        }

        // this gets used a few times, might as well sanitize it here
        $this->uid = (int)$_USER['uid'];

    }


    /**
    *   Set a property's value.
    *
    *   @param  string  $var    Name of property to set.
    *   @param  mixed   $value  New value for property.
    */
    public function __set($var, $value='')
    {
        switch ($var) {
        case 'ev_id':
            $this->properties[$var] = COM_sanitizeId($value, false);
            break;

        case 'rp_id':
        case 'det_id':
        case 'uid':
            $this->properties[$var] = (int)$value;
            break;

        case 'date_start':
        case 'date_end':
            // String values
            $this->properties[$var] = trim(COM_checkHTML($value));
            break;

        case 'time_start1':
        case 'time_end1':
        case 'time_start2':
        case 'time_end2':
            $this->properties[$var] = empty($value) ? '00:00:00' : trim($value);
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
    *   Get the value of a property.
    *
    *   @param  string  $var    Name of property to retrieve.
    *   @param  boolean $db     True if string values should be escaped for DB.
    *   @return mixed           Value of property, NULL if undefined.
    */
    public function __get($var)
    {
        if (array_key_exists($var, $this->properties)) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
     *  Sets all variables to the matching values from $rows.
     *
     *  @param  array   $row        Array of values, from DB or $_POST
     *  @param  boolean $fromDB     True if read from DB, false if from $_POST
     */
    public function SetVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;

        $fields = array('ev_id', 'det_id', 
                'date_start', 'date_end',
                'time_start1', 'time_end1', 
                'time_start2', 'time_end2', 
                );
        foreach ($fields as $field) {
            if (isset($row['rp_' . $field])) {
                $this->$field = $row['rp_' . $field];
            }
        }
        // Join or split the date values as needed
        if ($fromDB) {      // Read from the database

            // dates are YYYY-MM-DD
            list($startyear, $startmonth, $startday) = split('-', $row['rp_start_date']);
            list($endyear, $endmonth, $endday) = split('-', $row['rp_end_date']);
        } else {            // Coming from the form

            $this->date_start = $row['date_start1'];
            $this->date_end = $row['date_end1'];

            // Ignore time entries & set to all day if flagged as such
            if (isset($row['allday']) && $row['allday'] == '1') {
                $this->time_start1 = '00:00:00';
                $this->time_end1 = '23:59:59';
                $this->time_start2 = NULL;
                $this->time_end2 = NULL;
            } else {
                $tmp = EVLIST_12to24($row['starthour1'], $row['start1_ampm']);
                $this->time_start1 = sprintf('%02d:%02d:00', 
                    $tmp, $row['startminute1']);
                $tmp = EVLIST_12to24($row['endhour1'], $row['end1_ampm']);
                $this->time_end1 = sprintf('%02d:%02d:00', 
                    $tmp, $row['endminute1']);
                if (isset($row['split']) && $row['split'] == '1') {
                    $tmp = EVLIST_12to24($row['starthour2'], $row['start2_ampm']);
                    $this->time_start2 = sprintf('%02d:%02d:00', 
                        $tmp, $row['startminute1']);
                    $tmp = EVLIST_12to24($row['endhour2'], $row['end2_ampm']);
                    $this->time_end2 = sprintf('%02d:%02d:00', 
                        $tmp, $row['endminute2']);
                } else {
                    $this->time_start2 = NULL;
                    $this->time_end2   = NULL;
                }
            }
        }
    }


    /**
     *  Read a specific record and populate the local values.
     *
     *  @param  integer $id Optional ID.  Current ID is used if zero.
     *  @return boolean     True if a record was read, False on failure.
     */
    public function Read($rp_id = 0)
    {
        global $_TABLES;

        if ($rp_id != 0) {
            $this->rp_id = $rp_id;
        }

        $sql = "SELECT *
                FROM {$_TABLES['evlist_repeat']}
                WHERE rp_id='{$this->rp_id}'";
        $result = DB_query($sql);
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $A = DB_fetchArray($result, false);
            $this->ev_id        = $A['rp_ev_id'];
            $this->det_id       = $A['rp_det_id'];
            $this->date_start   = $A['rp_date_start'];
            $this->date_end     = $A['rp_date_end'];
            $this->time_start1  = $A['rp_time_start1'];
            $this->time_end1    = $A['rp_time_end1'];
            $this->time_start2  = $A['rp_time_start2'];
            $this->time_end2    = $A['rp_time_end2'];

            $this->Event = new evEvent($this->ev_id, $this->det_id);
            return true;
        }
    }


    /**
    *   Edit a single repeat.
    *
    *   @see    evEvent::Edit()
    *   @param  integer $rp_id      ID of instance to edit
    *   @param  string  $edit_type  Type of repeat (repeat or futurerepeat)
    *   @return string      Editing form
    */
    public function Edit($rp_id = 0, $edit_type='repeat')
    {
        if ($rp_id > 0) {
            $this->Read($rp_id);
        }
        return $this->Event->Edit($this->ev_id, $this->rp_id, 'save' . $edit_type);
    }


    /**
    *   Save this occurance info to the database.
    *   Only updates can be performed since the original record must have
    *   been created by the evEvent class.
    *
    *   The incoming $A parameter will contain all the event info, so it can
    *   be used to populate both the Detail and Repeat records.
    *
    *   @param  array   $A      Optional array of values from $_POST
    *   @return boolean         True if no errors, False otherwise
    */
    public function Save($A = '')
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->SetVars($A);
        }

        if ($this->rp_id > 0) {
            // Update this repeat's detail record if there is one.  Otherwise
            // create a new one.
            if ($this->det_id != $this->Event->det_id) {
                $D = new evDetail($this->det_id);
            } else {
                $D = new evDetail();
            }
            $D->SetVars($A);
            $D->ev_id = $this->ev_id;
            $this->det_id = $D->Save();
            $sql = "UPDATE {$_TABLES['evlist_repeat']} SET 
                rp_date_start = '" . DB_escapeString($this->date_start) . "',
                rp_date_end= '" . DB_escapeString($this->date_end) . "',
                rp_time_start1 = '" . DB_escapeString($this->time_start1) . "',
                rp_time_end1 = '" . DB_escapeString($this->time_end1) . "',
                rp_time_start2 = '" . DB_escapeString($this->time_start2) . "',
                rp_time_end2 = '" . DB_escapeString($this->time_end2) . "',
                rp_det_id='" . (int)$this->det_id . "'
            WHERE rp_id='{$this->rp_id}'";
            //echo $sql;die;
            DB_query($sql);
        }

    }


    /**
     *  Delete the current instance from the database
     */
    public function Delete()
    {
        global $_TABLES;

        if ($this->rp_id < 1) {
            return false;
        }

        DB_delete($_TABLES['evlist_repeat'], 'rp_id', $this->rp_id);

        // If we have our own detail record, then delete it also
        if ($this->det_id != $this->Event->det_id)
            $this->Event->Detail->Delete();

        return true;
    }


    /**
    *   Display the detail page for the event occurrence.
    *
    *   @param  integer $rp_id  ID of the repeat to display
    *   @param  string  $query  Optional query string, for highlighting
    *   @param  string  $tpl    Optional template filename, e.g. 'event_print'
    *   @return string      HTML for the page.
    */
    public function Detail($rp_id=0, $query='', $tpl='')
    {
        global $_CONF, $_USER, $_EV_CONF, $_TABLES, $LANG_EVLIST, $LANG_WEEK;

        $retval = '';

        $url = '';
        $location = '';
        $street = '';
        $city = '';
        $province = '';
        $country = '';
        $postal = '';
        $name = '';
        $email = '';
        $phone = '';

        if ($rp_id != 0) {
            $this->Read($rp_id);
        }

        if ($this->rp_id == 0) {
            return EVLIST_alertMessage($LANG_EVLIST['access_denied']);
        }

        //update hit count
        evlist_hit($this->ev_id);

        if (empty($tpl) || 
            !file_exists(EVLIST_PI_PATH . '/templates/' . $tpl . '.thtml')) {
            // use the default template if none specified or available
            $tpl = 'event';
        }
        $T = new Template(EVLIST_PI_PATH . '/templates/');
        $T->set_file(array(
                'event' => $tpl . '.thtml',
                //'editlinks' => 'edit_links.thtml',
                'datetime' => 'date_time.thtml',
                'address' => 'address.thtml',
                'contact' => 'contact.thtml',
        ) );

        // If plain text then replace newlines with <br> tags
        if ($this->Event->postmode == '1') {       //plaintext
            $this->Event->Detail->summary = nl2br($this->Event->Detail->summary);
            $this->Event->Detail->full_description = nl2br($this->Event->Detail->full_description);
            $this->Event->Detail->location = nl2br($this->Event->Detail->location);
        }
        $title = $this->Event->Detail->title;
        if ($this->postmode != 'plaintext') {
            $summary = PLG_replaceTags($this->Event->Detail->summary);
            $fulldescription = PLG_replaceTags($this->Event->Detail->full_description);
            $location = $this->Event->Detail->location != '' ?
                PLG_replaceTags($this->Event->Detail->location) : '';
        } else {
            $summary = $this->Event->Detail->summary;
            $fulldescription = $this->Event->Detail->full_description;
            $location = $this->Event->Detail->location;
        }
        if ($query != '') {
            $title = COM_highlightQuery($title, $query);
            if (!empty($summary)) {
                $summary  = COM_highlightQuery($summary, $query);
            }
            if (!empty($fulldescription)) {
                $fulldescription = COM_highlightQuery($fulldescription, $query);
            }
            if (!empty($location)) {
                $location = COM_highlightQuery($location, $query);
            }
        }
        $date_start = EVLIST_formattedDate($this->date_start);
        if ($this->date_start != $this->date_end) {
            $date_end = EVLIST_formattedDate($this->date_end);
        } else {
            $date_end = '';
        }

        if ($this->Event->allday == '1') {
            $allday = '<br />' . $LANG_EVLIST['all_day_event'];
        } else {
            $allday = '';
            if ($this->time_start1 != '') {
                $time_start1 = EVLIST_formattedTime($this->time_start1);
                $time_end1 =  EVLIST_formattedTime($this->time_end1);
            } else {
                $time_start1 = '';
                $time_end1 = '';
            }
            //$time_period = $time_start . $time_end;
            if ($this->Event->split == '1') {
                $time_start2 = EVLIST_formattedTime($this->time_start2);
                $time_end2 = EVLIST_formattedTime($this->time_end2);
            } 
        }

        $url = $this->Event->Detail->url;
        $street = $this->Event->Detail->street;
        $city = $this->Event->Detail->city;
        $province = $this->Event->Detail->province;
        $postal = $this->Event->Detail->postal;
        $country = $this->Event->Detail->country;

        // Now get the text description of the recurring interval, if any
        if ($this->Event->recurring && 
                $this->Event->rec_data['type'] < EV_RECUR_DATES) {
            $rec_data = $this->Event->rec_data;
            $rec_string = $LANG_EVLIST['recur_freq_txt'] . ' ' .
                $this->Event->RecurDescrip();
            switch ($rec_data['type']) {
            case EV_RECUR_WEEKLY:        // sequential days
                $weekdays = array();
                if (is_array($rec_data['listdays'])) {
                    foreach ($rec_data['listdays'] as $daynum) {
                        $weekdays[] = $LANG_WEEK[$daynum];
                    }
                    $days_text = implode(', ', $weekdays);
                } else {
                    $days_text = '';
                }
                $rec_string .= ' '.sprintf($LANG_EVLIST['on_days'], $days_text);
                break;
            case EV_RECUR_DOM:
                $days = array();
                foreach($rec_data['interval'] as $key=>$day) {
                    $days[] = $LANG_EVLIST['rec_intervals'][$day];
                }
                $days_text = implode(', ', $days) . ' ' . 
                        $LANG_WEEK[$rec_data['weekday']];
                $rec_string .= ' ' . sprintf($LANG_EVLIST['on_the_days'], 
                    $days_text);
                break;
            }
            if ($this->Event->rec_data['stop'] != '' &&
                $this->Event->rec_data['stop'] < EV_MAX_DATE) {
                $rec_string .= ' ' . sprintf($LANG_EVLIST['recur_stop_desc'],
                    EVLIST_formattedDate($this->Event->rec_data['stop']));
            }
        } else {
            $rec_string = '';
        }

        $T->set_var(array(
            'pi_url' => EVLIST_URL,
            'webcal_url' => preg_replace('/^https?/', 'webcal', EVLIST_URL),
            'rp_id'     => $this->rp_id,
            'ev_id'     => $this->ev_id,
            'title' => $title,
            'summary' => $summary,
            'full_description' => $fulldescription,
            'can_edit' => $this->isAdmin ? 'true' : '',
            'start_time1' => $time_start1,
            'end_time1' => $time_end1,
            'start_time2' => $time_start2,
            'end_time2' => $time_end2,
            'start_date' => $date_start,
            'end_date' => $date_end,
            'start_datetime1' => $date_start . $time_start,
            'end_datetime1' => $date_end . $time_end,
            'allday_event' => $this->Event->allday == 1 ? 'true' : '',
            'is_recurring' => $this->Event->recurring,
            'can_subscribe' => $this->Event->Calendar->cal_ena_ical,
            'recurring_event'    => $rec_string,
            'owner_id'      => $this->Event->owner_id,
            'cal_name'      => $this->Event->Calendar->cal_name,
            'cal_id'        => $this->Event->cal_id,
            'site_name'     => $_CONF['site_name'],
            'site_slogan'   => $_CONF['site_slogan'],
        ) );

        if ($_EV_CONF['enable_rsvp'] == 1 &&
                $this->Event->options['use_rsvp'] > 0) {
            if ($this->Event->options['rsvp_cutoff'] > 0) {
                $dt = new Date($this->event->date_start1 . ' ' . $this->Event->time_start1, $_CONF['timezone']);
                if (time() > $dt->toUnix() - ($this->Event->options['rsvp_cutoff'] * 86400)) {
                    $past_cutoff = false;
                } else {
                    $past_cutoff = true;
                }
            }
            if (COM_isAnonUser()) {
                // Just show a must-log-in message
                $T->set_var('login_to_register', 'true');
            } elseif (!$past_cutoff) {
                $num_free_tickets = $this->isRegistered(0, true);
                $total_tickets = $this->isRegistered(0, false);
                if ($num_free_tickets > 0) {
                    // If the user is already registered for any free tickets,
                    // show the cancel link
                    $T->set_var(array(
                        'unregister_link' => 'true',
                        'num_free_reg' => $num_free_tickets,
                    ) );
               }
               if (    ($this->Event->options['max_rsvp'] == 0 ||
                        $this->Event->options['rsvp_waitlist'] == 1 ||
                        $this->Event->options['max_rsvp'] > 
                        $this->TotalRegistrations() )
                        &&
                        ( $this->Event->options['max_user_rsvp'] == 0 ||
                          $total_tickets < $this->Event->options['max_user_rsvp']  )
                ) {
                    USES_evlist_class_tickettype();
                    $Ticks = evTicketType::GetTicketTypes();
                    if ($this->Event->options['max_user_rsvp'] > 1) {
                        $T->set_block('event', 'tickCntBlk', 'tcBlk');
                        $T->set_var('register_multi', true);
                        //$rsvp_user_count = '';
                        $avail_tickets = $this->Event->options['max_user_rsvp'] -
                                    $total_tickets;
                        for ($i = 1; $i <= $avail_tickets; $i++) {
                            $T->set_var('tick_cnt', $i);
                            $T->parse('tcBlk', 'tickCntBlk', true);
                            //$rsvp_user_count .= '<option value="'.$i.'">'.$i.
                            //        '</option>'.LB;
                        }
                        //$T->set_var('register_multi', $rsvp_user_count);
                    } else {
                        // max_rsvp == 0 indicates openended registration
                        $T->set_var('register_unltd', 'true');
                    }
                    $T->set_block('event', 'tickTypeBlk', 'tBlk');
                    foreach ($this->Event->options['tickets'] as $tick_id=>$data) {
                        /*$options .= '<option value="' . $tick_id . '">' .
                            $Ticks[$tick_id]->description;
                        if ($data['fee'] > 0) {
                            $options .= ' - ' . COM_numberFormat($data['fee'], 2);
                        }
                        $options .= '</option>' . LB;*/
                        $status = LGLIB_invokeService('paypal', 'formatAmount',
                                array('amount' => $data['fee']), $pp_fmt_amt, $svc_msg);
                        $fmt_amt = $status == PLG_RET_OK ?
                                $pp_fmt_amt : COM_numberFormat($data['fee'], 2);
                        $T->set_var(array(
                            'tick_type' => $tick_id,
                            'tick_descr' => $Ticks[$tick_id]->description,
                            'tick_fee' => $data['fee'] > 0 ? $fmt_amt : 'FREE',
                        ) );
                        $T->parse('tBlk', 'tickTypeBlk', true);
                    }
                    $T->set_var(array(
                        'register_link' => 'true',
                        'ticket_options' => $options,
                        'ticket_types_multi' => count($this->Event->options['tickets']) > 1 ? 'true' : '',
                    ) );

                }

            }

                // If ticket printing is enabled for this event, see if the
                // current user has any tickets to print.
                if ($this->Event->options['rsvp_print'] > 0) {
                    $paid = $this->Event->options['rsvp_print'] == 1 ? 'paid' : '';
                    USES_evlist_class_ticket();
                    $tickets = evTicket::GetTickets($this->ev_id, '', $this->uid, $paid);
                    if (count($tickets) > 0) {
                        $T->set_var('have_tickets', 'true');
                    }
                }
        }   // if enable_rsvp

        if (!empty($date_start) || !empty($date_end)) {
            $T->parse('datetime_info', 'datetime');
        }

        // Only process the location block if at least one element exists.
        // Don't want an empty block showing.
        if (!empty($url) || !empty($location) || !empty($street) || 
            !empty($city) || !empty($province) || !empty($postal)) {
            $T->set_var(array(
                'url' => $url,
                'more_info_link' => sprintf($LANG_EVLIST['click_here'], $url),
                'location' => $location,
                'street' => $street,
                'city' => $city,
                'province' => $province,
                'country' => $country,
                'postal' => $postal,
            ) );
            $T->parse('address_info', 'address');

            // Get info from the Weather plugin, if configured and available
            // There has to be at least some location data for this to work.
            if ($_EV_CONF['use_weather']) {
                // The postal code works best, but not internationally.
                // Try the regular address first.
                $loc = '';
                if (!empty($city) && !empty($province)) {
                    $loc = $city . ', ' . $province . ' ' . $country;
                } 
                if (!empty($postal)) {
                    $loc .= ' ' . $postal;
                } 
                if (!empty($loc)) {
                    // Location info was found, get the weather
                    LGLIB_invokeService('weather', 'embed',
                            array('loc' => $loc), $weather, $svc_msg);
                    if (!empty($weather)) {
                        // Weather info was found
                        $T->set_var('weather', $weather);
                    }
                }
            }
        }

        // Get a map from the Locator plugin, if configured and available
        if ($_EV_CONF['use_locator'] == 1 &&
                $this->Event->Detail->lat != 0 &&
                $this->Event->Detail->lng != 0) {
            $status = LGLIB_invokeService('locator', 'getMap',
                    array('lat' => $this->Event->Detail->lat,
                            'lng' => $this->Event->Detail->lng),
                    $map, $svc_msg);
            if ($status == PLG_RET_OK) {
                $T->set_var(array(
                    'map'   => $map,
                    'lat'   => number_format($this->Event->Detail->lat, 8, '.', ''),
                    'lng'   => number_format($this->Event->Detail->lng, 8, '.', ''),
                ) );
            }
        }

        //put contact info here: contact, email, phone#
        $name = $this->Event->Detail->contact != '' ? 
            COM_applyFilter($this->Event->Detail->contact) : '';
        if ($this->Event->Detail->email != '') {
            $email = COM_applyFilter($this->Event->Detail->email);
            $email = EVLIST_obfuscate($email);
        } else {
            $email = '';
        }
        $phone = $this->Event->Detail->phone != '' ?
            COM_applyFilter($this->Event->Detail->phone) : '';

        if (!empty($name) || !empty($email) || !empty($phone)) {
            $T->set_var(array(
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
            ) );
            $T->parse('contact_info', 'contact');
        }

        // TODO: Is the range needed?
        if (!empty($range)) {
            $andrange = '&amp;range=' . $range;
        } else {
            $andrange = '&amp;range=2';
        }

        if (!empty($cat)) {
            $andcat = '&amp;cat=' . $cat;
        } else {
            $andcat = '';
        }

        $cats = $this->Event->GetCategories();
        $catcount = count($cats);
        if ($catcount > 0) {
            $catlinks = array();
            for ($i = 0; $i < $catcount; $i++) {
                $catlinks[] = '<a href="' .
                COM_buildURL(EVLIST_URL . '/index.php?op=list' . $andrange . 
                '&cat=' . $cats[$i]['id']) .
                '">' . $cats[$i]['name'] . '</a>&nbsp;';
            }
            $catlink = join('|&nbsp;', $catlinks);
            $T->set_var('category_link', $catlink, true);
        }

        //  reminders must be enabled globally first and then per event in 
        //  order to be active
        if (!isset($_EV_CONF['reminder_days'])) {
            $_EV_CONF['reminder_days'] = 1;
        }

        $hasReminder = 0;
        if ($_EV_CONF['enable_reminders'] == '1' && 
                $this->Event->enable_reminders == '1' && 
                time() < strtotime("-".$_EV_CONF['reminder_days']." days", 
                    strtotime($this->date_start))) {
            //form will not appear within XX days of scheduled event.
            $show_reminders = true;

            // Let's see if we have already asked for a reminder...
            if ($_USER['uid'] > 1) {
                $hasReminder = DB_count($_TABLES['evlist_remlookup'],
                        array('eid', 'uid', 'rp_id'),
                        array($this->ev_id, $_USER['uid'], $this->rp_id) );
                /*if ($hasReminder > 0) {
                    $T->set_file('rem_form', 'reminder_delete_form.thtml');
                    $T->set_var(array(
                        'action' => EVLIST_URL . '/event.php',
                        'eid' => $this->ev_id,
                        'rp_id' => $this->rp_id,
                    ) );
                    $T->parse('reminder', 'rem_form');
                } else {
                    // user hasn't already requested a reminder
                    $T->set_file('rem_form', 'reminder_form.thtml');
                    $T->set_var(array(
                        'action' => EVLIST_URL . '/event.php',
                        'eid' => $this->ev_id,
                        'notice' => 1,
                        'rp_id' => $this->rp_id,
                        'user_email' => $_USER['email'],
                    ) );
                    $T->parse('reminder', 'rem_form');
                }*/
            }
        } else {
            $show_reminders = false;
        }

        if ($this->Event->options['contactlink'] == 1) {
            $ownerlink = $_CONF['site_url'] . '/profiles.php?uid=' . 
                    $this->Event->owner_id;
            $ownerlink = sprintf($LANG_EVLIST['contact_us'], $ownerlink);
        } else {
            $ownerlink = '';
        }
        $T->set_var(array(
            'owner_link' => $ownerlink,
            'reminder_set' => $hasReminder ? 'true' : 'false',
            'reminder_email' => isset($_USER['email']) ? $_USER['email'] : '',
            'notice' => 1,
            'rp_id' => $this->rp_id,
            'eid' => $this->ev_id,
            'show_reminderform' => $show_reminders ? 'true' : '',
        ) );

        USES_evlist_class_tickettype();
        $tick_types = evTicketType::GetTicketTypes();
        $T->set_block('event', 'registerBlock', 'rBlock');
        if (is_array($this->Event->options['tickets'])) {
            foreach ($this->Event->options['tickets'] as $tic_type=>$info) {
                $T->set_var(array(
                    'tic_description' => $tick_types[$tic_type]->description,
                    'tic_fee' => COM_numberFormat($info['fee'], 2),
                ) );
                $T->parse('rBlock', 'registerBlock', true);
            }
        }

        // Show the "manage reservations" link to the event owner
        if ($_EV_CONF['enable_rsvp'] == 1 &&
                $this->Event->options['use_rsvp'] > 0) {
            if ($this->isAdmin) {
                $T->set_var('admin_rsvp', EVLIST_adminRSVP($this->rp_id));
            }
        }

        $T->parse ('output','event');
        $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
    *   Register a user for an event.
    *
    *   @param  integer $num_attendees  Number of attendees, default 1
    *   @param  integer $uid    User ID to register, 0 for current user
    *   @return integer         Message code, zero for success
    */
    public function Register($num_attendees = 1, $tick_type = 0, $uid = 0)
    {
        global $_TABLES, $_USER, $_EV_CONF, $LANG_EVLIST;

        if ($_EV_CONF['enable_rsvp'] != 1) {
            return 0;
        }

        // Make sure that registrations are enabled and that the current user 
        // has access to this event.  If $uid > 0, then this is an admin
        // registering another user, don't check access
        if ($this->Event->options['use_rsvp'] == 0 ||
            ($uid == 0 && !$this->Event->hasAccess(2))) {
            LGLIB_storeMessage($LANG_EVLIST['messages'][20]);
            return 20;
        } elseif ($this->Event->options['use_rsvp'] == 1) {
            // Registering for entire event, all repeats
            $rp_id = 0;
        } else {
            // Registering for a single occurance
            $rp_id = $this->rp_id;
        }

        if (!isset($this->Event->options['tickets'][$tick_type])) {
            LGLIB_storeMessage($LANG_EVLIST['messages'][24]);
            return 24;
        }

        $uid = $uid == 0 ? (int)$_USER['uid'] : (int)$uid;
        $num_attendees = (int)$num_attendees;
        $fee = (float)$this->Event->options['tickets'][$tick_type]['fee'];

        // Check that the current user isn't already registered
        // TODO: Allow registrations up to max count, or to waitlist
        //if ($this->isRegistered()) {
        //    return 21;
        //}

        // Check that the event isn't already full, or that
        // waitlisting is disabled
        if ($this->Event->options['max_rsvp'] > 0 &&
                $this->Event->options['max_rsvp'] <= $this->TotalRegistrations()) {
            if ($this->Event->options['max_rsvp'] > 0 &&
                    $this->Event->options['rsvp_waitlist'] == 0 && 1) {
                LGLIB_storeMessage($LANG_EVLIST['messages'][22]);
                return 22;       // too many already signed up
            }
            if ($fee > 0) {
                LGLIB_storeMessage($LANG_EVLIST['messages'][22]);
                return 22;      // can't waitlist paid tickets
            }
        }

        if ($fee > 0) {
            // add tickes to the shopping cart. Tickets will be created
            // when paid.
            $this->AddToCart($tick_type, $num_attendees);
            LGLIB_storeMessage($LANG_EVLIST['messages']['24']);
            $status = LGLIB_invokeService('paypal', 'getURL',
                array('type'=>'checkout'), $url, $msg);
            if ($status == PLG_RET_OK) {
                LGLIB_storeMessage(sprintf($LANG_EVLIST['messages']['26'],
                    $url), '', true);
            }
        }
        // for free tickets, just create the ticket records
        USES_evlist_class_tickettype();
        USES_evlist_class_ticket();
        $TickType = new evTicketType($tick_type);
        if ($TickType->event_pass) {
            $t_rp_id = 0;
        } else {
            $t_rp_id = $this->rp_id;
        }
        for ($i = 0; $i < $num_attendees; $i++) {
            evTicket::Create($this->Event->id, $tick_type, $t_rp_id, $fee, $uid);
        }
        /*if ($fee < .01) {
        DB_query("INSERT INTO {$_TABLES['evlist_rsvp']} SET
                    ev_id = '{$this->Event->id}',
                    rp_id = '{$rp_id}',
                    uid = '{$uid}',
                    num_attendees = '{$num_attendees}',
                    dt_reg = " . time());
        if (DB_error())
            return 23;
        } else {
            $this->AddToCart($tick_type, $num_attendees);
        }*/

        return 0;
    }


    /**
    *   Cancel a user's registration for an event.
    *   Delete the newer records first, to preserve waitlist position for the user.
    *   
    *   @param  integer $uid    Optional User ID to remove, 0 for current user
    *   @param  integer $num    Number of reservations to cancel, 0 for all
    */
    public function CancelRegistration($uid = 0, $num = 0)
    {
        global $_TABLES, $_USER, $_EV_CONF;

        if ($_EV_CONF['enable_rsvp'] != 1) return false;

        $num = (int)$num;

        $uid = $uid == 0 ? (int)$_USER['uid'] : (int)$uid;
        $sql = "DELETE FROM {$_TABLES['evlist_tickets']} WHERE
                ev_id = '" . $this->Event->id . "'
                AND uid = $uid
                AND fee = 0
                ORDER BY dt DESC";
        if ($num > 0) $sql .= " LIMIT $num";
        DB_query($sql, 1);
        return DB_error() ? false : true;
    }


    /**
    *   Determine if the user is registered for this event/repeat.
    *
    *   @param  integer $uid    Optional user ID, current user by default
    *   @param  boolean $free_only  True to get count of only free tickets
    *   @return mixed   Number of registrations, or False if rsvp disabled
    */
    public function isRegistered($uid = 0, $free_only = false)
    {
        global $_TABLES, $_USER, $_EV_CONF;

        static $counter = array();
        if ($_EV_CONF['enable_rsvp'] != 1) return false;

        $uid = $uid == 0 ? (int)$_USER['uid'] : (int)$uid;
        $key = $free_only ? 1 : 0;

        /*if ($this->Event->options['use_rsvp'] == EV_RSVP_EVENT) {
            $count = DB_count($_TABLES['evlist_rsvp'], 
                    array('ev_id', 'uid'),
                    array($this->Event->id, $uid));
        } else {
            $count = DB_count($_TABLES['evlist_rsvp'],
                array('ev_id', 'rp_id', 'uid'),
                array($this->Event->id, $this->rp_id, $uid));
        }*/
        if (!isset($counter[$key])) {
            $counter[$key] = array();
        }
        if (!isset($counter[$key][$uid])) {
            $sql = "SELECT count(*) AS c FROM {$_TABLES['evlist_tickets']}
                    WHERE ev_id = '{$this->Event->id}'
                    AND (rp_id = 0 OR rp_id = {$this->rp_id})
                    AND uid = $uid";
            // check for fee = 0 if free_only is set
            if ($key == 1) $sql .= ' AND fee = 0';
            $res = DB_query($sql);
            $A = DB_fetchArray($res, false);
            $counter[$key][$uid] = isset($A['c']) ? (int)$A['c'] : 0;
        }
        return $counter[$key][$uid];
    }


    /**
    *   Get the total number of users registered for this event/repeat
    *   If provided, the $rp_id parameter will be considered an event ID or
    *   a repeat ID, depending on the event's registration option.
    *
    *   @param  mixed   $rp_id  Optional ID of event or repeat to check
    *   @return integer         Total registrations
    */
    public function TotalRegistrations($rp_id = '')
    {
        global $_TABLES, $_EV_CONF;

        if ($_EV_CONF['enable_rsvp'] != 1) return 0;

        if ($rp_id == '' && is_object($this)) {
            $rp_id = $this->id;
        }

        if ($this->Event->options['use_rsvp'] == EV_RSVP_EVENT) {
            $count = (int)DB_count($_TABLES['evlist_tickets'], 'ev_id', $rp_id);
        } else {
            $count = (int)DB_count($_TABLES['evlist_tickets'], 'rp_id', $rp_id);
        }
        return $count;

    }


    /**
    *   Get all the users registered for this event.
    *
    *   @return array   Array of uid's and dates, sorted by date
    */
    public function Registrations()
    {
        global $_TABLES, $_EV_CONF;

        $retval = array();
        if ($_EV_CONF['enable_rsvp'] != 1 ||
                $this->Event->options['use_rsvp'] == 0) {
            // Registrations disbled, return empty array
            return $retval;
        }

        $sql = "SELECT uid, dt_reg
            FROM {$_TABLES['evlist_tickets']}
            WHERE ev_id = '{$this->ev_id}' ";

        if ($this->Event->options['use_rsvp'] == EV_RSVP_REPEAT) {
            $sql .= " AND rp_id = '{$this->rp_id}' ";
        }
        $sql .= ' ORDER BY dt_reg ASC';

        $res = DB_query($sql, 1);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $retval[] = $A;
            }
        }

        return $retval;
    }


    /**
    *   Delete the current and all future occurrences of an event.
    *   First, gather and delete all the detail records for custom instances.
    *   Then, delete all the future repeat records. Finally, update the stop
    *   date for the main event.
    */
    public function DeleteFuture()
    {
        global $_TABLES;

        if ($this->date_start <= $this->Event->date_start1) {
            // This is easy- we're deleting ALL repeats, so also
            // delete the event
            $this->Event->Delete();
        } else {
            // Find all custom detail records and delete them.
            $sql = "SELECT rp_id, rp_det_id
                    FROM {$_TABLES['evlist_repeat']}
                    WHERE rp_ev_id='{$this->ev_id}' 
                    AND rp_date_start >= '{$this->date_start}'
                    AND rp_det_id <> '{$this->Event->det_id}'";
            $res = DB_query($sql);
            $details = array();
            while ($A = DB_fetchArray($res, false)) {
                $details[] = (int)$A['rp_det_id'];
            }
            if (!empty($details)) {
                $detail_str = implode(',', $details);
                $sql = "DELETE FROM {$_TABLES['evlist_detail']}
                        WHERE det_id IN ($detail_str)";
                DB_query($sql);
            }

            // Now delete the repeats
            $sql = "DELETE FROM {$_TABLES['evlist_repeat']}
                    WHERE rp_ev_id='{$this->ev_id}' 
                    AND rp_date_start >= '{$this->date_start}'";
            DB_query($sql);

            // Now adjust the recurring stop date for the event.
            $new_stop = DB_getItem($_TABLES['evlist_repeat'], 
                'rp_date_start', 
                "rp_ev_id='{$R->ev_id}' 
                    ORDER BY rp_date_start DESC LIMIT 1");
            if (!empty($new_stop)) {
                $this->Event->rec_data['stop'] = $new_stop;
                $this->Event->Save();
            }
        }
    }   // function DeleteFuture()


    /**
    *   Get one or all occurences of an event.
    *   If $rp_id is zero, return all repeats. Otherwise return only the
    *   requested one.
    *
    *   @param  string  $ev_id  Event ID
    *   @param  integer $rp_id  Repeat ID
    *   @return array       Array of occurrences
    */
    public static function GetRepeats($ev_id, $rp_id=0)
    {
        global $_TABLES;

        $repeats = array();
        $where = array();
        $ev_id = DB_escapeString($ev_id);
        $rp_id = (int)$rp_id;

        $where = "rp_ev_id = '$ev_id'";
        if ($rp_id > 0) {
            $where .= " AND rp_id = $rp_id";
        }
        if (!empty($where)) {
            $sql = "SELECT * FROM {$_TABLES['evlist_repeat']} WHERE $where";
            $res = DB_query($sql, 1);
            while ($A = DB_fetchArray($res, false)) {
                $repeats[$A['rp_id']] = new evRepeat();
                $repeats[$A['rp_id']]->SetVars($A, true);
            }
        }
        return $repeats;
    }


    /**
    *   Add the event fee to the shopping cart
    *   No checking is done here to see if it's paid, that must be done
    *   by the caller.
    *
    *   @param  boolean $info   True to just return vars, False to add to cart
    *   @return array           Array of cart vars
    */
    public function AddToCart($tick_type, $qty=1)
    {
        global $LANG_EVLIST, $_CONF;

        USES_evlist_class_tickettype();
        $TickType = new evTicketType($tick_type);
        $fee = $this->Event->options['tickets'][$tick_type]['fee'];
        $rp_id = $TickType->event_pass ? 0 : $this->rp_id;

        $evCart = array(
            'item_number' => 'evlist:eventfee:' . $this->Event->id . '/' . 
                    $tick_type . '/' . $rp_id,
            'item_name' => $TickType->description . ': ' . $LANG_EVLIST['event_fee'] . ' - ' .
                    $this->Event->Detail->title . ' ' . $this->start_date1 . 
                    ' ' . $this->start_time1,
            'short_description' => $TickType->description . ': ' . 
                    $this->Event->Detail->title . ' ' . $this->start_date1 . 
                    ' ' . $this->start_time1,

            'amount' => sprintf("%5.2f", (float)$fee),
            'quantity' => $qty,
            'extras' => array('shipping' => 0),
        );
        LGLIB_invokeService('paypal', 'addCartItem', $evCart, $output, $msg);
        return $evCart;
     }


}   // class evRepeat


?>
