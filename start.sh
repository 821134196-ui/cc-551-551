#!/usr/bin/env bash
# 毕业设计指导预约与评语版本系统 —— 一条命令启动
# 用法：./start.sh   （访问 http://127.0.0.1:8000）
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
APP="$ROOT/app"
PHP="$ROOT/.tools/php"
PORT="${PORT:-8000}"

if [ ! -x "$PHP" ]; then
    echo "未找到静态 PHP ($PHP)，请先检查 .tools 目录。" >&2
    exit 1
fi

cd "$APP"

# 1) 依赖（首次运行）
if [ ! -d vendor ]; then
    echo "→ 安装 Composer 依赖…"
    COMPOSER_HOME="$ROOT/.tools/composer" "$PHP" "$ROOT/.tools/composer.phar" install --no-interaction
fi
if [ ! -d node_modules ]; then
    echo "→ 安装 npm 依赖…"
    npm install --silent
fi

# 2) 数据库（SQLite）：不存在或 --fresh 时重建并填充演示数据
if [ ! -f database/database.sqlite ] || [ "${1:-}" = "--fresh" ]; then
    echo "→ 初始化 SQLite 数据库并写入演示数据…"
    touch database/database.sqlite
    "$PHP" artisan migrate:fresh --seed --force
fi

# 3) 前端资源（不存在时构建）
if [ ! -f public/build/manifest.json ]; then
    echo "→ 构建 React 前端…"
    npm run build
fi

# 4) 本地附件目录
mkdir -p storage/app/attachments storage/logs

echo ""
echo "════════════════════════════════════════════════════"
echo " 系统已启动： http://127.0.0.1:${PORT}"
echo " 演示账号（密码均为 password）："
echo "   学生  zhangsan@demo.edu   / sunwu@demo.edu（导师A）"
echo "         zhaosi@demo.edu（导师B）"
echo "   导师  wangming@demo.edu（王明） / lihua@demo.edu（李华）"
echo " 重新初始化演示数据： ./start.sh --fresh"
echo " 运行场景化验证：     ./demo.sh"
echo "════════════════════════════════════════════════════"
echo ""

exec "$PHP" artisan serve --host=127.0.0.1 --port="$PORT"
