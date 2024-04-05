<?php
include "baseInfo.php";
include "config.php";
$connection = new mysqli('localhost', $dbUserName, $dbPassword, $dbName);
if ($connection->connect_error) {
    exit("error " . $connection->connect_error);
}
$connection->set_charset("utf8mb4");
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    if (preg_match('/[a-zA-Z0-9]{30}/', $token)) {
        $stmt = $connection->prepare("SELECT * FROM `orders_list` WHERE `token` = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $remark = $info['remark'];
        $uuid = $info['uuid'] ?? "0";
        $server_id = $info['server_id'];
        $inbound_id = $info['inbound_id'];
        $protocol = $info['protocol'];
        $rahgozar = $info['rahgozar'];

        $file_id = $info['fileid'];

        $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $file_detail = $stmt->get_result()->fetch_assoc();
        $customPath = $file_detail['custom_path'];
        $customPort = $file_detail['custom_port'];
        $customSni = $file_detail['custom_sni'];

        $stmt = $connection->prepare("SELECT * FROM `server_config` WHERE id=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $server_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $serverType = $server_info['type'];

        $response = getJson($server_id)->obj;
        if ($inbound_id == 0) {
            foreach ($response as $row) {
                $clientInbound = $row->id;
                $clients = json_decode($row->settings)->clients;
                if ($clients[0]->id == $uuid || $clients[0]->password == $uuid) {
                    $total = $row->total;
                    $port = $row->port;
                    $up = $row->up;
                    $down = $row->down;
                    $netType = json_decode($row->streamSettings)->network;
                    $security = json_decode($row->streamSettings)->security;
                    break;
                }
            }
        } else {
            foreach ($response as $row) {
                if ($row->id == $inbound_id) {
                    $clientInbound = $row->id;
                    $port = $row->port;
                    $netType = json_decode($row->streamSettings)->network;
                    $security = json_decode($row->streamSettings)->security;

                    $clientsStates = $row->clientStats;
                    $clients = json_decode($row->settings)->clients;
                    foreach ($clients as $key => $client) {
                        if ($client->id == $uuid || $client->password == $uuid) {
                            $email = $client->email;
                            $emails = array_column($clientsStates, 'email');
                            $emailKey = array_search($email, $emails);

                            $total = $clientsStates[$emailKey]->total;
                            $up = $clientsStates[$emailKey]->up;
                            $enable = $clientsStates[$emailKey]->enable;
                            $down = $clientsStates[$emailKey]->down;
                            break;
                        }
                    }
                }
            }
        }
        $totalUsed = round(($up + $down) / 1073741824, 2);
        $total = round($total / 1073741824, 2);
        $daysLeft = round(($info['expire_date'] - time()) / 86400, 1);
        $link = json_decode($info['link'])[0];

        if (preg_match('/vmess/', $link)) {
            $link_info = json_decode(base64_decode(str_replace('vmess://', '', $link)));
            $uniqid = $link_info->id;
            $port = $link_info->port;
            $netType = $link_info->net;
        } else {
            $link_info = parse_url($link);
            $panel_ip = $link_info['host'];
            $uniqid = $link_info['user'];
            $protocol;
        }

        $linkTimeInfo = $link_info;
        $linkTimeInfo->ps = "⏰ زمان: ($daysLeft روز) باقی مانده";
        $linkTime = "vmess://" . base64_encode(json_encode($linkTimeInfo));

        $linkTrafficInfo = $link_info;
        $linkTrafficInfo->ps = ($total > 101) ? " حجم: ♾" : " حجم: ($total گیگ) باقی مانده";
        $linkTraffic = "vmess://" . base64_encode(json_encode($linkTrafficInfo));

        $vraylink = getConnectionLink($server_id, $uniqid, $protocol, $remark, $port, $netType, $inbound_id, $rahgozar, $customPath, $customPort, $customSni);
        $links = implode("\n", $vraylink);
        echo base64_encode($links . "\n" . $linkTime . "\n" . $linkTraffic);
        exit();
    }
}
echo "Wrong token";
?>
