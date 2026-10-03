# 高校毕业设计指导预约与评语版本系统

基于 **PHP 8.4 + Laravel 13 + Eloquent + SQLite + React 19（Vite/Tailwind）** 的本地一体化系统。
学生与导师在此安排约谈、提交稿件并跟进修改，每一次指导都可以查清针对的是哪一版内容。

## 一条命令启动

```bash
./start.sh            # 首次会自动准备 PHP 环境依赖、数据库、前端并启动
./start.sh --fresh    # 重建演示数据
./demo.sh             # 运行 21 项端到端 HTTP 验证（预约冲突/版本追溯/跨导师隔离）
```

启动后访问 <http://127.0.0.1:8000>。

> 运行环境随仓库附带静态 PHP 二进制（`.tools/php`，无 root 也可运行）。
> 如需自行准备：PHP ≥ 8.2（含 pdo_sqlite、mbstring 等扩展）+ Composer。

## 演示账号（密码均为 `password`）

| 角色 | 邮箱 | 说明 |
|---|---|---|
| 学生 | `zhangsan@demo.edu` | 王明的学生，已有 v1/v2(撤回)/v3 稿件和两轮评语 |
| 学生 | `sunwu@demo.edu` | 王明的学生，用于演示抢约同一时段冲突 |
| 学生 | `zhaosi@demo.edu` | 李华的学生 |
| 导师 | `wangming@demo.edu` | 导师 A：王明 |
| 导师 | `lihua@demo.edu` | 导师 B：李华 |

## 功能与业务规则

### 预约
- 导师开放时段；学生只能预约**自己导师**的开放时段并附讨论问题。
- **同一时段只能一名学生预约**：应用层事务复查 + 数据库部分唯一索引
  `appointments(time_slot_id) WHERE status IN ('booked','completed')` 双重保证，并发抢约返回 `409`。
- 学生取消 → 预约 `cancelled`、时段恢复 `open`，他人可重新预约。
- 导师**请假** → 区间内未来开放时段置 `cancelled`，已预约时段置 `released_by_leave`
  并释放时段，同时给学生写入本地通知。

### 稿件版本与评语追溯
- 每次提交是一条**不可变版本**（学生内自增 version），旧稿与附件永久保留。
- 导师约谈后写评语，记录**永久固定 `submission_id`**（"针对哪一版"），
  系统不提供评语的编辑/删除接口，同一约谈不可重复填写。
- 学生误传可**撤回**（状态置 `withdrawn`，原因留痕）：文件与附件保留、
  **引用它的指导记录完全不受影响**；撤回版本不能再作为新评语对象。
- 学生交新稿时可**逐条回应**旧意见；意见状态机：
  `open 待处理 → answered 已回应（学生）→ closed 已确认（仅导师）`。
- **学生无权关闭意见**；导师可关闭，也可复核后重新打开。
- 前端"稿件与评语"页提供版本时间线 + 评语/回应的完整追溯视图。

### 答辩资格
- **只能由导师**根据所选**最终稿版本 + 当前未解决（未关闭）意见数**确认，
  记录最终稿 ID、未解决数量、判定时间；学生本人或其他导师操作均返回 `403/403`。

### 访问隔离（防跨导师）
- 所有资源（时段、预约、稿件、评语、意见、附件、答辩记录）统一经
  `ChecksOwnership`：仅学生本人或其导师可访问；其他导师一律 `403`。
- 附件下载同样校验多态挂载对象的归属，无 token 为 `401`，他导师为 `403`。

### 通知与附件
- 通知为**本地模拟**：写入 `notifications` 表并追加
  `storage/logs/notifications.log`（预约、取消、请假释放、新稿、评语、意见关闭、答辩判定）。
- 附件保存在本地 `storage/app/attachments/`，按对象类型分目录。

## 首页待办
- **学生首页**：顶部高亮未处理意见（红色=待处理、黄色=已回应待确认），
  标明每条意见"针对第几版"，以及我的回应出处；右侧为约谈与答辩进度。
- **导师首页**：待办按 `sort_at` 统一排序——未来约谈按预约时间、稿件按提交截止日，
  外加"约谈已结束待写评语"和"学生已回应待确认关闭"。

## 技术结构

```
app/
├─ app/
│  ├─ Http/Controllers/   API 控制器（Auth/Dashboard/Slots/Appointments/
│  │                      Leave/Submissions/Guidance/Feedback/Defense/
│  │                      Attachment/Notification）+ ChecksOwnership
│  ├─ Http/Middleware/    AuthenticateToken（Bearer/query token，本地模拟）
│  │                      EnsureRole（student/advisor）
│  ├─ Services/           Appointment / Submission / Guidance / Feedback /
│  │                      Defense / Attachment / Notifier 服务
│  └─ Models/             User/TimeSlot/Appointment/LeaveRequest/Submission/
│                         Attachment/DiscussionQuestion/GuidanceSession/
│                         FeedbackItem/FeedbackResponse/DefenseDecision/AppNotification
├─ database/migrations/  含 appointments 部分唯一索引
├─ resources/js/          React SPA（api 封装、登录、学生/导师首页、
│                         时段预约、稿件版本追溯、通知）
└─ tests/Feature/CoreRulesTest.php  11 个场景化 PHPUnit 测试
```

## 验证示例

```bash
./demo.sh                 # 真实 HTTP 端到端：21 项断言全部 ✅
php artisan test          # PHPUnit：13 个测试全部通过
```

`./demo.sh` 覆盖：
1. **预约冲突**：首约成功 → 同人时段第二人 409 → 跨导师预约 403 →
   请假自动释放已预约时段并通知学生。
2. **版本追溯**：评语固定指向第 1 版 → 第 2 版撤回但附件/评语保留 →
   第 4 版随稿逐条回应 → 学生关意见 403、导师关闭 200 →
   追溯链（评语#1 ↔ v4 回应）→ 答辩资格学生 403、导师 201（带未解决意见数）。
3. **跨导师隔离**：导师 B 访问稿件、评语、附件、列表、答辩确认全部 403，
   且其列表中不出现导师 A 学生的任何数据。
