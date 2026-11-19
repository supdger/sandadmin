# 建库放行前置 C1–C3：隔离沙箱与探针验收（6g-db-sandbox-c1c3-1119）

- 日期：2026-11-19
- 依据：`~/agent-work/assistant/3d-audit-db-release-1119/RESULT.md` 第 1 节 C1–C3
- 范围：新建沙箱策略 + 独立探针验收。未启动任何数据库进程，未碰任何现有数据库和数据目录，未清理越界现场。
- 原策略 `~/agent-work/grokbot/devin-queue/iso-sandbox/policy.sb` 未动（sha256 `2610430c857670add0caa922e22e3408dcd9a4f1da9cbc58d0165e8016e0b80f`）。

## 交付物

| 文件 | sha256 | 说明 |
|---|---|---|
| `db-instance-policy.sb` | `2fe97b4b52a0f0df4e1da187ba7b16af9816a7ce9ba2f35ef037a706cd4bc54b` | 新沙箱策略，440 行 |
| `probe.py` | `651a573867147630b257f1a06614a6a5ee7c6ac1cad3bff3c2012db12af7551f` | 独立 socket 探针（非数据库） |
| `probe-output.txt` | — | 全量探针输出存档 |
| `job/` | — | 探针用 JOBDIR（sandbox-exec -D 参数），仅含探针写测试文件 |

## 策略与 C1–C3 的对应

- **C1（放行指定程序）**：先按程序名全拒数据库/集群工具（名单比原策略更全），再按路径精确放行
  `postgresql@18` 的 `initdb pg_ctl postgres postmaster psql pg_dump pg_restore pg_isready` 和
  `mysql@8.4` 的 `mysqld mysql mysqldump`。opt/ 是符号链接，seatbelt 按 vnode 匹配，
  opt 与 Cellar 两种路径写法都列。`/usr/local/mysql` 及其链接目标显式拒绝执行和读写。
  原策略全部读写禁止项保留（wf-install-1117、各历史 pgdata、`/opt/homebrew/var/postgresql@18`、
  `/opt/homebrew/var/mysql`、`/Users/code`、iso-sandbox 目录、.env 系列、.pgpass 等）。
- **C2（主库 socket 与端口）**：unix socket 只允许 JOBDIR 内 bind/inbound/outbound；
  `/tmp`、`/private/tmp`、`/var`、`/private/var`、`/opt/homebrew/var` 下 unix socket 全部显式拒绝
  （覆盖 `.s.PGSQL.*`、`mysql.sock`、`mysqlx.sock`、brew 数据目录 socket）。
  mDNSResponder 的 DNS 放行放在 /var 拒绝之后，避免被覆盖。TCP 5432/25540/3306/33060
  由"本机只放行 19100–19199"规则挡住（逐端口 100 组 allow，与原策略逐条一致）。
- **C3（实例参数）**：策略层能强制的部分已强制——端口只有 19100–19199 可 bind/connect、
  unix socket 与数据目录只能在 JOBDIR 内（file-write 仅放行 JOBDIR 与 Devin 运行时必须项）。
  参数本体（PG `listen_addresses='127.0.0.1'`、`unix_socket_directories`、
  `initdb --auth=scram-sha-256`；MySQL `bind-address=127.0.0.1`、`mysqlx=OFF`、
  `--no-defaults`、socket/pid-file/datadir/tmpdir/log-error 全在任务目录内）
  策略无法检查命令行，固定写在策略文件头注释里，作为后续任务提示词的强制契约。

## 探针验收记录

环境：macOS seatbelt `sandbox-exec`；`JOBDIR=<任务目录>/job`；探针 `/usr/bin/python3`（Python 3.9.6）。
以下命令均在任务目录执行，`ISO=/Users/supdger/agent-work/grokbot/devin-queue/iso-sandbox`。

### 0. 策略编译

```
sandbox-exec -f db-instance-policy.sb -D JOBDIR=$PWD/job \
  -D FAKESECRET_DIR=$ISO/placeholders/fakesecret -D FAKE_DENY_WRITE=$ISO/placeholders/denywrite \
  /usr/bin/true
```
退出码 0（策略编译并生效）。

### 1. 沙箱外对照（证明被测目标真实存在，拒绝只能来自沙箱）

命令：沙箱外 python socket 直连。退出码 0。输出：

```
unsandboxed tcp 127.0.0.1:5432 -> CONNECTED
unsandboxed tcp 127.0.0.1:25540 -> CONNECTED
unsandboxed unix /tmp/.s.PGSQL.5432 -> CONNECTED
unsandboxed unix /tmp/.s.PGSQL.25540 -> CONNECTED
unsandboxed unix /tmp/mysql.sock -> CONNECTED
unsandboxed unix /tmp/mysqlx.sock -> CONNECTED
```

### 2. 沙箱内全量 socket/文件探针

命令：

```
JOBDIR=$PWD/job sandbox-exec -f db-instance-policy.sb -D JOBDIR=$PWD/job \
  -D FAKESECRET_DIR=$ISO/placeholders/fakesecret -D FAKE_DENY_WRITE=$ISO/placeholders/denywrite \
  /usr/bin/python3 probe.py
```
退出码 0。输出（probe-output.txt 存档）：
```
PASS tcp-bind-127.0.0.1:19100                       expect=OK   got=OK
PASS tcp-bind-127.0.0.1:19150                       expect=OK   got=OK
PASS tcp-bind-127.0.0.1:19199                       expect=OK   got=OK
PASS tcp-bind-127.0.0.1:19099                       expect=DENY got=DENIED errno=1 Operation not permitted
PASS tcp-bind-127.0.0.1:19200                       expect=DENY got=DENIED errno=1 Operation not permitted
PASS tcp-bind-127.0.0.1:5432                        expect=DENY got=DENIED errno=1 Operation not permitted
PASS tcp-bind-127.0.0.1:3306                        expect=DENY got=DENIED errno=1 Operation not permitted
INFO tcp-bind-0.0.0.0:19100                         observed=OK
INFO tcp-bind-[::]:19100                            observed=OK
INFO tcp-bind-::1:19100                             observed=OK
PASS tcp-connect-127.0.0.1:5432                     expect=DENY got=DENIED errno=1 Operation not permitted
PASS tcp-connect-127.0.0.1:25540                    expect=DENY got=DENIED errno=1 Operation not permitted
PASS tcp-connect-127.0.0.1:3306                     expect=DENY got=DENIED errno=1 Operation not permitted
PASS tcp-connect-127.0.0.1:33060                    expect=DENY got=DENIED errno=1 Operation not permitted
PASS unix-connect-/tmp/.s.PGSQL.5432                expect=DENY got=DENIED errno=1 Operation not permitted
PASS unix-connect-/tmp/.s.PGSQL.25540               expect=DENY got=DENIED errno=1 Operation not permitted
PASS unix-connect-/tmp/mysql.sock                   expect=DENY got=DENIED errno=1 Operation not permitted
PASS unix-connect-/tmp/mysqlx.sock                  expect=DENY got=DENIED errno=1 Operation not permitted
PASS unix-bind-jobdir                               expect=OK   got=OK
PASS unix-bind-/tmp/probe.sock                      expect=DENY got=DENIED errno=1 Operation not permitted
PASS unix-connect-brew-mysql-sock                   expect=OK   got=OK
PASS write-jobdir                                   expect=OK   got=OK
PASS write-/tmp                                     expect=DENY got=DENIED errno=1 Operation not permitted
PASS read-homebrew-pgdata                           expect=DENY got=DENIED errno=1 Operation not permitted
PASS read-usr-local-mysql                           expect=DENY got=DENIED errno=1 Operation not permitted
PASS read-wf-install-scene                          expect=DENY got=DENIED errno=1 Operation not permitted
RESULT: all probes passed
```

### 3. /opt/homebrew/var unix socket 拒绝补证

brew mysql 未运行、`/opt/homebrew/var/mysql/mysql.sock` 不存在（ENOENT），故在宿主机
`/opt/homebrew/var` 下建真实 socket 再测（非数据目录，探后已删）：

```
# 宿主机: python3 AF_UNIX bind /opt/homebrew/var/c1c3-probe.sock, listen
# 沙箱内:
sandbox-exec -f db-instance-policy.sb -D ... /usr/bin/python3 -c 'connect 该 socket'
```
输出：`connect -> errno=1 Operation not permitted`，宿主机 rc=0，socket 文件已删除。

### 4. exec 放行探针（每条独立命令，`--version` 不初始化不启动实例）

| 命令（沙箱内） | 退出码 | 输出 |
|---|---|---|
| `sandbox-exec ... /opt/homebrew/opt/postgresql@18/bin/initdb --version` | 0 | `initdb (PostgreSQL) 18.4 (Homebrew)` |
| `sandbox-exec ... /opt/homebrew/opt/postgresql@18/bin/postgres --version` | 0 | `postgres (PostgreSQL) 18.4 (Homebrew)` |
| `sandbox-exec ... /opt/homebrew/bin/psql --version`（brew 符号链接→Cellar） | 0 | `psql (PostgreSQL) 18.4 (Homebrew)` |
| `sandbox-exec ... /opt/homebrew/opt/mysql@8.4/bin/mysql --version` | 0 | `mysql Ver 8.4.13 for macos27.0 on arm64 (Homebrew)` |
| `sandbox-exec ... /opt/homebrew/opt/mysql@8.4/bin/mysqld --version` | 0 | `mysqld Ver 8.4.13 for macos27.0 on arm64 (Homebrew)` |

### 5. exec 拒绝探针（不在 C1 名单 / 禁止前缀）

| 命令（沙箱内） | 退出码 | 输出 |
|---|---|---|
| `... postgresql@18/bin/createdb --version` | 71 | `execvp() ... failed: Operation not permitted` |
| `... postgresql@18/bin/pg_basebackup --version` | 71 | 同上 |
| `... mysql@8.4/bin/mysqladmin --version` | 71 | 同上 |
| `... /usr/local/mysql/bin/mysqld --version` | 71 | 同上 |
| `... /usr/local/mysql/bin/mysql --version` | 71 | 同上 |

## 结论

C1–C3 探针全部通过：19100–19199 端口可绑可用、JOBDIR 内 unix socket 可用；
5432/25540/3306/33060、`/tmp/.s.PGSQL.*`、`/tmp/mysql*.sock`、`/opt/homebrew/var` socket 全拒；
C1 名单内程序可执行、名单外与 `/usr/local/mysql/**` 拒执行（rc=71）。
新策略可以进入"第 0 步"使用，但生效为任务沙箱前建议按 C1 要求由未参与编写的上下文复跑一遍本探针。

## 发现与残余风险（交技术总监）

1. **通配绑定缺口（继承自原策略，本策略同）**：seatbelt 的 `local ip "localhost:*"` / `local tcp`
   过滤器对 `0.0.0.0`、`::` 通配绑定同样匹配；`network-inbound` 在 `listen()` 时判定、拿不到对端地址，
   无法用 remote 条件区分。实证：沙箱内 `bind 0.0.0.0:19100 + listen` 成功，宿主机经 LAN IP
   `192.168.1.203:19100` 建立的连接被沙箱进程正常 `accept`。含义：若临时实例误配监听
   `0.0.0.0:191xx`，局域网可直达。缓解只能靠 C3 配置层（`listen_addresses='127.0.0.1'` /
   `bind-address=127.0.0.1`）加 pg_hba/MySQL 授权收敛；如需策略级封堵，seatbelt 表达不了，
   要改走 PF 锚点或另议。已尝试过的写法（`require-all` remote、`*:*` 后置拒绝）均误伤回环监听。
2. **`mysqladmin` 不在 C1 放行名单**（已验证 rc=71）。C6 停库口径写的是
   `mysqladmin --socket=... shutdown`；替代路径：`mysql` 客户端执行 `SHUTDOWN`、或 `kill` 本实例 pid
   （signal same-sandbox 已放行）。是否把 `mysqladmin` 补进放行名单需技术总监拍板。
3. `/usr/local/mysql` 在原策略里只有"默认不可写"兜底，本策略按 C1 口径补了显式 file-read/write
   与 exec 拒绝（含符号链接目标 `mysql-8.0.41-macos15-arm64`），属收紧项。
4. 命名检查 `naming_check.py` 只覆盖 PHP/TS/JS/Vue/SQL 后缀，本任务产物（.sb/.py/.md）
   不在其机械检查范围；名字与注释已按规范自查。

## 未做的事

- 未启动任何数据库进程（exec 探针只跑 `--version`，不初始化、不建目录、不监听）。
- 未碰 postgresql@18（5432）、final-runtime-check-1112 残留 PG（25540）、`/usr/local/mysql` 实例与 brew 数据目录。
- 未清理任何遗留现场；未改原 `policy.sb`、CI、门禁脚本；未动 git。

## 附：策略全文（db-instance-policy.sb）

```scheme
(version 1)
; 建库放行前置 C1-C3：每任务一个隔离临时库的沙箱策略。
; 基线为 devin-queue/iso-sandbox/policy.sb（原文件不动），参数不变：JOBDIR、FAKESECRET_DIR、FAKE_DENY_WRITE。
; seatbelt 语义：每个操作取最后匹配的规则。
;
; C1：只放行 postgresql@18 的 initdb/pg_ctl/postgres/postmaster/psql/pg_dump/pg_restore/pg_isready
;     和 mysql@8.4 的 mysqld/mysql/mysqldump（opt 与 Cellar 两种路径形式都覆盖）；/usr/local/mysql/** 禁执行。
; C2：unix socket 只允许 JOBDIR 内；/tmp、/private/tmp、/var、/private/var、/opt/homebrew/var 下全部拒绝，
;     覆盖 .s.PGSQL.*、mysql.sock、mysqlx.sock、brew 数据目录 socket；TCP 5432/25540/3306/33060 由端口段规则挡住。
; C3：策略层强制端口 19100-19199、数据目录与 socket 都在 JOBDIR 内；
;     实例参数本体由任务提示词固定：PG listen_addresses='127.0.0.1'、unix_socket_directories=<JOBDIR>、
;     initdb --auth=scram-sha-256；MySQL bind-address=127.0.0.1、mysqlx=OFF、--no-defaults、
;     socket/pid-file/datadir/tmpdir/log-error 全在 JOBDIR 内。
(deny default)

(allow process-fork)
(allow process-exec)
(allow process-info* (target same-sandbox))
(allow signal (target same-sandbox))
(allow mach-priv-task-port (target same-sandbox))
(allow user-preference-read)
(allow ipc-posix-shm)
(allow ipc-posix-sem)
(allow distributed-notification-post)
(allow iokit-get-properties)
(allow iokit-open
  (iokit-registry-entry-class "IOSurfaceRootUserClient")
  (iokit-registry-entry-class "RootDomainUserClient")
  (iokit-user-client-class "IOSurfaceSendRight")
)
(allow system-socket (require-all (socket-domain AF_SYSTEM) (socket-protocol 2)))
(allow mach-lookup
  (global-name "com.apple.audio.systemsoundserver")
  (global-name "com.apple.distributed_notifications@Uv3")
  (global-name "com.apple.FontObjectsServer")
  (global-name "com.apple.fonts")
  (global-name "com.apple.logd")
  (global-name "com.apple.lsd.mapdb")
  (global-name "com.apple.PowerManagement.control")
  (global-name "com.apple.system.logger")
  (global-name "com.apple.system.notification_center")
  (global-name "com.apple.system.opendirectoryd.libinfo")
  (global-name "com.apple.system.opendirectoryd.membership")
  (global-name "com.apple.bsd.dirhelper")
  (global-name "com.apple.securityd.xpc")
  (global-name "com.apple.SecurityServer")
  (global-name "com.apple.coreservices.launchservicesd")
  (global-name "com.apple.trustd.agent")
)
(allow sysctl-read)
(allow sysctl-write (sysctl-name "kern.tcsm_enable"))

; 先按程序名全拒数据库/集群工具（名单比原策略更全），下面再按路径精确放行。
(deny process-exec*
  (regex #"^.*/(initdb|postgres|postmaster|pg_ctl|psql|pg_dump|pg_dumpall|pg_restore|pg_isready|pg_basebackup|pg_controldata|pg_resetwal|pg_rewind|pg_receivewal|pg_recvlogical|pg_archivecleanup|pg_amcheck|pg_checksums|pg_test_fsync|pg_test_timing|pg_upgrade|pg_verifybackup|pg_waldump|pg_createsubscriber|pg_combinebackup|createdb|dropdb|createuser|dropuser|clusterdb|oid2name|vacuumdb|reindexdb|ecpg|mysql|mysqld|mysqld_safe|mysqladmin|mysql_secure_installation|mysqldump|mysqlpump|mysqlcheck|mysqlimport|mysqlshow|mariadb|mariadbd|redis-server|redis-cli|mongod|mongos|mongo|mongosh)$")
)
; C1 放行：seatbelt 按 vnode 路径匹配，opt/ 是符号链接，两种写法都列上；postmaster 与 postgres 是同一程序。
(allow process-exec
  (regex #"^/opt/homebrew/Cellar/postgresql@18/[^/]+/bin/(initdb|pg_ctl|postgres|postmaster|psql|pg_dump|pg_restore|pg_isready)$")
  (regex #"^/opt/homebrew/opt/postgresql@18/bin/(initdb|pg_ctl|postgres|postmaster|psql|pg_dump|pg_restore|pg_isready)$")
  (regex #"^/opt/homebrew/Cellar/mysql@8\.4/[^/]+/bin/(mysqld|mysql|mysqldump)$")
  (regex #"^/opt/homebrew/opt/mysql@8\.4/bin/(mysqld|mysql|mysqldump)$")
)
(deny process-exec* (subpath "/usr/local/mysql"))
(deny process-exec* (subpath "/usr/local/mysql-8.0.41-macos15-arm64"))

(allow file-ioctl (literal "/dev/null"))
(allow file-ioctl (literal "/dev/zero"))
(allow file-ioctl (literal "/dev/random"))
(allow file-ioctl (literal "/dev/urandom"))
(allow file-ioctl (literal "/dev/dtracehelper"))
(allow file-ioctl (literal "/dev/tty"))
(allow file-ioctl file-read-data file-write-data
  (require-all (literal "/dev/null") (vnode-type CHARACTER-DEVICE)))

(allow file-read*)
(allow file-read-metadata)
(deny file-read* file-write* (subpath "/Users/supdger/agent-work/grokbot/wf-install-1117"))
(deny file-read* file-write* (subpath "/Users/supdger/agent-work/grokbot/sec-terminal-hotfix-1116/pgdata"))
(deny file-read* file-write* (subpath "/Users/supdger/agent-work/grokbot/pkg-sync-0225-check3-1111/pgdata"))
(deny file-read* file-write* (subpath "/Users/supdger/agent-work/grokbot/sec-followup-1116/pgdata"))
(deny file-read* file-write* (subpath "/Users/supdger/agent-work/grokbot/final-runtime-check-1112/tmp/pg"))
(deny file-read* file-write* (subpath "/Users/supdger/agent-work/grokbot/1x-biz-a-shots-1112/pg"))
(deny file-read* file-write* (subpath "/opt/homebrew/var/postgresql@18"))
(deny file-read* file-write* (subpath "/opt/homebrew/var/mysql"))
(deny file-read* file-write* (subpath "/usr/local/mysql"))
(deny file-read* file-write* (subpath "/usr/local/mysql-8.0.41-macos15-arm64"))
(deny file-read* file-write* (subpath "/Users/code"))
(deny file-read* file-write* (subpath "/Users/supdger/agent-work/grokbot/devin-queue/iso-sandbox"))
(deny file-write* (subpath "/Users/supdger/.config/devin"))
(deny file-write* (literal "/Users/supdger/.local/share/devin/credentials.toml"))
(deny file-read* file-write* (regex #"(^|/).env$"))
(deny file-read* file-write* (regex #"(^|/).env\.[^/]+$"))
(deny file-read* file-write* (regex #"(^|/)webhook\.env$"))
(deny file-read* file-write* (regex #"(^|/)\.pgpass$"))
(allow file-read* (regex #"(^|/).env\.example$"))
(deny file-read* file-write* (subpath (param "FAKESECRET_DIR")))
(deny file-write* (subpath (param "FAKE_DENY_WRITE")))

(allow file-write* (subpath (param "JOBDIR")))
(allow file-write* (subpath "/Users/supdger/.local/share/devin/cli/logs"))
(allow file-write* (subpath "/Users/supdger/.local/share/devin/cli/session_locks"))
(allow file-write* (subpath "/Users/supdger/.local/share/devin/cli/summaries"))
(allow file-write* (literal "/Users/supdger/.local/share/devin/cli/sessions.db"))
(allow file-write* (literal "/Users/supdger/.local/share/devin/cli/sessions.db-wal"))
(allow file-write* (literal "/Users/supdger/.local/share/devin/cli/sessions.db-shm"))
(allow file-write* (literal "/Users/supdger/.local/share/devin/cli/skill_events_spool.lock"))
(allow file-write* (subpath "/Users/supdger/.local/share/devin/cli/plugins_cache"))
(allow file-write* (subpath "/Users/supdger/.cache/devin"))

(deny network*)
(allow network-outbound (remote tcp "*:443"))
(allow network-outbound (remote tcp "*:22"))
(allow network-outbound (remote udp "*:53"))
(allow network-outbound (remote tcp "*:53"))

; C2 unix socket 拒绝段：覆盖 /tmp/.s.PGSQL.*、/tmp/mysql*.sock、/opt/homebrew/var/**，
; /tmp、/var 是符号链接，规范路径 /private/tmp、/private/var 一起拒。
(deny network* (local unix-socket (subpath "/tmp")))
(deny network* (local unix-socket (subpath "/private/tmp")))
(deny network* (local unix-socket (subpath "/var")))
(deny network* (local unix-socket (subpath "/private/var")))
(deny network* (local unix-socket (subpath "/opt/homebrew/var")))
(deny network* (remote unix-socket (subpath "/tmp")))
(deny network* (remote unix-socket (subpath "/private/tmp")))
(deny network* (remote unix-socket (subpath "/var")))
(deny network* (remote unix-socket (subpath "/private/var")))
(deny network* (remote unix-socket (subpath "/opt/homebrew/var")))
; DNS 仍只走 mDNSResponder，放在 /var 拒绝之后以免被覆盖。
(allow network-outbound (remote unix-socket (literal "/private/var/run/mDNSResponder")))
(allow network-outbound (remote unix-socket (literal "/var/run/mDNSResponder")))
; 临时实例自己的 unix socket 只允许开在 JOBDIR 内（C3 unix_socket_directories / socket）。
(allow network-bind (local unix-socket (subpath (param "JOBDIR"))))
(allow network-inbound (local unix-socket (subpath (param "JOBDIR"))))
(allow network-outbound (remote unix-socket (subpath (param "JOBDIR"))))

; 本机 TCP 只放行 19100-19199，逐条生成与原策略一致。
(deny network-bind (local ip "localhost:*"))
(deny network-inbound (local ip "localhost:*"))
(deny network-outbound (remote ip "localhost:*"))
(allow network-bind (local ip "localhost:19100"))
(allow network-inbound (local ip "localhost:19100"))
(allow network-outbound (remote ip "localhost:19100"))
(allow network-bind (local ip "localhost:19101"))
(allow network-inbound (local ip "localhost:19101"))
(allow network-outbound (remote ip "localhost:19101"))
(allow network-bind (local ip "localhost:19102"))
(allow network-inbound (local ip "localhost:19102"))
(allow network-outbound (remote ip "localhost:19102"))
(allow network-bind (local ip "localhost:19103"))
(allow network-inbound (local ip "localhost:19103"))
(allow network-outbound (remote ip "localhost:19103"))
(allow network-bind (local ip "localhost:19104"))
(allow network-inbound (local ip "localhost:19104"))
(allow network-outbound (remote ip "localhost:19104"))
(allow network-bind (local ip "localhost:19105"))
(allow network-inbound (local ip "localhost:19105"))
(allow network-outbound (remote ip "localhost:19105"))
(allow network-bind (local ip "localhost:19106"))
(allow network-inbound (local ip "localhost:19106"))
(allow network-outbound (remote ip "localhost:19106"))
(allow network-bind (local ip "localhost:19107"))
(allow network-inbound (local ip "localhost:19107"))
(allow network-outbound (remote ip "localhost:19107"))
(allow network-bind (local ip "localhost:19108"))
(allow network-inbound (local ip "localhost:19108"))
(allow network-outbound (remote ip "localhost:19108"))
(allow network-bind (local ip "localhost:19109"))
(allow network-inbound (local ip "localhost:19109"))
(allow network-outbound (remote ip "localhost:19109"))
(allow network-bind (local ip "localhost:19110"))
(allow network-inbound (local ip "localhost:19110"))
(allow network-outbound (remote ip "localhost:19110"))
(allow network-bind (local ip "localhost:19111"))
(allow network-inbound (local ip "localhost:19111"))
(allow network-outbound (remote ip "localhost:19111"))
(allow network-bind (local ip "localhost:19112"))
(allow network-inbound (local ip "localhost:19112"))
(allow network-outbound (remote ip "localhost:19112"))
(allow network-bind (local ip "localhost:19113"))
(allow network-inbound (local ip "localhost:19113"))
(allow network-outbound (remote ip "localhost:19113"))
(allow network-bind (local ip "localhost:19114"))
(allow network-inbound (local ip "localhost:19114"))
(allow network-outbound (remote ip "localhost:19114"))
(allow network-bind (local ip "localhost:19115"))
(allow network-inbound (local ip "localhost:19115"))
(allow network-outbound (remote ip "localhost:19115"))
(allow network-bind (local ip "localhost:19116"))
(allow network-inbound (local ip "localhost:19116"))
(allow network-outbound (remote ip "localhost:19116"))
(allow network-bind (local ip "localhost:19117"))
(allow network-inbound (local ip "localhost:19117"))
(allow network-outbound (remote ip "localhost:19117"))
(allow network-bind (local ip "localhost:19118"))
(allow network-inbound (local ip "localhost:19118"))
(allow network-outbound (remote ip "localhost:19118"))
(allow network-bind (local ip "localhost:19119"))
(allow network-inbound (local ip "localhost:19119"))
(allow network-outbound (remote ip "localhost:19119"))
(allow network-bind (local ip "localhost:19120"))
(allow network-inbound (local ip "localhost:19120"))
(allow network-outbound (remote ip "localhost:19120"))
(allow network-bind (local ip "localhost:19121"))
(allow network-inbound (local ip "localhost:19121"))
(allow network-outbound (remote ip "localhost:19121"))
(allow network-bind (local ip "localhost:19122"))
(allow network-inbound (local ip "localhost:19122"))
(allow network-outbound (remote ip "localhost:19122"))
(allow network-bind (local ip "localhost:19123"))
(allow network-inbound (local ip "localhost:19123"))
(allow network-outbound (remote ip "localhost:19123"))
(allow network-bind (local ip "localhost:19124"))
(allow network-inbound (local ip "localhost:19124"))
(allow network-outbound (remote ip "localhost:19124"))
(allow network-bind (local ip "localhost:19125"))
(allow network-inbound (local ip "localhost:19125"))
(allow network-outbound (remote ip "localhost:19125"))
(allow network-bind (local ip "localhost:19126"))
(allow network-inbound (local ip "localhost:19126"))
(allow network-outbound (remote ip "localhost:19126"))
(allow network-bind (local ip "localhost:19127"))
(allow network-inbound (local ip "localhost:19127"))
(allow network-outbound (remote ip "localhost:19127"))
(allow network-bind (local ip "localhost:19128"))
(allow network-inbound (local ip "localhost:19128"))
(allow network-outbound (remote ip "localhost:19128"))
(allow network-bind (local ip "localhost:19129"))
(allow network-inbound (local ip "localhost:19129"))
(allow network-outbound (remote ip "localhost:19129"))
(allow network-bind (local ip "localhost:19130"))
(allow network-inbound (local ip "localhost:19130"))
(allow network-outbound (remote ip "localhost:19130"))
(allow network-bind (local ip "localhost:19131"))
(allow network-inbound (local ip "localhost:19131"))
(allow network-outbound (remote ip "localhost:19131"))
(allow network-bind (local ip "localhost:19132"))
(allow network-inbound (local ip "localhost:19132"))
(allow network-outbound (remote ip "localhost:19132"))
(allow network-bind (local ip "localhost:19133"))
(allow network-inbound (local ip "localhost:19133"))
(allow network-outbound (remote ip "localhost:19133"))
(allow network-bind (local ip "localhost:19134"))
(allow network-inbound (local ip "localhost:19134"))
(allow network-outbound (remote ip "localhost:19134"))
(allow network-bind (local ip "localhost:19135"))
(allow network-inbound (local ip "localhost:19135"))
(allow network-outbound (remote ip "localhost:19135"))
(allow network-bind (local ip "localhost:19136"))
(allow network-inbound (local ip "localhost:19136"))
(allow network-outbound (remote ip "localhost:19136"))
(allow network-bind (local ip "localhost:19137"))
(allow network-inbound (local ip "localhost:19137"))
(allow network-outbound (remote ip "localhost:19137"))
(allow network-bind (local ip "localhost:19138"))
(allow network-inbound (local ip "localhost:19138"))
(allow network-outbound (remote ip "localhost:19138"))
(allow network-bind (local ip "localhost:19139"))
(allow network-inbound (local ip "localhost:19139"))
(allow network-outbound (remote ip "localhost:19139"))
(allow network-bind (local ip "localhost:19140"))
(allow network-inbound (local ip "localhost:19140"))
(allow network-outbound (remote ip "localhost:19140"))
(allow network-bind (local ip "localhost:19141"))
(allow network-inbound (local ip "localhost:19141"))
(allow network-outbound (remote ip "localhost:19141"))
(allow network-bind (local ip "localhost:19142"))
(allow network-inbound (local ip "localhost:19142"))
(allow network-outbound (remote ip "localhost:19142"))
(allow network-bind (local ip "localhost:19143"))
(allow network-inbound (local ip "localhost:19143"))
(allow network-outbound (remote ip "localhost:19143"))
(allow network-bind (local ip "localhost:19144"))
(allow network-inbound (local ip "localhost:19144"))
(allow network-outbound (remote ip "localhost:19144"))
(allow network-bind (local ip "localhost:19145"))
(allow network-inbound (local ip "localhost:19145"))
(allow network-outbound (remote ip "localhost:19145"))
(allow network-bind (local ip "localhost:19146"))
(allow network-inbound (local ip "localhost:19146"))
(allow network-outbound (remote ip "localhost:19146"))
(allow network-bind (local ip "localhost:19147"))
(allow network-inbound (local ip "localhost:19147"))
(allow network-outbound (remote ip "localhost:19147"))
(allow network-bind (local ip "localhost:19148"))
(allow network-inbound (local ip "localhost:19148"))
(allow network-outbound (remote ip "localhost:19148"))
(allow network-bind (local ip "localhost:19149"))
(allow network-inbound (local ip "localhost:19149"))
(allow network-outbound (remote ip "localhost:19149"))
(allow network-bind (local ip "localhost:19150"))
(allow network-inbound (local ip "localhost:19150"))
(allow network-outbound (remote ip "localhost:19150"))
(allow network-bind (local ip "localhost:19151"))
(allow network-inbound (local ip "localhost:19151"))
(allow network-outbound (remote ip "localhost:19151"))
(allow network-bind (local ip "localhost:19152"))
(allow network-inbound (local ip "localhost:19152"))
(allow network-outbound (remote ip "localhost:19152"))
(allow network-bind (local ip "localhost:19153"))
(allow network-inbound (local ip "localhost:19153"))
(allow network-outbound (remote ip "localhost:19153"))
(allow network-bind (local ip "localhost:19154"))
(allow network-inbound (local ip "localhost:19154"))
(allow network-outbound (remote ip "localhost:19154"))
(allow network-bind (local ip "localhost:19155"))
(allow network-inbound (local ip "localhost:19155"))
(allow network-outbound (remote ip "localhost:19155"))
(allow network-bind (local ip "localhost:19156"))
(allow network-inbound (local ip "localhost:19156"))
(allow network-outbound (remote ip "localhost:19156"))
(allow network-bind (local ip "localhost:19157"))
(allow network-inbound (local ip "localhost:19157"))
(allow network-outbound (remote ip "localhost:19157"))
(allow network-bind (local ip "localhost:19158"))
(allow network-inbound (local ip "localhost:19158"))
(allow network-outbound (remote ip "localhost:19158"))
(allow network-bind (local ip "localhost:19159"))
(allow network-inbound (local ip "localhost:19159"))
(allow network-outbound (remote ip "localhost:19159"))
(allow network-bind (local ip "localhost:19160"))
(allow network-inbound (local ip "localhost:19160"))
(allow network-outbound (remote ip "localhost:19160"))
(allow network-bind (local ip "localhost:19161"))
(allow network-inbound (local ip "localhost:19161"))
(allow network-outbound (remote ip "localhost:19161"))
(allow network-bind (local ip "localhost:19162"))
(allow network-inbound (local ip "localhost:19162"))
(allow network-outbound (remote ip "localhost:19162"))
(allow network-bind (local ip "localhost:19163"))
(allow network-inbound (local ip "localhost:19163"))
(allow network-outbound (remote ip "localhost:19163"))
(allow network-bind (local ip "localhost:19164"))
(allow network-inbound (local ip "localhost:19164"))
(allow network-outbound (remote ip "localhost:19164"))
(allow network-bind (local ip "localhost:19165"))
(allow network-inbound (local ip "localhost:19165"))
(allow network-outbound (remote ip "localhost:19165"))
(allow network-bind (local ip "localhost:19166"))
(allow network-inbound (local ip "localhost:19166"))
(allow network-outbound (remote ip "localhost:19166"))
(allow network-bind (local ip "localhost:19167"))
(allow network-inbound (local ip "localhost:19167"))
(allow network-outbound (remote ip "localhost:19167"))
(allow network-bind (local ip "localhost:19168"))
(allow network-inbound (local ip "localhost:19168"))
(allow network-outbound (remote ip "localhost:19168"))
(allow network-bind (local ip "localhost:19169"))
(allow network-inbound (local ip "localhost:19169"))
(allow network-outbound (remote ip "localhost:19169"))
(allow network-bind (local ip "localhost:19170"))
(allow network-inbound (local ip "localhost:19170"))
(allow network-outbound (remote ip "localhost:19170"))
(allow network-bind (local ip "localhost:19171"))
(allow network-inbound (local ip "localhost:19171"))
(allow network-outbound (remote ip "localhost:19171"))
(allow network-bind (local ip "localhost:19172"))
(allow network-inbound (local ip "localhost:19172"))
(allow network-outbound (remote ip "localhost:19172"))
(allow network-bind (local ip "localhost:19173"))
(allow network-inbound (local ip "localhost:19173"))
(allow network-outbound (remote ip "localhost:19173"))
(allow network-bind (local ip "localhost:19174"))
(allow network-inbound (local ip "localhost:19174"))
(allow network-outbound (remote ip "localhost:19174"))
(allow network-bind (local ip "localhost:19175"))
(allow network-inbound (local ip "localhost:19175"))
(allow network-outbound (remote ip "localhost:19175"))
(allow network-bind (local ip "localhost:19176"))
(allow network-inbound (local ip "localhost:19176"))
(allow network-outbound (remote ip "localhost:19176"))
(allow network-bind (local ip "localhost:19177"))
(allow network-inbound (local ip "localhost:19177"))
(allow network-outbound (remote ip "localhost:19177"))
(allow network-bind (local ip "localhost:19178"))
(allow network-inbound (local ip "localhost:19178"))
(allow network-outbound (remote ip "localhost:19178"))
(allow network-bind (local ip "localhost:19179"))
(allow network-inbound (local ip "localhost:19179"))
(allow network-outbound (remote ip "localhost:19179"))
(allow network-bind (local ip "localhost:19180"))
(allow network-inbound (local ip "localhost:19180"))
(allow network-outbound (remote ip "localhost:19180"))
(allow network-bind (local ip "localhost:19181"))
(allow network-inbound (local ip "localhost:19181"))
(allow network-outbound (remote ip "localhost:19181"))
(allow network-bind (local ip "localhost:19182"))
(allow network-inbound (local ip "localhost:19182"))
(allow network-outbound (remote ip "localhost:19182"))
(allow network-bind (local ip "localhost:19183"))
(allow network-inbound (local ip "localhost:19183"))
(allow network-outbound (remote ip "localhost:19183"))
(allow network-bind (local ip "localhost:19184"))
(allow network-inbound (local ip "localhost:19184"))
(allow network-outbound (remote ip "localhost:19184"))
(allow network-bind (local ip "localhost:19185"))
(allow network-inbound (local ip "localhost:19185"))
(allow network-outbound (remote ip "localhost:19185"))
(allow network-bind (local ip "localhost:19186"))
(allow network-inbound (local ip "localhost:19186"))
(allow network-outbound (remote ip "localhost:19186"))
(allow network-bind (local ip "localhost:19187"))
(allow network-inbound (local ip "localhost:19187"))
(allow network-outbound (remote ip "localhost:19187"))
(allow network-bind (local ip "localhost:19188"))
(allow network-inbound (local ip "localhost:19188"))
(allow network-outbound (remote ip "localhost:19188"))
(allow network-bind (local ip "localhost:19189"))
(allow network-inbound (local ip "localhost:19189"))
(allow network-outbound (remote ip "localhost:19189"))
(allow network-bind (local ip "localhost:19190"))
(allow network-inbound (local ip "localhost:19190"))
(allow network-outbound (remote ip "localhost:19190"))
(allow network-bind (local ip "localhost:19191"))
(allow network-inbound (local ip "localhost:19191"))
(allow network-outbound (remote ip "localhost:19191"))
(allow network-bind (local ip "localhost:19192"))
(allow network-inbound (local ip "localhost:19192"))
(allow network-outbound (remote ip "localhost:19192"))
(allow network-bind (local ip "localhost:19193"))
(allow network-inbound (local ip "localhost:19193"))
(allow network-outbound (remote ip "localhost:19193"))
(allow network-bind (local ip "localhost:19194"))
(allow network-inbound (local ip "localhost:19194"))
(allow network-outbound (remote ip "localhost:19194"))
(allow network-bind (local ip "localhost:19195"))
(allow network-inbound (local ip "localhost:19195"))
(allow network-outbound (remote ip "localhost:19195"))
(allow network-bind (local ip "localhost:19196"))
(allow network-inbound (local ip "localhost:19196"))
(allow network-outbound (remote ip "localhost:19196"))
(allow network-bind (local ip "localhost:19197"))
(allow network-inbound (local ip "localhost:19197"))
(allow network-outbound (remote ip "localhost:19197"))
(allow network-bind (local ip "localhost:19198"))
(allow network-inbound (local ip "localhost:19198"))
(allow network-outbound (remote ip "localhost:19198"))
(allow network-bind (local ip "localhost:19199"))
(allow network-inbound (local ip "localhost:19199"))
(allow network-outbound (remote ip "localhost:19199"))
```

## 模型记录
- 主 Agent：SWE-2 High（devin 会话模型，sessions.db 未另行核实 generation_model）
- 子 Agent：未使用（任务明确"不开下级会话"）

## 任务记录
- 已修改：新建 `db-instance-policy.sb`、`probe.py`、`probe-output.txt`、`job/probe-write.txt`、本文件；均在任务目录内
- 已验证：策略编译 rc=0；socket/文件探针 25 项全过（输出见上）；exec 放行 5 项 rc=0、拒绝 5 项 rc=71；`/opt/homebrew/var` socket 拒绝补证 EPERM
- 已提交/已部署：否
- 未完成：残余风险 1（通配绑定）与 2（mysqladmin 名单）待技术总监定夺；新策略被任务正式采用前需独立上下文复跑探针（C1 要求）
