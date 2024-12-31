<?php

$time = strtotime($_POST['new_time']);
$hour = (int)date('H', $time);
$minute = (int)date('i', $time);

if ($hour < 9 || $hour > 15 || ($hour === 15 && $minute > 0)) {
    echo json_encode(['success' => false, 'message' => 'Please select a time between 9:00 AM and 3:00 PM']);
    exit;
}

if ($minute !== 0 && $minute !== 30) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid 30-minute time slot']);
    exit;
}

$dayOfWeek = date('w', strtotime($_POST['new_date']));
if ($dayOfWeek == 0 || $dayOfWeek == 6) {
    echo json_encode(['success' => false, 'message' => 'Please select a date between Monday and Friday']);
    exit;
} 