<?php

$db->exec('INSERT INTO notification_settings (days, user_id)
    SELECT 1, user.id
    FROM user
    WHERE NOT EXISTS (
        SELECT 1
        FROM notification_settings
        WHERE notification_settings.user_id = user.id
    )');
