import { useEffect, useMemo, useRef, useState } from 'react';

import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import {
  Button,
  Dropdown,
  Input,
  Pagination,
  Select,
  Space,
  Table,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import { IconPlus, IconSearch } from '@douyinfe/semi-icons';
import type { ColumnProps } from '@douyinfe/semi-ui/lib/es/table/interface';

import { PageHeader } from '@/components/PageHeader';
import { Empty } from '@/components/Empty';
import { useCan } from '@/hooks/useCan';
import { useUrlQuery, type UrlQueryFields } from '@/hooks/useUrlQuery';
import {
  deletePost,
  listPosts,
  updatePost,
  type Post,
} from '@/api/v1/posts';
import type { UserStatus } from '@/api/v1/types';
import { isAdminApiError, isAdminSystemError } from '@/lib/adminClient';
import { formatDateTime } from '@/lib/format';
import { StatusTag } from '../shared/StatusTag';
import { OrgErrorState } from '../shared/OrgErrorState';
import { adminErrorMessage } from '@/lib/adminError';
import { AdminCopyButton } from '@/components/AdminErrorNotice';
import { PostFormModal } from './PostFormModal';
import { confirmAction, confirmDanger } from '@/lib/confirmAction';

const URL_FIELDS: UrlQueryFields = {
  keyword: {},
  status: {},
  page: { defaultValue: 1 },
  page_size: { defaultValue: 10 },
};

export function PostsPage() {
  const { t } = useTranslation('org');
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  const canCreate = useCan('system:post:create');
  const canUpdate = useCan('system:post:update');
  const canDelete = useCan('system:post:delete');

  const { values, setValues } = useUrlQuery(URL_FIELDS);
  const keyword = (values.keyword as string | undefined) ?? '';
  const status = values.status as UserStatus | undefined;
  const page = (values.page as number | undefined) ?? 1;
  const pageSize = (values.page_size as number | undefined) ?? 10;

  const [keywordInput, setKeywordInput] = useState(keyword);
  const lastPushed = useRef(keyword);
  useEffect(() => {
    if (keyword !== lastPushed.current) {
      lastPushed.current = keyword;
      setKeywordInput(keyword);
    }
  }, [keyword]);
  useEffect(() => {
    const timer = setTimeout(() => {
      const next = keywordInput.trim();
      if (next !== keyword) {
        lastPushed.current = next;
        setValues({ keyword: next || undefined, page: 1 });
      }
    }, 300);
    return () => clearTimeout(timer);
  }, [keywordInput, keyword, setValues]);

  const listQuery = useQuery({
    queryKey: ['v1', 'posts', { keyword: keyword || undefined, status, page, page_size: pageSize }],
    queryFn: () =>
      listPosts({
        keyword: keyword || undefined,
        status,
        page,
        page_size: pageSize,
      }),
  });
  const posts = useMemo(() => listQuery.data?.items ?? [], [listQuery.data]);
  const total = listQuery.data?.total ?? 0;

  const [formState, setFormState] = useState<{ open: boolean; post: Post | null }>({
    open: false,
    post: null,
  });
  const [moreOpenId, setMoreOpenId] = useState<string | null>(null);

  const invalidatePosts = () => {
    void queryClient.invalidateQueries({ queryKey: ['v1', 'posts'] });
    void queryClient.invalidateQueries({ queryKey: ['v1', 'users'] });
  };

  const toastAdminError = (error: unknown, fallback: string) => {
    if (isAdminSystemError(error) && isAdminApiError(error)) {
      Toast.error({
        content: (
          <span>
            {t('common:errors.system.message')} <AdminCopyButton error={error} />
          </span>
        ),
        duration: 8,
      });
      return;
    }
    Toast.error(adminErrorMessage(error, fallback));
  };

  const toggleStatus = (post: Post) => {
    const next: UserStatus = post.status === 'enabled' ? 'disabled' : 'enabled';
    const confirm = next === 'disabled' ? confirmDanger : confirmAction;
    confirm({
      title: t(next === 'disabled' ? 'posts.danger.disableTitle' : 'posts.danger.enableTitle', {
        name: post.name,
      }),
      content: t(next === 'disabled' ? 'posts.danger.disableContent' : 'posts.danger.enableContent'),
      okText: next === 'disabled' ? t('common.disable') : t('common.enable'),
      cancelText: t('common.thinkAgain'),
      okType: 'primary',
      onOk: async () => {
        try {
          await updatePost(post.id, {
            name: post.name,
            remark: post.remark,
            sort: post.sort,
            status: next,
          });
          Toast.success(
            next === 'disabled'
              ? t('posts.danger.doneDisable', { name: post.name })
              : t('posts.danger.doneEnable', { name: post.name }),
          );
          invalidatePosts();
        } catch (error) {
          toastAdminError(error, t('common:errors.system.message'));
        }
      },
    });
  };

  const removePost = (post: Post) => {
    confirmDanger({
      title: t('posts.danger.deleteTitle', { name: post.name }),
      content:
        post.user_count === 0
          ? t('posts.danger.deleteNone')
          : t('posts.danger.deleteContent', { count: post.user_count }),
      okText: t('posts.danger.deleteOk'),
      cancelText: t('common.thinkAgain'),
      okType: 'danger',
      onOk: async () => {
        try {
          await deletePost(post.id);
          Toast.success(t('posts.danger.doneDelete', { name: post.name }));
          invalidatePosts();
        } catch (error) {
          toastAdminError(error, t('common:errors.system.message'));
        }
      },
    });
  };

  const columns: ColumnProps<Post>[] = [
    {
      title: t('posts.table.name'),
      render: (_: unknown, record: Post) => (
        <Typography.Text strong ellipsis={{ showTooltip: true }}>
          {record.name}
        </Typography.Text>
      ),
    },
    {
      title: t('posts.table.remark'),
      width: 240,
      render: (_: unknown, record: Post) => (
        <Typography.Text
          type={record.remark ? undefined : 'tertiary'}
          ellipsis={{ showTooltip: Boolean(record.remark) }}
        >
          {record.remark || t('common.notFilled')}
        </Typography.Text>
      ),
    },
    {
      title: t('posts.table.status'),
      width: 90,
      render: (_: unknown, record: Post) => <StatusTag status={record.status} />,
    },
    {
      title: t('posts.table.userCount'),
      width: 80,
      render: (_: unknown, record: Post) => (
        <Button
          type="tertiary"
          size="small"
          onClick={() => navigate(`/system/user?post_id=${encodeURIComponent(record.id)}`)}
        >
          {record.user_count}
        </Button>
      ),
    },
    {
      title: t('posts.table.createTime'),
      width: 170,
      render: (_: unknown, record: Post) => formatDateTime(record.create_time),
    },
    {
      title: t('posts.table.actions'),
      width: 180,
      fixed: 'right',
      render: (_: unknown, record: Post) => {
        const menuItems = [
          canUpdate
            ? {
                key: 'toggle',
                name: record.status === 'enabled' ? t('common.disable') : t('common.enable'),
                onClick: () => toggleStatus(record),
              }
            : null,
          canDelete
            ? { key: 'delete', name: t('common.delete'), onClick: () => removePost(record) }
            : null,
        ].filter((i): i is { key: string; name: string; onClick: () => void } => i !== null);
        return (
          <Space spacing={4}>
            {canUpdate ? (
              <Button
                type="primary"
                size="small"
                theme="borderless"
                onClick={() => setFormState({ open: true, post: record })}
              >
                {t('common.edit')}
              </Button>
            ) : null}
            {menuItems.length > 0 ? (
              <Dropdown
                trigger="click"
                position="bottomRight"
                visible={moreOpenId === record.id}
                onVisibleChange={(v) => setMoreOpenId(v ? record.id : null)}
                render={
                  <Dropdown.Menu>
                    {menuItems.map((item) => (
                      <Dropdown.Item
                        key={item.key}
                        onClick={() => {
                          setMoreOpenId(null);
                          item.onClick();
                        }}
                      >
                        {item.name}
                      </Dropdown.Item>
                    ))}
                  </Dropdown.Menu>
                }
              >
                <Button type="primary" size="small" theme="borderless">
                  {t('common.more')}
                </Button>
              </Dropdown>
            ) : null}
          </Space>
        );
      },
    },
  ];

  const isFirstLoading = listQuery.isPending && !listQuery.data;
  const hasKeyword = keyword.trim().length > 0;
  const hasFilter = hasKeyword || Boolean(status);

  const clearFilters = () => {
    lastPushed.current = '';
    setKeywordInput('');
    setValues({ keyword: undefined, status: undefined, page: 1 });
  };

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title={t('posts.title')}
        subtitle={t('posts.subtitle')}
        extra={
          canCreate ? (
            <Button
              theme="solid"
              icon={<IconPlus />}
              onClick={() => setFormState({ open: true, post: null })}
            >
              {t('posts.addPost')}
            </Button>
          ) : undefined
        }
      />

      <div className="flex flex-wrap items-center gap-3">
        <Input
          prefix={<IconSearch />}
          placeholder={t('posts.filters.keywordPlaceholder')}
          value={keywordInput}
          onChange={setKeywordInput}
          showClear
          style={{ width: 260 }}
        />
        <Select
          placeholder={t('posts.filters.statusPlaceholder')}
          value={status}
          showClear
          style={{ width: 140 }}
          onChange={(v) => setValues({ status: (v as UserStatus) || undefined, page: 1 })}
        >
          <Select.Option value="enabled">{t('status.enabled')}</Select.Option>
          <Select.Option value="disabled">{t('status.disabled')}</Select.Option>
        </Select>
        {hasFilter ? (
          <Button type="tertiary" size="small" onClick={clearFilters}>
            {t('common.clearFilters')}
          </Button>
        ) : null}
      </div>

      {listQuery.isError || listQuery.isPaused ? (
        <OrgErrorState
          title={t('posts.loadFailed.title')}
          description={t('posts.loadFailed.description')}
          error={listQuery.error}
          paused={listQuery.isPaused}
          onRetry={() => void listQuery.refetch()}
        />
      ) : isFirstLoading ? (
        <Table<Post> columns={columns} dataSource={[]} loading rowKey="id" pagination={false} />
      ) : posts.length === 0 ? (
        <Empty
          title={hasFilter ? t('posts.noResult.title') : t('posts.empty.title')}
          description={hasFilter ? t('posts.noResult.description') : t('posts.empty.description')}
        >
          {hasFilter ? (
            <Button onClick={clearFilters}>{t('common.clearFilters')}</Button>
          ) : canCreate ? (
            <Button
              size="small"
              theme="solid"
              icon={<IconPlus />}
              onClick={() => setFormState({ open: true, post: null })}
            >
              {t('posts.addPost')}
            </Button>
          ) : null}
        </Empty>
      ) : (
        <Table<Post>
          columns={columns}
          dataSource={posts}
          rowKey="id"
          size="default"
          loading={listQuery.isFetching && !isFirstLoading}
          pagination={false}
        />
      )}

      {total > 0 ? (
        <div className="flex justify-end">
          <Pagination
            total={total}
            currentPage={page}
            pageSize={pageSize}
            showSizeChanger
            pageSizeOpts={[10, 20, 50]}
            onPageChange={(p) => setValues({ page: p })}
            onPageSizeChange={(size) => setValues({ page: 1, page_size: size })}
          />
        </div>
      ) : null}

      <PostFormModal
        visible={formState.open}
        post={formState.post}
        onClose={() => setFormState((s) => ({ ...s, open: false }))}
        onSaved={invalidatePosts}
      />
    </div>
  );
}
