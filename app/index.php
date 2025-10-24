<?php
require_once __DIR__ . '/helpers.php';
$cfg = require __DIR__ . '/config.php';
$pdo = db();

$entity = $_GET['entity'] ?? null;
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

function layout($title, $content) {
  global $cfg;
  echo "<!doctype html><html><head><meta name='viewport' content='width=device-width, initial-scale=1'>";
  echo "<title>" . h($cfg['app']['title']) . " · " . h($title) . "</title>";
  echo "<link rel='stylesheet' href='/assets/styles.css'>";
  echo "</head><body><header><a href='/' class='brand'>" . h($cfg['app']['title']) . "</a></header><main>";
  echo $content;
  echo "</main><footer><small>Kerana Admin • PHP + Postgres</small></footer></body></html>";
}

if (!$entity) {
  $tables = get_tables();
  $cards = "<div class='grid'>";
  foreach ($tables as $t) {
    $cards .= "<a class='card' href='/?entity=" . urlencode($t) . "'>" . h($t) . "</a>";
  }
  $cards .= "</div>";
  layout('Entities', "<h1>Entities</h1>" . $cards);
  exit;
}

if ($action === 'list') {
  $cols = get_columns($entity);
  $labelCols = array_slice(array_column($cols, 'column_name'), 0, 6);
  $page = max(1, (int)($_GET['page'] ?? 1));
  $per = 20;
  $off = ($page - 1) * $per;
  $q = $_GET['q'] ?? '';
  $where = '';
  $params = [];

  if ($q) {
    $likeCols = array_slice(array_column($cols, 'column_name'), 0, 5);
    $clauses = [];
    foreach ($likeCols as $c) {
      $clauses[] = '"' . $c . '" ILIKE :q';
    }
    $where = 'WHERE ' . implode(' OR ', $clauses);
    $params[':q'] = '%' . $q . '%';
  }

  $stmt = $pdo->prepare('SELECT * FROM "' . $entity . '" ' . $where . ' ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . $off);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();

  ob_start();
  echo "<div class='actions'>
          <a class='btn' href='/?entity=" . h($entity) . "&action=create'>+ New</a>
          <form method='get' class='search'>
            <input type='hidden' name='entity' value='" . h($entity) . "'>
            <input name='q' placeholder='Search' value='" . h($q) . "'>
            <button>Search</button>
          </form>
        </div>";

  echo "<div class='table-scroll'><table><thead><tr>";
  foreach ($labelCols as $c) echo '<th>' . h($c) . '</th>';
  echo "<th></th></tr></thead><tbody>";
  foreach ($rows as $r) {
    echo '<tr>';
    foreach ($labelCols as $c) echo '<td>' . h($r[$c] ?? '') . '</td>';
    echo "<td class='row-actions'>
            <a href='/?entity=" . h($entity) . "&action=edit&id=" . h($r['id']) . "'>Edit</a> ·
            <a class='danger' href='/?entity=" . h($entity) . "&action=delete&id=" . h($r['id']) . "' onclick='return confirm(\"Delete?\")'>Delete</a>
          </td>";
    echo '</tr>';
  }
  echo '</tbody></table></div>';

  layout(ucfirst($entity) . ' list', ob_get_clean());
  exit;
}