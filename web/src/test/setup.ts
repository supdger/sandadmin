import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
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

// jsdom has no layout engine: Range geometry APIs used by Semi Typography
// ellipsis do not exist. Provide zero-sized no-ops.
const zeroRect = {
  x: 0,
  y: 0,
  top: 0,
  right: 0,
  bottom: 0,
  left: 0,
  width: 0,
  height: 0,
  toJSON: () => ({}),
} as DOMRect;

const emptyRectList = {
  length: 0,
  item: () => null,
  [Symbol.iterator]: function* () {},
} as unknown as DOMRectList;

if (typeof Range !== 'undefined') {
  if (typeof Range.prototype.getBoundingClientRect !== 'function') {
    Range.prototype.getBoundingClientRect = () => zeroRect;
  }
  if (typeof Range.prototype.getClientRects !== 'function') {
    Range.prototype.getClientRects = () => emptyRectList;
  }
}

// jsdom does not implement ResizeObserver (Semi layout/resize hooks need it).
if (typeof globalThis.ResizeObserver !== 'function') {
  class ResizeObserverStub implements ResizeObserver {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
  }
  Object.defineProperty(globalThis, 'ResizeObserver', {
    value: ResizeObserverStub,
    configurable: true,
    writable: true,
  });
}

beforeAll(() => {
  server.listen({ onUnhandledRequest: 'error' });
});

afterEach(() => {
  cleanup();
  server.resetHandlers();
});

afterAll(() => {
  server.close();
});
