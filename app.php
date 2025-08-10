<?php
// app.php - Single-file web app for XAMPP
// Usage: place this file in C:\xampp\htdocs\app.php and open http://localhost/app.php

// --- CONFIG (ubah jika perlu) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = 'localhost';
$db_user = 'root';
$db_pass = ''; // ganti bila root MySQL Anda pakai password
$db_name = 'db_xampp_app_singlefile';
// ----------------------------------

session_start();

// Connect & create DB/tables if missing
$mysqli = new mysqli($db_host, $db_user, $db_pass);
if ($mysqli->connect_errno) {
    die("Koneksi MySQL gagal: " . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');
$mysqli->query("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$mysqli->select_db($db_name);

$mysqli->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mysqli->query("CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan VARCHAR(100) UNIQUE NOT NULL,
    nama VARCHAR(255) NOT NULL,
    nomor_meter VARCHAR(100),
    sim_card VARCHAR(100),
    ip_modem VARCHAR(100),
    alamat TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Helpers
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ROUTING & ACTIONS
$action = $_REQUEST['action'] ?? 'home';

// ==== REGISTER (modal)
$register_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'register') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    if ($username === '' || $password === '') {
        $register_error = "Username dan password harus diisi.";
    } elseif ($password !== $password2) {
        $register_error = "Password tidak sama.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param('ss', $username, $hashed);
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['username'] = $username;
            header("Location: ?action=dashboard");
            exit;
        } else {
            if ($mysqli->errno === 1062) $register_error = "Username sudah dipakai.";
            else $register_error = "Gagal registrasi: " . $mysqli->error;
        }
    }
}

// ==== LOGIN
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $login_error = "Username dan password harus diisi.";
    } else {
        $stmt = $mysqli->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $username;
                header("Location: ?action=dashboard");
                exit;
            } else $login_error = "Password salah.";
        } else {
            $login_error = "User tidak ditemukan.";
        }
    }
}

// ==== LOGOUT
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: ?action=home");
    exit;
}

// Protect certain actions
$need_login = ['dashboard','add_customer','edit_customer','delete_customer','export'];
if (in_array($action, $need_login) && empty($_SESSION['user_id'])) {
    header("Location: ?action=home");
    exit;
}

// ==== ADD CUSTOMER
$customer_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'add_customer') {
    $id_pelanggan = trim($_POST['id_pelanggan'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $nomor_meter = trim($_POST['nomor_meter'] ?? '');
    $sim_card = trim($_POST['sim_card'] ?? '');
    $ip_modem = trim($_POST['ip_modem'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    if ($id_pelanggan === '' || $nama === '') {
        $customer_msg = "ID Pelanggan dan Nama wajib diisi.";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO customers (id_pelanggan, nama, nomor_meter, sim_card, ip_modem, alamat) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $id_pelanggan, $nama, $nomor_meter, $sim_card, $ip_modem, $alamat);
        if ($stmt->execute()) $customer_msg = "Data berhasil ditambahkan.";
        else {
            if ($mysqli->errno === 1062) $customer_msg = "ID Pelanggan sudah ada.";
            else $customer_msg = "Gagal: " . $mysqli->error;
        }
    }
}

// ==== EDIT CUSTOMER (process)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'edit_customer') {
    $cid = intval($_POST['cid'] ?? 0);
    $id_pelanggan = trim($_POST['id_pelanggan'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $nomor_meter = trim($_POST['nomor_meter'] ?? '');
    $sim_card = trim($_POST['sim_card'] ?? '');
    $ip_modem = trim($_POST['ip_modem'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    if ($cid <= 0 || $id_pelanggan === '' || $nama === '') {
        $customer_msg = "Data tidak valid.";
    } else {
        $stmt = $mysqli->prepare("UPDATE customers SET id_pelanggan=?, nama=?, nomor_meter=?, sim_card=?, ip_modem=?, alamat=? WHERE id=?");
        $stmt->bind_param('ssssssi', $id_pelanggan, $nama, $nomor_meter, $sim_card, $ip_modem, $alamat, $cid);
        if ($stmt->execute()) $customer_msg = "Data berhasil diupdate.";
        else $customer_msg = "Gagal update: " . $mysqli->error;
    }
}

// ==== DELETE
if (isset($_GET['action']) && $_GET['action'] === 'delete_customer' && isset($_GET['id'])) {
    $cid = intval($_GET['id']);
    if ($cid > 0) {
        $stmt = $mysqli->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->bind_param('i', $cid);
        $stmt->execute();
    }
    header("Location: ?action=dashboard");
    exit;
}

// ==== EXPORT (TSV .xls)
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $q = trim($_GET['q'] ?? '');
    $params = [];
    $sql = "SELECT id_pelanggan, nama, nomor_meter, sim_card, ip_modem, alamat, created_at FROM customers";
    if ($q !== '') {
        $like = "%{$q}%";
        $sql .= " WHERE id_pelanggan LIKE ? OR nama LIKE ? OR nomor_meter LIKE ? OR sim_card LIKE ? OR ip_modem LIKE ? OR alamat LIKE ?";
        $params = [$like,$like,$like,$like,$like,$like];
    }
    $stmt = $mysqli->prepare($sql);
    if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=customers_export_" . date('Ymd_His') . ".xls");
    echo "\xEF\xBB\xBF";
    echo implode("\t", ['ID Pelanggan','Nama','Nomor Meter','SIM Card','IP Modem','Alamat','Dibuat']) . "\n";
    while ($row = $res->fetch_assoc()) {
        $line = [
            $row['id_pelanggan'],
            $row['nama'],
            $row['nomor_meter'],
            $row['sim_card'],
            $row['ip_modem'],
            $row['alamat'],
            $row['created_at']
        ];
        echo implode("\t", array_map(function($c){ return str_replace(["\r","\n","\t"], [' ',' ',' '], $c); }, $line)) . "\n";
    }
    exit;
}

// ==== Fetch editing customer if requested
$editing_customer = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_customer' && isset($_GET['id'])) {
    $cid = intval($_GET['id']);
    $stmt = $mysqli->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $res = $stmt->get_result();
    $editing_customer = $res->fetch_assoc();
}

// ==== Listing (search & pagination)
$search_q = trim($_GET['q'] ?? '');
$customers = [];
$perpage = 12;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page-1)*$perpage;
$where = '';
$params = [];
if ($search_q !== '') {
    $like = "%{$search_q}%";
    $where = " WHERE id_pelanggan LIKE ? OR nama LIKE ? OR nomor_meter LIKE ? OR sim_card LIKE ? OR ip_modem LIKE ? OR alamat LIKE ?";
    $params = [$like,$like,$like,$like,$like,$like];
}
$count_sql = "SELECT COUNT(*) as cnt FROM customers" . $where;
$stmt = $mysqli->prepare($count_sql);
if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$res = $stmt->get_result();
$total = ($row = $res->fetch_assoc()) ? intval($row['cnt']) : 0;
$pages = max(1, ceil($total / $perpage));
$list_sql = "SELECT * FROM customers" . $where . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($list_sql);
if ($params) {
    $types = str_repeat('s', count($params)) . 'ii';
    $bind_values = array_merge($params, [$perpage, $offset]);
    $stmt->bind_param($types, ...$bind_values);
} else {
    $stmt->bind_param('ii', $perpage, $offset);
}
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $customers[] = $r;

?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Aplikasi Pelanggan — Single File</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background: linear-gradient(135deg,#f7fbfe,#eef4f9); min-height:100vh; }
  .card { box-shadow: 0 10px 30px rgba(12,38,63,0.06); }
  .brand { font-weight:700; letter-spacing:0.6px; }
  .small-muted { color:#6c757d; font-size:0.9rem; }
  .table-wrap { overflow-x:auto; }
  .map-link { text-decoration:none; }
  footer { opacity:0.8; font-size:0.9rem; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand brand" href="?action=home">PelangganApp</a>
    <div class="ms-auto">
      <?php if (!empty($_SESSION['username'])): ?>
        <span class="me-3 small-muted">Hi, <?= e($_SESSION['username']) ?></span>
        <a class="btn btn-outline-secondary btn-sm" href="?action=logout">Logout</a>
      <?php else: ?>
        <a class="btn btn-outline-primary btn-sm" href="?action=home">Login</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container py-4">
<?php if ($action === 'home'): ?>
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card p-4 mb-3">
        <h4 class="mb-1">Login</h4>
        <p class="small-muted mb-3">Masuk untuk mengelola data pelanggan.</p>
        <?php if ($login_error): ?><div class="alert alert-danger"><?= e($login_error) ?></div><?php endif; ?>
        <form method="post" class="mb-3" style="max-width:520px;">
          <input type="hidden" name="do" value="login">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input name="username" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control" required>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary">Login</button>
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalRegister">Daftar Akun</button>
          </div>
        </form>
        <div class="small-muted">Belum punya akun? Klik tombol <strong>Daftar Akun</strong> untuk membuat akun baru.</div>
      </div>

      <div class="card p-3">
        <h6 class="mb-2">Info</h6>
        <p class="small-muted mb-0">Aplikasi demo single-file menggunakan PHP + MySQL (XAMPP). Setelah mendaftar, Anda akan otomatis login dan diarahkan ke dashboard.</p>
      </div>
    </div>
  </div>

  <!-- Register Modal -->
  <div class="modal fade" id="modalRegister" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post">
          <input type="hidden" name="do" value="register">
          <div class="modal-header">
            <h5 class="modal-title">Daftar Akun Baru</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <?php if ($register_error): ?><div class="alert alert-danger"><?= e($register_error) ?></div><?php endif; ?>
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input name="username" class="form-control" required>
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Password</label>
                <input name="password" type="password" class="form-control" required>
              </div>
              <div class="col">
                <label class="form-label">Ulangi Password</label>
                <input name="password2" type="password" class="form-control" required>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-success">Daftar & Masuk</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php elseif ($action === 'dashboard'): ?>
  <div class="d-flex mb-3 gap-3 align-items-start">
    <div class="flex-grow-1">
      <div class="card p-3">
        <form class="row g-2" method="get">
          <input type="hidden" name="action" value="dashboard">
          <div class="col-md-6">
            <input name="q" value="<?= e($search_q) ?>" class="form-control" placeholder="Cari ID / nama / nomor meter / sim / ip / alamat...">
          </div>
          <div class="col-auto">
            <button class="btn btn-primary">Cari</button>
            <a class="btn btn-outline-secondary" href="?action=dashboard">Reset</a>
            <a class="btn btn-success" href="?action=export<?= $search_q ? '&q='.urlencode($search_q):'' ?>">Export ke Excel</a>
          </div>
        </form>
      </div>
    </div>
    <div>
      <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAdd">Tambah Pelanggan</button>
    </div>
  </div>

  <?php if ($customer_msg): ?><div class="alert alert-info"><?= e($customer_msg) ?></div><?php endif; ?>

  <div class="card mb-3 p-3">
    <h5 class="mb-3">Daftar Pelanggan (<?= $total ?>)</h5>
    <div class="table-wrap">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID Pelanggan</th>
            <th>Nama</th>
            <th>Nomor Meter</th>
            <th>SIM Card</th>
            <th>IP Modem</th>
            <th>Alamat</th>
            <th>Dibuat</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (count($customers)===0): ?>
          <tr><td colspan="8" class="text-center small-muted">Tidak ada data</td></tr>
        <?php else: foreach ($customers as $c): ?>
          <tr>
            <td><?= e($c['id_pelanggan']) ?></td>
            <td><?= e($c['nama']) ?></td>
            <td><?= e($c['nomor_meter']) ?></td>
            <td><?= e($c['sim_card']) ?></td>
            <td><?= e($c['ip_modem']) ?></td>
            <td style="max-width:240px;">
              <?= e($c['alamat']) ?><br>
              <?php if (trim($c['alamat'])!==''): ?>
                <a class="map-link small-muted" target="_blank" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($c['alamat']) ?>">Lihat di Maps</a>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;"><?= e($c['created_at']) ?></td>
            <td>
              <a class="btn btn-sm btn-outline-secondary" href="?action=edit_customer&id=<?= $c['id'] ?>">Edit</a>
              <a class="btn btn-sm btn-outline-danger" href="?action=delete_customer&id=<?= $c['id'] ?>" onclick="return confirm('Hapus data ini?')">Hapus</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <nav>
      <ul class="pagination">
        <?php for($p=1;$p<=$pages;$p++): ?>
          <li class="page-item <?= $p===$page ? 'active' : '' ?>">
            <a class="page-link" href="?action=dashboard&page=<?=$p?><?= $search_q ? '&q='.urlencode($search_q):'' ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  </div>

  <!-- Add Modal -->
  <div class="modal fade" id="modalAdd" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="post">
          <input type="hidden" name="do" value="add_customer">
          <div class="modal-header">
            <h5 class="modal-title">Tambah Pelanggan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2">
              <div class="col-md-6 mb-2">
                <label class="form-label">ID Pelanggan</label>
                <input name="id_pelanggan" class="form-control" required>
              </div>
              <div class="col-md-6 mb-2">
                <label class="form-label">Nama</label>
                <input name="nama" class="form-control" required>
              </div>
              <div class="col-md-4 mb-2">
                <label class="form-label">Nomor Meter</label>
                <input name="nomor_meter" class="form-control">
              </div>
              <div class="col-md-4 mb-2">
                <label class="form-label">SIM Card</label>
                <input name="sim_card" class="form-control">
              </div>
              <div class="col-md-4 mb-2">
                <label class="form-label">IP Modem</label>
                <input name="ip_modem" class="form-control">
              </div>
              <div class="col-12 mb-2">
                <label class="form-label">Alamat</label>
                <textarea name="alamat" class="form-control" rows="3"></textarea>
                <div class="small-muted mt-1">Alamat dapat dibuka di Google Maps melalui tautan pada tabel.</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-primary">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php elseif ($action === 'edit_customer' && $editing_customer): ?>
  <div class="card p-4">
    <h5>Edit Pelanggan</h5>
    <form method="post">
      <input type="hidden" name="do" value="edit_customer">
      <input type="hidden" name="cid" value="<?= e($editing_customer['id']) ?>">
      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label">ID Pelanggan</label>
          <input name="id_pelanggan" class="form-control" required value="<?= e($editing_customer['id_pelanggan']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nama</label>
          <input name="nama" class="form-control" required value="<?= e($editing_customer['nama']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Nomor Meter</label>
          <input name="nomor_meter" class="form-control" value="<?= e($editing_customer['nomor_meter']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">SIM Card</label>
          <input name="sim_card" class="form-control" value="<?= e($editing_customer['sim_card']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">IP Modem</label>
          <input name="ip_modem" class="form-control" value="<?= e($editing_customer['ip_modem']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Alamat</label>
          <textarea name="alamat" class="form-control" rows="3"><?= e($editing_customer['alamat']) ?></textarea>
        </div>
      </div>
      <div class="mt-3">
        <a class="btn btn-outline-secondary" href="?action=dashboard">Batal</a>
        <button class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>

<?php else: ?>
  <div class="card p-4">
    <h5>Halaman tidak ditemukan</h5>
    <p class="small-muted">Kembali ke <a href="?action=home">beranda</a>.</p>
  </div>
<?php endif; ?>

  <footer class="text-center mt-4 small-muted">
    Dibuat dengan PHP & MySQL (XAMPP) — Single-file demo sederhana.
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Jika ada error di register (server-side), buka modal otomatis supaya user melihat pesan
<?php if ($register_error): ?>
  var modalEl = document.getElementById('modalRegister');
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();
<?php endif; ?>
</script>
</body>
</html>
