import { Button, Card, Empty, Skeleton, Tag, Typography } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';

import { getWorkbenchTodos, type TodoItem } from '@/api/v1/dashboard';
import { formatRelativeTime } from '@/pages/personal/relativeTime';
import { listOrEmpty } from './listOrEmpty';

const TODOS_QUERY_KEY = ['dashboard', 'todos'] as const;

function TodoRow({ item, onOpen }: { item: TodoItem; onOpen: () => void }) {
  const { t } = useTranslation('workbench');
  return (
    <button
      type="button"
      onClick={onOpen}
      className="flex w-full items-center gap-3 rounded px-2 py-2 text-left hover:bg-[var(--semi-color-fill-0)]"
    >
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <Typography.Text strong ellipsis={{ showTooltip: true }}>
            {item.title}
          </Typography.Text>
          {item.overdue ? (
            <Tag size="small" color="red">
              {t('todosSection.overdue')}
            </Tag>
          ) : null}
        </div>
        <Typography.Text type="tertiary" size="small">
          {item.source_label} · {formatRelativeTime(t, item.create_time)}
        </Typography.Text>
      </div>
    </button>
  );
}

/** Workbench 「待我处理」 card — first five todos + link to the full page. */
export function TodoPanel() {
  const { t } = useTranslation('workbench');
  const navigate = useNavigate();
  const todos = useQuery({ queryKey: TODOS_QUERY_KEY, queryFn: () => getWorkbenchTodos(5) });
  const items = listOrEmpty(todos.data?.items);

  return (
    <Card
      title={t('todosSection.title')}
      headerExtraContent={
        (todos.data?.total ?? 0) > 0 ? (
          <Link to="/todos">
            <Button size="small" theme="borderless">
              {t('todosSection.viewAll')}
            </Button>
          </Link>
        ) : null
      }
    >
      {todos.isPending ? (
        <Skeleton loading placeholder={<Skeleton.Paragraph rows={3} />}>
          <div />
        </Skeleton>
      ) : todos.isError ? (
        <Typography.Text type="danger" size="small">
          {t('cards.error')}
        </Typography.Text>
      ) : items.length === 0 ? (
        <Empty title={t('todosSection.empty')} description={undefined} />
      ) : (
        <div className="flex flex-col">
          {items.map((item) => (
            <TodoRow key={`${item.source_key}-${item.title}`} item={item} onOpen={() => navigate(item.link)} />
          ))}
        </div>
      )}
    </Card>
  );
}
