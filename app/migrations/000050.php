<?php
// Login attempt tracking for IP-based rate limiting (5 fails per 15 min).
// One row per (ip, username) pair, updated on each failed attempt.
$tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='login_attempts'");
if (!$tableExists) {
    $db->exec("CREATE TABLE login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL,
        username TEXT NOT NULL DEFAULT '',
        attempts INTEGER NOT NULL DEFAULT 0,
        last_attempt TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $db->exec("CREATE INDEX idx_login_attempts_ip ON login_attempts(ip)");
    $db->exec("CREATE INDEX idx_login_attempts_last_attempt ON login_attempts(last_attempt)");
}
