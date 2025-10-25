<?php
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__.'/helpers.php';
$cfg = require __DIR__.'/config.php';
$pdo = db();

$entity = $_GET['entity'] ?? null;
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

function layout($title, $content) {
  global $cfg;
  echo "<!doctype html><html><head><meta name='viewport' content='width=device-width, initial-scale=1'>";
  echo "<title>".h($cfg['app']['title'])." · ".h($title)."</title>";
  echo "<link rel='stylesheet' href='/assets/styles.css'>";
  echo "</head><body><header><a href='/' class='brand'>".h($cfg['app']['title'])."</a></header><main>";
  if (!empty($_GET['saved'])) echo "<div class='flash ok'>Saved.</div>";
  echo $content;
  echo "</main><footer><small>Kerana Admin • PHP + Postgres</small></footer></body></html>";
}

if (!$entity) {
  $tables = get_tables();
  $cards = "<div class='grid'>";
  foreach ($tables as $t) {
    $cards .= "<a class='card' href='/?entity=".urlencode($t)."'>".h($t)."</a>";
  }
  $cards .= "</div>";
  layout('Entities', "<h1>Entities</h1>".$cards);
  exit;
}

if ($action === 'list') {
  $cols = get_columns($entity);
  $rels = infer_relations($entity);
  $labelCols = array_slice(array_column($cols, 'column_name'), 0, 6);

  // Preload options for related tables
  foreach ($rels as $col=>$target) fetch_options($target);

  $page = max(1, (int)($_GET['page'] ?? 1));
  $per = 20; $off = ($page-1)*$per;
  $q = $_GET['q'] ?? '';
  $where = ''; $params = [];

  if ($q) {
    $likeCols = array_slice(array_column($cols,'column_name'),0,5);
    $clauses = [];
    foreach ($likeCols as $cn) { $clauses[] = '"'.$cn.'" ILIKE :q'; }
    $where = 'WHERE '.implode(' OR ', $clauses);
    $params[':q'] = '%'.$q.'%';
  }

  $sqlList = 'SELECT * FROM "'.$entity.'" '.$where.' ORDER BY id DESC LIMIT '.$per.' OFFSET '.$off;
  $stmt = $pdo->prepare($sqlList);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();

  $html = "<div class='actions'>
    <a class='btn' href='/?entity=".h($entity)."&action=create'>+ New</a>
    <form method='get' class='search'>
      <input type='hidden' name='entity' value='".h($entity)."'>
      <input name='q' placeholder='Search' value='".h($q)."'>
      <button>Search</button>
    </form>
  </div>";

  $html .= "<div class='table-scroll'><table><thead><tr>";
  foreach ($labelCols as $c) $html .= '<th>'.h($c).'</th>';
  $html .= "<th></th></tr></thead><tbody>";

  foreach ($rows as $r) {
    $html .= '<tr>';
    foreach ($labelCols as $c) {
      $val = $r[$c] ?? '';
      if (isset($rels[$c])) {
        $val = option_label($rels[$c], $val);
      }
      $html .= '<td>'.h($val).'</td>';
    }
    $html .= "<td class='row-actions'>
      <a href='/?entity=".h($entity)."&action=edit&id=".h($r['id'])."'>Edit</a> ·
      <a class='danger' href='/?entity=".h($entity)."&action=delete&id=".h($r['id'])."' onclick='return confirm(\"Delete?\")'>Delete</a>
    </td>";
    $html .= '</tr>';
  }
  $html .= '</tbody></table></div>';

  // Pager
  $html .= "<div class='pagination'>";
  if ($page>1) $html .= "<a href='/?entity=".h($entity)."&page=".($page-1)."'>&larr; Prev</a>";
  $html .= "<span>Page ".h($page)."</span>";
  if (count($rows)===$per) $html .= "<a href='/?entity=".h($entity)."&page=".($page+1)."'>Next &rarr;</a>";
  $html .= "</div>";

  layout(ucfirst($entity).' list', $html);
  exit;
}

if ($action === 'create' || $action === 'edit') {
  $cols = get_columns($entity);
  $rels = infer_relations($entity);
  $row = [];

  if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare('SELECT * FROM "'.$entity.'" WHERE id=:id');
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch() ?: [];
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    foreach ($data as $k=>$v) if ($v==='') $data[$k]=null;
    $savedId = save_row($entity, $data, $id);
    header('Location: /?entity='.$entity.'&saved=1');
    exit;
  }

  // Preload options
  foreach ($rels as $col=>$target) fetch_options($target);

  $html = "<form method='post' class='form'>";
  foreach ($cols as $col) {
    $name = $col['column_name'];
    if ($name === 'id') continue;
    $val = $row[$name] ?? '';
    $label = h($name);
    $type = 'text';
    $lc = strtolower($name);
    if (str_contains($lc,'date') && !str_contains($lc,'updated') && !str_contains($lc,'created') && !str_contains($lc,'timestamp')) $type='date';
    if (str_contains($lc,'amount') || str_contains($lc,'price') || str_contains($lc,'salary') || str_contains($lc,'vat') || str_contains($lc,'debit') || str_contains($lc,'credit') || str_contains($lc,'total') || str_contains($lc,'balance')) $type='number';

    $html .= "<label>$label";
    if (isset($rels[$name])) {
      $opts = fetch_options($rels[$name]);
      $html .= "<select name='".h($name)."'><option value=''>—</option>";
      foreach ($opts as $o) {
        $sel = ($o['value'] === $val) ? ' selected' : '';
        $html .= "<option value='".h($o['value'])."'$sel>".h($o['label'])."</option>";
      }
      $html .= "</select>";
    } else {
      if (strlen((string)$val) > 120) {
        $html .= "<textarea name='".h($name)."' rows='3'>".h($val)."</textarea>";
      } else {
        $step = $type==='number' ? " step='0.01'" : '';
        $html .= "<input type='$type'$step name='".h($name)."' value='".h($val)."'>";
      }
    }
    $html .= "</label>";
  }
  $html .= "<div class='form-actions stick'><button class='btn'>Save</button><a class='btn ghost' href='/?entity=".h($entity)."'>Cancel</a></div>";
  $html .= "</form>";

  layout(($action==='create'?'Create ':'Edit ').$entity, $html);
  exit;
}

if ($action === 'delete' && $id) {
  $stmt = $pdo->prepare('DELETE FROM "'.$entity.'" WHERE id=:id');
  $stmt->execute([':id'=>$id]);
  header('Location: /?entity='.$entity.'&saved=1');
  exit;
}

http_response_code(404);
layout('Not found', '<p>Action not found.</p>');