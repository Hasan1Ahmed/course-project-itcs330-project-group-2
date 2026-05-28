<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!is_array($data)) {
    $data = [];
}

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$weekId = $_GET['week_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;

function getAllWeeks(PDO $db): void
{
    $query = 'SELECT id, title, start_date, description, links, created_at FROM weeks';
    $params = [];

    $search = trim($_GET['search'] ?? '');

    if ($search !== '') {
        $query .= ' WHERE title LIKE :search OR description LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $allowedSorts = ['title', 'start_date'];
    $sort = $_GET['sort'] ?? 'start_date';

    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'start_date';
    }

    $order = strtolower($_GET['order'] ?? 'asc');

    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'asc';
    }

    $query .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();

    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weeks as &$week) {
        $week['links'] = json_decode($week['links'] ?? '[]', true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks]);
}

function getWeekById(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid week id.'], 400);
    }

    $stmt = $db->prepare('SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?');
    $stmt->execute([(int) $id]);

    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$week) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $week['links'] = json_decode($week['links'] ?? '[]', true) ?? [];

    sendResponse(['success' => true, 'data' => $week]);
}

function createWeek(PDO $db, array $data): void
{
    if (
        empty(trim((string) ($data['title'] ?? ''))) ||
        empty(trim((string) ($data['start_date'] ?? '')))
    ) {
        sendResponse(['success' => false, 'message' => 'Title and start_date are required.'], 400);
    }

    $title = sanitizeInput((string) $data['title']);
    $startDate = trim((string) $data['start_date']);
    $description = sanitizeInput((string) ($data['description'] ?? ''));

    if (!validateDate($startDate)) {
        sendResponse(['success' => false, 'message' => 'Invalid start_date format. Use YYYY-MM-DD.'], 400);
    }

    $links = isset($data['links']) && is_array($data['links'])
        ? array_values($data['links'])
        : [];

    $linksJson = json_encode($links);

    $stmt = $db->prepare('INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)');
    $stmt->execute([$title, $startDate, $description, $linksJson]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Week created successfully.',
            'id' => (int) $db->lastInsertId(),
        ], 201);
    }

    sendResponse(['success' => false, 'message' => 'Week could not be created.'], 500);
}

function updateWeek(PDO $db, array $data): void
{
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Week id is required.'], 400);
    }

    $id = (int) $data['id'];

    $exists = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $exists->execute([$id]);

    if (!$exists->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $clauses = [];
    $values = [];

    if (array_key_exists('title', $data)) {
        $title = sanitizeInput((string) $data['title']);

        if ($title === '') {
            sendResponse(['success' => false, 'message' => 'Title cannot be empty.'], 400);
        }

        $clauses[] = 'title = ?';
        $values[] = $title;
    }

    if (array_key_exists('start_date', $data)) {
        $startDate = trim((string) $data['start_date']);

        if (!validateDate($startDate)) {
            sendResponse(['success' => false, 'message' => 'Invalid start_date format. Use YYYY-MM-DD.'], 400);
        }

        $clauses[] = 'start_date = ?';
        $values[] = $startDate;
    }

    if (array_key_exists('description', $data)) {
        $clauses[] = 'description = ?';
        $values[] = sanitizeInput((string) $data['description']);
    }

    if (array_key_exists('links', $data)) {
        $links = is_array($data['links']) ? array_values($data['links']) : [];
        $clauses[] = 'links = ?';
        $values[] = json_encode($links);
    }

    if (empty($clauses)) {
        sendResponse(['success' => false, 'message' => 'No fields to update.'], 400);
    }

    $values[] = $id;

    $query = 'UPDATE weeks SET ' . implode(', ', $clauses) . ' WHERE id = ?';
    $stmt = $db->prepare($query);
    $ok = $stmt->execute($values);

    if ($ok) {
        sendResponse(['success' => true, 'message' => 'Week updated successfully.']);
    }

    sendResponse(['success' => false, 'message' => 'Week could not be updated.'], 500);
}

function deleteWeek(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid week id.'], 400);
    }

    $exists = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $exists->execute([(int) $id]);

    if (!$exists->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM weeks WHERE id = ?');
    $stmt->execute([(int) $id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted successfully.']);
    }

    sendResponse(['success' => false, 'message' => 'Week could not be deleted.'], 500);
}

function getCommentsByWeek(PDO $db, $weekId): void
{
    if ($weekId === null || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Invalid week id.'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, week_id, author, text, created_at
         FROM comments_week
         WHERE week_id = ?
         ORDER BY created_at ASC'
    );

    $stmt->execute([(int) $weekId]);

    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment(PDO $db, array $data): void
{
    if (
        !isset($data['week_id']) ||
        !is_numeric($data['week_id']) ||
        empty(trim((string) ($data['author'] ?? ''))) ||
        empty(trim((string) ($data['text'] ?? '')))
    ) {
        sendResponse(['success' => false, 'message' => 'week_id, author, and text are required.'], 400);
    }

    $weekId = (int) $data['week_id'];
    $author = sanitizeInput((string) $data['author']);
    $text = sanitizeInput((string) $data['text']);

    $exists = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $exists->execute([$weekId]);

    if (!$exists->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $stmt = $db->prepare('INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)');
    $stmt->execute([$weekId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = (int) $db->lastInsertId();

        $select = $db->prepare(
            'SELECT id, week_id, author, text, created_at
             FROM comments_week
             WHERE id = ?'
        );

        $select->execute([$newId]);
        $comment = $select->fetch(PDO::FETCH_ASSOC);

        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id' => $newId,
            'data' => $comment,
        ], 201);
    }

    sendResponse(['success' => false, 'message' => 'Comment could not be created.'], 500);
}

function deleteComment(PDO $db, $commentId): void
{
    if ($commentId === null || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment id.'], 400);
    }

    $exists = $db->prepare('SELECT id FROM comments_week WHERE id = ?');
    $exists->execute([(int) $commentId]);

    if (!$exists->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM comments_week WHERE id = ?');
    $stmt->execute([(int) $commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
    }

    sendResponse(['success' => false, 'message' => 'Comment could not be deleted.'], 500);
}

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif ($id !== null) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateWeek($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal server error.'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal server error.'], 500);
}

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);

    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
