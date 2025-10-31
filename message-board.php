<?php
// message-board.php
// Persistent message board with markdown, votes, dark mode, unique user IDs, and IP display

$dataFile = __DIR__ . '/messages.json';
if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));

function loadMessages($dataFile) {
    return json_decode(file_get_contents($dataFile), true);
}

function saveMessages($dataFile, $messages) {
    file_put_contents($dataFile, json_encode($messages, JSON_PRETTY_PRINT));
}

function markdownToHtml($text) {
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $text);
    $text = preg_replace('/`(.*?)`/s', '<code>$1</code>', $text);
    $text = preg_replace('/\[(.*?)\]\((https?:\/\/[^\s]+)\)/', '<a href="$2" target="_blank">$1</a>', $text);
    $text = preg_replace('/\[(https?:\/\/[^\s\]]+)\]/', '<img src="$1" alt="image" style="max-width:300px; border-radius:8px;">', $text);
    return nl2br($text);
}

// Load or assign user ID
if (!isset($_COOKIE['user_id'])) {
    $newId = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
    setcookie('user_id', $newId, time() + (365 * 24 * 60 * 60)); // 1 year
    $_COOKIE['user_id'] = $newId;
}
$userId = $_COOKIE['user_id'];
$clientIp = $_SERVER['HTTP_CLIENT_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? 'unknown';
$clientIp = explode(',', $clientIp)[0]; // use the first if comma-separated


$messages = loadMessages($dataFile);

// Handle new post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'post' && trim($_POST['message']) !== '') {
        $messages[] = [
            'id' => uniqid(),
            'user' => $userId,
            'ip' => $clientIp,
            'time' => date('Y-m-d H:i:s'),
            'text' => $_POST['message'],
            'upvotes' => 0,
            'downvotes' => 0
        ];
        saveMessages($dataFile, $messages);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'vote' && isset($_POST['id'], $_POST['vote'])) {
        foreach ($messages as &$msg) {
            if ($msg['id'] === $_POST['id']) {
                if ($_POST['vote'] === 'up') $msg['upvotes']++;
                if ($_POST['vote'] === 'down') $msg['downvotes']++;
                saveMessages($dataFile, $messages);
                echo json_encode(['status' => 'ok', 'upvotes' => $msg['upvotes'], 'downvotes' => $msg['downvotes']]);
                exit;
            }
        }
        echo json_encode(['status' => 'not_found']);
        exit;
    }
}

// Handle search
if (isset($_GET['search'])) {
    $query = strtolower($_GET['search']);
    $messages = array_filter($messages, fn($msg) =>
        str_contains(strtolower($msg['text']), $query)
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Message Board</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
<style>
body {
    font-family: Arial, sans-serif;
    background-color: var(--bg);
    color: var(--text);
    margin: 20px auto;
    max-width: 700px;
    transition: background-color 0.3s, color 0.3s;
}
:root {
    --bg: #fff;
    --text: #000;
    --card: #f0f0f0;
}
body.dark {
    --bg: #121212;
    --text: #e0e0e0;
    --card: #1e1e1e;
}
.message {
    background: var(--card);
    border-radius: 10px;
    padding: 10px 15px;
    margin-bottom: 10px;
}
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
textarea {
    width: 100%;
    height: 80px;
    border-radius: 6px;
    padding: 8px;
    background: var(--card);
    color: var(--text);
    border: 1px solid #888;
}
button {
    margin-top: 8px;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    background: #007acc;
    color: white;
    border: none;
}
.search-box {
    margin: 10px 0;
}
.vote {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-right: 10px;
    cursor: pointer;
}
.vote span {
    vertical-align: middle;
}
.meta {
    font-size: 0.8em;
    opacity: 0.7;
}
.dark-toggle {
    cursor: pointer;
    background: none;
    color: var(--text);
    border: 1px solid var(--text);
    border-radius: 6px;
    padding: 4px 10px;
}
</style>
<script>
function toggleDarkMode() {
    document.body.classList.toggle('dark');
    localStorage.setItem('darkMode', document.body.classList.contains('dark'));
}
window.onload = () => {
    if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark');
};

async function vote(id, type) {
    const form = new FormData();
    form.append('action', 'vote');
    form.append('id', id);
    form.append('vote', type);
    const res = await fetch('', { method: 'POST', body: form });
    const data = await res.json();
    if (data.status === 'ok') {
        document.getElementById('up-' + id).innerText = data.upvotes;
        document.getElementById('down-' + id).innerText = data.downvotes;
    }
}

async function submitPost() {
    const msg = document.querySelector('textarea[name="message"]').value.trim();
    if (!msg) return;
    const form = new FormData();
    form.append('action', 'post');
    form.append('message', msg);
    await fetch('', { method: 'POST', body: form });
    location.reload();
}
</script>
</head>
<body>
<header>
<h2>Message Board</h2>
<button class="dark-toggle" onclick="toggleDarkMode()">🌓</button>
</header>

<div class="search-box">
<form method="get">
<input type="text" name="search" placeholder="Search messages..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
<button type="submit">Search</button>
</form>
</div>

<form onsubmit="event.preventDefault(); submitPost();">
<textarea name="message" placeholder="Write your message..."></textarea>
<br>
<button type="submit">Post</button>
</form>

<hr>

<?php if (empty($messages)): ?>
<p>No messages yet.</p>
<?php else: ?>
<?php foreach (array_reverse($messages) as $msg): ?>
<div class="message">
<div class="meta">
<strong><?= $msg['user'] ?></strong> | <?= $msg['time'] ?> | IP: <?= htmlspecialchars($msg['ip']) ?>
</div>
<div><?= markdownToHtml($msg['text']) ?></div>
<div>
<span class="vote" onclick="vote('<?= $msg['id'] ?>', 'up')">
<span class="material-symbols-outlined">arrow_upward</span> <span id="up-<?= $msg['id'] ?>"><?= $msg['upvotes'] ?></span>
</span>
<span class="vote" onclick="vote('<?= $msg['id'] ?>', 'down')">
<span class="material-symbols-outlined">arrow_downward</span> <span id="down-<?= $msg['id'] ?>"><?= $msg['downvotes'] ?></span>
</span>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>
