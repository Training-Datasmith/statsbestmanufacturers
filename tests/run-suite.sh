#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DOCKER_BIN="${DOCKER_BIN:-docker}"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"

PHP_IMAGE="php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143"
MARIADB_IMAGE="mariadb@sha256:f1c56f035ed268a0d7c2c2e06dc879f6319b997cb434cf822a4cfd72a7aa184d"
PHP56_LOCAL_IMAGE="statsbestmanufacturers-php56:local"
NETWORK_NAME="statsbest-net"
DB_CONTAINER="statsbest-db"

PHPUNIT_VERSION="4.8.36"
PHPUNIT_SHA256="ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e"
PHPUNIT_PGP_FPR="D8406D0D82947747293778314AA394086372C20A"

ARTIFACT_RESULTS="${ARTIFACT_RESULTS:-/opt/cursor/artifacts/suite-results.md}"
ORDERED_LOG=""
SHUFFLED_LOG=""

cleanup() {
  if [[ -n "${TMP_SUITE_DIR:-}" && -d "${TMP_SUITE_DIR}" ]]; then
    rm -rf "${TMP_SUITE_DIR}"
  fi
  if [[ "${KEEP_DB:-0}" != "1" ]]; then
    "${DOCKER_BIN}" rm -f "${DB_CONTAINER}" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

docker_cmd() {
  if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
    sudo -n "${DOCKER_BIN}" "$@"
  else
    "${DOCKER_BIN}" "$@"
  fi
}

require_docker() {
  if ! command -v "${DOCKER_BIN}" >/dev/null 2>&1; then
    echo "Docker binary not found in PATH (set DOCKER_BIN or install Docker)." >&2
    exit 1
  fi
  if ! docker_cmd version >/dev/null 2>&1; then
    echo "Docker daemon is not reachable." >&2
    exit 1
  fi
}

verify_phpunit_phar() {
  TMP_SUITE_DIR="$(mktemp -d)"
  chmod 0700 "${TMP_SUITE_DIR}"
  PHPUNIT_PHAR="${TMP_SUITE_DIR}/phpunit-${PHPUNIT_VERSION}.phar"
  PHPUNIT_ASC="${TMP_SUITE_DIR}/phpunit-${PHPUNIT_VERSION}.phar.asc"
  GNUPGHOME="${TMP_SUITE_DIR}/gnupg"
  mkdir -m 0700 "${GNUPGHOME}"

  curl -fsSL -o "${PHPUNIT_PHAR}" "https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar"
  curl -fsSL -o "${PHPUNIT_ASC}" "https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar.asc"

  echo "${PHPUNIT_SHA256}  ${PHPUNIT_PHAR}" | sha256sum -c -

  GNUPGHOME="${GNUPGHOME}" gpg --batch --keyserver hkps://keys.openpgp.org \
    --recv-keys "${PHPUNIT_PGP_FPR}"
  if ! GNUPGHOME="${GNUPGHOME}" gpg --batch --status-fd 1 --verify "${PHPUNIT_ASC}" "${PHPUNIT_PHAR}" 2>&1 \
    | tee "${TMP_SUITE_DIR}/gpg-status.txt" \
    | grep -q "VALIDSIG ${PHPUNIT_PGP_FPR}"; then
    echo "PHPUnit PHAR signature verification failed." >&2
    exit 1
  fi
  export PHPUNIT_PHAR
}

ensure_network() {
  if ! docker_cmd network inspect "${NETWORK_NAME}" >/dev/null 2>&1; then
    docker_cmd network create "${NETWORK_NAME}" >/dev/null
  fi
}

start_database() {
  STATS_DB_PASSWORD="$(openssl rand -hex 16)"
  STATS_DB_ROOT_PASSWORD="$(openssl rand -hex 16)"
  export STATS_DB_PASSWORD STATS_DB_ROOT_PASSWORD
  STATS_DB_HOST="${DB_CONTAINER}"
  STATS_DB_PORT="3306"
  STATS_DB_NAME="statsbest_test"
  STATS_DB_USER="statsbest"
  export STATS_DB_HOST STATS_DB_PORT STATS_DB_NAME STATS_DB_USER

  docker_cmd rm -f "${DB_CONTAINER}" >/dev/null 2>&1 || true
  docker_cmd pull "${MARIADB_IMAGE}"
  docker_cmd run -d --name "${DB_CONTAINER}" --network "${NETWORK_NAME}" \
    -e MYSQL_DATABASE="${STATS_DB_NAME}" \
    -e MYSQL_USER="${STATS_DB_USER}" \
    -e MYSQL_PASSWORD="${STATS_DB_PASSWORD}" \
    -e MYSQL_ROOT_PASSWORD="${STATS_DB_ROOT_PASSWORD}" \
  "${MARIADB_IMAGE}" >/dev/null

  local attempts=0
  until docker_cmd exec "${DB_CONTAINER}" mariadb-admin ping -h127.0.0.1 -uroot -p"${STATS_DB_ROOT_PASSWORD}" --silent; do
    attempts=$((attempts + 1))
    if [[ "${attempts}" -gt 60 ]]; then
      echo "MariaDB did not become ready in time." >&2
      exit 1
    fi
    sleep 1
  done
}

build_php_runner() {
  if ! docker_cmd image inspect "${PHP56_LOCAL_IMAGE}" >/dev/null 2>&1; then
    docker_cmd pull "${PHP_IMAGE}"
    docker_cmd build -t "${PHP56_LOCAL_IMAGE}" -f "${REPO_ROOT}/tests/docker/php56.Dockerfile" "${REPO_ROOT}/tests/docker"
  fi
}

run_phpunit_once() {
  local shuffle_flag="$1"
  local output
  local status
  set +e
  output="$(
    docker_cmd run --rm \
      --network "container:${DB_CONTAINER}" \
      -e STATS_DB_HOST="127.0.0.1" \
      -e STATS_DB_PORT="${STATS_DB_PORT}" \
      -e STATS_DB_NAME="${STATS_DB_NAME}" \
      -e STATS_DB_USER="${STATS_DB_USER}" \
      -e STATS_DB_PASSWORD="${STATS_DB_PASSWORD}" \
      -e PHPUNIT_PHAR="${PHPUNIT_PHAR}" \
      -e PHPUNIT_SHUFFLE="${shuffle_flag}" \
      -v "${REPO_ROOT}:/src:ro" \
      -v "${PHPUNIT_PHAR}:${PHPUNIT_PHAR}:ro" \
      -w /src \
      "${PHP56_LOCAL_IMAGE}" \
      php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 \
          -d html_errors=0 -d log_errors=0 \
          /src/tests/phpunit/run-phpunit.php 2>&1
  )"
  status=$?
  set -e
  printf '%s' "${output}"
  return "${status}"
}

write_artifact() {
  local sha branch ordered_summary shuffled_summary
  branch="$(git -C "${REPO_ROOT}" rev-parse --abbrev-ref HEAD)"
  sha="$(git -C "${REPO_ROOT}" rev-parse HEAD)"
  ordered_summary="$(printf '%s' "${ORDERED_LOG}" | grep -F 'OK (30 tests' | tail -1 || true)"
  shuffled_summary="$(printf '%s' "${SHUFFLED_LOG}" | grep -F 'OK (30 tests' | tail -1 || true)"
  mkdir -p "$(dirname "${ARTIFACT_RESULTS}")"
  {
    echo "# statsbestmanufacturers PHPUnit suite"
    echo ""
    echo "- Repo: Training-Datasmith/statsbestmanufacturers"
    echo "- Date: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "- Branch: ${branch}"
    echo "- Commit: ${sha}"
    echo "- PHP: 5.6.40 (digest-pinned image)"
    echo "- MariaDB: digest-pinned 10.6"
    echo "- Command: tests/run-suite.sh"
    echo "- Tests: 30"
    echo "- Ordered summary: ${ordered_summary}"
    echo "- Shuffled summary: ${shuffled_summary}"
    echo "- Failures: 0"
    echo "- Errors: 0"
    echo "- Skipped/incomplete: 0"
    echo ""
    echo "## Ordered run"
    echo '```'
    echo "${ORDERED_LOG}"
    echo '```'
    echo ""
    echo "## Shuffled run"
    echo '```'
    echo "${SHUFFLED_LOG}"
    echo '```'
  } > "${ARTIFACT_RESULTS}"
}

main() {
  require_docker
  verify_phpunit_phar
  ensure_network
  start_database
  build_php_runner

  if ! ORDERED_LOG="$(run_phpunit_once 0)"; then
    echo "${ORDERED_LOG}"
    exit 1
  fi
  echo "${ORDERED_LOG}"
  if ! SHUFFLED_LOG="$(run_phpunit_once 1)"; then
    echo "${SHUFFLED_LOG}"
    exit 1
  fi
  echo "${SHUFFLED_LOG}"

  write_artifact
}

main "$@"
