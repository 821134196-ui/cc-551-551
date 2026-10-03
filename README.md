# 毕业设计指导预约与评语版本系统

高校毕业设计场景的**约谈预约 + 稿件版本 + 评语追溯**系统。学生预约导师时段、上传稿件、逐条回应修改意见；导师约谈后针对具体版本留评语、确认关闭、依据最终稿确认答辩资格。

- **后端**：PHP 8.3 / Laravel 13 / Eloquent / Sanctum token 鉴权
- **数据库**：SQLite（单文件，无需任何外部服务）
- **前端**：React 18 + Vite + Tailwind CSS（SPA，由 Laravel 提供页面）
- **通知**：本地模拟 —— 写库（站内 🔔 轮询）+ 追加 `storage/logs/notifications.log`
- **附件**：本地目录 `storage/app/private/attachments/`，旧稿附件永不删除
- **启动**：一条命令 `./start.sh`

## 快速开始

```bash
./start.sh          # 首次启动：自动装依赖、迁移、填充演示数据、构建前端并起服务
# 打开 http://localhost:8000
```

常用参数：

```bash
./start.sh --fresh    # 清空数据库、清理旧附件与通知日志，重新填充演示数据
./start.sh --no-seed  # 保留现有数据，只启动
PORT=8080 ./start.sh  # 换端口
```

> 脚本会自动检测 PHP/Composer；当前环境无 root 时，会下载内置 SQLite 扩展的静态 PHP 到 `~/.local/bin`。

### 演示账号（密码均为 `password`）

| 角色 | 姓名 | 邮箱 |
| --- | --- | --- |
| 学生 | 张一鸣 | `zhang@example.edu` |
| 学生 | 陈思琪 | `chen@example.edu` |
| 导师 | 王雅琴 | `wang@example.edu` |
| 导师 | 李国栋 | `li@example.edu` |

张一鸣的课题已预置：v1 稿件 → 导师约谈评语 2 条 → v2 逐条回应（1 条已关闭、1 条仍待处理）→ 另有一场未来约谈。陈思琪/李国栋用于演示跨导师隔离。

## 业务规则落地位置

| 需求 | 实现 |
| --- | --- |
| 同一时段只能一人预约 | 事务 + 行锁校验，且 `bookings` 上有 SQLite 部分唯一索引 `(slot_id) WHERE status='booked'` 兜底并发（`BookingService::book`） |
| 导师请假释放时段 | `BookingService::advisorLeave`：预约取消、时段置 `leave`、向学生发改约通知 |
| 学生取消 | 时段回到 `open`，历史预约行保留（故部分唯一索引允许再次预约） |
| 每次指导能查清针对哪一版 | `guidance_records.manuscript_version_id` 外键绑定；项目详情按版本时间线展示，新建指导必须选择版本 |
| 旧稿/附件/评语全部保留 | 稿件只有新增版本（`version_no` 课题内自增），从不物理删除；附件随版本/记录保存 |
| 误传稿件可撤回 | 可将未被引用的版本标记 `withdrawn`；**一旦被指导记录引用则拒绝撤回（422）**（`ManuscriptVersion::canWithdraw`） |
| 指导记录不可改 | 只有创建/查看接口，**没有 update/delete 路由**；外键 `restrictOnDelete`；稿件撤回后记录原样指向该版本 |
| 学生交新稿逐条回应 | 上传时携带 `responses[comment_id,content]`，回应绑定到新版本，评语转 `resolved` 待导师确认 |
| 学生无权关闭意见 | 关闭/重开评语走 `CommentPolicy::manage`（仅提出意见的导师），学生调用 403 |
| 答辩资格仅导师确认 | `EligibilityController` + `ProjectPolicy::decideEligibility`；需有最终稿，有未关闭意见时拦截并要求显式 `force` |
| 防跨导师访问 | 所有资源经 Policy 鉴权，回溯到课题的 `student_id / advisor_id`；附件下载同样鉴权，其他导师/学生一律 403 |
| 学生首页突出未处理意见 | `GET /api/dashboard/student` 返回 `open_comments`（open 优先排序），首页顶部红/黄卡片 |
| 导师首页按时间排待办 | `GET /api/dashboard/advisor`：预约按开始时间升序、截止日按临近程度升序，另有待确认回应、待确认资格 |

## 版本与评语状态机

```
稿件版本：active ──学生撤回（且无指导记录引用）──> withdrawn

评语：    open ──学生在新稿回应──> resolved
            ▲                          │
            └────导师退回(reopen)──────┴────导师确认(close)──> closed
                                         （仅导师可 close/reopen）

答辩资格：null（未确认）──导师──> true / false   依据：最终稿 + 未关闭意见
```

## 主要 API（前缀 `/api`）

- `POST /auth/login` · `GET /auth/me` · `POST /auth/logout`
- `GET /dashboard/student` · `GET /dashboard/advisor`
- 时段：`POST /slots` · `GET /slots/open` · `GET /slots/mine` · `POST /slots/{id}/leave` · `POST /slots/{id}/reopen`
- 预约：`POST /bookings` · `DELETE /bookings/{id}`
- 课题：`GET/POST /projects` · `GET /projects/{id}`（版本+指导完整时间线）
- 稿件：`POST /projects/{id}/manuscripts`（multipart，可带 `responses[]`）· `POST /manuscripts/{id}/withdraw`
- 指导：`POST /projects/{id}/guidance`（选择 `manuscript_version_id`，只读不可改）
- 评语：`POST /comments/{id}/close` · `POST /comments/{id}/reopen`
- 资格：`POST /projects/{id}/eligibility`
- 附件：`GET /attachments/{id}/download`
- 通知：`GET /notifications` · `POST /notifications/{id}/read`

## 测试（示例验证）

```bash
php artisan test
```

24 个特性测试覆盖并断言了题目要求的三类示例：

1. **预约冲突** `BookingConflictTest`：同时段第二人 409、取消后释放可再约、请假取消预约并通知、不能挂别人课题；
2. **稿件与评语版本追溯** `ManuscriptVersioningTest`：版本自增旧稿全保留、指导记录精确绑定版本、回应挂到新稿、已引用稿件禁止撤回、学生不能关评语、最终稿+未决意见的资格确认流程；
3. **跨导师访问** `CrossAdvisorAccessTest`：其他导师对课题/稿件/附件/指导/评语/资格/时段/预约全部 403，学生间互不可见，未登录 401。

另含 `DashboardTest`（导师待办排序、学生意见聚合）。

## 目录要点

```
app/
├── Models/            User(role)、Project、AdvisorSlot、Booking、
│                      ManuscriptVersion、GuidanceRecord、Comment、
│                      CommentResponse、Attachment、AppNotification
├── Services/          BookingService（冲突/请假事务）、
│                      ManuscriptService（版本/撤回/指导/资格）、
│                      NotificationService（本地模拟通知）
├── Policies/          Project/AdvisorSlot/Booking/Comment/Attachment 策略
└── Http/Controllers/  Auth、Dashboard、Slot、Booking、Project、
                       Manuscript、Guidance、Comment、Eligibility、
                       Attachment、Notification
database/migrations/   含部分唯一索引 bookings_slot_active_unique
resources/js/          React SPA（pages/student、pages/advisor）
start.sh               一键启动
```
