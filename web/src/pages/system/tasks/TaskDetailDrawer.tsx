import { Button, Descriptions, SideSheet, Skeleton, Toast, Typography } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { downloadTask, getTask, type Task } from '@/api/v1/tasks';
import { ErrorState } from '@/components/ErrorState';
import { adminErrorMessage } from '@/lib/adminError';
import { formatDateTime } from '@/lib/format';
import { saveDownloadedFile } from '@/pages/org/shared/download';
import { TaskStateTag } from './TaskStateTag';

export interface TaskDetailDrawerProps {
  /** null 表示抽屉关闭；任务在打开期间按 id 重新拉取。 */
  taskId: string | null;
  onClose: () => void;
}

const POLL_INTERVAL_MS = 2000;

/** 任务详情抽屉；排队中/执行中的任务持续轮询，让进度自己走完。 */
export function TaskDetailDrawer({ taskId, onClose }: TaskDetailDrawerProps) {
  const { t } = useTranslation('tasks');

  const detailQuery = useQuery({
    queryKey: ['v1', 'tasks', 'detail', taskId],
    queryFn: () => getTask(taskId ?? ''),
    enabled: taskId !== null,
    refetchInterval: (query) => {
      const state = query.state.data?.state;
      return state === 'queued' || state === 'running' ? POLL_INTERVAL_MS : false;
    },
  });
  const task = detailQuery.data;

  const handleDownload = async (item: Task) => {
    try {
      const file = await downloadTask(item.id);
      saveDownloadedFile(file, `${item.id}-result.csv`);
      Toast.success(t('actions.downloaded'));
    } catch (error) {
      Toast.error(adminErrorMessage(error, t('actions.downloadFailed')));
    }
  };

  return (
    <SideSheet
      visible={taskId !== null}
      width={480}
      title={t('detail.title')}
      onCancel={onClose}
    >
      {taskId === null ? null : detailQuery.isPending ? (
        <Skeleton placeholder={<Skeleton.Paragraph rows={8} />} loading />
      ) : detailQuery.isError || !task ? (
        <ErrorState
          kind="error"
          error={detailQuery.error}
          onRetry={() => void detailQuery.refetch()}
          showHome={false}
        />
      ) : (
        <div className="flex flex-col gap-4">
          <Descriptions align="left">
            <Descriptions.Item itemKey={t('detail.id')}>
              <Typography.Text copyable={{ content: task.id }}>{task.id}</Typography.Text>
            </Descriptions.Item>
            <Descriptions.Item itemKey={t('columns.type')}>{task.type}</Descriptions.Item>
            <Descriptions.Item itemKey={t('columns.state')}>
              <TaskStateTag state={task.state} />
            </Descriptions.Item>
            <Descriptions.Item itemKey={t('columns.progress')}>
              {task.progress === null ? '—' : `${task.progress}%`}
            </Descriptions.Item>
            <Descriptions.Item itemKey={t('columns.stage')}>
              {task.stage ?? '—'}
            </Descriptions.Item>
            <Descriptions.Item itemKey={t('columns.createTime')}>
              {formatDateTime(task.create_time)}
            </Descriptions.Item>
            <Descriptions.Item itemKey={t('detail.startTime')}>
              {formatDateTime(task.start_time)}
            </Descriptions.Item>
            <Descriptions.Item itemKey={t('columns.finishTime')}>
              {formatDateTime(task.finish_time)}
            </Descriptions.Item>
            <Descriptions.Item itemKey={t('detail.resultFile')}>
              {task.result?.file_id ?? '—'}
            </Descriptions.Item>
          </Descriptions>
          {task.error ? (
            <Typography.Paragraph type="danger">{task.error.message}</Typography.Paragraph>
          ) : null}
          {task.state === 'succeeded' ? (
            <Button theme="solid" onClick={() => void handleDownload(task)}>
              {t('detail.download')}
            </Button>
          ) : null}
        </div>
      )}
    </SideSheet>
  );
}
