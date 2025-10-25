<?php
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(0);

/*
Google Sheets (public) → Postgres, per-sheet CSV by table name
- Input: spreadsheetId
- For every BASE TABLE in schema public:
    fetch https://docs.google.com/spreadsheets/d/{ID}/gviz/tq?tqx=out:csv&sheet=<table>
    Row 1 = headers (STRICT). No guessing. No gid. No XLSX.
- Columns matched by exact lowercase names.
- UPSERT on uuid if table has uuid.
*/

function db(): PDO {
  static $pdo=null; if($pdo) return $pdo;
  $dsn = getenv('DB_DSN') ?: 'pgsql:host=db;dbname=kerana';
  $user = getenv('DB_USER') ?: 'kerana';
  $pass = getenv('DB_PASS') ?: 'kerana';
  $pdo = new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}
function qident(string $i): string { return '"'.str_replace('"','""',$i).'"'; }
function list_tables(PDO $pdo): array {
  $sql = "SELECT table_name
          FROM information_schema.tables
          WHERE table_schema='public' AND table_type='BASE TABLE'
          ORDER BY table_name";
  return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}
function table_cols(PDO $pdo,string $t): array {
  $s=$pdo->prepare("SELECT column_name
                    FROM information_schema.columns
                    WHERE table_schema='public' AND table_name=:t
                    ORDER BY ordinal_position");
  $s->execute([':t'=>$t]);
  return array_map('strval',$s->fetchAll(PDO::FETCH_COLUMN));
}
function table_col_types(PDO $pdo,string $t): array {
  $s=$pdo->prepare("SELECT column_name,data_type FROM information_schema.columns WHERE table_schema='public' AND table_name=:t");
  $s->execute([':t'=>$t]);
  $m=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){ $m[strtolower($r['column_name'])]=strtolower($r['data_type']); }
  return $m;
}
function table_required_cols(PDO $pdo,string $t): array {
  $s=$pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=:t AND is_nullable='NO' AND column_default IS NULL");
  $s->execute([':t'=>$t]);
  return array_map(fn($c)=>strtolower($c), $s->fetchAll(PDO::FETCH_COLUMN));
}
function table_primary_key_cols(PDO $pdo, string $t): array {
  $sql = "SELECT kcu.column_name
          FROM information_schema.table_constraints tc
          JOIN information_schema.key_column_usage kcu
            ON tc.constraint_name = kcu.constraint_name
           AND tc.table_schema = kcu.table_schema
          WHERE tc.table_schema = 'public'
            AND tc.table_name = :t
            AND tc.constraint_type = 'PRIMARY KEY'
          ORDER BY kcu.ordinal_position";
  $s = $pdo->prepare($sql);
  $s->execute([':t' => $t]);
  return array_map(fn($c) => strtolower($c), $s->fetchAll(PDO::FETCH_COLUMN));
}

function list_single_column_fks(PDO $pdo): array {
  $sql = "
    SELECT
      con.conname AS constraint_name,
      child_ns.nspname AS child_schema,
      child_tbl.relname AS child_table,
      child_att.attname AS child_column,
      parent_ns.nspname AS parent_schema,
      parent_tbl.relname AS parent_table,
      parent_att.attname AS parent_column
    FROM pg_constraint con
    JOIN pg_class child_tbl ON child_tbl.oid = con.conrelid
    JOIN pg_namespace child_ns ON child_ns.oid = child_tbl.relnamespace
    JOIN pg_class parent_tbl ON parent_tbl.oid = con.confrelid
    JOIN pg_namespace parent_ns ON parent_ns.oid = parent_tbl.relnamespace
    -- map single-column FK key numbers to attnames
    JOIN LATERAL (
      SELECT attname FROM pg_attribute
      WHERE attrelid = con.conrelid AND attnum = con.conkey[1]
    ) AS child_att ON TRUE
    JOIN LATERAL (
      SELECT attname FROM pg_attribute
      WHERE attrelid = con.confrelid AND attnum = con.confkey[1]
    ) AS parent_att ON TRUE
    WHERE con.contype = 'f'
      AND array_length(con.conkey,1) = 1
      AND array_length(con.confkey,1) = 1
      AND child_ns.nspname = 'public'
      AND parent_ns.nspname = 'public'
    ORDER BY child_table, constraint_name
  ";
  $stmt = $pdo->query($sql);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function validate_single_column_fks(PDO $pdo, int $sample = 10): array {
  $out = [];
  foreach (list_single_column_fks($pdo) as $fk) {
    $ct = $fk['child_table'];
    $cc = $fk['child_column'];
    $pt = $fk['parent_table'];
    $pc = $fk['parent_column'];
    $cname = $fk['constraint_name'];

    $sqlCnt = 'SELECT count(*) AS cnt FROM ' . qident($ct) . ' c LEFT JOIN ' . qident($pt) . ' p ON c.' . qident($cc) . ' = p.' . qident($pc) . ' WHERE c.' . qident($cc) . ' IS NOT NULL AND p.' . qident($pc) . ' IS NULL';
    $cnt = (int)$pdo->query($sqlCnt)->fetchColumn();
    if ($cnt > 0) {
      $sqlVals = 'SELECT DISTINCT c.' . qident($cc) . ' AS missing_id FROM ' . qident($ct) . ' c LEFT JOIN ' . qident($pt) . ' p ON c.' . qident($cc) . ' = p.' . qident($pc) . ' WHERE c.' . qident($cc) . ' IS NOT NULL AND p.' . qident($pc) . ' IS NULL LIMIT ' . (int)$sample;
      $vals = array_map(fn($r) => $r['missing_id'], $pdo->query($sqlVals)->fetchAll(PDO::FETCH_ASSOC));
      $out[] = [
        'constraint' => $cname,
        'child_table' => $ct,
        'child_column' => $cc,
        'parent_table' => $pt,
        'parent_column' => $pc,
        'count' => $cnt,
        'examples' => $vals,
      ];
    }
  }
  return $out;
}

function http_get(string $url): array {
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_FOLLOWLOCATION=>true,
    CURLOPT_TIMEOUT=>120,
    CURLOPT_USERAGENT=>'KeranaImporter/CSV/1.0',
  ]);
  $body=curl_exec($ch);
  $err=curl_error($ch);
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code,$body,$err];
}
function clean_str(string $s): string { return trim(preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F]+/","",$s)); }
function normalize_numeric($v){
  if($v===null||$v==='') return 0;
  $s=trim($v);
  if(substr($s,-1)==='%'){ $num=str_replace([',','%',' '],'',$s); if($num==='') return 0; return (float)$num/100.0; }
  $s=str_replace([',',' '],'',$s);
  if($s==='') return 0; return is_numeric($s)?$s:0;
}
function month_name_to_num($m){ $m=strtolower(substr($m,0,3)); $map=['jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12]; return $map[$m]??null; }
function normalize_date($v){
  if($v===null||$v==='') return null;
  $s=trim($v);
  if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$s)) return $s;
  if(preg_match('/^(\d{4})[\-\s]+(\d{2})$/',$s,$m)) return sprintf('%04d-%02d-01',$m[1],$m[2]);
  if(preg_match('/^(\d{4})[\-\s]+([A-Za-z]{3,})$/',$s,$m)){ $mm=month_name_to_num($m[2]); if($mm) return sprintf('%04d-%02d-01',$m[1],$mm); }
  if(preg_match('/^([A-Za-z]{3,})-(\d{4})$/',$s,$m)){ $mm=month_name_to_num($m[1]); if($mm) return sprintf('%04d-%02d-01',$m[2],$mm); }
  if(preg_match('/^(\d{4})\s+(\d{2})\s+(\d{2})/',$s,$m)) return sprintf('%04d-%02d-%02d',$m[1],$m[2],$m[3]);
  if(preg_match('/^(\d{4})\s+(\d{2})$/',$s,$m)) return sprintf('%04d-%02d-01',$m[1],$m[2]);
  if(preg_match('/^(\d{4})\-(\d{2})\-(\d{2})/',$s,$m)) return sprintf('%04d-%02d-%02d',$m[1],$m[2],$m[3]);
  // European dd/mm/yyyy
  if(preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/',$s,$m)) return sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]);
  // dd.mm.yyyy
  if(preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/',$s,$m)) return sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]);
  // dd/mm/yy (assume 2000-2099)
  if(preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/',$s,$m)) return sprintf('%04d-%02d-%02d',2000+(int)$m[3],$m[2],$m[1]);
  // 26 Dec 2025 or 26-Dec-25 etc.
  if(preg_match('/^(\d{1,2})[ \.-]([A-Za-z]{3,})[ \.-](\d{2,4})$/',$s,$m)){
    $dd=(int)$m[1]; $mm=month_name_to_num($m[2]); $yy=(int)$m[3]; if($yy<100) $yy+=2000; if($mm) return sprintf('%04d-%02d-%02d',$yy,$mm,$dd);
  }
  // Fallback: first token YYYY or timestamp ending
  if(preg_match('/(\d{4})[\-\s\/]?(\d{2})[\-\s\/]?(\d{2})/',$s,$m)) return sprintf('%04d-%02d-%02d',$m[1],$m[2],$m[3]);
  if(preg_match('/(\d{4})[\-\s\/](\d{2})/',$s,$m)) return sprintf('%04d-%02d-01',$m[1],$m[2]);
  return null;
}
function normalize_cell(string $table,string $col,string $val,string $type){
  $l=strtolower($col); $t=strtolower($type);
  if($val===''){
    if(in_array($t,['numeric','double precision','real','integer','bigint','smallint'])) return 0;
    if(substr($l,-5)==='_uuid') return null; // empty FK → NULL
    return null;
  }
  if(in_array($t,['numeric','double precision','real'])) return normalize_numeric($val);
  if(in_array($t,['integer','bigint','smallint'])){
    $n = normalize_numeric($val);
    $n = is_numeric($n) ? (int)round((float)$n) : 0;
    return (string)$n;
  }
  if($t==='date') return normalize_date($val);
  if($t==='timestamp without time zone' || $t==='timestamp with time zone'){
    $d=normalize_date($val); return $d?($d.' 00:00:00'):null;
  }
  return $val;
}

function fetch_sheet_csv_rows(string $spreadsheetId, string $sheetName): array {
  $url = "https://docs.google.com/spreadsheets/d/$spreadsheetId/gviz/tq?tqx=out:csv&sheet=".rawurlencode($sheetName);
  [$code,$csv,$err] = http_get($url);
  if ($code!==200 || $csv===false || $csv==='') {
    throw new RuntimeException("HTTP $code");
  }
  $f = fopen('php://temp','r+'); fwrite($f,$csv); rewind($f);
  $rows=[];
  while(($r=fgetcsv($f))!==false){
    while($r && end($r)==='') array_pop($r);
    $rows[] = array_map(fn($v)=>clean_str((string)$v), $r);
  }
  fclose($f);
  return $rows;
}

function invoice_exists(PDO $pdo, ?string $uuid): bool {
  if (!$uuid) return false;
  $q = $pdo->prepare('SELECT 1 FROM invoices_in WHERE uuid = ?');
  $q->execute([$uuid]);
  return (bool)$q->fetchColumn();
}
function resolve_invoice_via_transaction(PDO $pdo, ?string $txnUuid): ?string {
  if (!$txnUuid) return null;
  $q = $pdo->prepare('SELECT invoice_uuid FROM transactions WHERE uuid = ?');
  $q->execute([$txnUuid]);
  $inv = $q->fetchColumn();
  if (!$inv) return null;
  // only accept if it actually exists in invoices_in
  $q2 = $pdo->prepare('SELECT 1 FROM invoices_in WHERE uuid = ?');
  $q2->execute([$inv]);
  return $q2->fetchColumn() ? $inv : null;
}

function invoice_in_exists(PDO $pdo, ?string $uuid): bool {
  if (!$uuid) return false;
  $q = $pdo->prepare('SELECT 1 FROM invoices_in WHERE uuid = ?');
  $q->execute([$uuid]);
  return (bool)$q->fetchColumn();
}
function invoice_out_exists(PDO $pdo, ?string $uuid): bool {
  if (!$uuid) return false;
  $q = $pdo->prepare('SELECT 1 FROM invoices_out WHERE uuid = ?');
  $q->execute([$uuid]);
  return (bool)$q->fetchColumn();
}

function row_exists_by_uuid(PDO $pdo, string $table, ?string $uuid): bool {
  if (!$uuid) return false;
  $sql = 'SELECT 1 FROM ' . qident($table) . ' WHERE ' . qident('uuid') . ' = ?';
  $q = $pdo->prepare($sql);
  $q->execute([$uuid]);
  return (bool)$q->fetchColumn();
}

function import_rows(PDO $pdo, string $table, array $rows): array {
  // Special case: treat 'invoices_in_2024' as an alias for 'invoices_in'
  if ($table === 'invoices_in_2024') {
    $table = 'invoices_in';
  }
  // Each table import runs in its own transaction with deferred constraints.
  try {
    $pdo->beginTransaction();
    if ($table === 'transactions' || $table === 'reimbursements') {
      // For child tables with FKs into invoices/transactions, check constraints per row so a single bad row doesn't blow the whole table at COMMIT.
      $pdo->exec('SET CONSTRAINTS ALL IMMEDIATE');
    } else {
      // For independent tables (including invoices_in), defer to end of this table's transaction for speed.
      $pdo->exec('SET CONSTRAINTS ALL DEFERRED');
    }
    $dbCols = table_cols($pdo,$table);
    if(!$dbCols) { $pdo->rollBack(); return ['skip','no columns',0,0]; }
    $dbLower = array_map('strtolower',$dbCols);
    $hasUuid = in_array('uuid',$dbLower,true);
    $types = table_col_types($pdo,$table);
    $required = table_required_cols($pdo,$table);
    $pkCols = table_primary_key_cols($pdo, $table);

    $header = $rows[0] ?? [];
    if (!$header) { $pdo->rollBack(); return ['skip','row1 empty',0,0]; }

    $hIndex=[];
    foreach($header as $i=>$h){ $l=strtolower($h); if($l!=='') $hIndex[$l]=$i; }

    $useCols=[];
    foreach($dbCols as $dbc){ $l=strtolower($dbc); if(isset($hIndex[$l])) $useCols[]=$dbc; }
    // Ensure we can bind invoice_uuid and invoice_out_uuid for transactions even if the sheet lacks the columns
    if ($table === 'transactions') {
      $useColsLowerTmp = array_map('strtolower', $useCols);
      // invoice_uuid
      if (in_array('invoice_uuid', $dbLower, true) && !in_array('invoice_uuid', $useColsLowerTmp, true)) {
        $useCols[] = 'invoice_uuid';
        $useColsLowerTmp[] = 'invoice_uuid';
      }
      // invoice_out_uuid
      if (in_array('invoice_out_uuid', $dbLower, true) && !in_array('invoice_out_uuid', $useColsLowerTmp, true)) {
        $useCols[] = 'invoice_out_uuid';
        $useColsLowerTmp[] = 'invoice_out_uuid';
      }
    }
    if(!$useCols){
      $pdo->rollBack();
      return ['skip','no matching columns; header=['.implode(',',$header).'] db=['.implode(',',$dbCols).']',0,0];
    }
    $useColsLower = array_map('strtolower',$useCols);
    $missingReq = array_values(array_diff($required, $useColsLower));
    if($missingReq){
      $pdo->rollBack();
      return ['skip','missing required columns: '.implode(',', $missingReq),0,0];
    }

    // Decide conflict columns: prefer primary key if fully present; otherwise uuid if present
    $conflictCols = [];
    if ($pkCols && !array_diff($pkCols, $useColsLower)) {
      $conflictCols = $pkCols;
    } elseif ($hasUuid && in_array('uuid', $useColsLower, true)) {
      $conflictCols = ['uuid'];
    }

    $colsSql = implode(', ', array_map('qident',$useCols));
    $placeSql='('.implode(', ', array_map(fn($c)=>':'.strtolower($c), $useCols)).')';

    $conflictLower = $conflictCols;
    $updateSetParts = [];
    foreach ($useCols as $c) {
      $l=strtolower($c);
      if (in_array($l, $conflictLower, true)) continue; // never update conflict columns
      $updateSetParts[] = qident($c).'=EXCLUDED.'.qident($c);
    }
    $updateSet = implode(', ', $updateSetParts);

    if (!empty($conflictCols)) {
      $conflictSql = '('.implode(', ', array_map('qident', $conflictCols)).')';
      if ($updateSet!=='') {
        $sql='INSERT INTO '.qident($table).' ('.$colsSql.') VALUES '.$placeSql.' ON CONFLICT '.$conflictSql.' DO UPDATE SET '.$updateSet;
      } else {
        $sql='INSERT INTO '.qident($table).' ('.$colsSql.') VALUES '.$placeSql.' ON CONFLICT '.$conflictSql.' DO NOTHING';
      }
    } else {
      $sql='INSERT INTO '.qident($table).' ('.$colsSql.') VALUES '.$placeSql;
    }

    $stmt = $pdo->prepare($sql);

    $writes=0; $seen=0; $errs=0;
    for($r=1;$r<count($rows);$r++){
      $seen++;
      // Determine UUID (if present) for better error reporting
      $uuidFromHeaderIdx = $hIndex['uuid'] ?? null;
      $rowUuid = null;
      if ($uuidFromHeaderIdx !== null && isset($rows[$r][$uuidFromHeaderIdx])) {
        $rowUuid = trim((string)$rows[$r][$uuidFromHeaderIdx]);
      }
      $assoc=[];
      foreach($useCols as $dbc){
        $l=strtolower($dbc); $idx=$hIndex[$l]??null; if($idx===null) continue;
        $raw = $rows[$r][$idx] ?? '';
        $assoc[$l] = normalize_cell($table,$l,$raw,$types[$l] ?? 'text');
      }
      // --- Auto-route invoice reference for transactions ---
      if ($table === 'transactions') {
        // Pull raw values as provided by the sheet (may be absent)
        $raw_in  = $assoc['invoice_uuid']     ?? null;
        $raw_out = $assoc['invoice_out_uuid'] ?? null;

        // If neither was provided, leave both NULL and proceed (no errors).
        if (empty($raw_in) && empty($raw_out)) {
          $assoc['invoice_uuid'] = null;
          $assoc['invoice_out_uuid'] = null;
        } else {
          // One (or both) provided: try to route the single candidate to the correct table
          $candidate = $raw_in ?: $raw_out;
          $in_ok  = invoice_in_exists($pdo, $candidate);
          $out_ok = invoice_out_exists($pdo, $candidate);

          if ($in_ok) {
            $assoc['invoice_uuid'] = $candidate;
            $assoc['invoice_out_uuid'] = null;
          } elseif ($out_ok) {
            $assoc['invoice_uuid'] = null;
            $assoc['invoice_out_uuid'] = $candidate;
          } else {
            // A value was provided but it doesn't match ANY invoices table → hard error for this row
            $present = row_exists_by_uuid($pdo, $table, $rowUuid) ? 'yes' : 'no';
            echo "err: $table uuid=".($rowUuid ?: 'n/a')." row=".($r)." present=".$present." detail=unable to resolve invoice reference to invoices_in or invoices_out\n";
            $errs++;
            continue;
          }
        }
      }
      // --- end auto-route ---
      // --- Sanitize reimbursement FK: if referenced transaction doesn't exist, set NULL ---
      if ($table === 'reimbursements') {
        $rtx = $assoc['reimbursement_transaction_uuid'] ?? null;
        if (!empty($rtx) && !row_exists_by_uuid($pdo, 'transactions', (string)$rtx)) {
          // Keep the reimbursement row, but null out the missing FK to satisfy the FK constraint
          $assoc['reimbursement_transaction_uuid'] = null;
        }
      }
      // --- end sanitize reimbursement FK ---
      // If upsert, require uuid non-empty
      if (!empty($conflictCols) && in_array('uuid', $conflictCols, true) && (empty($assoc['uuid']))) {
        $present = row_exists_by_uuid($pdo, $table, $rowUuid) ? 'yes' : 'no';
        echo "err: $table uuid=".($rowUuid ?: 'n/a')." row=".($r)." present=".$present." detail=missing uuid\n"; $errs++; continue;
      }
      try{
        $pdo->exec('SAVEPOINT import_row');
        foreach($useCols as $dbc){
          $l = strtolower($dbc);
          $stmt->bindValue(':'.$l, $assoc[$l] ?? null);
        }
        $stmt->execute();
        $pdo->exec('RELEASE SAVEPOINT import_row');
        $writes += (int)$stmt->rowCount();
      }catch(Throwable $e){
        // Undo just this row's work; keep the global transaction healthy
        try { $pdo->exec('ROLLBACK TO SAVEPOINT import_row'); } catch(Throwable $_) {}
        try { $pdo->exec('RELEASE SAVEPOINT import_row'); } catch(Throwable $_) {}
        $present = row_exists_by_uuid($pdo, $table, $rowUuid) ? 'yes' : 'no';
        echo "err: $table uuid=".($rowUuid ?: 'n/a')." row=".($r)." present=".$present." detail=".$e->getMessage()."\n";
        $errs++;
      }
    }
    $pdo->commit();
    return ['ok',"writes=$writes, errs=$errs",$writes,$seen];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo "err: $table detail=".$e->getMessage()."\n";
    return ['skip','error: '.$e->getMessage(),0,0];
  }
}


function mirror_invoices_2024_into_canonical(PDO $pdo): void {
  // Always fetch from the Google Sheet tab 'invoices_in_2024' via CSV, using the current $spreadsheetId.
  global $spreadsheetId;
  if (empty($spreadsheetId)) {
    throw new RuntimeException('spreadsheetId is not set in global scope.');
  }
  $rows = fetch_sheet_csv_rows($spreadsheetId, 'invoices_in_2024');
  if (!$rows || !is_array($rows) || count($rows) < 2) {
    // Nothing to import if no data rows.
    return;
  }
  // Row 0 is header, rows 1+ are data.
  $header = $rows[0];
  // Build mapping of header to index
  $hIndex = [];
  foreach ($header as $i => $h) {
    $l = strtolower($h);
    if ($l !== '') $hIndex[$l] = $i;
  }
  // Prepare insert (idempotent, preserve incoming UUID).
  $sql = "
    INSERT INTO invoices_in (
      uuid, id, file_name, folder, url,
      invoice_in_number, invoice_in_date, invoice_in_merchant_country,
      invoice_in_category, invoice_in_merchant, invoice_in_due_date,
      invoice_in_period_start_date, invoice_in_period_end_date,
      invoice_in_currency, invoice_in_original_amount, invoice_in_original_vat,
      invoice_in_eur_amount, invoice_in_eur_vat, invoice_in_eur_net,
      updated_date, created_date
    ) VALUES (
      :uuid, :id, :file_name, :folder, :url,
      :invoice_in_number, :invoice_in_date, :invoice_in_merchant_country,
      :invoice_in_category, :invoice_in_merchant, :invoice_in_due_date,
      :invoice_in_period_start_date, :invoice_in_period_end_date,
      :invoice_in_currency, :invoice_in_original_amount, :invoice_in_original_vat,
      :invoice_in_eur_amount, :invoice_in_eur_vat, :invoice_in_eur_net,
      :updated_date, :created_date
    )
    ON CONFLICT (uuid) DO NOTHING
  ";
  $pdo->beginTransaction();
  $stmt = $pdo->prepare($sql);
  for ($r = 1; $r < count($rows); $r++) {
    $row = $rows[$r];
    // Compose by header index
    $get = function($col) use ($hIndex, $row) {
      $idx = $hIndex[$col] ?? null;
      return ($idx !== null && isset($row[$idx])) ? $row[$idx] : null;
    };
    $uuid = $get('uuid');
    if (!$uuid) continue;
    $stmt->execute([
      ':uuid' => $uuid,
      ':id' => $get('id'),
      ':file_name' => $get('file_name'),
      ':folder' => $get('folder'),
      ':url' => $get('url'),
      ':invoice_in_number' => $get('invoice_in_number'),
      ':invoice_in_date' => $get('invoice_in_date'),
      ':invoice_in_merchant_country' => $get('invoice_in_merchant_country'),
      ':invoice_in_category' => $get('invoice_in_category'),
      ':invoice_in_merchant' => $get('invoice_in_merchant'),
      ':invoice_in_due_date' => $get('invoice_in_due_date'),
      ':invoice_in_period_start_date' => $get('invoice_in_period_start_date'),
      ':invoice_in_period_end_date' => $get('invoice_in_period_end_date'),
      ':invoice_in_currency' => $get('invoice_in_currency'),
      ':invoice_in_original_amount' => $get('invoice_in_original_amount'),
      ':invoice_in_original_vat' => $get('invoice_in_original_vat'),
      ':invoice_in_eur_amount' => $get('invoice_in_eur_amount'),
      ':invoice_in_eur_vat' => $get('invoice_in_eur_vat'),
      ':invoice_in_eur_net' => $get('invoice_in_eur_net'),
      ':updated_date' => $get('updated_date'),
      ':created_date' => $get('created_date'),
    ]);
  }
  $pdo->commit();
}

/* -------- Controller -------- */
// Adapter: fetch a sheet/tab as an array of associative rows
if (!function_exists('get_sheet_rows')) {
function get_sheet_rows(string $tab): array {
  global $WORKBOOK; // adjust this to whatever your loader exposes
  if (!isset($WORKBOOK[$tab]) || !is_array($WORKBOOK[$tab])) {
    return [];
  }
  return $WORKBOOK[$tab];
}
}

if (php_sapi_name()==='cli') {
  $spreadsheetId = $argv[1] ?? '';
  if ($spreadsheetId===''){ fwrite(STDERR,"ERR: need spreadsheet id\n"); exit(1); }
  try{
    $pdo = db();
    $tables = list_tables($pdo);
    // Ensure invoices from 2024 sheet are wired into canonical before any dependents (e.g., reimbursements) run.
    mirror_invoices_2024_into_canonical($pdo);
    $maxPasses = 3;
    for ($pass = 1; $pass <= $maxPasses; $pass++) {
      $passWrites = 0;
      echo "-- PASS $pass --\n";

      // Phase A: all tables except transactions & reimbursements
      $phaseA = array_values(array_filter($tables, fn($t) => $t !== 'transactions' && $t !== 'reimbursements'));
      foreach ($phaseA as $t) {
        try {
          $rows = fetch_sheet_csv_rows($spreadsheetId, $t);
        } catch (Throwable $e) {
          echo "skip: $t (no sheet or fetch failed: ".$e->getMessage().")\n";
          continue;
        }
        try {
          [$st,$detail,$w,$seen] = import_rows($pdo,$t,$rows);
          if ($st!=='ok') { echo "skip: $t ($detail)\n"; }
          else { echo "ok: $t ($detail, rows_seen=$seen)\n"; $passWrites += $w; }
        } catch (Throwable $e) { echo "skip: $t (error: ".$e->getMessage().")\n"; }
      }

      // Barrier: ensure all per-table transactions are fully committed before dependent tables
      echo "-- COMMIT BARRIER AFTER INVOICES --\n";

      // Phase B: now process transactions then reimbursements
      foreach (['transactions','reimbursements'] as $t) {
        if (!in_array($t, $tables, true)) continue;
        try {
          $rows = fetch_sheet_csv_rows($spreadsheetId, $t);
        } catch (Throwable $e) {
          echo "skip: $t (no sheet or fetch failed: ".$e->getMessage().")\n";
          continue;
        }
        try {
          [$st,$detail,$w,$seen] = import_rows($pdo,$t,$rows);
          if ($st!=='ok') { echo "skip: $t ($detail)\n"; }
          else { echo "ok: $t ($detail, rows_seen=$seen)\n"; $passWrites += $w; }
        } catch (Throwable $e) { echo "skip: $t (error: ".$e->getMessage().")\n"; }
      }

      if ($passWrites === 0) break; // no progress -> stop
    }
    // Validate orphans (only print warnings, do not rollback/abort)
    $orphans = validate_single_column_fks($pdo, 10);
    if (!empty($orphans)) {
      echo "\nFK ORPHANS DETECTED (deferred constraints would fail):\n";
      foreach ($orphans as $o) {
        echo " - {$o['constraint']} :: {$o['child_table']}.{$o['child_column']} → {$o['parent_table']}.{$o['parent_column']} : missing={$o['count']}\n";
        if (!empty($o['examples'])) {
          echo "   e.g. ".implode(', ', $o['examples'])."\n";
        }
      }
    }
    echo "DONE.\n";
  }catch(Throwable $e){
    echo "ERROR: ".$e->getMessage()."\n";
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  header('Content-Type: text/plain; charset=utf-8');
  $spreadsheetId = trim($_POST['spreadsheet_id'] ?? '');
  if ($spreadsheetId===''){ echo "ERROR: missing spreadsheet id\n"; exit; }
  try{
    $pdo = db();
    $tables = list_tables($pdo);
    // Ensure invoices from 2024 sheet are wired into canonical before any dependents (e.g., reimbursements) run.
    mirror_invoices_2024_into_canonical($pdo);
    $maxPasses = 3;
    for ($pass = 1; $pass <= $maxPasses; $pass++) {
      $passWrites = 0;
      echo "-- PASS $pass --\n";

      // Phase A: all tables except transactions & reimbursements
      $phaseA = array_values(array_filter($tables, fn($t) => $t !== 'transactions' && $t !== 'reimbursements'));
      foreach ($phaseA as $t) {
        try {
          $rows = fetch_sheet_csv_rows($spreadsheetId, $t);
        } catch (Throwable $e) {
          echo "skip: $t (no sheet or fetch failed: ".$e->getMessage().")\n";
          continue;
        }
        try {
          [$st,$detail,$w,$seen] = import_rows($pdo,$t,$rows);
          if ($st!=='ok') { echo "skip: $t ($detail)\n"; }
          else { echo "ok: $t ($detail, rows_seen=$seen)\n"; $passWrites += $w; }
        } catch (Throwable $e) { echo "skip: $t (error: ".$e->getMessage().")\n"; }
      }

      // Barrier: ensure all per-table transactions are fully committed before dependent tables
      echo "-- COMMIT BARRIER AFTER INVOICES --\n";

      // Phase B: now process transactions then reimbursements
      foreach (['transactions','reimbursements'] as $t) {
        if (!in_array($t, $tables, true)) continue;
        try {
          $rows = fetch_sheet_csv_rows($spreadsheetId, $t);
        } catch (Throwable $e) {
          echo "skip: $t (no sheet or fetch failed: ".$e->getMessage().")\n";
          continue;
        }
        try {
          [$st,$detail,$w,$seen] = import_rows($pdo,$t,$rows);
          if ($st!=='ok') { echo "skip: $t ($detail)\n"; }
          else { echo "ok: $t ($detail, rows_seen=$seen)\n"; $passWrites += $w; }
        } catch (Throwable $e) { echo "skip: $t (error: ".$e->getMessage().")\n"; }
      }

      if ($passWrites === 0) break; // no progress -> stop
    }
    // Validate orphans (only print warnings, do not rollback/abort)
    $orphans = validate_single_column_fks($pdo, 10);
    if (!empty($orphans)) {
      echo "\nFK ORPHANS DETECTED (deferred constraints would fail):\n";
      foreach ($orphans as $o) {
        echo " - {$o['constraint']} :: {$o['child_table']}.{$o['child_column']} → {$o['parent_table']}.{$o['parent_column']} : missing={$o['count']}\n";
        if (!empty($o['examples'])) {
          echo "   e.g. ".implode(', ', $o['examples'])."\n";
        }
      }
    }
    echo "DONE.\n";
  }catch(Throwable $e){
    echo "ERROR: ".$e->getMessage()."\n";
  }
  exit;
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Google Sheets CSV → Postgres (Row1 headers only)</title>
<form method="post" style="display:flex;flex-direction:column;gap:8px;max-width:640px">
  <label>Spreadsheet ID
    <input name="spreadsheet_id" required style="width:100%">
  </label>
  <button type="submit">Import all tables</button>
  <p><small>Sheet tabs must be named exactly like your tables (lowercase). Row 1 = headers. Publicly readable sheet.</small></p>
</form>