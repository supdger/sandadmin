import type { ReactNode } from 'react';

import { Pagination, Table } from '@douyinfe/semi-ui';
import type { ColumnProps, RowKey } from '@douyinfe/semi-ui/lib/es/table/interface';

import { Empty } from './Empty';
import { PageHeader } from './PageHeader';

export interface ListPagePagination {
  page: number;
  pageSize: number;
  total: number;
  onChange: (page: number, pageSize: number) => void;
}

export interface ListPageProps<T extends object> {
  /** Page heading shown in the header area. */
  title: ReactNode;
  subtitle?: ReactNode;
  /** Filter controls rendered between the header and the table. */
  filters?: ReactNode;
  /** Toolbar actions rendered on the right side of the header. */
  toolbar?: ReactNode;
  columns: ColumnProps<T>[];
  dataSource: T[];
  rowKey?: RowKey<T>;
  loading?: boolean;
  pagination?: ListPagePagination;
  /** Custom empty placeholder; defaults to the shared <Empty />. */
  empty?: ReactNode;
}

/**
 * Standard list-page skeleton: header + filter area + toolbar + table +
 * pagination. Fillers (filter fields, columns, toolbar buttons) are supplied
 * by the caller.
 */
export function ListPage<T extends object>({
  title,
  subtitle,
  filters,
  toolbar,
  columns,
  dataSource,
  rowKey = 'id',
  loading,
  pagination,
  empty,
}: ListPageProps<T>) {
  return (
    <div className="flex flex-col gap-4">
      <PageHeader title={title} subtitle={subtitle} extra={toolbar} />
      {filters ? <div className="flex flex-wrap items-center gap-3">{filters}</div> : null}
      <Table<T>
        columns={columns}
        dataSource={dataSource}
        rowKey={rowKey}
        loading={Boolean(loading)}
        pagination={false}
        empty={empty ?? <Empty />}
      />
      {pagination ? (
        <div className="flex justify-end">
          <Pagination
            total={pagination.total}
            currentPage={pagination.page}
            pageSize={pagination.pageSize}
            onChange={pagination.onChange}
            showTotal
            showSizeChanger
          />
        </div>
      ) : null}
    </div>
  );
}
