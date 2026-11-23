"""
Bangun data wilayah statis untuk form pendaftaran (provinsi > kota > kecamatan > kelurahan + kode pos).

Sumber (MIT): https://github.com/cahyadsn/wilayah (db/wilayah.sql)
              https://github.com/cahyadsn/wilayah_kodepos (db/wilayah_kodepos.sql)
Pemakaian:    python3 database/wilayah/build.py wilayah.sql wilayah_kodepos.sql

Hasil di public_html/assets/wilayah/:
  provinsi.json  -> [[kode, nama], ...]
  32.json        -> kota/kabupaten di provinsi 32: [[kode, nama], ...]
  32.73.json     -> kecamatan di kota 32.73 beserta kelurahannya:
                    [[kode_kec, nama_kec, [[kode_kel, nama_kel, kodepos], ...]], ...]
"""
import json
import os
import re
import sys

ROW = re.compile(r"\(\s*'([\d.]+)'\s*,\s*'((?:[^'\\]|\\.|'')*)'\s*\)")


def rows(path):
    with open(path, encoding="utf-8") as f:
        for kode, val in ROW.findall(f.read()):
            yield kode, val.replace("''", "'").replace("\\'", "'")


def main(wilayah_sql, kodepos_sql):
    out = os.path.join(os.path.dirname(__file__), "..", "..", "public_html", "assets", "wilayah")
    os.makedirs(out, exist_ok=True)

    kodepos = dict(rows(kodepos_sql))
    prov, kota, kec, kel = [], {}, {}, {}
    for kode, nama in rows(wilayah_sql):
        level = kode.count(".")
        parent = kode.rsplit(".", 1)[0]
        if level == 0:
            prov.append([kode, nama])
        elif level == 1:
            kota.setdefault(parent, []).append([kode, nama])
        elif level == 2:
            kec.setdefault(parent, []).append([kode, nama])
        elif level == 3:
            kel.setdefault(parent, []).append([kode, nama, kodepos.get(kode, "")])

    def dump(name, data):
        with open(os.path.join(out, name + ".json"), "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, separators=(",", ":"))

    by_name = lambda r: r[1]
    dump("provinsi", sorted(prov, key=by_name))
    for p, items in kota.items():
        dump(p, sorted(items, key=by_name))
    for k, items in kec.items():
        dump(k, [[c, n, sorted(kel.get(c, []), key=by_name)] for c, n in sorted(items, key=by_name)])

    assert len(prov) >= 38 and sum(map(len, kota.values())) >= 514, "data wilayah tidak lengkap"
    print(f"{len(prov)} provinsi, {sum(map(len, kota.values()))} kota/kab, "
          f"{sum(map(len, kec.values()))} kecamatan, {sum(map(len, kel.values()))} kelurahan/desa, "
          f"{sum(1 for v in kel.values() for r in v if r[2])} dengan kode pos")


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
