/**
 * OD Report → Google Sheets "Weekly Report Organization Development"
 *
 * Menerima pekerjaan Daily Report yang sudah "Simpan Laporan" dari website OD Report
 * lalu menuliskannya ke tab bulan (Nov'26, Des'26, ...) pada blok POSISI + WEEK yang sesuai.
 *
 * Cara pasang (sekali saja, oleh pemilik spreadsheet):
 *  1. Buka spreadsheet Weekly Report → Ekstensi → Apps Script.
 *  2. Hapus isi Code.gs, tempel seluruh file ini, lalu Simpan.
 *  3. Deploy → Deployment baru → jenis "Aplikasi web":
 *       Jalankan sebagai : Saya
 *       Yang memiliki akses : Siapa saja
 *     Izinkan akses saat diminta, lalu salin URL yang berakhiran /exec.
 *  4. Di Modul, buka menu Pengaturan → Sinkron Google Sheets (akun OD Leader),
 *     isi link spreadsheet + URL /exec, lalu Simpan & Tes.
 *  Setiap kali file ini diubah: Deploy → Kelola deployment → pensil → Versi: Versi baru → Deploy.
 *
 * Catatan:
 *  - Tanpa kode rahasia: siapa pun yang tahu URL /exec bisa mengirim baris ke spreadsheet ini.
 *  - Kolom H ("SYNC ID", disembunyikan) dipakai untuk mengenali baris yang dibuat website,
 *    sehingga edit memperbarui baris yang sama dan hapus mengosongkan isi barisnya.
 *    Baris lama yang diketik manual (tanpa SYNC ID) tidak pernah diubah.
 *  - Pekerjaan baru mengisi baris kosong pertama di blok POSISI + WEEK yang sesuai.
 *    Kalau blok sudah penuh, satu baris ditambahkan di ujung blok (merge ikut memanjang).
 *    Merge POSISI / WEEK yang ada tidak pernah dipotong.
 *  - Tab bulan yang belum ada dibuat otomatis dengan menyalin struktur tab bulan terakhir
 *    (posisi, blok week, baris kosong) lalu mengosongkan isi pekerjaannya.
 */

const KEY_COL = 8;   // kolom H
const TAB_RE = /^(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agt|Ags|AGS|Sep|Sept|Okt|Nov|Des)'\d{2}$/i;

/* ---------- HTTP ---------- */

function out_(obj, cb) {
  const s = JSON.stringify(obj);
  if (cb && /^[\w.$]+$/.test(cb)) {
    return ContentService.createTextOutput(cb + "(" + s + ")").setMimeType(ContentService.MimeType.JAVASCRIPT);
  }
  return ContentService.createTextOutput(s).setMimeType(ContentService.MimeType.JSON);
}

function doGet(e) {
  const p = (e && e.parameter) || {}, cb = p.callback;
  if (p.ping) return out_({ ok: true }, cb);                       // cek akses
  if (p.action == "status") {
    const v = CacheService.getScriptCache().get("st:" + p.stamp);
    return out_(v ? JSON.parse(v) : { ok: null, pending: true }, cb);
  }
  if (p.action == "ping") {
    try {
      return out_({ ok: true, weekly: p.w ? SpreadsheetApp.openById(p.w).getName() : "" }, cb);
    } catch (err) {
      return out_({ ok: false, error: "Spreadsheet tidak bisa dibuka: " + err.message }, cb);
    }
  }
  return out_({ ok: false, error: "Aksi tidak dikenal" }, cb);
}

function doPost(e) {
  let body;
  try { body = JSON.parse(e.postData.contents); } catch (err) { return out_({ ok: false, error: "Data tidak terbaca" }); }
  const stamp = String(body.stamp || "");
  const done = r => {
    r.stamp = stamp;
    if (stamp) CacheService.getScriptCache().put("st:" + stamp, JSON.stringify(r), 21600);
    return out_(r);
  };
  if (!body.weeklyId) return done({ ok: true, a: 0, d: 0, log: "" });   // tes jalur kirim, tidak menulis apa pun

  const lock = LockService.getScriptLock();
  if (!lock.tryLock(30000)) return done({ ok: false, error: "Spreadsheet sedang dipakai sinkron lain, coba lagi" });
  try {
    const ss = SpreadsheetApp.openById(body.weeklyId);
    const log = [], fail = [];
    let a = 0, d = 0;
    (body.del || []).forEach(k => {
      try { if (removeKey_(ss, String(k))) d++; } catch (err) { fail.push(String(k)); log.push("Hapus gagal: " + err.message); }
    });
    (body.entries || []).forEach(en => {
      try { upsert_(ss, en); a++; } catch (err) { fail.push(String(en.key)); log.push(en.n + ": " + err.message); }
    });
    SpreadsheetApp.flush();
    return done({ ok: true, a: a, d: d, fail: fail, log: log.join("\n") });
  } catch (err) {
    return done({ ok: false, error: err.message });
  } finally {
    lock.releaseLock();
  }
}

/* ---------- Spreadsheet ---------- */

const norm_ = s => String(s == null ? "" : s).replace(/\s+/g, " ").trim().toUpperCase();
const clean_ = v => (v == null || String(v).trim() == "-" ? "" : String(v));

function findHeader_(sh) {
  const n = Math.min(sh.getLastRow(), 10);
  if (n < 1 || sh.getMaxColumns() < 3) return 0;
  const v = sh.getRange(1, 3, n, 1).getDisplayValues();
  for (let i = 0; i < n; i++) if (norm_(v[i][0]) == "DETAIL PEKERJAAN") return i + 1;
  return 0;
}

const monthTabs_ = ss => ss.getSheets().filter(s => TAB_RE.test(s.getName().trim()));

function getTab_(ss, name) {
  let sh = ss.getSheetByName(name) || ss.getSheets().find(s => s.getName().trim().toUpperCase() == name.toUpperCase());
  if (sh) return sh;
  const tpl = monthTabs_(ss).filter(s => findHeader_(s)).pop();
  if (!tpl) throw new Error("Tidak ada tab bulan yang bisa dijadikan contoh untuk " + name);
  const h = findHeader_(tpl);
  sh = tpl.copyTo(ss).setName(name);
  ss.setActiveSheet(sh);
  ss.moveActiveSheet(ss.getNumSheets());
  // Pertahankan struktur POSISI / WEEK (merge + baris kosong per week), kosongkan isi pekerjaannya.
  const n = sh.getMaxRows() - h;
  if (n > 0) sh.getRange(h + 1, 3, n, Math.max(1, Math.min(sh.getMaxColumns(), KEY_COL) - 2)).clearContent();
  return sh;
}

function ensureKeyCol_(sh, h) {
  if (sh.getMaxColumns() < KEY_COL) sh.insertColumnsAfter(sh.getMaxColumns(), KEY_COL - sh.getMaxColumns());
  const c = sh.getRange(h, KEY_COL);
  if (c.getValue() !== "SYNC ID") {
    c.setValue("SYNC ID");
    sh.hideColumns(KEY_COL);
  }
}

// Baris data di bawah header, dengan POSISI / WEEK dibaca dari merge yang ada (bukan ditebak dari teks).
// Tab Weekly Report punya baris kosong yang sudah disiapkan per week; baris itu ikut terbaca (c kosong).
function readRows_(sh, h) {
  const n = sh.getMaxRows() - h;
  if (n <= 0) return [];
  const v = sh.getRange(h + 1, 1, n, Math.min(KEY_COL, sh.getMaxColumns())).getDisplayValues();
  const mA = {}, mB = {};
  sh.getRange(h + 1, 1, n, 2).getMergedRanges().forEach(m => {
    const r0 = m.getRow(), r1 = m.getLastRow(), c0 = m.getColumn(), c1 = m.getLastColumn();
    for (let r = r0; r <= r1; r++) {
      if (c0 <= 1 && c1 >= 1) mA[r] = [r0, r1];
      if (c0 <= 2 && c1 >= 2) mB[r] = [r0, r1];
    }
  });
  const val = (r, c) => (v[r - h - 1] || [])[c - 1] || "";
  const out = [];
  for (let i = 0; i < n; i++) {
    const row = h + 1 + i;
    const a = mA[row] || [row, row], b = mB[row] || [row, row];
    const posRaw = val(a[0], 1), wk = String(val(b[0], 2)).trim();
    out.push({ row, posRaw, pos: norm_(posRaw), wk, a, b, c: String(v[i][2] || "").trim(), key: v[i][KEY_COL - 1] || "" });
  }
  // potong baris kosong di ujung bawah yang tidak termasuk blok mana pun
  while (out.length && !out[out.length - 1].pos && !out[out.length - 1].c) out.pop();
  return out;
}

function matchPos_(rows, pos) {
  const P = norm_(pos);
  if (rows.some(r => r.pos == P)) return P;
  const kw = /LEADER/.test(P) ? "LEADER" : /STAFF/.test(P) ? "STAFF" : "";
  const hit = kw && rows.find(r => r.pos.indexOf(kw) >= 0 && r.pos.indexOf("ORGANIZATION") >= 0);
  return hit ? hit.pos : P;
}

function findKey_(ss, key) {
  const tabs = monthTabs_(ss);
  for (let i = 0; i < tabs.length; i++) {
    const sh = tabs[i];
    if (sh.getMaxColumns() < KEY_COL) continue;
    const f = sh.getRange(1, KEY_COL, sh.getMaxRows(), 1).createTextFinder(key).matchEntireCell(true).findNext();
    if (f) return { sh: sh, row: f.getRow() };
  }
  return null;
}

function writeRow_(sh, row, vals, key) {
  sh.getRange(row, 3, 1, 5).setValues(vals);
  sh.getRange(row, KEY_COL).setValue(key);
}

function upsert_(ss, en) {
  const key = String(en.key || "");
  if (!key) throw new Error("SYNC ID kosong");
  const sh = getTab_(ss, String(en.tab));
  const h = findHeader_(sh);
  if (!h) throw new Error("Header DETAIL PEKERJAAN tidak ditemukan di tab " + en.tab);
  ensureKeyCol_(sh, h);
  const vals = [[clean_(en.n), clean_(en.r), clean_(en.k), clean_(en.s), clean_(en.ke)]];   // C..G
  const W = String(en.wk);

  const found = findKey_(ss, key);
  if (found) {
    if (found.sh.getSheetId() == sh.getSheetId()) {
      const rows = readRows_(sh, h), cur = rows.find(r => r.row == found.row);
      if (cur && cur.pos == matchPos_(rows, en.pos) && cur.wk == W) {
        writeRow_(sh, found.row, vals, key);
        return;
      }
    }
    clearRow_(found.sh, found.row);                                 // pindah week / bulan: kosongkan baris lama
  }
  insert_(sh, h, en, vals, key);
}

// Gabungkan ulang satu kolom pada r0..r1 (yang sebelumnya merge r0..r1-1 atau r0+1..r1) dengan satu nilai.
function remerge_(sh, col, r0, r1, value) {
  const rg = sh.getRange(r0, col, r1 - r0 + 1, 1);
  rg.getMergedRanges().forEach(m => m.breakApart());
  rg.getCell(1, 1).setValue(value);
  if (r1 > r0) rg.merge();
  rg.setVerticalAlignment("middle").setHorizontalAlignment("center");
}

function copyFormat_(sh, from, to) {
  if (from > 0 && from <= sh.getMaxRows()) {
    sh.getRange(from, 3, 1, KEY_COL - 2).copyTo(sh.getRange(to, 3, 1, KEY_COL - 2), SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
  }
}

function insert_(sh, h, en, vals, key) {
  const rows = readRows_(sh, h);
  const P = matchPos_(rows, en.pos), W = String(en.wk);
  const inPos = rows.filter(r => r.pos == P);
  const blk = inPos.filter(r => r.wk == W);

  // 1) Blok posisi + week sudah ada: isi baris kosong pertama, atau tambah satu baris di ujung blok.
  if (blk.length) {
    const empty = blk.find(r => !r.c && !r.key);
    if (empty) { writeRow_(sh, empty.row, vals, key); return; }
    const last = blk[blk.length - 1];
    const aEnd = last.a[1], bTop = last.b[0];
    if (last.row > bTop) {
      // sisip di dalam merge (A dan B ikut memanjang otomatis), lalu geser isi baris terakhir ke baris baru
      sh.insertRowAfter(last.row - 1);
      const moved = sh.getRange(last.row + 1, 3, 1, KEY_COL - 2).getValues();
      sh.getRange(last.row, 3, 1, KEY_COL - 2).setValues(moved);
      copyFormat_(sh, last.row + 1, last.row);
      writeRow_(sh, last.row + 1, vals, key);
      return;
    }
    // blok week hanya satu baris: tambah di bawahnya lalu perpanjang merge WEEK (dan POSISI bila perlu)
    sh.insertRowAfter(last.row);
    const nr = last.row + 1;
    copyFormat_(sh, last.row, nr);
    writeRow_(sh, nr, vals, key);
    remerge_(sh, 2, bTop, nr, W);
    if (aEnd == last.row) remerge_(sh, 1, last.a[0], nr, last.posRaw);
    return;
  }

  // 2) Posisi ada, week belum: sisip setelah week sebelumnya (urut), perpanjang merge POSISI.
  if (inPos.length) {
    const before = inPos.filter(r => r.wk !== "" && +r.wk < +W);
    const posTop = inPos[0].a[0], posEnd = inPos[inPos.length - 1].a[1], posRaw = inPos[0].posRaw;
    let nr;
    if (before.length) {
      const after = before[before.length - 1].b[1];               // akhir blok week sebelumnya
      sh.insertRowAfter(after);
      nr = after + 1;
      copyFormat_(sh, after, nr);
      if (after >= posEnd) remerge_(sh, 1, posTop, nr, posRaw);    // di ujung blok posisi: perpanjang manual
    } else {
      sh.insertRowBefore(posTop);
      nr = posTop;
      copyFormat_(sh, posTop + 1, nr);
      remerge_(sh, 1, nr, posEnd + 1, posRaw);
    }
    sh.getRange(nr, 2).setValue(W).setHorizontalAlignment("center").setVerticalAlignment("middle");
    writeRow_(sh, nr, vals, key);
    return;
  }

  // 3) Posisi belum ada: tambahkan blok baru di bawah semua data.
  const end = rows.length ? Math.max(...rows.map(r => Math.max(r.row, r.a[1], r.b[1]))) : h;
  let nr = end + 1;
  if (nr > sh.getMaxRows()) sh.insertRowAfter(sh.getMaxRows());
  copyFormat_(sh, end > h ? end : 0, nr);
  sh.getRange(nr, 1, 1, 2).setValues([[norm_(en.pos), W]]);
  sh.getRange(nr, 1, 1, 2).setHorizontalAlignment("center").setVerticalAlignment("middle");
  writeRow_(sh, nr, vals, key);
}

function removeKey_(ss, key) {
  const f = findKey_(ss, key);
  if (!f) return false;
  clearRow_(f.sh, f.row);
  return true;
}

// Hapus = kosongkan isi baris (DETAIL..KETERANGAN + SYNC ID). Baris & merge tetap, seperti baris kosong template.
function clearRow_(sh, row) {
  sh.getRange(row, 3, 1, KEY_COL - 2).clearContent();
}
