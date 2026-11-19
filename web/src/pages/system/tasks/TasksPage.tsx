import { useEffect, useMemo, useRef, useState } from 'react';

import {
  Button,
  Checkbox,
  Input,
  Pagination,
  Progress,
  Select,
  Table,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import { IconDownload, IconEyeOpened, IconRefresh } from '@douyinfe/semi-icons';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import type { ColumnProps } from '@douyinfe/semi-ui/lib/es/table/interface';

import { downloadTask, listTasks, type Task } from '@/api/v1/tasks';
import type { TaskState } from '@/api/v1/types';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useUrlQuery, type UrlQueryFields } from '@/hooks/useUrlQuery';
import { adminErrorMessage } from '@/lib/adminError';
import { formatDateTime } from '@/lib/format';
import { saveDownloadedFile } from '@/pages/org/shared/download';
import { TaskDetailDrawer } from './TaskDetailDrawer';
import { TaskStateTag } from './TaskStateTag';

const PAGE_SIZE = 20;

const URL_FIELDS: UrlQueryFields = {
  type: {},
  state: {},
  mine: { kind: 'boolean' },
  page: { defaultValue: 1, kind: 'number' },
};

const STATE_OPTIONS: TaskState[] = ['queued', 'running', 'succeeded', 'failed', 'cancelled'];

/**
 * /tasks — 任务中心：后台任务（导出、清理等）的进度与结果列表。
 * 契约按登录人收口：持 system:task:index 看全部，本页路由即按该码挂门。
 */
export function TasksPage() {
  const { t } = useTranslation('tasks');
  const queryClient = useQueryClient();

  const { values, setValues } = useUrlQuery(URL_FIELDS);
  const type = (values.type as string | undefined) ?? '';
  const state = values.state as TaskState | undefined;
  const mine = values.mine === true;
  const page = (values.page as number | undefined) ?? 1;

  const [typeInput, setTypeInput] = useState(type);
  const lastPushed = useRef(type);
  useEffect(() => {
    if (type !== lastPushed.current) {
      lastPushed.current = type;
      setTypeInput(type);
    }
  }, [type]);
  useEffect(() => {
    const timer = setTimeout(() => {
      const next = typeInput.trim();
      if (next !== type) {
        lastPushed.current = next;
        setValues({ type: next || undefined, page: 1 });
      }
    }, 300);
    return () => clearTimeout(timer);
  }, [typeInput, type, setValues]);

  const listQuery = useQuery({
    queryKey: ['v1', 'tasks', { type: type || undefined, state, mine, page }],
    queryFn: () =>
      listTasks({
        type: type || undefined,
        state,
        mine: mine || undefined,
        page,
        page_size: PAGE_SIZE,
      }),
  });
  const tasks = useMemo(() => listQuery.data?.items ?? [], [listQuery.data]);
  const total = listQuery.data?.total ?? 0;

  const [detailId, setDetailId] = useState<string | null>(null);

  const handleDownload = async (item: Task) => {
    try {
      const file = await downloadTask(item.id);
      saveDownloadedFile(file, `${item.id}-result.csv`);
      Toast.success(t('actions.downloaded'));
    } catch (error) {
      Toast.error(adminErrorMessage(error, t('actions.downloadFailed')));
    }
  };

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['v1', 'tasks'] });
  };

  const columns: ColumnProps<Task>[] = [
    {
      title: t('columns.type'),
      dataIndex: 'type',
      render: (value) => <Typography.Text code>{String(value ?? '')}</Typography.Text>,
    },
    {
      title: t('columns.state'),
      dataIndex: 'state',
      render: (value) => <TaskStateTag state={String(value ?? '') as TaskState} />,
    },
    {
      title: t('columns.progress'),
      dataIndex: 'progress',
      render: (value) =>
        value === null || value === undefined ? (
          '—'
        ) : (
          <Progress
            percent={Number(value)}
            size="small"
            style={{ width: 96 }}
            aria-label={t('columns.progress')}
          />
        ),
    },
    {
      title: t('columns.stage'),
      dataIndex: 'stage',
      render: (value) => (value ? String(value) : '—'),
    },
    {
      title: t('columns.createTime'),
      dataIndex: 'create_time',
      render: (value) => formatDateTime(String(value ?? '')),
    },
    {
      title: t('columns.finishTime'),
      dataIndex: 'finish_time',
      render: (value) => formatDateTime(String(value ?? '')),
    },
    {
      title: t('columns.actions'),
      dataIndex: 'actions',
      render: (_value, task) => (
        <span className="flex items-center gap-2">
          <Button
            size="small"
            theme="borderless"
            icon={<IconEyeOpened />}
            onClick={() => setDetailId(task.id)}
          >
            {t('actions.detail')}
          </Button>
          {task.state === 'succeeded' ? (
            <Button
              size="small"
              theme="borderless"
              icon={<IconDownload />}
              onClick={() => void handleDownload(task)}
            >
              {t('actions.download')}
            </Button>
          ) : null}
        </span>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        extra={
          <Button
            icon={<IconRefresh />}
            loading={listQuery.isFetching}
            onClick={refresh}
          >
            {t('actions.refresh')}
          </Button>
        }
      />

      <div className="flex flex-wrap items-center gap-3">
        <Input
          value={typeInput}
          placeholder={t('search.typePlaceholder')}
          style={{ width: 220 }}
          showClear
          onChange={(value) => setTypeInput(value)}
        />
        <Select
          value={state ?? ''}
          placeholder={t('search.stateAll')}
          style={{ width: 140 }}
          onChange={(value) => {
            const next = String(value ?? '');
            setValues({ state: next || undefined, page: 1 });
          }}
          optionList={[
            { label: t('search.stateAll'), value: '' },
            ...STATE_OPTIONS.map((key) => ({ label: t(`states.${key}`), value: key })),
          ]}
        />
        <Checkbox
          checked={mine}
          onChange={(e) => setValues({ mine: e.target.checked || undefined, page: 1 })}
        >
          {t('search.mineOnly')}
        </Checkbox>
      </div>

      {listQuery.isPending ? (
        <Table columns={columns} dataSource={[]} loading pagination={false} />
      ) : listQuery.isError && tasks.length === 0 ? (
        <ErrorState
          kind="error"
          error={listQuery.error}
          onRetry={refresh}
          showHome={false}
        />
      ) : (
        <>
          <Table
            columns={columns}
            dataSource={tasks}
            rowKey="id"
            pagination={false}
            empty={<Empty title={t('empty')} description={t('emptyDesc')} />}
          />
          {total > PAGE_SIZE ? (
            <div className="flex justify-end">
              <Pagination
                currentPage={page}
                pageSize={PAGE_SIZE}
                total={total}
                showTotal
                onPageChange={(next) => setValues({ page: next <= 1 ? 1 : next })}
              />
            </div>
          ) : null}
        </>
      )}

      <TaskDetailDrawer taskId={detailId} onClose={() => setDetailId(null)} />
    </div>
  );
}
