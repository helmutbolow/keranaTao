<?php
require_once __DIR__.'/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function get_tables() {
  $pdo = db();
  $stmt = $pdo->query("""
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema='public' AND table_type='BASE TABLE'
    ORDER BY table_name
  """);
  return array_map(fn($r) => $r['table_name'], $stmt->fetchAll());
}

function get_columns($table) {
  $pdo = db();
  $stmt = $pdo->prepare("""
    SELECT column_name, data_type, is_nullable, column_default
    FROM information_schema.columns
    WHERE table_schema='public' AND table_name=:t
    ORDER BY ordinal_position
  """);
  $stmt->execute([':t' => $table]);
  return $stmt->fetchAll();
}

function infer_relations($table) {
  // convention: *_uuid -> table plural match before _uuid, or exact table name if provided
  $cols = get_columns($table);
  $rels = [];
  foreach ($cols as $c) {
    $name = $c['column_name'];
    if (str_ends_with($name, '_uuid') && $name !== 'uuid') {
      $base = substr($name, 0, -5); // remove _uuid
      // try plural and exact
      $candidates = [$base.'s', $base];
      foreach ($candidates as $cand) {
        // does cand table exist and have 'uuid' column?
        $tables = get_tables();
        if (in_array($cand, $tables)) {
          $tcols = array_column(get_columns($cand), 'column_name');
          if (in_array('uuid', $tcols)) {
            $rels[$name] = $cand;
            break;
          }
        }
      }
    }
  }
  return $rels;
}

function label_column($table) {
  $prio = ['name','customer','employee','supplier_name','bank','public_holiday','description','invoice_out_number','invoice_in_number','file_name','key'];
  $cols = array_column(get_columns($table), 'column_name');
  foreach ($prio as $p) if (in_array($p,$cols)) return $p;
  if (in_array('uuid',$cols)) return 'uuid';
  return $cols[0] ?? 'id';
}

function fetch_options($table) {
  $pdo = db();
  $label = label_column($table);
  $sql = "SELECT uuid AS value, " . $label . " AS label FROM \"$table\" ORDER BY label LIMIT 500";
  try {
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
  } catch (Exception $e) {
    return [];
  }
}

function save_row($table, $data, $id=null) {
  $pdo = db();
  // Filter to known columns skipping id
  $cols = array_column(get_columns($table), 'column_name');
  $cols = array_values(array_filter($cols, fn($c)=>$c!=='id'));
  $data = array_intersect_key($data, array_flip($cols));

  if ($id) {
    $set = implode(', ', array_map(fn($c)=>"\"$c\"=:$c", array_keys($data)));
    $sql = "UPDATE \"$table\" SET $set WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $data['id'] = $id;
    $stmt->execute($data);
    return $id;
  } else {
    $keys = implode(', ', array_map(fn($c)=>"\"$c\"", array_keys($data)));
    $vals = implode(', ', array_map(fn($c)=>":$c", array_keys($data)));
    if (!$keys) { $keys='"uuid"'; $vals=':uuid'; $data=['uuid'=>bin2hex(random_bytes(16))]; }
    $sql = "INSERT INTO \"$table\" ($keys) VALUES ($vals) RETURNING id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    return $stmt->fetchColumn();
  }
}
