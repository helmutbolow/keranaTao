<?php
require_once __DIR__.'/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function get_tables() {
  $pdo = db();
  $sql = "
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
    ORDER BY table_name
  ";
  $stmt = $pdo->query($sql);
  return array_map(fn($r) => $r['table_name'], $stmt->fetchAll());
}

function get_columns($table) {
  $pdo = db();
  $sql = "
    SELECT column_name, data_type, is_nullable, column_default
    FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = :t
    ORDER BY ordinal_position
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':t' => $table]);
  return $stmt->fetchAll();
}

// Optional explicit relation overrides (column => table)
function relation_overrides(): array {
  return [
    'customer_uuid' => 'customers',
    'contract_uuid' => 'contracts',
    'timesheet_employee_uuid' => 'employees',
    'customer_employee_contract_uuid' => 'employee_contracts',
    'bank_uuid' => 'banks',
    'invoice_uuid' => 'invoices_in',
    'reimbursement_invoice_in_uuid' => 'invoices_in',
    'reimbursement_transaction_uuid' => 'transactions',
  ];
}

function infer_relations($table) {
  $cols = get_columns($table);
  $rels = [];
  $over = relation_overrides();
  $tables = get_tables();

  foreach ($cols as $c) {
    $name = $c['column_name'];
    if ($name === 'uuid') continue;

    // explicit override wins
    if (isset($over[$name])) {
      $target = $over[$name];
      if (in_array($target, $tables, true)) $rels[$name] = $target;
      continue;
    }

    // convention *_uuid
    if (str_ends_with($name, '_uuid')) {
      $base = substr($name, 0, -5);
      foreach ([$base.'s', $base] as $cand) {
        if (in_array($cand, $tables, true)) {
          $tcols = array_column(get_columns($cand), 'column_name');
          if (in_array('uuid', $tcols, true)) { $rels[$name] = $cand; break; }
        }
      }
    }
  }
  return $rels;
}

function label_column($table) {
  $prio = ['name','customer','employee','supplier_name','bank','public_holiday','description','invoice_out_number','invoice_in_number','file_name','key','title'];
  $cols = array_column(get_columns($table), 'column_name');
  foreach ($prio as $p) { if (in_array($p,$cols,true)) return $p; }
  if (in_array('uuid',$cols,true)) return 'uuid';
  return $cols[0] ?? 'id';
}

// cache for option lookups
$GLOBALS['_optcache'] = [];

function fetch_options($table) {
  $pdo = db();
  $label = label_column($table);
  $sql = 'SELECT uuid AS value, "'.$label.'" AS label FROM "'.$table.'" ORDER BY label LIMIT 2000';
  try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    // fill cache
    foreach ($rows as $r) { $GLOBALS['_optcache'][$table][$r['value']] = $r['label']; }
    return $rows;
  } catch (Throwable $e) {
    return [];
  }
}

function option_label(string $table, ?string $uuid): string {
  if (!$uuid) return '';
  if (isset($GLOBALS['_optcache'][$table][$uuid])) return (string)$GLOBALS['_optcache'][$table][$uuid];
  // lazy single fetch
  $pdo = db();
  $label = label_column($table);
  $sql = 'SELECT "'.$label.'" AS label FROM "'.$table.'" WHERE uuid=:u LIMIT 1';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':u'=>$uuid]);
  $label = (string)($stmt->fetchColumn() ?: '');
  if (!isset($GLOBALS['_optcache'][$table])) $GLOBALS['_optcache'][$table] = [];
  $GLOBALS['_optcache'][$table][$uuid] = $label;
  return $label;
}

function save_row($table, $data, $id = null) {
  $pdo = db();
  $cols = array_column(get_columns($table), 'column_name');
  $cols = array_values(array_filter($cols, fn($c) => $c !== 'id'));
  $data = array_intersect_key($data, array_flip($cols));

  if ($id) {
    if (empty($data)) return $id;
    $set = [];
    foreach (array_keys($data) as $c) { $set[] = '"'.$c.'"=:'.$c; }
    $sql = 'UPDATE "'.$table.'" SET '.implode(', ', $set).' WHERE id=:id';
    $stmt = $pdo->prepare($sql);
    $data['id'] = $id;
    $stmt->execute($data);
    return $id;
  } else {
    if (empty($data)) {
      $data = ['uuid' => bin2hex(random_bytes(16))];
      $keys = '"uuid"';
      $vals = ':uuid';
    } else {
      if (!isset($data['uuid'])) $data['uuid'] = bin2hex(random_bytes(16));
      $keys = implode(', ', array_map(fn($c)=>'"'.$c.'"', array_keys($data)));
      $vals = implode(', ', array_map(fn($c)=>':'.$c, array_keys($data)));
    }
    $sql = 'INSERT INTO "'.$table.'" ('.$keys.') VALUES ('.$vals.') RETURNING id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    return (int)$stmt->fetchColumn();
  }
}