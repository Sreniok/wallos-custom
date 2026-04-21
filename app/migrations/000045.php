<?php

$db->exec('ALTER TABLE subscriptions ADD COLUMN regular_price REAL');
$db->exec('ALTER TABLE subscriptions ADD COLUMN last_payment_date DATE');
$db->exec('ALTER TABLE subscriptions ADD COLUMN ended_at DATE');
$db->exec('ALTER TABLE subscriptions ADD COLUMN completion_notified INTEGER DEFAULT 0');
