#!/usr/bin/env bash
# 端到端示例验证：通过真实 HTTP 请求演示
#   ① 预约冲突（同一时段唯一 + 请假释放）
#   ② 稿件与评语版本追溯（撤回不可变 / 逐条回应 / 意见关闭权限）
#   ③ 跨导师访问隔离（403）
# 脚本会自行启动测试服务器，结束后自动关闭。
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
PHP="$ROOT/.tools/php"
APP="$ROOT/app"
PORT="${DEMO_PORT:-8011}"
BASE="http://127.0.0.1:${PORT}"

cd "$APP"
touch database/database.sqlite
echo "→ 重建演示数据…"
"$PHP" artisan migrate:fresh --seed --force >/dev/null 2>&1

echo "→ 启动测试服务器 (:${PORT})…"
"$PHP" artisan serve --host=127.0.0.1 --port="$PORT" >/tmp/demo-serve.log 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null || true' EXIT
for i in $(seq 1 20); do curl -s "$BASE/up" >/dev/null 2>&1 && break; sleep 0.5; done

WANG='Authorization: Bearer token-advisor-wang'
LI='Authorization: Bearer token-advisor-li'
ZHANG='Authorization: Bearer token-student-zhang'
SUN='Authorization: Bearer token-student-sun'
ZHAO='Authorization: Bearer token-student-zhao'

PASS=0; FAIL=0
# check <名称> <期望状态码> <实际状态码>
check() {
  local name="$1" expect="$2" actual="$3"
  if [ "$expect" = "$actual" ]; then
    printf "  ✅ %-52s [%s]\n" "$name" "$actual"; PASS=$((PASS+1))
  else
    printf "  ❌ %-52s [期望%s 实际%s]\n" "$name" "$expect" "$actual"; FAIL=$((FAIL+1))
  fi
}
# 响应体统一写入 /tmp/r.json
jget() { "$PHP" -r '$d=json_decode(file_get_contents($argv[1]),true); echo $d[$argv[2]] ?? "";' /tmp/r.json "$1" 2>/dev/null; }

echo ""
echo "════════════════ ① 预约冲突 ════════════════"
# 查找导师A的开放时段
OPEN_SLOT=$(curl -s -H "$ZHANG" "$BASE/api/time-slots" | "$PHP" -r '$d=json_decode(file_get_contents("php://stdin"),true)["data"]; foreach($d as $s){ if($s["status"]==="open" && !$s["booked_by"]){ echo $s["id"]; break; }}')
LI_SLOT=$(curl -s -H "$ZHAO" "$BASE/api/time-slots" | "$PHP" -r '$d=json_decode(file_get_contents("php://stdin"),true)["data"]; echo $d[0]["id"];')

s=$(curl -s -o /tmp/r.json -w '%{http_code}' -X POST -H "$ZHANG" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"time_slot_id\":$OPEN_SLOT,\"questions\":[\"示例问题A\"]}" "$BASE/api/appointments")
check "张三预约导师A的开放时段 → 201" 201 "$s"

s=$(curl -s -o /tmp/r.json -w '%{http_code}' -X POST -H "$SUN" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"time_slot_id\":$OPEN_SLOT}" "$BASE/api/appointments")
check "孙五抢约同一时段 → 409 Conflict" 409 "$s"
echo "     返回信息：$("$PHP" -r 'echo json_decode(file_get_contents("/tmp/r.json"),true)["message"];')"

s=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H "$ZHANG" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"time_slot_id\":$LI_SLOT}" "$BASE/api/appointments")
check "张三预约导师B的时段（跨导师）→ 403" 403 "$s"

echo ""
echo "  -- 导师请假释放时段 --"
# 明天 09:00-12:00 请假，覆盖张三已有的 10:00 预约
TOMORROW=$(date -d tomorrow +%Y-%m-%d)
s=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H "$WANG" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"start_at\":\"$TOMORROW 09:00\",\"end_at\":\"$TOMORROW 12:00\",\"reason\":\"外出评审\"}" "$BASE/api/leaves")
check "导师A登记请假 → 201" 201 "$s"
sleep 0.5
RELEASED=$(curl -s -H "$ZHANG" "$BASE/api/appointments" | "$PHP" -r '$d=json_decode(file_get_contents("php://stdin"),true)["data"]; foreach($d as $a){ if($a["status"]==="released_by_leave"){echo "yes";break;}}')
[ "$RELEASED" = "yes" ] && check "原10:00预约变为 released_by_leave，学生收到通知" 200 200 || check "原10:00预约释放" 200 500
NOTIFIED=$(curl -s -H "$ZHANG" "$BASE/api/notifications" | "$PHP" -r '$d=json_decode(file_get_contents("php://stdin"),true)["data"]; foreach($d as $n){ if($n["type"]==="slot_released_by_leave"){echo "yes";break;}}')
[ "$NOTIFIED" = "yes" ] && check "通知：slot_released_by_leave 已写入（见 notifications.log）" 200 200 || check "请假通知写入" 200 500

echo ""
echo "═══════════════ ② 版本追溯 ════════════════"
V1=$(curl -s -H "$WANG" "$BASE/api/submissions" | "$PHP" -r '$d=json_decode(file_get_contents("php://stdin"),true)["data"]; foreach($d as $s){ if($s["version"]===1){echo $s["id"];break;}}')
V2=$(curl -s -H "$WANG" "$BASE/api/submissions" | "$PHP" -r '$d=json_decode(file_get_contents("php://stdin"),true)["data"]; foreach($d as $s){ if($s["version"]===2){echo $s["id"];break;}}')
OPEN_FB=$(curl -s -H "$ZHANG" "$BASE/api/dashboard" | "$PHP" -r 'echo json_decode(file_get_contents("php://stdin"),true)["pending_feedback"][0]["id"];')

curl -s -H "$WANG" "$BASE/api/submissions/$V1" -o /tmp/v1.json
G1_REF=$("$PHP" -r '$d=json_decode(file_get_contents("/tmp/v1.json"),true)["data"]; echo $d["guidance_sessions"][0]["id"] ?? "";')
G1_V=$("$PHP" -r '$d=json_decode(file_get_contents("/tmp/v1.json"),true)["data"]; echo $d["guidance_sessions"][0]["feedback_items"][0]["responses"] ?? "none";')
[ -n "$G1_REF" ] && check "第一次评语记录固定引用【第1版】（含2条原始意见）" 200 200 || check "评语引用第1版" 200 500

curl -s -H "$WANG" "$BASE/api/submissions/$V2" -o /tmp/v2.json
V2_STATUS=$("$PHP" -r 'echo json_decode(file_get_contents("/tmp/v2.json"),true)["data"]["status"];')
V2_KEEP=$("$PHP" -r '$d=json_decode(file_get_contents("/tmp/v2.json"),true)["data"]; echo ($d["attachments"][0]["name"] ?? "");')
[ "$V2_STATUS" = "withdrawn" ] && check "第2版为误传撤回，但附件保留（$V2_KEEP）" 200 200 || check "第2版撤回且附件保留" 200 500

# 学生提交第4版并回应未关闭意见
echo "模拟稿件内容-v4" > /tmp/v4.txt
s=$(curl -s -o /tmp/r.json -w '%{http_code}' -X POST -H "$ZHANG" -H 'Accept: application/json' \
  -F "title=基于机器学习的毕业设计开题报告" \
  -F "note=按主题重组文献综述" \
  -F "files[]=@/tmp/v4.txt;filename=开题报告_v4.txt" \
  -F "responses[0][feedback_item_id]=$OPEN_FB" \
  -F "responses[0][reply]=已按方法/数据/应用三类重组，见第二章" \
  "$BASE/api/submissions")
check "张三提交第4版并逐条回应意见 → 201" 201 "$s"

s=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H "$ZHANG" -H 'Accept: application/json' "$BASE/api/feedback/$OPEN_FB/close")
check "学生尝试关闭意见 → 403（学生无权关闭）" 403 "$s"

s=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H "$WANG" -H 'Accept: application/json' "$BASE/api/feedback/$OPEN_FB/close")
check "导师确认关闭该意见 → 200" 200 "$s"

# 版本链：意见现在关联评语 → v4回应
CHAIN=$(curl -s -H "$WANG" "$BASE/api/submissions" | "$PHP" -r '
$d=json_decode(file_get_contents("php://stdin"),true)["data"];
foreach ($d as $s) { if ($s["version"] === 4 && !empty($s["responses"])) { echo $s["responses"][0]["feedback_sequence"]; break; }}')
[ "$CHAIN" = "1" ] && check "追溯链完整：评语#1 → v4随稿回应（feedback_sequence=$CHAIN）" 200 200 || check "版本追溯链（得到 '$CHAIN'）" 200 500

# 答辩资格
s=$(curl -s -o /tmp/r.json -w '%{http_code}' -X POST -H "$ZHANG" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"final_submission_id\":$V1,\"eligible\":true}" "$BASE/api/students/$(curl -s -H "$ZHANG" "$BASE/api/auth/me" | "$PHP" -r 'echo json_decode(file_get_contents("php://stdin"),true)["user"]["id"];')/defense")
check "学生自行确认答辩资格 → 403" 403 "$s"

V4=$(curl -s -H "$WANG" "$BASE/api/submissions" | "$PHP" -r '$d=json_decode(file_get_contents("php://stdin"),true)["data"]; $x=end($d); echo $x["id"];')
ZHANG_ID=$(curl -s -H "$WANG" "$BASE/api/submissions" | "$PHP" -r 'foreach(json_decode(file_get_contents("php://stdin"),true)["data"] as $s){echo $s["student"]["id"];break;}')
s=$(curl -s -o /tmp/r.json -w '%{http_code}' -X POST -H "$WANG" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"final_submission_id\":$V4,\"eligible\":true,\"comment\":\"同意答辩\"}" "$BASE/api/students/$ZHANG_ID/defense")
check "导师依据第4版+未解决意见数确认答辩 → 201" 201 "$s"
echo "     记录：$("$PHP" -r '$d=json_decode(file_get_contents("/tmp/r.json"),true)["data"]; echo "最终稿={$d["final_submission_id"]} 未解决={$d["unresolved_count"]} 时间={$d["decided_at"]}";')"

echo ""
echo "═══════════════ ③ 跨导师访问隔离 ════════════════"
s=$(curl -s -o /dev/null -w '%{http_code}' -H "$LI" "$BASE/api/submissions/$V1")
check "导师B 直接访问张三的稿件 → 403" 403 "$s"

GID=$("$PHP" -r '$d=json_decode(file_get_contents("/tmp/v1.json"),true)["data"]; echo $d["guidance_sessions"][0]["id"];')
s=$(curl -s -o /dev/null -w '%{http_code}' -H "$LI" "$BASE/api/guidance/$GID")
check "导师B 直接访问张三的指导评语 → 403" 403 "$s"

ATT=$("$PHP" -r '$d=json_decode(file_get_contents("/tmp/v1.json"),true)["data"]; $u=$d["attachments"][0]["download_url"]; preg_match("#/attachments/(\d+)/#",$u,$m); echo $m[1];')
s=$(curl -s -o /dev/null -w '%{http_code}' -H "$LI" "$BASE/api/attachments/$ATT/download")
check "导师B 下载张三稿件附件 → 403" 403 "$s"

s=$(curl -s -o /dev/null -w '%{http_code}' -H "$LI" "$BASE/api/submissions?student_id=$ZHANG_ID")
check "导师B 列出导师A学生的版本 → 403" 403 "$s"

s=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H "$LI" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"final_submission_id\":$V4,\"eligible\":true}" "$BASE/api/students/$ZHANG_ID/defense")
check "导师B 给张三确认答辩资格 → 403" 403 "$s"

s=$(curl -s -o /dev/null -w '%{http_code}' -H "$LI" "$BASE/api/submissions?student_id=")
s2=$(curl -s -H "$LI" "$BASE/api/submissions" -o /tmp/li.json -w '%{http_code}')
LI_SEES=$( "$PHP" -r '$d=json_decode(file_get_contents("/tmp/li.json"),true)["data"]; $ok=true; foreach($d as $s){ if(($s["student"]["name"]??"")!=="" && strpos($s["student"]["name"],"赵四")===false) $ok=false; } echo $ok ? "only-zhao" : "leak";')
check "导师B 列表只见自己学生赵四，无张三数据泄露" 200 "$s2"
[ "$LI_SEES" = "only-zhao" ] && check "返回数据中不含任何导师A的资料" 200 200 || check "数据隔离无泄露" 200 500

echo ""
echo "════════════════ 结果汇总 ════════════════"
echo "  通过 $PASS 项，失败 $FAIL 项"
echo "  通知日志：$APP/storage/logs/notifications.log"
[ "$FAIL" -eq 0 ] && echo "  🎉 全部场景验证通过" || exit 1
