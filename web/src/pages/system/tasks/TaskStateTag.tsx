import { Tag } from '@douyinfe/semi-ui';
import type { TagColor } from '@douyinfe/semi-ui/lib/es/tag/interface';
import { useTranslation } from 'react-i18next';

import type { TaskState } from '@/api/v1/types';

export interface TaskStateTagProps {
  state: TaskState;
}

const STATE_COLORS: Record<TaskState, TagColor> = {
  queued: 'grey',
  running: 'blue',
  succeeded: 'green',
  failed: 'red',
  cancelled: 'orange',
};

/** 任务流转状态标签；列表与详情共用同一套配色文案。 */
export function TaskStateTag({ state }: TaskStateTagProps) {
  const { t } = useTranslation('tasks');
  return <Tag color={STATE_COLORS[state]}>{t(`states.${state}`)}</Tag>;
}
