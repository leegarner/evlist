<?php
/**
*   View functions for the evList plugin.
*   Creates daily, weekly, monthly and yearly calendar views
*
*   @author     Lee P. Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017 Lee Garner <lee@leegarner.com
*   @package    evlist
*   @version    1.4.3
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Evlist;

/**
*   Create a weekly view calendar
*   @class View_week
*/
class View_week extends View
{
    /**
    *   Construct the weekly view
    *
    *   @param  integer $year   Year to display, default is current year
    *   @param  integer $month  Starting month
    *   @param  integer $day    Starting day
    *   @param  integer $cat    Event category
    *   @param  integer $cal    Calendar to show
    *   @param  string  $opt    Optional template modifier, e.g. "print"
    */
    public function __construct($year=0, $month=0, $day=0, $cat=0, $cal=0, $opts=array())
    {
        $this->type = 'week';
        parent::__construct($year, $month, $day, $cat, $cal, $opts);
    }


    /**
    *   Get the actual calendar view content
    *
    *   @return string      HTML for calendar content
    */
    public function Content()
    {
        global $_CONF, $_EV_CONF, $LANG_MONTH, $LANG_EVLIST;

        $retval = '';

        // Get the events
        $calendarView = \Date_Calc::getCalendarWeek($this->day, $this->month, $this->year, '%Y-%m-%d');
        $start_date = $calendarView[0];
        $end_date = $calendarView[6];

        $dtStart = new \Date(strtotime($start_date));
        $dtToday = $dtStart;    // used to update date strings each day
        $week_secs = 86400 * 7;
        $dtPrev = new \Date($dtStart->toUnix() - $week_secs);
        $dtNext = new \Date($dtStart->toUnix() + $week_secs);

        // Set up next and previous week links
        list($sYear, $sMonth, $sDay) = explode('-', $start_date);

        $T = new \Template(EVLIST_PI_PATH . '/templates/weekview');
        $tpl = $this->getTemplate();
        $T->set_file(array(
            'week'      => $tpl . '.thtml',
            //'events'    => 'weekview/events.thtml',
        ) );

        $daynames = self::DayNames();
        $events = EVLIST_getEvents($start_date, $end_date,
                array('cat'=>$this->cat, 'cal'=>$this->cal));

        $start_mname = $LANG_MONTH[(int)$sMonth];
        $last_date = getdate($dtStart->toUnix() + (86400 * 6));
        $end_mname = $LANG_MONTH[$last_date['mon']];
        $end_ynum = $last_date['year'];
        $end_dnum = sprintf('%02d', $last_date['mday']);
        $date_range = $start_mname . ' ' . $sDay;
        if ($this->year <> $end_ynum) {
            $date_range .= ', ' . $this->year . ' - ';
        } else {
            $date_range .= ' - ';
        }
        if ($start_mname <> $end_mname) {
            $date_range .= $end_mname . ' ';
        }
        $date_range .= "$end_dnum, $end_ynum";
        $T->set_var('date_range', $date_range);

        $T->set_block('week', 'dayBlock', 'dBlk');
        foreach($calendarView as $idx=>$weekData) {
            list($curyear, $curmonth, $curday) = explode('-', $weekData);
            $dtToday->setDateTimestamp($curyear, $curmonth, $curday, 1, 0, 0);
            $T->clear_var('eBlk');
            if ($weekData == $_EV_CONF['_today']) {
                $T->set_var('dayclass', 'weekview-curday');
            } else {
                $T->set_var('dayclass', 'weekview-offday');
            }

            $monthname = $LANG_MONTH[(int)$curmonth];
            $T->set_var('dayinfo', $daynames[$idx] . ', ' .
                COM_createLink($dtToday->format($_CONF['shortdate']),
                    EVLIST_URL . "/index.php?view=day&amp;day=$curday" .
                    "&amp;cat={$this->cat}&amp;cal={$this->cal}" .
                    "&amp;month=$curmonth&amp;year=$curyear")
            );

            if (EVLIST_canSubmit()) {
                $T->set_var(array(
                    'can_add'       => 'true',
                    'curday'        => $curday,
                    'curmonth'      => $curmonth,
                    'curyear'       => $curyear,
                ) );
            }

            if (!isset($events[$weekData])) {
                // Make sure it's a valid but empty array if no events today
                $events[$weekData] = array();
            }

            $T->set_block('week', 'eventBlock', 'eBlk');
            foreach ($events[$weekData] as $A) {
                //$fgstyle = 'color:' . $A['fgcolor'].';';
                if ($A['allday'] == 1 ||
                        ($A['rp_date_start'] < $weekData &&
                        $A['rp_date_end'] > $weekData)) {
                    $event_time = $LANG_EVLIST['allday'];
                    /*$event_div = '<div class="monthview_allday"
                        style="background-color:'. $event['bgcolor'].';">';*/
                } else {
                    if ($A['rp_date_start'] == $weekData) {
                        $startstamp = strtotime($weekData . ' ' . $A['rp_time_start1']);
                        $starttime = date('g:i a', $startstamp);
                    } else {
                        $starttime = ' ... ';
                    }

                    if ($A['rp_date_end'] == $weekData) {
                        $endstamp = strtotime($weekData . ' ' . $A['rp_time_end1']);
                        $endtime = date('g:i a', $endstamp);
                    } else {
                        $endtime = ' ... ';
                    }
                    $event_time = $starttime . ' - ' . $endtime;

                    if ($A['split'] == 1 && !empty($A['rp_time_start2'])) {
                        $startstamp2 = strtotime($weekData . ' ' . $A['rp_time_start2']);
                        $starttime2 = date('g:i a', $startstamp2);
                        $endstamp2 = strtotime($weekData . ' ' . $A['rp_time_end2']);
                        $endtime2 = date('g:i a', $endstamp2);
                        $event_time .= ' & ' . $starttime2 . ' - ' . $endtime2;
                    }
                }
                if (isset($A['cal_id'])) {
                    $this->cal_used[$A['cal_id']] = array(
                        'cal_name' => $A['cal_name'],
                        'cal_ena_ical' => $A['cal_ena_ical'],
                        'cal_id' => $event['cal_id'],
                        'fgcolor' => $A['fgcolor'],
                        'bgcolor' => $A['bgcolor'],
                    );
                }

                $T->set_var(array(
                    'event_times'   => $event_time,
                    'event_title'   => htmlspecialchars($A['title']),
                    'event_summary' => htmlspecialchars($A['summary']),
                    'event_id'      => $A['rp_id'],
                    'cal_id'        => $A['cal_id'],
                    'delete_imagelink' => EVLIST_deleteImageLink($A, $token),
                    //'event_title_and_link' => $eventlink,
                    'pi_url'        => EVLIST_URL,
                    'fgcolor'       => $A['fgcolor'],
                ) );
                if ($A['cal_id'] < 0) {
                    $T->set_var(array(
                        'is_meetup' => 'true',
                        'ev_url' => $A['url'],
                    ) );
                } else {
                    $T->clear_var('is_meetup');
                }
                $T->parse('eBlk', 'eventBlock', true);
            }

            $T->parse('dBlk', 'dayBlock', true);
        }

        $T->set_var(array(
            'pi_url'        => EVLIST_URL,
            'cal_header'    => $this->Header(),
            'cal_footer'    => $this->Footer(),
            'prevmonth'     => $dtPrev->format('n', false),
            'prevday'       => $dtPrev->format('j', false),
            'prevyear'      => $dtPrev->format('Y', false),
            'nextmonth'     => $dtNext->format('n', false),
            'nextday'       => $dtNext->format('j', false),
            'nextyear'      => $dtNext->format('Y', false),
            'urlfilt_cat'   => $this->cat,
            'urlfilt_cal'   => $this->cal,
            'cal_checkboxes' => $this->getCalCheckboxes(),
            'site_name'     => $_CONF['site_name'],
            'site_slogan'   => $_CONF['site_slogan'],
            'year'          => $this->year,
            'month'         => $this->month,
            'day'           => $this->day,
            'is_uikit'      => $_EV_CONF['_is_uikit'] ? 'true' : '',
        ) );
        $T->parse('output','week');
        return $T->finish($T->get_var('output'));
    }
}

?>
