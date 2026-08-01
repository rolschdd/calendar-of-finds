<?php
$db_file = __DIR__ . '/gc.db';

// ---------------------------------------------------------
// DB connection
// ---------------------------------------------------------
try {
  $pdo = new PDO('sqlite:' . $db_file);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::SQLITE_ATTR_OPEN_FLAGS, PDO::SQLITE_OPEN_READONLY);
} catch (PDOException $e) {
  die('Datenbankfehler: ' . $e->getMessage());
}

// ---------------------------------------------------------
// helpers
// ---------------------------------------------------------

$month_names = [
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
];
$days_per_month = [
  1 => 31, 2 => 29, 3 => 31, 4 => 30, 5 => 31, 6 => 30,
  7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31
];
$available_types = ['alle'];
foreach ($pdo->query("SELECT DISTINCT type FROM caches") as $row) {
  $available_types[] = $row['type'];
}
$available_sizes = ['alle'];
foreach ($pdo->query("SELECT DISTINCT size FROM caches") as $row) {
  $available_sizes[] = $row['size'];
}

// ---------------------------------------------------------
// form data
// ---------------------------------------------------------
$type_param = isset($_GET['type']) ? $_GET['type'] : null;
if (!in_array($type_param, $available_types)) {
  $type_param = $available_types[0];
}
$size_param = isset($_GET['size']) ? $_GET['size'] : null;
if (!in_array($size_param, $available_sizes)) {
  $size_param = $available_sizes[0];
}
$filtered_by_type = ($type_param != $available_types[0]);
$filtered_by_size = ($size_param != $available_sizes[0]);

// ---------------------------------------------------------
// Get found dates from DB
// ---------------------------------------------------------
$stmt = $pdo->prepare("
  SELECT DISTINCT strftime(\"%m-%d\", log_date) AS shortdate
  FROM caches
  WHERE (type = :type OR \"alle\" = :type) AND (size = :size OR \"alle\" = :size)
  ORDER BY shortdate
");
$stmt->execute([
  ':type' => $type_param,
  ':size' => $size_param
]);
$found_days = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $found_days[] = $row['shortdate'];
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Fundkalender</title>
  <style>
    :root {
      --border-color: #ddd;
      --header-bg: #2c3e50;
      --header-fg: #fff;
      --ok-color: #2e7d32;
      --fail-color: #c62828;
    }

    body {
      font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      background: #fafafa;
      color: #222;
      margin: 0;
      padding: 24px;
    }

    h1 {
      font-size: 1.4rem;
      margin: 0 0 16px;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }

    /* Schmale Bildschirme (Smartphones) */
    @media (max-width: 600px) {
      body {
        padding: 12px;
      }

      h1 {
        font-size: 1.15rem;
        gap: 10px;
      }

      table {
        font-size: 0.75rem;
      }

      th, td {
        padding: 3px 1px;
        min-width: 22px;
      }

      tbody th {
        padding-left: 6px;
      }

      form.filter {
        flex-direction: column;
        font-size: 1rem;
        gap: 10px;
      }

      form.filter select {
        font-size: 1rem;
        padding: 10px 8px;
        width: 100px;
      }

      form.filter button {
        font-size: 1rem;
        padding: 10px 16px;
      }
    }

    form.filter {
      display: flex;
      font-size: 0.9rem;
      gap: 10px;
    }

    form.filter select {
      height: stretch;
      padding: 4px 6px;
      width: 80px;
    }

    form.filter button {
      cursor: pointer;
      max-width: 150px;
      padding: 4px 10px;
    }

    .table-wrapper {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      border: 1px solid var(--border-color);
      border-radius: 10px;
      margin-top: 15px;
    }

    table {
      border-collapse: collapse;
      min-width: 100%;
      font-size: 0.85rem;
    }

    th, td {
      border: 1px solid var(--border-color);
      text-align: center;
      padding: 4px 2px;
      min-width: 26px;
    }

    thead th {
      background: var(--header-bg);
      color: var(--header-fg);
      position: sticky;
      top: 0;
    }

    tbody th {
      background: var(--header-bg);
      color: var(--header-fg);
      text-align: right;
      padding-right: 10px;
      white-space: nowrap;
      position: sticky;
      left: 0;
    }

    td.empty {
      background: repeating-linear-gradient(
        45deg,
        #f0f0f0,
        #f0f0f0 4px,
        #e8e8e8 4px,
        #e8e8e8 8px
      );
    }

    .ok {
      color: var(--ok-color);
      font-weight: bold;
      font-size: 1rem;
    }

    .fail {
      background-color: #ff8888;
      color: var(--fail-color);
      font-weight: bold;
      font-size: 1rem;
    }

    .footer {
      margin-top: 10px;
      padding: 5px;
    }
  </style>
</head>
<body>

<h1>Fundkalender</h1>

<form class="filter" method="get">
  <div>
    <label for="type-select">Cache-Typ</label>
    <select id="type-select" name="type">
      <?php foreach ($available_types as $type_option) {
        $selected = ($type_param == $type_option);
        echo '<option'. ($selected ? ' selected' : '') .'>' . $type_option . '</option>';
      } ?>
    </select>
  </div>
  <div>
    <label for="size-select">Cache-Größe</label>
    <select id="size-select" name="size">
      <?php foreach ($available_sizes as $size_option) {
        $selected = ($size_param == $size_option);
        echo '<option'. ($selected ? ' selected' : '') .'>' . $size_option . '</option>';
      } ?>
    </select>
  </div>
  <button type="submit">Filtern</button>
</form>

<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th>Monat</th>
        <?php for ($day = 1; $day <= 31; $day++): ?>
          <th><?= $day ?></th>
        <?php endfor; ?>
      </tr>
    </thead>
    <tbody>
      <?php for ($month = 1; $month <= 12; $month++): ?>
        <tr>
          <th><?= $month_names[$month] ?></th>
          <?php
          $max_days = $days_per_month[$month];
          for ($day = 1; $day <= 31; $day++):
            if ($day > $max_days) {
              // Tag existiert in diesem Monat nicht (z. B. 30. Februar)
              echo '<td class="empty"></td>';
              continue;
            }

            $month_and_day = sprintf('%02d-%02d', $month, $day);
            if (in_array($month_and_day, $found_days)) {
              echo '<td class="ok">&#10003;</td>';
            } else {
              echo '<td class="fail">&#10007;</td>';
            }
          endfor;
          ?>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>
</div>
<div class="footer">
  Du hast an <?= count($found_days) ?> von 366 Tagen einen Geocache gefunden.
  (Typ: <?= $type_param?>, Größe: <?= $size_param ?>)
</div>

</body>
</html>
