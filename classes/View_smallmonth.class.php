<?php
/**
*   Small Month view for the evList plugin.
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
*   @class View_smallmonth
*   @package Evlist
*/
class View_smallmonth extends View
{
    /**
    *   Display a small monthly calendar for the current month.
    *   Dates that have events scheduled are highlighted.
    *
    *   @param  integer $year   Year to display, default is current year
    *   @param  integer $month  Starting month
    *   @return string          HTML for calendar page
    */
    public function Render()
    {
        global $_CONF, $_EV_CONF, $LANG_MONTH, $_SYSTEM;

        $retval = '';

        // Default to the current year
        $monthnum_str = sprintf('%02d', (int)$this->month);
        $dt = $_EV_CONF['_now'];

        // Get all the dates in the period
        $starting_date = date('Y-m-d', mktime(0, 0, 0, $this->month, 1, $this->year));
        $ending_date = date('Y-m-d', mktime(23, 59, 59, $this->month,
            \Date_Calc::daysInMonth($this->year, $this->month), $this->year));
        $calendarView = \Date_Calc::getCalendarMonth($this->month, $this->year, '%Y-%m-%d');
        $events = EVLIST_getEvents($starting_date, $ending_date, $opts);

        $T = new \Template(EVLIST_PI_PATH . '/templates');
        $T->set_file(array(
            'smallmonth'  => 'phpblock_month.thtml',
        ) );

        $T->set_var(array(
            'thisyear' => $this->year,
            'month' => $this->month,
            'monthname' => $LANG_MONTH[(int)$this->month],
        ));

        // Set each day column header to the first letter of the day name
        $T->set_block('smallmonth', 'daynames', 'nBlock');
        $daynames = self::DayNames(1);
        foreach ($daynames as $key=>$dayname) {
            $T->set_var('dayname', $dayname);
            $T->parse('nBlock', 'daynames', true);
        }

        $T->set_block('smallmonth', 'week', 'wBlock');

        foreach ($calendarView as $weeknum => $weekdata) {
            list($weekYear, $weekMonth, $weekDay) = explode('-', $weekdata[0]);
            $T->set_var(array(
                    'weekyear'  => $weekYear,
                    'weekmonth' => $weekMonth,
                    'weekday'   => $weekDay,
            ) );
            $T->set_block('smallmonth', 'day', 'dBlock');
            foreach ($weekdata as $daynum => $daydata) {
                list($y, $m, $d) = explode('-', $daydata);
                $T->clear_var('no_day_link');
                if ($daydata == $_EV_CONF['_today']) {
                    $dayclass = 'monthtoday';
                } elseif ($m == $monthnum_str) {
                    $dayclass = 'monthon';
                } else {
                    $T->set_var('no_day_link', 'true');
                    $dayclass = 'monthoff';
                }
                $popup = '';
                if (isset($events[$daydata])) {
                    // Create the tooltip hover text
                    $daylinkclass = $dayclass == 'monthoff' ?
                                'nolink-events' : 'day-events';
                    $dayspanclass='tooltip gl_mootip';
                    foreach ($events[$daydata] as $event) {
                        // Show event titles on different lines if more than one
                        if (!empty($popup)) $popup .= self::tooltip_newline();
                        // Don't show a time for all-day events
                        if ($event['allday'] == 0 &&
                            $event['rp_date_start'] == $event['rp_date_end']) {
                            $dt->setTimestamp(strtotime($event['rp_date_start'] .
                                ' ' . $event['rp_time_start1']));
                            // Time is a localized string, not a timestamp, so
                            // don't adjust for the timezone
                            $popup .= $dt->format($_CONF['timeonly'], false) . ': ';
                        }
                        $popup .= htmlentities($event['title']);
                    }
                    $T->set_var('popup', $popup);
                } else {
                    $dayspanclass='';
                    $daylinkclass = 'day-noevents';
                    $T->clear_var('popup');
                }
                $T->set_var(array(
                    'daylinkclass'      => $daylinkclass,
                    'dayclass'          => $dayclass,
                    'dayspanclass'      => $dayspanclass,
                    'day'               => substr($daydata, 8, 2),
                    'pi_url'            => EVLIST_URL,
                ) );
                $T->parse('dBlock', 'day', true);
            }
            $T->parse('wBlock', 'week', true);
            $T->clear_var('dBlock');
        }
        $T->parse('output', 'smallmonth');
        return $T->finish($T->get_var('output'));
    }
}

?>
