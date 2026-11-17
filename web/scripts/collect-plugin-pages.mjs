// 构建期插件前端收集：把 ../server/plugin/<app>/plugin-web/<app>/ 合并复制
// 到 src/plugins/<app>/（只增与覆盖，不删未登记目录）。
// ../server/plugin 不存在时安静退出：web 可脱离后端单独开发。
import { cpSync, existsSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const webRoot = fileURLToPath(new URL('..', import.meta.url));
const pluginRoot = join(webRoot, '..', 'server', 'plugin');
const targetRoot = join(webRoot, 'src', 'plugins');

if (!existsSync(pluginRoot) || !statSync(pluginRoot).isDirectory()) {
  process.exit(0);
}

for (const entry of readdirSync(pluginRoot, { withFileTypes: true })) {
  if (!entry.isDirectory()) {
    continue;
  }
  const sourceDir = join(pluginRoot, entry.name, 'plugin-web', entry.name);
  if (!existsSync(sourceDir) || !statSync(sourceDir).isDirectory()) {
    continue;
  }
  const targetDir = join(targetRoot, entry.name);
  cpSync(sourceDir, targetDir, { recursive: true });
  console.log(`collect:plugins ${entry.name} -> ${relative(webRoot, targetDir)}`);
}
