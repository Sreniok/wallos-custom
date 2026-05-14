<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$allChannels = ['email', 'ntfy', 'webhook', 'discord', 'telegram', 'gotify', 'pushover', 'pushplus', 'mattermost', 'serverchan'];

$firstNotificationEnabled = !empty($data['first_notification_enabled']) ? 1 : 0;
$secondNotificationEnabled = !empty($data['second_notification_enabled']) ? 1 : 0;
$secondNotificationDays = isset($data['second_notification_days']) ? $data['second_notification_days'] : 0;

$channelFlags = [];
foreach ($allChannels as $ch) {
    $channelFlags['first_notification_' . $ch]  = !empty($data['first_notification_' . $ch])  ? 1 : 0;
    $channelFlags['second_notification_' . $ch] = !empty($data['second_notification_' . $ch]) ? 1 : 0;
}

$anySecondChannelEnabled = false;
foreach ($allChannels as $ch) {
    if (!empty($channelFlags['second_notification_' . $ch])) {
        $anySecondChannelEnabled = true;
        break;
    }
}

if (
    !isset($data["days"]) || $data['days'] === '' || !is_numeric($data['days']) || (int) $data['days'] < 0 ||
    $secondNotificationDays === '' || !is_numeric($secondNotificationDays) || (int) $secondNotificationDays < 0 ||
    ($secondNotificationEnabled && !$anySecondChannelEnabled)
) {
    $response = [
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ];
    echo json_encode($response);
} else {
    $days = (int) $data["days"];
    $query = "SELECT COUNT(*) FROM notification_settings WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":userId", $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($result === false) {
        $response = [
            "success" => false,
            "message" => translate('error_saving_notifications', $i18n)
        ];
        echo json_encode($response);
    } else {
        $row = $result->fetchArray();
        $count = $row[0];

        $columnList = implode(', ', array_keys($channelFlags));
        $placeholderList = implode(', ', array_map(fn($k) => ':' . $k, array_keys($channelFlags)));

        if ($count == 0) {
            $query = "INSERT INTO notification_settings (
                          days, first_notification_enabled, second_notification_enabled, second_notification_days,
                          {$columnList}, user_id
                      ) VALUES (
                          :days, :firstNotificationEnabled, :secondNotificationEnabled, :secondNotificationDays,
                          {$placeholderList}, :userId
                      )";
        } else {
            $setClauses = implode(",\n                          ", array_map(fn($k) => "{$k} = :{$k}", array_keys($channelFlags)));
            $query = "UPDATE notification_settings
                      SET days = :days,
                          first_notification_enabled = :firstNotificationEnabled,
                          second_notification_enabled = :secondNotificationEnabled,
                          second_notification_days = :secondNotificationDays,
                          {$setClauses}
                      WHERE user_id = :userId";
        }

        $stmt = $db->prepare($query);
        $stmt->bindValue(':days', $days, SQLITE3_INTEGER);
        $stmt->bindValue(':firstNotificationEnabled', $firstNotificationEnabled, SQLITE3_INTEGER);
        $stmt->bindValue(':secondNotificationEnabled', $secondNotificationEnabled, SQLITE3_INTEGER);
        $stmt->bindValue(':secondNotificationDays', (int) $secondNotificationDays, SQLITE3_INTEGER);
        foreach ($channelFlags as $key => $value) {
            $stmt->bindValue(':' . $key, $value, SQLITE3_INTEGER);
        }
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

        if ($stmt->execute()) {
            $response = [
                "success" => true,
                "message" => translate('notifications_settings_saved', $i18n)
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "message" => translate('error_saving_notifications', $i18n)
            ];
            echo json_encode($response);
        }
    }
}
