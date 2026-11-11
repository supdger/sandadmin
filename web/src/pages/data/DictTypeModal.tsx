import { useState } from 'react';

import { Input, Modal, TextArea, Toast, Typography } from '@douyinfe/semi-ui';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  createDictType,
  updateDictType,
  type DictType,
  type DictTypeWrite,
} from '@/api/v1/dict';
import { FIELD_NAME_WIDTH } from '@/components/fieldWidth';
import { useFormIdempotencyKey } from '@/hooks/useFormIdempotencyKey';
import { adminErrorMessage } from '@/lib/adminError';

export interface DictTypeModalProps {
  visible: boolean;
  /** null 表示新建；否则编辑该组。 */
  type: DictType | null;
  onClose: () => void;
  onSaved: (saved: DictType) => void;
}

/** 选项组的新建/编辑弹窗：组名必填 ≤20 字，说明 ≤100 字。 */
export function DictTypeModal({ visible, type, onClose, onSaved }: DictTypeModalProps) {
  const { t } = useTranslation('dict');
  const queryClient = useQueryClient();
  const [name, setName] = useState(type?.name ?? '');
  const [remark, setRemark] = useState(type?.remark ?? '');
  const [nameError, setNameError] = useState<string | null>(null);

  const editing = type !== null;
  const submitKey = useFormIdempotencyKey(visible && !editing);

  const save = useMutation({
    mutationFn: (body: DictTypeWrite) =>
      editing ? updateDictType(type.id, body) : createDictType(body, submitKey),
    onSuccess: async (saved) => {
      Toast.success(
        t(editing ? 'typeModal.saved' : 'confirm.added', { name: saved.name }),
      );
      await queryClient.invalidateQueries({ queryKey: ['dict'] });
      onSaved(saved);
      onClose();
    },
    onError: (error) => {
      Toast.error(adminErrorMessage(error, t('typeModal.saveFailed')));
    },
  });

  const handleSubmit = () => {
    const trimmed = name.trim();
    if (!trimmed) {
      setNameError(t('typeModal.nameRequired'));
      return;
    }
    setNameError(null);
    save.mutate({ name: trimmed, remark: remark.trim() || null });
  };

  return (
    <Modal
      visible={visible}
      title={t(editing ? 'typeModal.editTitle' : 'typeModal.addTitle')}
      size="small"
      onCancel={onClose}
      onOk={handleSubmit}
      okText={t('typeModal.save')}
      cancelText={t('confirm.think')}
      confirmLoading={save.isPending}
      closable
    >
      <div className="flex flex-col gap-4 pt-2">
        <div className="flex flex-col gap-1">
          <Typography.Text strong>
            {t('typeModal.name')}
            <Typography.Text type="danger"> *</Typography.Text>
          </Typography.Text>
          <Input
            style={{ width: FIELD_NAME_WIDTH }}
            value={name}
            maxLength={20}
            showClear
            placeholder={t('typeModal.namePlaceholder')}
            onChange={setName}
          />
          {nameError ? (
            <Typography.Text type="danger" size="small">
              {nameError}
            </Typography.Text>
          ) : null}
        </div>
        <div className="flex flex-col gap-1">
          <Typography.Text strong>{t('typeModal.remark')}</Typography.Text>
          <TextArea
            value={remark}
            maxLength={100}
            rows={3}
            placeholder={t('typeModal.remarkPlaceholder')}
            onChange={setRemark}
          />
        </div>
      </div>
    </Modal>
  );
}
