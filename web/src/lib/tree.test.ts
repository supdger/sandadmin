import { describe, expect, it } from 'vitest';

import { buildTree, flattenTree } from './tree';

interface Row {
  id: string;
  parent_id: string | null;
  name: string;
}

const options = {
  id: (row: Row) => row.id,
  parentId: (row: Row) => row.parent_id,
};

describe('buildTree', () => {
  it('nests children under their parents preserving order', () => {
    const rows: Row[] = [
      { id: 'a', parent_id: null, name: 'A' },
      { id: 'b', parent_id: 'a', name: 'B' },
      { id: 'c', parent_id: 'a', name: 'C' },
      { id: 'd', parent_id: 'b', name: 'D' },
      { id: 'e', parent_id: null, name: 'E' },
    ];
    const tree = buildTree(rows, options);
    expect(tree.map((n) => n.id)).toEqual(['a', 'e']);
    const a = tree[0];
    expect(a.children.map((n) => n.id)).toEqual(['b', 'c']);
    expect(a.children[0].children.map((n) => n.id)).toEqual(['d']);
  });

  it('mounts orphans at the root instead of dropping them', () => {
    const rows: Row[] = [
      { id: 'a', parent_id: null, name: 'A' },
      { id: 'ghost', parent_id: 'missing', name: 'G' },
    ];
    const tree = buildTree(rows, options);
    expect(tree.map((n) => n.id)).toEqual(['a', 'ghost']);
  });

  it('does not mutate the input array', () => {
    const rows: Row[] = [{ id: 'a', parent_id: null, name: 'A' }];
    buildTree(rows, options);
    expect('children' in rows[0]).toBe(false);
  });
});

describe('flattenTree', () => {
  it('walks the tree depth-first', () => {
    const rows: Row[] = [
      { id: 'a', parent_id: null, name: 'A' },
      { id: 'b', parent_id: 'a', name: 'B' },
      { id: 'c', parent_id: 'a', name: 'C' },
      { id: 'e', parent_id: null, name: 'E' },
    ];
    const flat = flattenTree(buildTree(rows, options));
    expect(flat.map((n) => n.id)).toEqual(['a', 'b', 'c', 'e']);
  });
});
