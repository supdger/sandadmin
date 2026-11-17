# TypePHP 编译友好写法（硬规矩）

适用范围：SandAdmin 1.x 所有 PHP 代码。目标：代码**能编译也能不编译**——按 TypePHP 可编译标准写，日常开发用普通 PHP 跑；写法规矩是硬的，编译动作不是每次都要做。硬规矩出处：v4 T-8 / B-23（strict_types、全量类型、禁魔术与动态特性、不存全局请求态、PHPStan 最高级别门禁、编译检查可选）。

## 一、文件与声明

- 每个 PHP 文件第一行声明后必须 `declare(strict_types=1);`。
- 业务声明文件（类、接口、trait）**只放声明**：禁止在文件顶层执行代码（发请求、连库、读写文件、注册副作用）；初始化一律进引导/Worker 生命周期入口。
- 配置、模板、运行资源单独分类，不混进类声明文件。

## 二、类型标注（全量）

- 参数、返回值、属性、局部变量类型全部写明，不用 `mixed` 兜底（确需时写明原因）；回调必须声明实际接收的参数类型，不写裸 `callable` 收一切。
- 泛型/数组形状写不出来的用 `@param`/`@return`/`@template` 标注给 PHPStan 看；不写复述型注释。
- 一个变量不跨不兼容类型复用（先当 string 后当 array 这种写法禁止）；可空就标 `?type` 或联合类型，不靠"有时候是 null"。
- 非空 `switch` 分支显式结束（`break`/`return`/`throw`），不隐式跌落。

## 三、禁止清单（编译器搞不定的写法）

| 禁止 | 替代 |
| --- | --- |
| 魔术方法 `__get`/`__set`/`__call`/`__callStatic`/`__invoke` 当业务入口 | 显式方法、显式属性 |
| 动态属性（运行时 `$obj->foo = ...` 新增属性） | 声明过的属性；确实要宽松结构用 `stdClass` 或数组并标注形状 |
| 可变变量 `$$name`、可变方法 `$obj->$method()` 不可静态确定 | 显式分支/`match` 分发 |
| 动态 `include`/`require`/`eval` 业务代码 | 自动加载 + 显式 use |
| 闭包 `$closure->bindTo()` 重绑定作用域 | 显式参数传递 |
| `extract()`、`compact()` 反向造变量、字符串拼类名/函数名直调 | `new` 显式类、工厂方法登记映射 |
| 全局请求状态进静态属性/单例属性 | `RequestContext`（每请求新建、try/finally 清理） |

- 框架/第三方内部的动态行为不背锅：由已验证的适配层处理，业务代码不自己去玩魔术。
- 业务源码**禁止**通过编译器 ignore 退回解释执行凑数。
- **运行期反射/注解扫描注册同样禁止**（M-12）：权限点、路由、MCP 工具清单等注册表一律构建期从 `openapi.yaml` 生成静态 PHP 数组文件随制品发布，运行期只读表；启动期只做「生成物与代码/契约一致性校验」，不一致拒绝启动。理由有二：编译期拦不住运行期才发现的东西，且扫描注册本身依赖动态特性。

## 四、常驻进程规矩（webman 多进程，和 TypePHP 同源要求）

- 请求态数据（当前用户、请求参数、事务、租户上下文）**禁止**进 `static` 属性、全局变量、进程级缓存；每条请求结束必须清干净。
- 计数器、限流桶、快照**禁止**放进程内存——多进程下各自一份等于没有；全部落库或走共享存储。
- 单例服务只能装"真·无状态"的东西（连接工厂、配置）；带请求态的一律每请求新建。

## 五、静态检查门禁（合并硬门槛）

| 检查 | 级别/内容 | 性质 |
| --- | --- | --- |
| PHPStan | **最高级别（level max）**，零报告才过 | 硬门禁 |
| 自定义 PHPStan 规则 | 方言字面量只许在 `Infra/`（NoDialectSqlRule）、仓储白名单字段、请求态泄漏检查 | 硬门禁 |
| deptrac | Service/模块层禁依赖 `Connection` 等 DBAL 连接对象；分层依赖方向不许倒挂 | 硬门禁 |
| 编译检查 | `SAND_DEV_BUILD` 未定义时 dev 链不挂载不注册；发布产物对 dev 链残留 grep 为零 | 发版门禁 |

- 本地开发验证跑 `phpstan` + 受影响面测试即可，不要求每次起 TypePHP 构建；改编译规则、依赖或出发版候选时才跑全量编译验证。
- 历史存量不合规的文件不批量重写；**改到哪合规到哪**——动过的文件按本文件全量达标。1.0 前存量全过 PHPStan 最高级别（S-10）：过渡期可用 PHPStan 基线，但基线/豁免**只减不增**，新增豁免须人审并写到期日（M-09 门禁自保护，流程细节归 ai-loop skill）。

## 六、骨架示意

```php
<?php

declare(strict_types=1);

namespace Sandadmin\Module\System;

use Sandadmin\Infra\Db\Repository;

final class DeptService
{
    public function __construct(
        private readonly Repository $deptRepo,   // 依赖显式进构造，不用容器魔术取
    ) {}

    /** @return list<array{id:int,name:string,parent_id:int}> */
    public function childrenOf(int $parentId): array
    {
        return $this->deptRepo
            ->where(['parent_id' => $parentId, 'delete_time' => null])
            ->fieldMap(['id', 'name', 'parent_id'])   // 白名单字段，不是 SELECT *
            ->all();
    }
}
```
