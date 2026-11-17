import type { ReactNode } from 'react';

import { Pagination, Table } from '@douyinfe/semi-ui';
import type {
  ChangeInfo,
  ColumnProps,
  RowKey,
  TableProps,
} from '@douyinfe/semi-ui/lib/es/table/interface';

import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { useMe } from '@/hooks/useMe';
import { hasPermission } from '@/hooks/useCan';
import type { TableQueryState, TableSorter } from '@/hooks/useTable';
import type { AdminQuery } from '@/lib/adminClient';

export type TableColumn<T extends object> = ColumnProps<T> & {
  /** 列级权限码：无权限整列不渲染。 */
  permission?: string | string[];
};

export interface SaTableProps<T extends object> {
  columns: TableColumn<T>[];
  rows: T[];
  rowKey?: RowKey<T>;
  loading?: boolean;
  /** 取数失败对象；有错误且当前无行时整块渲染失败态。 */
  error?: unknown;
  onRetry?: () => void;
  empty?: ReactNode;
  /** 受控查询状态：驱动分页与列头排序/筛选回显。 */
  query: TableQueryState;
  total: number;
  /** 分页/排序/筛选任何变化都回传完整新状态；数据不留在组件内。 */
  onQueryChange?: (query: TableQueryState) => void;
  rowSelection?: TableProps<T>['rowSelection'];
  size?: TableProps<T>['size'];
  scroll?: TableProps<T>['scroll'];
  onRow?: TableProps<T>['onRow'];
  showPagination?: boolean;
}

function sorterOf<T extends object>(info: ChangeInfo<T>): TableSorter | null {
  const raw = info.sorter;
  const order = raw?.sortOrder;
  if (raw && typeof raw.dataIndex === 'string' && (order === 'ascend' || order === 'descend')) {
    return { field: raw.dataIndex, order };
  }
  return null;
}

function filtersOf<T extends object>(info: ChangeInfo<T>, base: AdminQuery): AdminQuery {
  const filters: AdminQuery = { ...base };
  for (const item of info.filters ?? []) {
    const key = typeof item.dataIndex === 'string' ? item.dataIndex : '';
    if (!key) {
      continue;
    }
    const values = item.filteredValue;
    filters[key] = Array.isArray(values) && values.length > 0 ? values : undefined;
  }
  return filters;
}

/**
 * 服务端驱动的 Semi Table：排序/筛选/分页只回传新查询状态，不本地处理数据。
 * 列上 sorter:true 出排序箭头、filters 出筛选下拉，onFilter 一律剥掉防本地过滤。
 */
export function SaTable<T extends object>({
  columns,
  rows,
  rowKey = 'id',
  loading,
  error,
  onRetry,
  empty,
  query,
  total,
  onQueryChange,
  rowSelection,
  size,
  scroll,
  onRow,
  showPagination = true,
}: SaTableProps<T>) {
  const { data: me } = useMe();
  const granted = me?.permissions ?? [];

  const semiColumns: ColumnProps<T>[] = columns
    .filter((column) => !column.permission || hasPermission(granted, column.permission))
    .map((column) => {
      const next: ColumnProps<T> = { ...column };
      // `permission` 是扩展键，交给 Semi 前剥掉。
      delete (next as TableColumn<T>).permission;
      const dataIndex = typeof next.dataIndex === 'string' ? next.dataIndex : '';
      if (next.sorter) {
        next.sorter = true;
        next.sortOrder =
          dataIndex && query.sorter?.field === dataIndex ? query.sorter.order : false;
      }
      if (next.filters) {
        const value = dataIndex ? query.filters[dataIndex] : undefined;
        next.filteredValue = Array.isArray(value) ? value : value === undefined ? undefined : [value];
        delete next.onFilter;
      }
      return next;
    });

  const handleChange = (info: ChangeInfo<T>) => {
    onQueryChange?.({
      ...query,
      sorter: sorterOf(info),
      filters: filtersOf(info, query.filters),
    });
  };

  if (error && rows.length === 0) {
    return <ErrorState error={error} onRetry={onRetry} showHome={false} />;
  }

  return (
    <div className="flex flex-col gap-3">
      <Table<T>
        columns={semiColumns}
        dataSource={rows}
        rowKey={rowKey}
        loading={Boolean(loading)}
        pagination={false}
        empty={empty ?? <Empty />}
        onChange={handleChange}
        rowSelection={rowSelection}
        size={size}
        scroll={scroll}
        onRow={onRow}
      />
      {showPagination ? (
        <div className="flex justify-end">
          <Pagination
            total={total}
            currentPage={query.page}
            pageSize={query.pageSize}
            onChange={(page, pageSize) =>
              onQueryChange?.({ ...query, page, pageSize })
            }
            showTotal
            showSizeChanger
          />
        </div>
      ) : null}
    </div>
  );
}
