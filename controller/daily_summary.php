<?php
$this->respond('GET', '/[s:date]', function ($request, $response, $service, $app) {
    $mysqli = $app->db;

    $time = strtotime($request->date);

    if (!$time)
        return "Invalid date: " . var_dump($time);

    function pad($num) {
        if ($num < 10)
            return "0" . strval($num);
        return strval($num);
    }

    $query_date_min = date("Ymd", $time) . "000000";
    $query_date_max = date("Ymd", $time + 24 * 3600) . "000000";

    $query = "SELECT
        rc_namespace,
        rc_title,
        rc_cur_id,
        MAX(rc_timestamp),
        GROUP_CONCAT(rc_timestamp ORDER BY rc_timestamp) AS timestamps, 
        GROUP_CONCAT(DISTINCT rc_user_text ORDER BY rc_timestamp) AS users,
        GROUP_CONCAT(rc_this_oldid),
        GROUP_CONCAT(rc_last_oldid)
    FROM recentchanges
    WHERE
        rc_bot = 0 AND /* exclude bots */
        0 <= rc_type AND rc_type <= 4 AND 
        rc_timestamp >= $query_date_min AND rc_timestamp < $query_date_max
    GROUP BY rc_namespace, rc_title
    ORDER BY rc_timestamp DESC;";

    $result = $mysqli->query($query);

    require_once("utils.php");
    $namespaces = json_decode(http_request("https://id.wikipedia.org/w/api.php?action=query&meta=siteinfo&siprop=namespaces&format=json"), true);
    $namespaces = $namespaces['query']['namespaces'];

    $ns = array();
    foreach ($namespaces as $k => $v) {
        $ns[$k] = $v['*'];
        if ($k !== 0)
            $ns[$k] .= ":";
    }
    // var_dump($ns);
    // 
    // TODO: integrate with https://ores.wmflabs.org/scores/idwiki/reverted/ <diff_id> /

    $service->render('view/daily_summary.phtml', array('result' => $result, 'ns' => $ns));
});