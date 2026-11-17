import '@/i18n/i18n';

import { act, render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

/*
 * jsdom 缺 slate 依赖的选区/测量 API，wangeditor 本体跑不起来；
 * 这里用桩替换 @wangeditor/editor-for-react，只验证本组件的
 * 受控值同步、只读禁用、卸载销毁三个外包行为。
 */
const hoisted = vi.hoisted(() => ({
  editorProps: {} as Record<string, unknown>,
  toolbarMounted: 0,
}));

vi.mock('@wangeditor/editor-for-react', () => ({
  Editor: (props: Record<string, unknown>) => {
    hoisted.editorProps = props;
    return null;
  },
  Toolbar: () => {
    hoisted.toolbarMounted += 1;
    return null;
  },
}));

import { WangEditor } from './WangEditor';

function fakeEditor() {
  const editor = {
    html: '',
    getHtml() {
      return editor.html;
    },
    setHtml: vi.fn((html: string) => {
      editor.html = html;
    }),
    destroy: vi.fn(),
    disable: vi.fn(),
    enable: vi.fn(),
  };
  return editor;
}

type FakeEditor = ReturnType<typeof fakeEditor>;

function create(props: Record<string, unknown>): FakeEditor {
  const editor = fakeEditor();
  act(() => {
    (props.onCreated as (e: unknown) => void)(editor);
  });
  return editor;
}

describe('WangEditor', () => {
  it('creates the editor and destroys it on unmount', () => {
    const onCreated = vi.fn();
    const { unmount } = render(<WangEditor onCreated={onCreated} />);
    const editor = create(hoisted.editorProps);
    expect(onCreated).toHaveBeenCalledWith(editor);

    unmount();
    expect(editor.destroy).toHaveBeenCalledOnce();
  });

  it('hides the toolbar and disables editing in readOnly mode', () => {
    hoisted.toolbarMounted = 0;
    render(<WangEditor readOnly />);
    const editor = create(hoisted.editorProps);
    expect(hoisted.toolbarMounted).toBe(0);
    expect(editor.disable).toHaveBeenCalled();
  });

  it('syncs external value changes into the editor but not its own output', () => {
    const onChange = vi.fn();
    const { rerender } = render(<WangEditor value="<p>旧</p>" onChange={onChange} />);
    const editor = create(hoisted.editorProps);

    // 编辑器自身输入 → onChange，不再 setHtml 回刷。
    editor.html = '<p>新输入</p>';
    (hoisted.editorProps.onChange as (e: FakeEditor) => void)(editor);
    expect(onChange).toHaveBeenCalledWith('<p>新输入</p>');
    expect(editor.setHtml).not.toHaveBeenCalled();

    rerender(<WangEditor value="<p>外部赋值</p>" onChange={onChange} />);
    expect(editor.setHtml).toHaveBeenCalledWith('<p>外部赋值</p>');
  });

  it('uses the default placeholder from the feForm bundle', () => {
    render(<WangEditor />);
    expect(hoisted.editorProps.defaultConfig).toMatchObject({ placeholder: '请输入内容' });
  });
});
