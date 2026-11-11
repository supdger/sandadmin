import { useMemo, useState } from 'react';

import {
  Button,
  Input,
  Popover,
  Select,
  SideSheet,
  Switch,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import { IconSearch } from '@douyinfe/semi-icons';
import { useTranslation } from 'react-i18next';

import {
  createMenuGroup,
  createMenuLink,
  updateMenu,
  type MenuNode,
} from '@/api/v1/menus';
import { AdminErrorNotice } from '@/components/AdminErrorNotice';
import { FIELD_CHOICE_WIDTH, FIELD_NAME_WIDTH } from '@/components/fieldWidth';
import { isAdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import { MENU_ICON_OPTIONS, menuIconNode } from '@/pages/rbac/lib/menuIcons';
import { findMenuRow, menuSiblings } from '@/pages/rbac/lib/menuTree';

export type MenuFormMode = 'group' | 'link' | 'edit';

export interface MenuFormSheetProps {
  mode: MenuFormMode;
  /** 编辑目标；新建模式下忽略。 */
  node: MenuNode | null;
  /** 完整菜单树——用于上级下拉和重名校验。 */
  items: MenuNode[];
  onClose: () => void;
  onSaved: () => void;
}

const TITLE_MAX = 6;
const URL_RE = /^https?:\/\//i;

interface FormErrors {
  title?: string;
  parent?: string;
  url?: string;
}

/** 新建/编辑节点不得与之重名的同级标题。 */
function siblingTitles(
  items: MenuNode[],
  parentKey: string | null,
  excludeKey?: string,
): string[] {
  return menuSiblings(items, parentKey)
    .filter((n) => n.key !== excludeKey)
    .map((n) => n.title);
}

/**
 * 添加分组/添加链接/编辑抽屉。标题限六字以内，同组内名称不得重复，
 * 外链必须以 http(s):// 开头。
 */
export function MenuFormSheet({
  mode,
  node,
  items,
  onClose,
  onSaved,
}: MenuFormSheetProps) {
  const { t } = useTranslation('rbac');
  const isEdit = mode === 'edit' && node !== null;
  const isLinkEdit = isEdit && node?.type === 'link';

  // 每次打开都是新挂载——useState 初始值就是全部种子。
  const [title, setTitle] = useState(isEdit ? (node?.title ?? '') : '');
  const [iconKey, setIconKey] = useState<string | null>(
    isEdit ? (node?.icon ?? null) : null,
  );
  const [parentKey, setParentKey] = useState<string>('');
  const [url, setUrl] = useState(isEdit ? (node?.url ?? '') : '');
  const [visibleFlag, setVisibleFlag] = useState(
    isEdit ? Boolean(node?.visible) : true,
  );
  const [errors, setErrors] = useState<FormErrors>({});
  const [iconKeyword, setIconKeyword] = useState('');
  const [pending, setPending] = useState(false);
  const [submitError, setSubmitError] = useState<unknown>(null);

  const groupOptions = useMemo(
    () => items.filter((n) => n.type === 'group'),
    [items],
  );

  const filteredIcons = useMemo(() => {
    const kw = iconKeyword.trim().toLowerCase();
    if (!kw) return MENU_ICON_OPTIONS;
    return MENU_ICON_OPTIONS.filter(
      (opt) =>
        opt.label.toLowerCase().includes(kw) ||
        opt.key.toLowerCase().includes(kw) ||
        opt.keywords.toLowerCase().includes(kw),
    );
  }, [iconKeyword]);

  const iconPicker = (
    <Popover
      trigger="click"
      position="bottomLeft"
      content={
        <div className="flex w-72 flex-col gap-2 p-2">
          <Input
            size="small"
            prefix={<IconSearch />}
            placeholder={t('menus.form.searchIcon')}
            value={iconKeyword}
            onChange={setIconKeyword}
          />
          <div className="grid max-h-56 grid-cols-6 gap-1 overflow-auto">
            {filteredIcons.map((opt) => (
              <button
                key={opt.key}
                type="button"
                title={opt.label}
                className={`flex h-9 w-9 items-center justify-center rounded-md hover:bg-[var(--semi-color-fill-0)] ${
                  iconKey === opt.key
                    ? 'bg-[var(--semi-color-primary-light-default)] text-[var(--semi-color-primary)]'
                    : ''
                }`}
                onClick={() => setIconKey(opt.key)}
              >
                {opt.icon}
              </button>
            ))}
            {filteredIcons.length === 0 ? (
              <Typography.Text type="secondary" size="small">
                {t('permission.noResult')}
              </Typography.Text>
            ) : null}
          </div>
        </div>
      }
    >
      <Button theme="light" size="small">
        <span className="flex items-center gap-2">
          {menuIconNode(iconKey) ?? <span className="text-[var(--semi-color-text-2)]">—</span>}
          {t('menus.form.chooseIcon')}
        </span>
      </Button>
    </Popover>
  );

  const validate = (): FormErrors => {
    const next: FormErrors = {};
    const trimmed = title.trim();
    if (!trimmed) {
      next.title = t('menus.form.nameRequired');
    } else if (trimmed.length > TITLE_MAX) {
      next.title = t('menus.form.nameTooLong');
    } else {
      // 名称须在同一父组内唯一；新分组在顶层，其同级就是顶层节点。
      const parentForCheck =
        mode === 'edit' && node
          ? (findMenuRow(items, node.key)?.parentKey ?? null)
          : mode === 'link'
            ? parentKey || null
            : null;
      const titles = siblingTitles(
        items,
        parentForCheck,
        isEdit ? node?.key : undefined,
      );
      if (titles.includes(trimmed)) {
        next.title = t('menus.form.nameDuplicated', { name: trimmed });
      }
    }
    if (mode === 'link' && !isEdit) {
      if (!parentKey) {
        next.parent = t('menus.form.parentRequired');
      }
      if (!url.trim() || !URL_RE.test(url.trim())) {
        next.url = t('menus.form.urlInvalid');
      }
    }
    return next;
  };

  const submit = async () => {
    const found = validate();
    setErrors(found);
    if (Object.keys(found).length > 0) {
      return;
    }
    setPending(true);
    setSubmitError(null);
    try {
      if (mode === 'group') {
        await createMenuGroup({ title: title.trim(), icon: iconKey });
        Toast.success(t('menus.toast.groupAdded', { name: title.trim() }));
      } else if (mode === 'link') {
        await createMenuLink({
          title: title.trim(),
          url: url.trim(),
          icon: iconKey,
          parent_key: parentKey || null,
        });
        Toast.success(t('menus.toast.linkAdded', { name: title.trim() }));
      } else if (node) {
        await updateMenu(node.key, {
          title: title.trim(),
          icon: iconKey,
          visible: visibleFlag,
        });
        Toast.success(t('menus.toast.saved', { name: title.trim() }));
      }
      onSaved();
      onClose();
    } catch (error) {
      // 字段级原因留在各自输入框下；其余的内联展示。
      if (isAdminApiError(error)) {
        const titleMessage = error.fields?.title?.[0];
        const urlMessage = error.fields?.url?.[0];
        if (titleMessage || urlMessage) {
          setErrors((prev) => ({
            ...prev,
            title: titleMessage ?? prev.title,
            url: urlMessage ?? prev.url,
          }));
          return;
        }
      }
      setSubmitError(error);
    } finally {
      setPending(false);
    }
  };

  /** 把这个节点的自定义覆盖层清回内置值。 */
  const restoreDefault = async () => {
    if (!node) return;
    setPending(true);
    setSubmitError(null);
    try {
      // `icon: null` 让后端丢掉图标覆盖；标题回填 `original_title`，
      // 后端把它当作默认值。
      await updateMenu(node.key, {
        ...(node.original_title ? { title: node.original_title } : {}),
        icon: null,
      });
      Toast.success(t('menus.toast.saved', { name: node.original_title || node.title }));
      onSaved();
      onClose();
    } catch (error) {
      setSubmitError(error);
    } finally {
      setPending(false);
    }
  };

  const canRestore =
    isEdit &&
    node !== null &&
    node.source?.kind !== 'custom' &&
    Boolean(node.original_title);

  const titleText =
    mode === 'group'
      ? t('menus.form.addGroupTitle')
      : mode === 'link'
        ? t('menus.form.addLinkTitle')
        : t('menus.form.editTitle');

  return (
    <SideSheet
      visible
      onCancel={onClose}
      size="small"
      title={titleText}
      footer={
        <div className="flex items-center justify-between">
          <div>
            {canRestore ? (
              <Button
                theme="borderless"
                type="danger"
                disabled={pending}
                onClick={() => void restoreDefault()}
              >
                {t('menus.form.restoreDefault')}
              </Button>
            ) : null}
          </div>
          <div className="flex gap-2">
            <Button theme="light" disabled={pending} onClick={onClose}>
              {t('common:actions.cancel')}
            </Button>
            <Button
              theme="solid"
              loading={pending}
              onClick={() => void submit()}
            >
              {pending ? t('menus.form.saving') : t('menus.form.save')}
            </Button>
          </div>
        </div>
      }
    >
      <div className="flex flex-col gap-4">
        {submitError ? (
          <AdminErrorNotice
            error={submitError}
            fallback={
              <Typography.Text type="danger">
                {adminErrorMessage(submitError, t('menus.loadFailed'))}
              </Typography.Text>
            }
          />
        ) : null}

        <div>
          <Typography.Text strong>{t('menus.form.name')}</Typography.Text>
          <Input
            className="mt-1"
            style={{ width: FIELD_NAME_WIDTH }}
            value={title}
            placeholder={t('menus.form.namePlaceholder')}
            onChange={(v) => {
              setTitle(v);
              setErrors((prev) => ({ ...prev, title: undefined }));
            }}
          />
          {errors.title ? (
            <Typography.Text type="danger" size="small">
              {errors.title}
            </Typography.Text>
          ) : null}
        </div>

        <div>
          <Typography.Text strong>{t('menus.form.icon')}</Typography.Text>
          <div className="mt-1">{iconPicker}</div>
        </div>

        {mode === 'link' && !isEdit ? (
          <div>
            <Typography.Text strong>{t('menus.form.parent')}</Typography.Text>
            <Select
              className="mt-1"
              style={{ width: FIELD_CHOICE_WIDTH }}
              placeholder={t('menus.form.parentPlaceholder')}
              value={parentKey || undefined}
              onChange={(v) => {
                setParentKey(String(v ?? ''));
                setErrors((prev) => ({ ...prev, parent: undefined }));
              }}
            >
              {groupOptions.map((group) => (
                <Select.Option key={group.key} value={group.key}>
                  {group.title}
                </Select.Option>
              ))}
            </Select>
            {errors.parent ? (
              <Typography.Text type="danger" size="small">
                {errors.parent}
              </Typography.Text>
            ) : null}
          </div>
        ) : null}

        {mode === 'link' && !isEdit ? (
          <div>
            <Typography.Text strong>{t('menus.form.url')}</Typography.Text>
            <Input
              className="mt-1"
              value={url}
              placeholder={t('menus.form.urlPlaceholder')}
              onChange={(v) => {
                setUrl(v);
                setErrors((prev) => ({ ...prev, url: undefined }));
              }}
            />
            {errors.url ? (
              <Typography.Text type="danger" size="small">
                {errors.url}
              </Typography.Text>
            ) : null}
          </div>
        ) : null}

        {isLinkEdit ? (
          <div>
            <Typography.Text strong>{t('menus.form.url')}</Typography.Text>
            <Input
              className="mt-1"
              value={url}
              disabled
              placeholder={t('menus.form.urlPlaceholder')}
            />
            <Typography.Text type="secondary" size="small">
              {t('menus.form.urlReadonlyHint')}
            </Typography.Text>
          </div>
        ) : null}

        <div className="flex items-center justify-between">
          <div>
            <Typography.Text strong>{t('menus.form.visible')}</Typography.Text>
            <div>
              <Typography.Text type="secondary" size="small">
                {t('menus.form.visibleHint')}
              </Typography.Text>
            </div>
          </div>
          <Switch
            checked={visibleFlag}
            onChange={(checked) => setVisibleFlag(checked)}
            aria-label={t('menus.form.visible')}
          />
        </div>
      </div>
    </SideSheet>
  );
}
