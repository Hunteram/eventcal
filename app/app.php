<?php

/**
 * Remove events from $content with the status $action and returns the result
 * @param $content string
 * @param $action string STATUS_MAYBE, STATUS_GOING or STATUS_UNDECIDED
 * @return string Modified content
 */
function removeEvents($content, $action)
{
    $last = 0;
    while (strpos($content, "PARTSTAT:$action", $last + 1) !== false) {
        $pos = strpos($content, "PARTSTAT:$action", $last + 1);
        $startPos = strrpos($content, "BEGIN:VEVENT", $pos - strlen($content));
        $endPos = strpos($content, "END:VEVENT", $pos) + strlen("END:VEVENT") + 2;
        $content = substr_replace($content, '', $startPos, $endPos - $startPos);
        $last = $startPos;
    }

    return $content;
}

/**
 * Get statuses
 * @return array The statuses that should be removed
 */
function status() {
    $types = [STATUS_MAYBE, STATUS_GOING, STATUS_UNDECIDED];
    $status = array_get($_GET, "status", STATUS_GOING . ',' . STATUS_MAYBE);
    $status = $status ? explode(',', strtoupper($status)) : [];
    foreach($status as $s) {
        if (!in_array($s, $types)) {
            http_response_code(400);
            die('Not supported status: ' . $s);
        }
    }
    return array_diff($types, $status);
}

if (isset($_GET["calendar"])) {
    if (isset($_GET["base64"])) {
        $fbCal = base64_decode($_GET["calendar"]);
    } else {
        $fbCal = urldecode($_GET["calendar"]);
    }

    if (strpos($fbCal, 'webcal') === 0) {
        $fbCal = substr_replace($fbCal, "https", 0, strlen('webcal'));
    }

    if (!preg_match('#^https?://www\.facebook\.com/ical/#', $fbCal)) {
        http_response_code(400);
        die("Not a valid Facebook calendar url");
    }

    // Facebook blocks requests without user agent
    $content = @file_get_contents($fbCal, false, stream_context_create(['http' => [
        'header'=>"User-Agent: eventcal.flown.io\r\n"
    ]]));

    if ($content === false) {
        http_response_code(400);
        die("Could not fetch calendar");
    }

    if (strpos($content, 'BEGIN:VCALENDAR') !== 0) {
        http_response_code(400);
        die("Calendar not valid. Facebook error: " . $content);
    }

    foreach(status() as $status) {
        $content = removeEvents($content, $status);
    }

    header('Content-Type: text/calendar;charset=utf-8');
    header('Content-Disposition: attachment;filename=calendar.ics');
    die($content);
}
