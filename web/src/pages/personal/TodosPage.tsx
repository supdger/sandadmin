import { useState } from 'react';

import { Button, Pagination, Skeleton, Table, Tag, Typography } from '@douyinfe/semi-ui';
import type { ColumnProps } from '@douyinfe/semi-ui/lib/es/table/interface';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { getAllTodos, type TodoItem } from '@/api/v1/dashboard';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { formatRelativeTime } from './relativeTime';

const PAGE_SIZE = 20;
const TODOS_QUERY_KEY = ['dashboard', 'todos', 'all'] as const;

/** /todos — the full "待我处理" list behind the workbench card's 查看全部. */
export function TodosPage() {
  const { t } = useTranslation('workbench');
  const navigate = useNavigate();
  const [page, setPage] = useState(1);

  const todos = useQuery({
    queryKey: [...TODOS_QUERY_KEY, page],
    queryFn: () => getAllTodos(page, PAGE_SIZE),
  });

  const columns: ColumnProps<TodoItem>[] = [
    {
      title: t('todos.title'),
      dataIndex: 'title',
      render: (_: unknown, item: TodoItem) => (
        <div className="flex flex-col">
          <span>{item.title}</span>
          {item.overdue ? (
            <Tag size="small" color="red" className="mt-1 w-fit">
              {t('todos.overdue')}
            </Tag>
          ) : null}
        </div>
      ),
    },
    { title: t('todos.sourceLabel'), dataIndex: 'source_label', width: 160 },
    {
      title: t('todos.timeLabel'),
      dataIndex: 'create_time',
      width: 140,
      render: (value: string) => formatRelativeTime(t, value),
    },
    {
      title: '',
      width: 100,
      render: (_: unknown, item: TodoItem) => (
        <Button size="small" onClick={() => navigate(item.link)}>
          {t('todos.handle')}
        </Button>
      ),
    },
  ];

  const data = todos.data;

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title={t('todos.title')} subtitle={t('todos.subtitle')} />
      {todos.isPending ? (
        <Skeleton loading placeholder={<Skeleton.Paragraph rows={6} />}>
          <div />
        </Skeleton>
      ) : todos.isError ? (
        <ErrorState
          kind="error"
          error={todos.error}
          onRetry={() => void todos.refetch()}
          showHome={false}
        />
      ) : (
        <>
          <Table<TodoItem>
            columns={columns}
            dataSource={data?.items ?? []}
            rowKey={(item) =>
              item ? `${item.source_key}-${item.create_time}-${item.title}` : ''
            }
            pagination={false}
            empty={
              <Empty title={t('todos.empty')} description={undefined}>
                <Typography.Text type="tertiary" size="small">
                  {t('todos.emptyHint')}
                </Typography.Text>
              </Empty>
            }
          />
          {(data?.total ?? 0) > PAGE_SIZE ? (
            <Pagination
              currentPage={page}
              pageSize={PAGE_SIZE}
              total={data?.total ?? 0}
              onPageChange={setPage}
            />
          ) : null}
        </>
      )}
    </div>
  );
}
