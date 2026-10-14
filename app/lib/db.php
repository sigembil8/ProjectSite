<?php
declare(strict_types=1);

/** Koneksi PDO utama (aplikasi). */
function kh_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = kh_db_connect((array) kh_cfg('db'));
    }
    return $pdo;
}

/** Koneksi untuk dashboard peneliti (boleh pengguna DB terbatas). */
function kh_db_research(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = kh_cfg('db_research');
        $pdo = is_array($cfg) ? kh_db_connect($cfg) : kh_db();
    }
    return $pdo;
}

function kh_db_connect(array $cfg): PDO
{
    $pdo = new PDO($cfg['dsn'], $cfg['user'] ?? null, $cfg['pass'] ?? null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ]);
    // Simpan DATETIME dalam zona waktu server aplikasi.
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    return $pdo;
}

/** Jalankan kueri berparameter dan kembalikan statement. */
function kh_q(string $sql, array $params = [], ?PDO $pdo = null): PDOStatement
{
    $stmt = ($pdo ?? kh_db())->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
