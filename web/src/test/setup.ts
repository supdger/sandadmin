import '@testing-library/jest-dom/vitest';
import { afterAll, afterEach, beforeAll } from 'vitest';

import { server } from '@/mocks/server';

// Node's built-in `localStorage` global is inert without a backing file and
// blocks jsdom's from being injected into the test environment. Install a
// functional in-memory Storage so app code gets real semantics.
class MemoryStorage implements Storage {
  private readonly map = new Map<string, string>();

  get length(): number {
    return this.map.size;
  }

  clear(): void {
    this.map.clear();
  }

  getItem(key: string): string | null {
    return this.map.has(key) ? this.map.get(key)! : null;
  }

  key(index: number): string | null {
    return [...this.map.keys()][index] ?? null;
  }

  removeItem(key: string): void {
    this.map.delete(key);
  }

  setItem(key: string, value: string): void {
    this.map.set(key, String(value));
  }
}

if (typeof globalThis.localStorage?.setItem !== 'function') {
  Object.defineProperty(globalThis, 'localStorage', {
    value: new MemoryStorage(),
    configurable: true,
    writable: true,
  });
}

// jsdom has no canvas implementation; Semi's Lottie import touches 2d
// context at module load. Stub it with a permissive no-op context.
if (typeof HTMLCanvasElement !== 'undefined') {
  const makeContextStub = (): object => {
    const target: Record<string | symbol, unknown> = {};
    return new Proxy(target, {
      get(t, prop) {
        if (!(prop in t)) {
          t[prop] = () => makeContextStub();
        }
        return t[prop];
      },
    });
  };
  (HTMLCanvasElement.prototype as { getContext: unknown }).getContext = () =>
    makeContextStub();
}

beforeAll(() => {
  server.listen({ onUnhandledRequest: 'error' });
});

afterEach(() => {
  server.resetHandlers();
});

afterAll(() => {
  server.close();
});
