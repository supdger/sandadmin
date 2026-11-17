import { describe, expect, it } from 'vitest';

import { splitByKeyword } from './useHighlight';

describe('splitByKeyword', () => {
  it('空关键词返回整段原文', () => {
    expect(splitByKeyword('hello world', '')).toEqual([
      { text: 'hello world', matched: false },
    ]);
  });

  it('默认大小写不敏感并保留原文大小写', () => {
    expect(splitByKeyword('Hello HELLO world', 'hello')).toEqual([
      { text: 'Hello', matched: true },
      { text: ' ', matched: false },
      { text: 'HELLO', matched: true },
      { text: ' world', matched: false },
    ]);
  });

  it('caseSensitive 时只匹配同样大小写', () => {
    expect(splitByKeyword('Hello hello', 'hello', true)).toEqual([
      { text: 'Hello ', matched: false },
      { text: 'hello', matched: true },
    ]);
  });

  it('连续命中不丢中间片段', () => {
    expect(splitByKeyword('abXcdXef', 'x')).toEqual([
      { text: 'ab', matched: false },
      { text: 'X', matched: true },
      { text: 'cd', matched: false },
      { text: 'X', matched: true },
      { text: 'ef', matched: false },
    ]);
  });
});
