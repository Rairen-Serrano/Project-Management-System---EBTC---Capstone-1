// When getting project personnel
$stmt = $pdo->prepare("
    SELECT u.* 
    FROM users u
    JOIN project_assignees pa ON u.user_id = pa.user_id
    WHERE pa.project_id = ?
"); 