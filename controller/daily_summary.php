<?php
$this->respond('GET', '/[s:date]', function ($request, $response, $service, $app) {
    return $response->redirect($service->base_url . "/daily_summary/" . $request->date . "/1");
});
$this->respond('GET', '/[s:date]/[i:page_num]', function ($request, $response, $service, $app) {
    $mysqli = $app->db;
    $page_num = max(intval($request->page_num) - 1, 0);
    $offset_rows = $page_num * 100;

    $time = strtotime($request->date);

    if (!$time)
        return "Invalid date: " . var_dump($time);

    function pad($num)
    {
        if ($num < 10)
            return "0" . strval($num);
        return strval($num);
    }
    require_once("utils.php");

    $query_date_min = date("Ymd", $time) . "000000";
    $query_date_max = date("Ymd", $time + 24 * 3600) . "000000";

    $query = "SELECT SQL_CALC_FOUND_ROWS
        rc_namespace,
        rc_title,
        rc_cur_id,
        GROUP_CONCAT(rc_log_type) AS log_types,
        GROUP_CONCAT(rc_log_action) AS log_actions,
        GROUP_CONCAT(rc_type) AS types, 
        GROUP_CONCAT(rc_timestamp) AS timestamps, 
        GROUP_CONCAT(rc_actor) AS actors,
        GROUP_CONCAT(rc_this_oldid) AS diffs,
        GROUP_CONCAT(rc_last_oldid) AS prev_diffs
    FROM recentchanges
    WHERE
        rc_bot = 0 AND /* exclude bots */
        0 <= rc_type AND rc_type <= 4 AND 
        rc_timestamp >= $query_date_min AND rc_timestamp < $query_date_max
    GROUP BY rc_namespace, rc_title
    ORDER BY MAX(rc_timestamp) DESC
    LIMIT $offset_rows, 100;";

    $query .= "SELECT FOUND_ROWS();";

    // Query all the actor_ids found in query #1
    $query .= "SELECT
        actor_id, actor_name
    FROM actor
    INNER JOIN
        (SELECT 
          rc_actor
          FROM recentchanges
          WHERE
              rc_bot = 0 AND /* exclude bots */
              0 <= rc_type AND rc_type <= 4 AND 
              rc_timestamp >= $query_date_min AND rc_timestamp < $query_date_max
          GROUP BY rc_namespace, rc_title
          ORDER BY MAX(rc_timestamp) DESC
          LIMIT $offset_rows, 100) as rc
        ON rc.rc_actor = actor.actor_id
    LIMIT 0, 100;";

    $mysqli->multi_query($query);
    // dat long SQL
    $result = $mysqli->store_result();
    $namespaces = json_decode(http_request("https://id.wikipedia.org/w/api.php?action=query&meta=siteinfo&siprop=namespaces&format=json"), true);
    $namespaces = $namespaces['query']['namespaces'];

    $ns = array();
    foreach ($namespaces as $k => $v) {
        $ns[$k] = $v['*'];
        if ($k !== 0)
            $ns[$k] .= ":";
    }

    $data = array();
    while ($row = $result->fetch_assoc()) {
        $datum = array();

        $datum['ns'] = intval($row['rc_namespace']);
        $datum['title'] = $ns[$datum['ns']] . $row['rc_title'];

        $datum['timestamps'] = explode(',', $row['timestamps']);
        $datum['actors'] = explode(',', $row['actors']);
        $datum['diffs'] = explode(',', $row['diffs']);
        $datum['prev_diffs'] = explode(',', $row['prev_diffs']);
        $datum['types'] = explode(',', $row['types']);
        $datum['log_types'] = explode(',', $row['log_types']);
        $datum['log_actions'] = explode(',', $row['log_actions']);

        $datum['revisions'] = array();
        $k = 0;
        for ($i = 0; $i < count($datum['timestamps']); $i++) {
            array_push($datum['revisions'], array(
                'timestamp' => $datum['timestamps'][$i],
                'user' => $datum['actors'][$i],
                'diff' => $datum['diffs'][$i],
                'prev_diff' => $datum['prev_diffs'][$i],
                'type' => $datum['types'][$i],
                'log_type' => (strlen($datum['log_actions'][$i]) > 0) ? $datum['log_types'][$k] : '',
                'log_action' => $datum['log_actions'][$i],
                'diff_score' => ''
            ));

            if (strlen($datum['log_actions'][$i]) > 0) {
                $k++;
            }
        }

        array_push($data, $datum);
    };



    // found_rows
    $mysqli->next_result();
    $result = $mysqli->store_result();
    $row = $result->fetch_row();
    $num_found = $row[0];


    // Mapping of actor_id -> actor_name
    $mysqli->next_result();
    $result = $mysqli->store_result();
    $actor_map = array();
    while ($row = $result->fetch_assoc()) {
        $actor_map[$row['actor_id']] = $row['actor_name'];
    };

    function ores(&$data, $i)
    {
        $url = "https://ores.wikimedia.org/v3/scores/idwiki/?models=reverted&revids=";
        $cnt = 0;
        $limit = 100;
        $start = $limit * $i;
        $check = [];
        $more = false;
        foreach ($data as $row) {
            foreach ($row['revisions'] as $revision) {
                if ($revision['type'] < 3) {
                    if ($cnt >= $start) {
                        array_push($check, $revision['diff']);
                    }
                    $cnt++;
                    if ($cnt >= $start + $limit) {
                        $more = true;
                        break;
                    }
                }
            }
            if ($cnt >= $start + $limit) {
                break;
            }
        }


        $ores_result = json_decode(http_request($url . implode("|", $check)), true);
        $ores_result = $ores_result['idwiki']['scores'];

        $cnt = 0;
        foreach ($data as &$row) {
            foreach ($row['revisions'] as &$revision) {
                if ($revision['type'] < 3) {
                    if ($cnt >= $start) {
                        $revision['diff_score'] = round($ores_result[$revision['diff']]['reverted']['score']['probability']['true'] * 100);
                        //var_dump($revision);
                    }
                    $cnt++;
                    if ($cnt >= $start + $limit) {
                        break;
                    }
                }
            }
            if ($cnt >= $start + $limit) {
                break;
            }
        }

        return $more;
    }
    $x = -1;
    do {
        $x++;
    } while (ores($data, $x));

    // https://id.wikipedia.org/w/api.php?action=sitematrix

    
    $service->render(
        'view/daily_summary.phtml',
        array(
            'data' => $data,
            'page_num' => $page_num,
            'num_found' => $num_found,
            'ns' => $ns,
            'actor_map' => $actor_map
        )
    );
});
