#!/usr/bin/env bash
#
# 一键启动：毕业设计指导预约与评语版本系统
# 用法：
#   ./start.sh            首次安装依赖、迁移+演示数据，然后在 http://localhost:8000 启动
#   ./start.sh --fresh    清空数据库并重新填充演示数据后启动
#   ./start.sh --no-seed  不执行数据库初始化（保留现有数据）
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-}"
FRESH=0
NO_SEED=0
for arg in "$@"; do
    case "$arg" in
        --fresh) FRESH=1 ;;
        --no-seed) NO_SEED=1 ;;
    esac
done

log() { printf '\033[36m[start]\033[0m %s\n' "$1"; }

# ---------------------------------------------------------------------------
# 1. 确保 PHP 可用（无 root 时下载静态 PHP 到用户目录）
# ---------------------------------------------------------------------------
ensure_php() {
    if [ -n "$PHP_BIN" ] && [ -x "$PHP_BIN" ]; then return; fi
    if command -v php >/dev/null 2>&1 && php -m | grep -q pdo_sqlite; then
        PHP_BIN="$(command -v php)"; return
    fi
    if [ -x "$HOME/.local/bin/php" ] && "$HOME/.local/bin/php" -m | grep -q pdo_sqlite; then
        PHP_BIN="$HOME/.local/bin/php"; return
    fi

    log "未找到带 pdo_sqlite 的 PHP，下载静态 PHP 到 ~/.local/bin …"
    mkdir -p "$HOME/.local/bin"
    local url="https://dl.static-php.dev/static-php-cli/common/php-8.3.20-cli-linux-x86_64.tar.gz"
    curl -sSL --fail "$url" -o /tmp/static-php.tar.gz
    tar xzf /tmp/static-php.tar.gz -C /tmp
    mv /tmp/php "$HOME/.local/bin/php"
    chmod +x "$HOME/.local/bin/php"
    PHP_BIN="$HOME/.local/bin/php"
}

# ---------------------------------------------------------------------------
# 2. 确保 Composer 可用
# ---------------------------------------------------------------------------
ensure_composer() {
    if command -v composer >/dev/null 2>&1; then COMPOSER="composer"; return; fi
    if [ -x "$HOME/.local/bin/composer" ]; then COMPOSER="$HOME/.local/bin/composer"; return; fi
    log "安装 Composer …"
    mkdir -p "$HOME/.local/bin"
    curl -sS https://getcomposer.org/installer | "$PHP_BIN" -- --install-dir="$HOME/.local/bin" --filename=composer
    COMPOSER="$HOME/.local/bin/composer"
}

ensure_php
ensure_composer
export PATH="$HOME/.local/bin:$PATH"
log "使用 PHP：$($PHP_BIN -r 'echo PHP_VERSION;')"

# ---------------------------------------------------------------------------
# 3. PHP / Node 依赖
# ---------------------------------------------------------------------------
if [ ! -d vendor ]; then
    log "安装 PHP 依赖（composer install）…"
    "$COMPOSER" install --no-interaction --prefer-dist
fi

if [ ! -d node_modules ]; then
    log "安装前端依赖（npm install）…"
    npm install
fi

# ---------------------------------------------------------------------------
# 4. 环境配置
# ---------------------------------------------------------------------------
if [ ! -f .env ]; then
    log "从 .env.example 创建 .env …"
    cp .env.example .env
    # 切换到 SQLite
    sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
    grep -q '^DB_DATABASE=' .env \
        && sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$ROOT/database/database.sqlite|" .env \
        || echo "DB_DATABASE=$ROOT/database/database.sqlite" >> .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    log "生成 APP_KEY …"
    "$PHP_BIN" artisan key:generate --force
fi

mkdir -p database storage/app/private storage/logs bootstrap/cache
touch database/database.sqlite
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true

# ---------------------------------------------------------------------------
# 5. 前端构建
# ---------------------------------------------------------------------------
log "构建 React 前端 …"
npm run build

# ---------------------------------------------------------------------------
# 6. 数据库迁移与演示数据
# ---------------------------------------------------------------------------
if [ "$NO_SEED" -eq 0 ]; then
    if [ "$FRESH" -eq 1 ]; then
        log "清理旧附件与本地通知日志 …"
        rm -rf storage/app/private/attachments
        rm -f storage/logs/notifications.log
        mkdir -p storage/app/private storage/logs
        log "重置数据库并填充演示数据 …"
        "$PHP_BIN" artisan migrate:fresh --seed --force
    else
        "$PHP_BIN" artisan migrate --force
        # 仅在尚无用户时填充演示数据
        user_count="$("$PHP_BIN" artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -1 | tr -dc '0-9' || echo 0)"
        if [ "${user_count:-0}" = "0" ]; then
            log "填充演示数据 …"
            "$PHP_BIN" artisan db:seed --force
        fi
    fi
fi

"$PHP_BIN" artisan config:clear >/dev/null 2>&1 || true

# ---------------------------------------------------------------------------
# 7. 启动
# ---------------------------------------------------------------------------
PORT="${PORT:-8000}"
log "启动完成：请在浏览器打开 http://localhost:${PORT}"
log "演示账号：zhang@example.edu / chen@example.edu / wang@example.edu / li@example.edu（密码均为 password）"
exec "$PHP_BIN" artisan serve --host=0.0.0.0 --port="${PORT}"
