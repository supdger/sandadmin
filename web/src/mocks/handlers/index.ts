import type { HttpHandler } from 'msw';

type HandlerModule = { default: HttpHandler[] };

// Each domain contributes one file exporting `default: HttpHandler[]`.
// This file is skipped explicitly; helpers like _shared.ts export [].
const modules = import.meta.glob<HandlerModule>('./*.ts', { eager: true });

export const handlers: HttpHandler[] = Object.entries(modules)
  .filter(([path]) => path !== './index.ts')
  .flatMap(([, mod]) => mod.default);
