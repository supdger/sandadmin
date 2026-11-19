【任务】补齐契约套件的命名连接规则 M-05

依据：独立审计新增表 #6（v4 第 1148 行附近）已部分验收，剩“命名连接四规则 M-05”未做，见 ~/agent-work/grokbot/contract2-1117/RESULT.md。

输入（Mac 路径）：上述 v4 和 RESULT；/workspace/intro/老板已定事项总表.md；supdger/sandadmin main 的 tools/contract-build、server/src/Contract、tools/naming_check.py 及其测试（只读起步）；docs/1x-skills 分支的 .agents/skills。

要做：先在上述文件里找到 M-05 四条规则的确切定义，找不到就停下回报，不要自己编规则。找到后在契约构建或检查中实现这四条规则，写失败用例和通过用例，现有插件契约若不符合，在 RESULT 列出，不擅自改插件仓。

仓库与分支：supdger/sandadmin，从 main 开 feat/contract-naming-m05，开工写明基线 SHA。

范围：只改契约构建和检查代码及对应测试；contract/openapi.yaml 不改，派生产物只用 php tools/contract-build 重新生成。不改门禁白名单、基线和 CI 配置。

禁止：同任务 A 的禁止条款全部适用。

交付：目标为 main 的未合并 PR（分支和最终 SHA）；任务目录 RESULT.md：四条规则的来源、实现位置、用例、命令、退出码和关键输出（phpunit unit 和 contract、phpstan、contract-build --check、pre-merge --fast）。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/1c-contract-m05-1119/，RESULT.md 写在这里。
