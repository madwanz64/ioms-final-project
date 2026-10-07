#!/usr/bin/env bash
# Perintah harian IOMS lewat Docker Compose (Linux, macOS, Git Bash di Windows).
# Pakai: ./ioms.sh <perintah>      Daftar perintah: ./ioms.sh help
# Padanan Windows CMD: ioms.bat
set -euo pipefail
cd "$(dirname "$0")"

env_value() { # nilai dari .env, atau default bila tidak ada
  local value
  value=$(grep -E "^$1=" .env 2>/dev/null | tail -n 1 | cut -d= -f2- | tr -d '\r' || true)
  echo "${value:-$2}"
}

ensure_env() {
  if [ ! -f .env ]; then
    cp .env.example .env
    echo ">> .env dibuat dari .env.example (ubah DB_PASS bila perlu, sebelum database pertama kali dibuat)."
  fi
}

wait_for() { # $1 URL, $2 label, $3 pola isi yang ditunggu (opsional)
  printf '>> Menunggu %s' "$2"
  for _ in $(seq 1 90); do
    if body=$(curl -fsS "$1" 2>/dev/null) && [[ -z "${3:-}" || "$body" == *"$3"* ]]; then
      echo " siap: $1"
      return 0
    fi
    printf '.'
    sleep 2
  done
  echo
  echo "!! $2 belum siap setelah 3 menit. Cek: ./ioms.sh logs" >&2
  return 1
}

app_exec() { docker compose exec app "$@"; }

usage() {
  cat <<'EOF'
Pakai: ./ioms.sh <perintah>

Aplikasi
  up           Build & jalankan app + database (membuat .env bila belum ada)
  down         Hentikan container (data tetap)
  reset        Hapus database & upload lalu mulai ulang dari seed (minta konfirmasi)
  status       Status container
  logs         Log app & database (Ctrl+C untuk keluar)
  shell        Masuk shell container app

Kualitas
  test         Unit + integration test
  test-unit    Unit test saja
  test-int     Integration test saja
  analyse      PHPStan level 6
  low-stock    Script terjadwal JOB-01 (laporan stok di bawah reorder point)

SonarQube (opsional)
  sonar        Jalankan server SonarQube (http://localhost:9000)
  sonar-scan   Coverage + scan (butuh SONAR_TOKEN di .env)
  sonar-stop   Hentikan server SonarQube
EOF
}

cmd="${1:-help}"
case "$cmd" in
  up)
    ensure_env
    docker compose up --build -d
    wait_for "http://localhost:$(env_value APP_PORT 8080)/login" "aplikasi"
    ;;
  down)
    docker compose down
    ;;
  reset)
    read -r -p "Semua data database & gambar upload akan dihapus dan diganti seed. Lanjut? (y/N) " answer
    if [[ "$answer" != [yY] ]]; then
      echo "Dibatalkan."
      exit 0
    fi
    ensure_env
    docker compose down -v
    docker compose up --build -d
    wait_for "http://localhost:$(env_value APP_PORT 8080)/login" "aplikasi"
    ;;
  status)
    docker compose --profile sonar ps
    ;;
  logs)
    docker compose logs -f --tail=100 app db
    ;;
  shell)
    app_exec bash
    ;;
  test)       app_exec composer test ;;
  test-unit)  app_exec composer test:unit ;;
  test-int)   app_exec composer test:integration ;;
  analyse)    app_exec composer analyse ;;
  low-stock)  app_exec composer low-stock ;;
  sonar)
    docker compose --profile sonar up -d sonarqube
    wait_for "http://localhost:$(env_value SONAR_PORT 9000)/api/system/status" "SonarQube" '"UP"'
    if [ -z "$(env_value SONAR_TOKEN '')" ]; then
      echo ">> Pertama kali: login admin/admin, ganti password, buat token di"
      echo "   My Account > Security, lalu isi SONAR_TOKEN=... di .env"
    fi
    ;;
  sonar-scan)
    if [ -z "$(env_value SONAR_TOKEN '')" ]; then
      echo "!! SONAR_TOKEN belum diisi di .env. Jalankan ./ioms.sh sonar lalu buat token." >&2
      exit 1
    fi
    app_exec composer test:coverage
    rm -rf build
    docker compose cp app:/var/www/html/build ./build
    docker compose --profile sonar run --rm sonar-scanner
    echo ">> Hasil: http://localhost:$(env_value SONAR_PORT 9000)/dashboard?id=ioms"
    ;;
  sonar-stop)
    docker compose --profile sonar stop sonarqube
    ;;
  help|-h|--help)
    usage
    ;;
  *)
    echo "Perintah tidak dikenal: $cmd" >&2
    usage >&2
    exit 1
    ;;
esac
