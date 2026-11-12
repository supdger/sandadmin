import { describe, expect, it } from 'vitest';

import type { Dept } from '@/api/v1/depts';
import {
  buildDeptTreeOptions,
  collectDescendantIds,
  defaultExpandedKeys,
  findDeptNode,
  isDescendantOf,
  nextChildSort,
  nextRootSort,
  relocateDept,
} from './deptTree';

function makeDept(id: string, parentId: string | null, sort: number, name = id): Dept {
  return {
    id,
    parent_id: parentId,
    name,
    leader_id: null,
    leader_name: null,
    leader_avatar: null,
    leader_status: null,
    phone: null,
    sort,
    status: 'enabled',
    user_count: 0,
    update_time: '2026-01-01T00:00:00+08:00',
    children: [],
  };
}

function makeTree(): Dept[] {
  const root = makeDept('root', null, 1, '总部');
  const tech = makeDept('tech', 'root', 1, '技术部');
  const fe = makeDept('fe', 'tech', 1, '前端组');
  const be = makeDept('be', 'tech', 2, '后端组');
  const ops = makeDept('ops', 'root', 2, '运营部');
  tech.children = [fe, be];
  root.children = [tech, ops];
  return [root];
}

describe('buildDeptTreeOptions', () => {
  it('nests children by parent_id and sorts by sort', () => {
    const flat = [
      makeDept('b', 'a', 2, '乙'),
      makeDept('a', null, 1, '甲'),
      makeDept('c', 'a', 1, '丙'),
    ];
    const tree = buildDeptTreeOptions(flat);
    expect(tree).toHaveLength(1);
    expect(tree[0].value).toBe('a');
    expect(tree[0].children?.map((n) => n.value)).toEqual(['c', 'b']);
  });

  it('marks disabled dept nodes as disabled options', () => {
    const disabled = { ...makeDept('x', null, 1), status: 'disabled' };
    const [node] = buildDeptTreeOptions([disabled]);
    expect(node.disabled).toBe(true);
  });
});

describe('collectDescendantIds / isDescendantOf', () => {
  const tree = makeTree();

  it('collects all descendants of a node', () => {
    const ids = collectDescendantIds(tree, 'tech');
    expect(ids).toEqual(new Set(['fe', 'be']));
  });

  it('detects descendants and self', () => {
    expect(isDescendantOf(tree, 'tech', 'fe')).toBe(true);
    expect(isDescendantOf(tree, 'tech', 'tech')).toBe(true);
    expect(isDescendantOf(tree, 'tech', 'ops')).toBe(false);
    expect(isDescendantOf(tree, 'missing', 'fe')).toBe(false);
  });
});

describe('relocateDept', () => {
  it('moves a node under a new parent and keeps it sorted', () => {
    const tree = makeTree();
    const next = relocateDept(tree, 'ops', 'tech', 3);
    const tech = findDeptNode(next, 'tech')!;
    const root = findDeptNode(next, 'root')!;
    expect(tech.children.map((c) => c.id)).toEqual(['fe', 'be', 'ops']);
    expect(tech.children[2].parent_id).toBe('tech');
    expect(root.children.map((c) => c.id)).toEqual(['tech']);
    // 原树不被修改。
    expect(findDeptNode(tree, 'ops')?.parent_id).toBe('root');
  });

  it('moves a node back to the top level', () => {
    const next = relocateDept(makeTree(), 'fe', null, 5);
    expect(findDeptNode(next, 'fe')?.parent_id).toBeNull();
    expect(next.map((d) => d.id)).toContain('fe');
    expect(findDeptNode(next, 'tech')?.children.map((c) => c.id)).toEqual(['be']);
  });

  it('reorders within the same parent', () => {
    const next = relocateDept(makeTree(), 'fe', 'tech', 9);
    const tech = findDeptNode(next, 'tech')!;
    expect(tech.children.map((c) => c.id)).toEqual(['be', 'fe']);
  });

  it('returns the original array when the node is missing', () => {
    const tree = makeTree();
    expect(relocateDept(tree, 'missing', null, 1)).toBe(tree);
  });
});

describe('sort helpers', () => {
  it('computes next child and root sort', () => {
    const tree = makeTree();
    const tech = findDeptNode(tree, 'tech')!;
    expect(nextChildSort(tech, 'ops')).toBe(3);
    expect(nextRootSort(tree, 'ops')).toBe(2);
    // 根 + 根的直接子级：root、tech、ops（fe/be 是第三层，不展开）。
    expect(defaultExpandedKeys(tree)).toEqual(['root', 'tech', 'ops']);
  });
});
