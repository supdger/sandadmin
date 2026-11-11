import { useState } from 'react';

import { Input, Modal, Switch, Toast, Typography } from '@douyinfe/semi-ui';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  createDictItem,
  updateDictItem,
  type DictItem,
  type DictItemWrite,
} from '@/api/v1/dict';
import { useFormIdempotencyKey } from '@/hooks/useFormIdempotencyKey';
import { adminErrorMessage } from '@/lib/adminError';

/** 8 个预设色；null 表示「默认」色。 */
const DICT_COLORS = [
  '#3491FA',
  '#13C2C2',
  '#52C41A',
  '#7B61FF',
  '#F77234',
  '#D83931',
  '#C7A252',
  '#8C8C8C',
] as const;

export interface DictItemModalProps {
  visible: boolean;
  typeId: string;
  /** 同组全部选项：开启「默认选中」时把其他默认项一并取消。 */
  items: DictItem[];
  /** null 表示新建；否则编辑该选项。 */
  item: DictItem | null;
  onClose: () => void;
  onSaved: () => void;
}

function toWrite(
  label: string,
  color: string | null,
  isDefault: boolean,
  available: boolean,
): DictItemWrite {
  return {
    label,
    color,
    is_default: isDefault,
    status: available ? 'enabled' : 'disabled',
  };
}

/** 选项的新建/编辑弹窗。 */
export function DictItemModal({
  visible,
  typeId,
  items,
  item,
  onClose,
  onSaved,
}: DictItemModalProps) {
  const { t } = useTranslation('dict');
  const queryClient = useQueryClient();
  const [label, setLabel] = useState(item?.label ?? '');
  const [color, setColor] = useState<string | null>(item?.color ?? null);
  const [isDefault, setIsDefault] = useState(item?.is_default ?? false);
  const [available, setAvailable] = useState(item?.status !== 'disabled');
  const [labelError, setLabelError] = useState<string | null>(null);

  const editing = item !== null;
  const submitKey = useFormIdempotencyKey(visible && !editing);

  const save = useMutation({
    mutationFn: async () => {
      const body = toWrite(label.trim(), color, isDefault, available);
      const saved = editing
        ? await updateDictItem(item.id, body)
        : await createDictItem(typeId, body, submitKey);
      // 「默认选中」一组只能有一个：保存成功后把同组其他默认项取消。
      if (isDefault) {
        const others = items.filter((i) => i.is_default && i.id !== saved.id);
        for (const other of others) {
          await updateDictItem(
            other.id,
            toWrite(other.label, other.color, false, other.status === 'enabled'),
          );
        }
      }
      return saved;
    },
    onSuccess: async (saved) => {
      Toast.success(
        t(editing ? 'itemModal.saved' : 'confirm.added', { name: saved.label }),
      );
      await queryClient.invalidateQueries({ queryKey: ['dict'] });
      onSaved();
      onClose();
    },
    onError: (error) => {
      Toast.error(adminErrorMessage(error, t('itemModal.saveFailed')));
    },
  });

  const handleSubmit = () => {
    const trimmed = label.trim();
    if (!trimmed) {
      setLabelError(t('itemModal.labelRequired'));
      return;
    }
    setLabelError(null);
    save.mutate();
  };

  return (
    <Modal
      visible={visible}
      title={t(editing ? 'itemModal.editTitle' : 'itemModal.addTitle')}
      size="small"
      onCancel={onClose}
      onOk={handleSubmit}
      okText={t('itemModal.save')}
      cancelText={t('confirm.think')}
      confirmLoading={save.isPending}
      closable
    >
      <div className="flex flex-col gap-4 pt-2">
        <div className="flex flex-col gap-1">
          <Typography.Text strong>
            {t('itemModal.label')}
            <Typography.Text type="danger"> *</Typography.Text>
          </Typography.Text>
          <Input
            value={label}
            maxLength={20}
            showClear
            placeholder={t('itemModal.labelPlaceholder')}
            onChange={(v) => {
              setLabel(v);
              if (labelError) setLabelError(null);
            }}
          />
          {labelError ? (
            <Typography.Text type="danger" size="small">
              {labelError}
            </Typography.Text>
          ) : null}
        </div>

        <div className="flex flex-col gap-1">
          <Typography.Text strong>{t('itemModal.color')}</Typography.Text>
          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              aria-label={t('itemModal.colorDefault')}
              title={t('itemModal.colorDefault')}
              className={`h-6 w-6 rounded-full border ${
                color === null ? 'border-[var(--semi-color-primary)] ring-2 ring-[var(--semi-color-primary-light-default)]' : 'border-[var(--semi-color-border)]'
              }`}
              style={{ background: 'var(--semi-color-bg-1)' }}
              onClick={() => setColor(null)}
            />
            {DICT_COLORS.map((c) => (
              <button
                key={c}
                type="button"
                aria-label={c}
                title={c}
                className={`h-6 w-6 rounded-full border ${
                  color === c
                    ? 'border-transparent ring-2 ring-[var(--semi-color-primary)]'
                    : 'border-transparent'
                }`}
                style={{ background: c }}
                onClick={() => setColor(c)}
              />
            ))}
          </div>
        </div>

        <div className="flex items-center justify-between gap-4">
          <div>
            <Typography.Text strong>{t('itemModal.isDefault')}</Typography.Text>
            <div>
              <Typography.Text type="tertiary" size="small">
                {t('itemModal.isDefaultHint')}
              </Typography.Text>
            </div>
          </div>
          <Switch checked={isDefault} onChange={setIsDefault} />
        </div>

        <div className="flex items-center justify-between gap-4">
          <Typography.Text strong>{t('itemModal.available')}</Typography.Text>
          <Switch checked={available} onChange={setAvailable} />
        </div>
      </div>
    </Modal>
  );
}
