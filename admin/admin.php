<?php
// admin.php - backend CRUD untuk admin dashboard (PostgreSQL)
// Requires db.php which should create $conn via pg_connect()

include 'db.php';
header('Content-Type: application/json; charset=utf-8');

// helper respond
function respond($success, $message, $data = null, $http = 200) {
    http_response_code($http);
    echo json_encode(['success'=>$success, 'message'=>$message, 'data'=>$data]);
    exit;
}

// helper: safe fetch all (pg_fetch_all returns false if no rows)
function fetch_all_or_empty($res) {
    $rows = pg_fetch_all($res);
    return $rows === false ? [] : $rows;
}

// helper: generate new id with prefix and fixed width digits
function generate_id($conn, $table, $col, $prefix, $digits) {
    // get max id
    $q = "SELECT MAX($col) AS maxid FROM $table";
    $r = pg_query($conn, $q);
    $row = pg_fetch_assoc($r);
    $maxid = $row['maxid'] ?? null;
    if (!$maxid) {
        $num = 1;
    } else {
        // strip non-digits
        $numstr = preg_replace('/\D/', '', $maxid);
        $num = intval($numstr) + 1;
    }
    return $prefix . str_pad((string)$num, $digits, '0', STR_PAD_LEFT);
}

// ensure uploads dir
$upload_dir = __DIR__ . '/uploads';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$entity = $_GET['entity'] ?? null;
$action = $_GET['action'] ?? null;
if (!$entity || !$action) respond(false, "Parameter entity & action wajib dikirim.", null, 400);


/* ------------------------------
   CRUD MAHASISWA
   table mahasiswa(nim,nama,no_telp,prodi)
   nim is provided manually
   ------------------------------ */
if ($entity === 'mahasiswa') {
    try {
        switch ($action) {
            case 'create':
                $nim = trim($_POST['nim'] ?? '');
                $nama = trim($_POST['nama'] ?? '');
                $no_telp = trim($_POST['no_telp'] ?? null);
                $prodi = trim($_POST['prodi'] ?? null);
                if (!$nim || !$nama) respond(false, "nim & nama wajib", null, 400);

                $q = "INSERT INTO mahasiswa (nim, nama, no_telp, prodi) VALUES ($1,$2,$3,$4)";
                $res = pg_query_params($conn, $q, [$nim, $nama, $no_telp, $prodi]);
                if (!$res) respond(false, "Insert gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Mahasiswa berhasil ditambahkan.");
                break;

            case 'read':
                $res = pg_query($conn, "SELECT nim, nama, no_telp, prodi FROM mahasiswa ORDER BY nim");
                $rows = fetch_all_or_empty($res);
                respond(true, "Data mahasiswa", $rows);
                break;

            case 'update':
                $nim = trim($_POST['nim'] ?? '');
                if (!$nim) respond(false, "nim wajib", null, 400);
                $nama = isset($_POST['nama']) ? trim($_POST['nama']) : null;
                $no_telp = isset($_POST['no_telp']) ? trim($_POST['no_telp']) : null;
                $prodi = isset($_POST['prodi']) ? trim($_POST['prodi']) : null;
                $q = "UPDATE mahasiswa SET nama = COALESCE($2, nama), no_telp = COALESCE($3, no_telp), prodi = COALESCE($4, prodi) WHERE nim = $1";
                $res = pg_query_params($conn, $q, [$nim, $nama, $no_telp, $prodi]);
                if (!$res) respond(false, "Update gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Mahasiswa berhasil diupdate.");
                break;

            case 'delete':
                $nim = trim($_POST['nim'] ?? '');
                if (!$nim) respond(false, "nim wajib", null, 400);
                $res = pg_query_params($conn, "DELETE FROM mahasiswa WHERE nim = $1", [$nim]);
                if (!$res) respond(false, "Delete gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Mahasiswa berhasil dihapus.");
                break;

            default:
                respond(false, "Action tidak dikenali untuk mahasiswa", null, 400);
        }
    } catch (Exception $e) {
        respond(false, "Server error: ".$e->getMessage(), null, 500);
    }
}

/* ------------------------------
   CRUD PRODUK
   produk(produk_id char(4) PK, vendor_id int, nama_baju, harga, stok, ukuran)
   produk_id akan digenerate otomatis (prefix P + 3 digits -> P001)
   ------------------------------ */
if ($entity === 'produk') {
    try {
        switch ($action) {
            case 'create':
                // require vendor_id and nama_baju at minimum
                $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
                $nama_baju = trim($_POST['nama_baju'] ?? '');
                $harga = isset($_POST['harga']) ? intval($_POST['harga']) : 0;
                $stok = isset($_POST['stok']) ? intval($_POST['stok']) : 0;
                $ukuran = trim($_POST['ukuran'] ?? null);

                if (!$vendor_id || !$nama_baju) respond(false, "vendor_id & nama_baju wajib", null, 400);

                $produk_id = generate_id($conn, 'produk', 'produk_id', 'P', 3); // P001
                $q = "INSERT INTO produk (produk_id, vendor_id, nama_baju, harga, stok, ukuran) VALUES ($1,$2,$3,$4,$5,$6)";
                $res = pg_query_params($conn, $q, [$produk_id, $vendor_id, $nama_baju, $harga, $stok, $ukuran]);
                if (!$res) respond(false, "Insert produk gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Produk berhasil dibuat", ['produk_id'=>$produk_id]);
                break;

            case 'read':
                $sql = "SELECT p.produk_id, p.vendor_id, v.vendor_nama, p.nama_baju, p.harga, p.stok, p.ukuran
                        FROM produk p LEFT JOIN vendor v ON p.vendor_id = v.vendor_id
                        ORDER BY p.produk_id";
                $res = pg_query($conn, $sql);
                $rows = fetch_all_or_empty($res);
                respond(true, "Data produk", $rows);
                break;

            case 'update':
                $produk_id = trim($_POST['produk_id'] ?? '');
                if (!$produk_id) respond(false, "produk_id wajib", null, 400);
                $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
                $nama_baju = isset($_POST['nama_baju']) ? trim($_POST['nama_baju']) : null;
                $harga = isset($_POST['harga']) ? intval($_POST['harga']) : null;
                $stok = isset($_POST['stok']) ? intval($_POST['stok']) : null;
                $ukuran = isset($_POST['ukuran']) ? trim($_POST['ukuran']) : null;

                $q = "UPDATE produk SET vendor_id = COALESCE($2, vendor_id),
                                       nama_baju = COALESCE($3, nama_baju),
                                       harga = COALESCE($4, harga),
                                       stok = COALESCE($5, stok),
                                       ukuran = COALESCE($6, ukuran)
                      WHERE produk_id = $1";
                $res = pg_query_params($conn, $q, [$produk_id, $vendor_id, $nama_baju, $harga, $stok, $ukuran]);
                if (!$res) respond(false, "Update produk gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Produk berhasil diupdate.");
                break;

            case 'delete':
                $produk_id = trim($_POST['produk_id'] ?? '');
                if (!$produk_id) respond(false, "produk_id wajib", null, 400);
                $res = pg_query_params($conn, "DELETE FROM produk WHERE produk_id = $1", [$produk_id]);
                if (!$res) respond(false, "Delete produk gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Produk berhasil dihapus.");
                break;

            default:
                respond(false, "Action tidak dikenali untuk produk", null, 400);
        }
    } catch (Exception $e) {
        respond(false, "Server error: ".$e->getMessage(), null, 500);
    }
}

/* ------------------------------
   CRUD PRE_ORDER
   pre_order(order_id char(4) PK, produk_id, nim, admin_id, tanggal_order, status_pembayaran)
   order_id di-generate otomatis (prefix O + 3 digits -> O001)
   ------------------------------ */
if ($entity === 'preorder') {
    try {
        switch ($action) {
            case 'create':
                $produk_id = trim($_POST['produk_id'] ?? '');
                $nim = trim($_POST['nim'] ?? '');
                $admin_id = trim($_POST['admin_id'] ?? null);
                $tanggal_order = trim($_POST['tanggal_order'] ?? date('Y-m-d'));
                $status_pembayaran = trim($_POST['status_pembayaran'] ?? 'Belum Bayar');

                if (!$produk_id || !$nim) respond(false, "produk_id & nim wajib", null, 400);

                $order_id = generate_id($conn, 'pre_order', 'order_id', 'O', 3); // O001
                $q = "INSERT INTO pre_order (order_id, produk_id, nim, admin_id, tanggal_order, status_pembayaran)
                      VALUES ($1,$2,$3,$4,$5,$6)";
                $res = pg_query_params($conn, $q, [$order_id, $produk_id, $nim, $admin_id, $tanggal_order, $status_pembayaran]);
                if (!$res) respond(false, "Insert pre_order gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Pre-order berhasil dibuat", ['order_id'=>$order_id]);
                break;

            case 'read':
                $sql = "SELECT po.order_id, po.produk_id, pr.nama_baju, po.nim, m.nama AS mahasiswa_nama,
                               po.admin_id, a.admin_nama, po.tanggal_order, po.status_pembayaran
                        FROM pre_order po
                        LEFT JOIN produk pr ON po.produk_id = pr.produk_id
                        LEFT JOIN mahasiswa m ON po.nim = m.nim
                        LEFT JOIN admin a ON po.admin_id = a.admin_id
                        ORDER BY po.tanggal_order DESC";
                $res = pg_query($conn, $sql);
                $rows = fetch_all_or_empty($res);
                respond(true, "Data pre-order", $rows);
                break;

            case 'update':
                $order_id = trim($_POST['order_id'] ?? '');
                if (!$order_id) respond(false, "order_id wajib", null, 400);
                $produk_id = isset($_POST['produk_id']) ? trim($_POST['produk_id']) : null;
                $nim = isset($_POST['nim']) ? trim($_POST['nim']) : null;
                $admin_id = isset($_POST['admin_id']) ? trim($_POST['admin_id']) : null;
                $tanggal_order = isset($_POST['tanggal_order']) ? trim($_POST['tanggal_order']) : null;
                $status_pembayaran = isset($_POST['status_pembayaran']) ? trim($_POST['status_pembayaran']) : null;

                $q = "UPDATE pre_order SET produk_id = COALESCE($2, produk_id),
                                            nim = COALESCE($3, nim),
                                            admin_id = COALESCE($4, admin_id),
                                            tanggal_order = COALESCE($5, tanggal_order),
                                            status_pembayaran = COALESCE($6, status_pembayaran)
                      WHERE order_id = $1";
                $res = pg_query_params($conn, $q, [$order_id, $produk_id, $nim, $admin_id, $tanggal_order, $status_pembayaran]);
                if (!$res) respond(false, "Update pre-order gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Pre-order berhasil diupdate.");
                break;

            case 'delete':
                $order_id = trim($_POST['order_id'] ?? '');
                if (!$order_id) respond(false, "order_id wajib", null, 400);
                $res = pg_query_params($conn, "DELETE FROM pre_order WHERE order_id = $1", [$order_id]);
                if (!$res) respond(false, "Delete pre-order gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Pre-order berhasil dihapus.");
                break;

            default:
                respond(false, "Action tidak dikenali untuk preorder", null, 400);
        }
    } catch (Exception $e) {
        respond(false, "Server error: ".$e->getMessage(), null, 500);
    }
}

/* ------------------------------
   CRUD ADMIN (admin_id char(3), admin_nama, admin_email)
   admin_id digenerate otomatis (prefix A + 2 digits -> A01)
   ------------------------------ */
if ($entity === 'admin') {
    try {
        switch ($action) {
            case 'create':
                $admin_nama = trim($_POST['admin_nama'] ?? '');
                $admin_email = trim($_POST['admin_email'] ?? '');
                if (!$admin_nama || !$admin_email) respond(false, "admin_nama & admin_email wajib", null, 400);

                $admin_id = generate_id($conn, 'admin', 'admin_id', 'A', 2); // A01
                $q = "INSERT INTO admin (admin_id, admin_nama, admin_email) VALUES ($1,$2,$3)";
                $res = pg_query_params($conn, $q, [$admin_id, $admin_nama, $admin_email]);
                if (!$res) respond(false, "Insert admin gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Admin berhasil dibuat", ['admin_id'=>$admin_id]);
                break;

            case 'read':
                $res = pg_query($conn, "SELECT admin_id, admin_nama, admin_email FROM admin ORDER BY admin_id");
                $rows = fetch_all_or_empty($res);
                respond(true, "Data admin", $rows);
                break;

            case 'update':
                $admin_id = trim($_POST['admin_id'] ?? '');
                if (!$admin_id) respond(false, "admin_id wajib", null, 400);
                $admin_nama = isset($_POST['admin_nama']) ? trim($_POST['admin_nama']) : null;
                $admin_email = isset($_POST['admin_email']) ? trim($_POST['admin_email']) : null;
                $q = "UPDATE admin SET admin_nama = COALESCE($2, admin_nama), admin_email = COALESCE($3, admin_email) WHERE admin_id = $1";
                $res = pg_query_params($conn, $q, [$admin_id, $admin_nama, $admin_email]);
                if (!$res) respond(false, "Update admin gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Admin berhasil diupdate.");
                break;

            case 'delete':
                $admin_id = trim($_POST['admin_id'] ?? '');
                if (!$admin_id) respond(false, "admin_id wajib", null, 400);
                $res = pg_query_params($conn, "DELETE FROM admin WHERE admin_id = $1", [$admin_id]);
                if (!$res) respond(false, "Delete admin gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Admin berhasil dihapus.");
                break;

            default:
                respond(false, "Action tidak dikenali untuk admin", null, 400);
        }
    } catch (Exception $e) {
        respond(false, "Server error: ".$e->getMessage(), null, 500);
    }
}

/* ------------------------------
   CRUD TAGIHAN
   tagihan(tagihan_id serial, order_id char(4) unique, metode_pembayaran, bukti_pembayaran, total_amount, status_pembayaran, tanggal_tagihan)
   ------------------------------ */
if ($entity === 'tagihan') {
    try {
        switch ($action) {
            case 'create':
                // support multipart upload or regular form-data
                $isMultipart = !empty($_FILES);
                $order_id = trim($_POST['order_id'] ?? trim($_POST['order_id'] ?? ''));
                $metode = trim($_POST['metode_pembayaran'] ?? null);
                $total = isset($_POST['total_amount']) ? intval($_POST['total_amount']) : 0;
                $tanggal = trim($_POST['tanggal_tagihan'] ?? date('Y-m-d'));
                $status = trim($_POST['status_pembayaran'] ?? 'Belum Bayar');

                // handle file
                $bukti_path = null;
                if ($isMultipart && isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['bukti_pembayaran']['tmp_name'];
                    $orig = basename($_FILES['bukti_pembayaran']['name']);
                    $ext = pathinfo($orig, PATHINFO_EXTENSION);
                    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
                    $target = $upload_dir . '/' . $filename;
                    if (!move_uploaded_file($tmp, $target)) {
                        respond(false, "Upload file gagal", null, 500);
                    }
                    $bukti_path = 'uploads/' . $filename;
                } else {
                    // allow posting bukti_pembayaran as text path as fallback
                    $bukti_path = trim($_POST['bukti_pembayaran'] ?? null);
                }

                if (!$order_id || !$total) respond(false, "order_id & total_amount wajib", null, 400);

                $q = "INSERT INTO tagihan (order_id, metode_pembayaran, bukti_pembayaran, total_amount, status_pembayaran, tanggal_tagihan)
                      VALUES ($1,$2,$3,$4,$5,$6)";
                $res = pg_query_params($conn, $q, [$order_id, $metode, $bukti_path, $total, $status, $tanggal]);
                if (!$res) respond(false, "Insert tagihan gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Tagihan berhasil dibuat.");
                break;

            case 'read':
                $sql = "SELECT t.tagihan_id, t.order_id, t.metode_pembayaran, t.bukti_pembayaran, t.total_amount, t.status_pembayaran, t.tanggal_tagihan
                        FROM tagihan t
                        ORDER BY t.tanggal_tagihan DESC";
                $res = pg_query($conn, $sql);
                $rows = fetch_all_or_empty($res);
                respond(true, "Data tagihan", $rows);
                break;

            case 'update':
                $tagihan_id = isset($_POST['tagihan_id']) ? intval($_POST['tagihan_id']) : null;
                if (!$tagihan_id) respond(false, "tagihan_id wajib", null, 400);
                $metode = isset($_POST['metode_pembayaran']) ? trim($_POST['metode_pembayaran']) : null;
                $total = isset($_POST['total_amount']) ? intval($_POST['total_amount']) : null;
                $tanggal = isset($_POST['tanggal_tagihan']) ? trim($_POST['tanggal_tagihan']) : null;
                $status = isset($_POST['status_pembayaran']) ? trim($_POST['status_pembayaran']) : null;
                $bukti_path = null;
                if (!empty($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['bukti_pembayaran']['tmp_name'];
                    $orig = basename($_FILES['bukti_pembayaran']['name']);
                    $ext = pathinfo($orig, PATHINFO_EXTENSION);
                    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
                    $target = $upload_dir . '/' . $filename;
                    if (!move_uploaded_file($tmp, $target)) respond(false, "Upload file gagal", null, 500);
                    $bukti_path = 'uploads/' . $filename;
                } else {
                    $bukti_path = isset($_POST['bukti_pembayaran']) ? trim($_POST['bukti_pembayaran']) : null;
                }

                $q = "UPDATE tagihan SET metode_pembayaran = COALESCE($2, metode_pembayaran),
                                        bukti_pembayaran = COALESCE($3, bukti_pembayaran),
                                        total_amount = COALESCE($4, total_amount),
                                        status_pembayaran = COALESCE($5, status_pembayaran),
                                        tanggal_tagihan = COALESCE($6, tanggal_tagihan)
                      WHERE tagihan_id = $1";
                $res = pg_query_params($conn, $q, [$tagihan_id, $metode, $bukti_path, $total, $status, $tanggal]);
                if (!$res) respond(false, "Update tagihan gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Tagihan berhasil diperbarui.");
                break;

            case 'delete':
                $tagihan_id = isset($_POST['tagihan_id']) ? intval($_POST['tagihan_id']) : null;
                if (!$tagihan_id) respond(false, "tagihan_id wajib", null, 400);
                $res = pg_query_params($conn, "DELETE FROM tagihan WHERE tagihan_id = $1", [$tagihan_id]);
                if (!$res) respond(false, "Delete tagihan gagal: " . pg_last_error($conn), null, 500);
                respond(true, "Tagihan berhasil dihapus.");
                break;

            default:
                respond(false, "Action tidak dikenali untuk tagihan", null, 400);
        }
    } catch (Exception $e) {
        respond(false, "Server error: ".$e->getMessage(), null, 500);
    }
}

respond(false, "Entity tidak dikenali.");
