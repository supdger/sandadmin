可合并

# PR #106 合并前独立审计结论

- PR：https://github.com/supdger/sandadmin/pull/106 `feat(web): artd 残留宿主页面迁移——任务中心`，`feat/artd-residue` → `main`，1 个提交 `afdd56e`，+575/−8，11 个文件
- 审计方式：只读（`gh pr view/diff/checks 106`、`gh api contents?ref=feat/artd-residue`、本地读契约文件）；没有合并、推送、评论，也没有改仓库文件
- 日期：2026-11-19

## 1. 范围是否与描述一致：一致
- 11 个文件都在 `web/src/` 下（新增 tasks 页面、详情抽屉、状态标签、测试、zh-CN 文案；改了路由、路径常量和 mock 数据/权限）。web/ 之外没有改动，也没有改 package.json 或锁文件。
- 新页面调用 `/tasks`、`/tasks/{id}`、`/tasks/{id}/download`，和 `contract/openapi.yaml` 里的 tasksIndex/tasksShow/tasksDownload 一一对应，没有调用契约外的接口，也没有改契约。
- 正文已写明"未删 artd 任何文件"，artd 其余残留页因为没有契约接口而没实现。实际情况与正文相符。

## 2. 安全：没有发现问题
- 没有 innerHTML/dangerouslySetInnerHTML、eval、console 输出、直接读写 storage，也没有硬编码密钥（只有测试夹具里的 `mock-admin-token`）。
- 没有删掉或放宽原有的权限检查；新路由加了 `permission: 'system:task:index'`，后端 PermissionRegistry 里已有这个码。

## 3. 门禁文件只减不增：符合
- 没有改 CI、门禁脚本、lint 配置或任何白名单/豁免/基线文件。
- diff 里 `eslint-disable`、`@ts-ignore`/`@ts-expect-error`/`@ts-nocheck`、`any` 的新增数都是 0。

## 4. 与《老板已定事项总表》对照：没有冲突
- 第 14 条"代码生成器不做"：PR 没实现，一致。
- 第 24 条"AI 改代码须自动测试，不过不合并"：PR 自报 vitest/tsc/eslint 通过，CI 6 项都通过，一致。
- 第 40 条"新模块须出能力对照表"：正文里的盘点就是逐页对照契约做的，一致。
- 第 10 条框架/插件边界、第 17 条编号规则、待确认第 4 条（i18n 只做中文）：没有冲突，PR 只加了 zh-CN 文案。
- 统一词表（task / cancelled）：用法一致。

## 5. gh pr checks 106（原样）
```
composer audit	pass	8s	.../runs/44577917667/job/135750356401
composer audit	pass	9s	.../runs/44577939296/job/135750430127
frontend prod audit	pass	20s	.../runs/44577917667/job/135750357158
frontend prod audit	pass	16s	.../runs/44577939296/job/135750430623
release self-check	pass	57s	.../runs/44577917667/job/135750357259
release self-check	pass	59s	.../runs/44577939296/job/135750430713
```
全部通过。

## 合并前确认（不算修改点）
- 审计时 GitHub 显示 `mergeable: UNKNOWN`。合并时要确认它显示没有冲突、可以合并。

## 建议后续处理（不影响合并）
1. 路由比契约更严：契约规定 `/tasks` 登录即可访问，有 `system:task:index` 的人看全部、没有的人看自己发起的；前端路由却要求必须有这个权限码，没有的人在界面上看不到自己的任务。这只是更严，不是安全漏洞，后续可以决定是否放开。
2. "任务中心"菜单只加在了 mock 菜单树里，后端真实菜单种子有没有这一项没核实到；如果没有，只能直接输入网址进入页面。
3. 标题写"迁移"，但 artd `tool/crontab` 旧文件还在（正文已说明）。旧页是定时任务的增删改查，新页是后台任务运行面，两者不完全等价。
4. 下载兜底文件名固定为 `-result.csv`；服务端实际文件类型不同时，扩展名会误导（小问题）。
5. `TasksPage.tsx` 里 `String(value ?? '') as TaskState` 没有类型守卫，后端返回契约外的状态时会显示原始 key（健壮性小瑕疵）。

## 证据局限
- 没有在本地跑测试或构建（本地工作区在别的分支，本次只读）；测试结论来自 CI 结果和 PR 自报。
- 没有展开 CI 各 job 的具体断言，只确认了 pass 状态。
