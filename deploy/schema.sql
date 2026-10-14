-- KELUARGA HEBAT — skema database (MariaDB 10.4+ / MySQL 8+)
-- Impor lewat phpMyAdmin (tab "Import") atau:  mysql -u USER -p NAMA_DB < schema.sql
--
-- Prinsip privasi:
--   * Tidak ada data tentang anak di tabel mana pun.
--   * Email disimpan terenkripsi (email_enc) + indeks buta HMAC (email_bidx) untuk login.
--     Peneliti tidak pernah membaca tabel users secara langsung; dashboard memakai VIEW di bawah.
--   * Tidak ada kolom teks bebas untuk catatan pengguna.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  handle          VARCHAR(32)  NOT NULL,
  email_bidx      CHAR(64)     CHARACTER SET ascii NOT NULL COMMENT 'HMAC-SHA256 email ternormalisasi',
  email_enc       VARBINARY(400) NOT NULL COMMENT 'nonce + secretbox(email)',
  password_hash   VARCHAR(255) NOT NULL,
  consent_status  ENUM('given','withdrawn') NOT NULL DEFAULT 'given',
  consent_version VARCHAR(32)  NOT NULL,
  is_seed         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = data contoh dari seed.php',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_handle (handle),
  UNIQUE KEY uq_users_email (email_bidx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Token "tetap masuk" (cookie HttpOnly). Hanya hash validator yang disimpan.
CREATE TABLE IF NOT EXISTS sessions (
  selector        CHAR(24)     CHARACTER SET ascii NOT NULL,
  validator_hash  CHAR(64)     CHARACTER SET ascii NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME     NOT NULL,
  PRIMARY KEY (selector),
  KEY ix_sessions_user (user_id),
  KEY ix_sessions_expires (expires_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satu check-in per pengguna per hari. uuid dibuat di HP (idempotensi sinkronisasi).
CREATE TABLE IF NOT EXISTS checkins (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid              CHAR(36)     CHARACTER SET ascii NOT NULL,
  user_id           INT UNSIGNED NOT NULL,
  checkin_date      DATE         NOT NULL COMMENT 'tanggal lokal di HP pengguna',
  weather           ENUM('cerah','berawan','gerimis','badai') NOT NULL,
  rested            TINYINT(1)   NOT NULL COMMENT 'jawaban pertanyaan centang (1 = ya)',
  client_updated_at BIGINT UNSIGNED NOT NULL COMMENT 'epoch ms dari HP; aturan last-write-wins',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checkins_uuid (uuid),
  UNIQUE KEY uq_checkins_user_date (user_id, checkin_date),
  KEY ix_checkins_date (checkin_date),
  CONSTRAINT fk_checkins_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Riwayat persetujuan (diberikan / ditarik) beserta versinya.
CREATE TABLE IF NOT EXISTS consents (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  version     VARCHAR(32)  NOT NULL,
  action      ENUM('given','withdrawn') NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_consents_user (user_id),
  CONSTRAINT fk_consents_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opsional: kapan kartu dukungan tampil / kontak diketuk (maks. 1 per jenis per hari).
CREATE TABLE IF NOT EXISTS support_events (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  event_date  DATE         NOT NULL,
  kind        ENUM('card_shown','contact_tapped') NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_support_once (user_id, event_date, kind),
  CONSTRAINT fk_support_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  token_hash  CHAR(64)     CHARACTER SET ascii NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  PRIMARY KEY (token_hash),
  KEY ix_resets_user (user_id),
  CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pembatas percobaan (login, daftar, reset). bucket = sha256(nama|kunci), IP tidak disimpan polos.
CREATE TABLE IF NOT EXISTS rate_limits (
  bucket        CHAR(64)     CHARACTER SET ascii NOT NULL,
  hits          INT UNSIGNED NOT NULL,
  window_start  INT UNSIGNED NOT NULL,
  PRIMARY KEY (bucket),
  KEY ix_rate_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

-- Akun peneliti (peran terpisah, tidak terhubung ke users).
CREATE TABLE IF NOT EXISTS researchers (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username        VARCHAR(64)  NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  totp_secret_enc VARBINARY(200) NULL,
  totp_last_step  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'cegah kode TOTP dipakai ulang',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at   DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_researchers_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- VIEW untuk peneliti: hanya peserta yang persetujuannya aktif, tanpa email.
-- Dashboard peneliti HANYA membaca dua view ini.
-- ---------------------------------------------------------------------------
CREATE OR REPLACE VIEW research_participants AS
  SELECT u.id AS pid, u.handle, DATE(u.created_at) AS joined_on
  FROM users u
  WHERE u.consent_status = 'given';

CREATE OR REPLACE VIEW research_checkins AS
  SELECT u.id AS pid, u.handle, c.checkin_date, c.weather, c.rested
  FROM checkins c
  JOIN users u ON u.id = c.user_id
  WHERE u.consent_status = 'given';

CREATE OR REPLACE VIEW research_support_events AS
  SELECT s.event_date, s.kind
  FROM support_events s
  JOIN users u ON u.id = s.user_id
  WHERE u.consent_status = 'given';
