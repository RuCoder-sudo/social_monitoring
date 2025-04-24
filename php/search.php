<?php

$existingLinks = [];
$newResults = [];
$foundDuplicate = false;
$stringArray6 = ["1721088000", "1721347200", "1720656000", "1721088000"];

function okApiRequest($method, $params, $access_token, $public_key, $secret_key) {
    $params['application_key'] = $public_key;
    $params['format'] = 'json';
    
    ksort($params);
    $sig = '';
    foreach ($params as $key => $value) {
        $sig .= $key . '=' . $value;
    }
    $sig .= md5($access_token . $secret_key);
    $sig = strtolower(md5($sig));
    
    $params['access_token'] = $access_token;
    $params['sig'] = $sig;
    
    $url = 'https://api.ok.ru/fb.do?'.http_build_query($params);
    $response = file_get_contents($url);
    
    return json_decode($response, true);
}

function vkApiRequest($method, $params) {
    $url = 'https://api.vk.com/method/' . $method . '?' . http_build_query($params);
    $response = file_get_contents($url);
    
    return json_decode($response, true);
}

function telegramApiRequest($method, $params) {
    $url = 'https://api.telegram.org/bot' . $params['token'] . '/' . $method;
    $response = file_get_contents($url . '?' . http_build_query($params));
    
    return json_decode($response, true);
}

function getChatId($token, $username) {
    $url = "https://api.telegram.org/bot$token/getChat";
    $params = [
        'chat_id' => $username
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($data['ok']) {
        return $data['result']['id'];
    } else {
        return null;
    }
}

function getVkGroupInfo($groupId, $token) {
    $params = [
        'access_token' => $token,
        'v' => '5.131',
        'group_id' => $groupId,
        'fields' => 'name,photo_50'
    ];
    $response = vkApiRequest('groups.getById', $params);
    return $response['response'][0] ?? null;
}

function getVkUserInfo($userId, $token) {
    $params = [
        'access_token' => $token,
        'v' => '5.131',
        'user_ids' => $userId,
        'fields' => 'photo_50'
    ];
    $response = vkApiRequest('users.get', $params);
    return $response['response'][0] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $token = $_POST['token'];
    $keywords = explode(',', $_POST['keywords']);
    $groupIds = array_filter(explode(',', $_POST['group_ids']));
    $socialNetwork = $_POST['social_network'];
    $results = [];
    $count = 100;
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $offset = ($page - 1) * $count;

    if (isset($_POST['time_start']) && isset($_POST['time_end'])) {
        $originalDate = $_POST['time_end'];
        file_put_contents('debug.log', $originalDate . PHP_EOL, FILE_APPEND);
        $start_time = strtotime($_POST['time_start']);
        $end_time = strtotime($_POST['time_end']);
    } else {
        $start_time = strtotime('now');
        $end_time = strtotime('now');
    }
    file_put_contents('debug.log', $end_time . PHP_EOL, FILE_APPEND);

    $current_start_time = $start_time - 86400;
    $one_day_seconds = 86400;

    while ($current_start_time < $end_time) {
        $current_end_time = min($current_start_time + $one_day_seconds - 1, $end_time);

        if ($socialNetwork == 'vk') {
            if (empty($groupIds)) {
                foreach ($keywords as $keyword) {
                    $params = [
                        'access_token' => $token,
                        'v' => '5.199',
                        'q' => $keyword,
                        'count' => $count,
                        'offset' => $offset,
                        'end_time' => $current_end_time
                    ];

                    $response = vkApiRequest('newsfeed.search', $params);

                    if (isset($response['response']['items'])) {
                        foreach ($response['response']['items'] as $item) {
                            $containsAllKeywords = true;
                            foreach ($keywords as $kw) {
                                if (!preg_match('/\b' . preg_quote($kw, '/') . '\b/ui', $item['text'])) {
                                    $containsAllKeywords = false;
                                    break;
                                }
                            }
                            if ($containsAllKeywords) {
                                $link = 'https://vk.com/feed?w=wall' . $item['owner_id'] . '_' . $item['id'];
                                if (!in_array($link, $existingLinks)) {
                                    $highlightedText = $item['text'];
                                    foreach ($keywords as $kw) {
                                        $highlightedText = preg_replace('/\b(' . preg_quote($kw, '/') . ')\b/ui', '<mark>$1</mark>', $highlightedText);
                                    }

                                    $author = 'Пользователь';
                                    $avatar = 'https://vk.com/images/camera_50.png';
                                    if ($item['owner_id'] < 0) {
                                        $groupInfo = getVkGroupInfo(abs($item['owner_id']), $token);
                                        if ($groupInfo) {
                                            $author = $groupInfo['name'];
                                            $avatar = $groupInfo['photo_50'];
                                        } else {
                                            $author = 'Группа';
                                            $avatar = 'https://vk.com/images/community_50.png';
                                        }
                                    } else {
                                        $userInfo = getVkUserInfo($item['owner_id'], $token);
                                        if ($userInfo) {
                                            $author = $userInfo['first_name'] . ' ' . $userInfo['last_name'];
                                            $avatar = $userInfo['photo_50'];
                                        }
                                    }

                                    $newResults[] = [
                                        'text' => $highlightedText,
                                        'link' => $link,
                                        'avatar' => $avatar,
                                        'author' => $author,
                                        'date' => date('d.m.Y H:i', $item['date']),
                                        'from' => $item['from_id'] > 0 ? 'Пользователь' : 'Группа'
                                    ];
                                    $existingLinks[] = $link;
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($groupIds as $groupId) {
                    $params = [
                        'access_token' => $token,
                        'v' => '5.131',
                        'owner_id' => '-' . $groupId,
                        'count' => $count,
                        'offset' => $offset
                    ];
                    $response = vkApiRequest('wall.get', $params);

                    if (isset($response['response']['items'])) {
                        foreach ($response['response']['items'] as $item) {
                            $containsAllKeywords = true;
                            foreach ($keywords as $kw) {
                                if (!preg_match('/\b' . preg_quote($kw, '/') . '\b/ui', $item['text'])) {
                                    $containsAllKeywords = false;
                                    break;
                                }
                            }
                            if ($containsAllKeywords) {
                                $link = 'https://vk.com/wall' . $item['owner_id'] . '_' . $item['id'];
                                if (!in_array($link, $existingLinks)) {
                                    $highlightedText = $item['text'];
                                    foreach ($keywords as $kw) {
                                        $highlightedText = preg_replace('/\b(' . preg_quote($kw, '/') . ')\b/ui', '<mark>$1</mark>', $highlightedText);
                                    }

                                    $author = 'Пользователь';
                                    $avatar = 'https://vk.com/images/camera_50.png';
                                    if ($item['owner_id'] < 0) {
                                        $groupInfo = getVkGroupInfo(abs($item['owner_id']), $token);
                                        if ($groupInfo) {
                                            $author = $groupInfo['name'];
                                            $avatar = $groupInfo['photo_50'];
                                        } else {
                                            $author = 'Группа';
                                            $avatar = 'https://vk.com/images/community_50.png';
                                        }
                                    } else {
                                        $userInfo = getVkUserInfo($item['owner_id'], $token);
                                        if ($userInfo) {
                                            $author = $userInfo['first_name'] . ' ' . $userInfo['last_name'];
                                            $avatar = $userInfo['photo_50'];
                                        }
                                    }

                                    $newResults[] = [
                                        'text' => $highlightedText,
                                        'link' => $link,
                                        'avatar' => $avatar,
                                        'author' => $author,
                                        'date' => date('d.m.Y H:i', $item['date']),
                                        'from' => $item['from_id'] > 0 ? 'Пользователь' : 'Группа'
                                    ];
                                    $existingLinks[] = $link;
                                }
                            }
                        }
                    }
                }
            }
        
        } elseif ($socialNetwork == 'telegram') {
            foreach ($groupIds as $groupId) {
                if (strpos($groupId, '@') === 0) {
                    $groupide = $groupId;
                    $groupId = getChatId($token, $groupId);
                }

                $url = 'https://api.telegram.org/bot' . $token . '/getUpdates';
                $response = file_get_contents($url);
                $updates = json_decode($response, true);

                if ($updates['ok']) {
                    foreach ($updates['result'] as $update) {
                        if (isset($update['message']) && $update['message']['chat']['id'] == $groupId) {
                            if (isset($update['message']['text'])) {
                                $containsAllKeywords = true;
                                foreach ($keywords as $kw) {
                                    if (!preg_match('/\b' . preg_quote($kw, '/') . '\b/ui', $update['message']['text'])) {
                                        $containsAllKeywords = false;
                                        break;
                                    }
                                }
                                if ($containsAllKeywords) {
                                    $groupide = str_replace("@", "", $groupide);
                                    $link = 'https://t.me/' . $groupide . '/' . $update['message']['message_id'];
                                    if (!in_array($link, $existingLinks)) {
                                        $highlightedText = $update['message']['text'];
                                        foreach ($keywords as $kw) {
                                            $highlightedText = preg_replace('/\b(' . preg_quote($kw, '/') . ')\b/ui', '<mark>$1</mark>', $highlightedText);
                                        }
                                        $newResults[] = [
                                            'text' => $highlightedText,
                                            'link' => $link,
                                            'avatar' => 'https://telegram.org/img/t_logo.png', // Пример аватарки
                                            'author' => $update['message']['chat']['title'], // Название группы или автора
                                            'date' => date('d.m.Y H:i', $update['message']['date']), // Дата поста
                                            'from' => $update['message']['from']['username'] // От кого
                                        ];
                                        $existingLinks[] = $link;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $current_start_time = $current_end_time + 1;
    }

    echo json_encode($newResults);
    exit;
} elseif ($socialNetwork == 'ok') {
    $ok_token = $_POST['ok_token'];
    $ok_public_key = $_POST['ok_public_key'];
    $ok_secret_key = $_POST['ok_secret_key'];
    
    if (!empty($ok_token) {
        foreach ($keywords as $keyword) {
            $params = [
                'method' => 'search.search',
                'query' => $keyword,
                'count' => $count,
                'fields' => 'group.*,user.*',
                'types' => 'GROUP_TOPIC,USER_TOPIC'
            ];
            
            $response = okApiRequest('search.search', $params, $ok_token, $ok_public_key, $ok_secret_key);
            
            if (isset($response['topics'])) {
                foreach ($response['topics'] as $topic) {
                    $containsAllKeywords = true;
                    foreach ($keywords as $kw) {
                        if (!preg_match('/\b'.preg_quote($kw, '/').'\b/ui', $topic['text'])) {
                            $containsAllKeywords = false;
                            break;
                        }
                    }
                    
                    if ($containsAllKeywords) {
                        $link = 'https://ok.ru/group/'.$topic['group']['uid'].'/topic/'.$topic['id'];
                        if (!in_array($link, $existingLinks)) {
                            $highlightedText = $topic['text'];
                            foreach ($keywords as $kw) {
                                $highlightedText = preg_replace('/\b('.preg_quote($kw, '/').')\b/ui', '<mark>$1</mark>', $highlightedText);
                            }
                            
                            $author = isset($topic['user']) ? $topic['user']['name'] : 'Аноним';
                            $avatar = isset($topic['user']) ? $topic['user']['pic_1'] : 'https://ok.ru/images/default_user.png';
                            
                            $newResults[] = [
                                'text' => $highlightedText,
                                'link' => $link,
                                'avatar' => $avatar,
                                'author' => $author,
                                'date' => date('d.m.Y H:i', strtotime($topic['created_at'])),
                                'from' => isset($topic['group']) ? $topic['group']['name'] : 'Личный блог'
                            ];
                            $existingLinks[] = $link;
                        }
                    }
                }
            }
        }
    }
}
?>