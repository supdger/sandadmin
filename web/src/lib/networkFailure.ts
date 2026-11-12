import { AdminApiError } from './adminClient';
import { ApiError } from './apiClient';

/** 请求没到后端。两套客户端的判定方式不同，页面只认这一个结果。 */
export function isNetworkFailure(error: unknown): boolean {
  if (error instanceof AdminApiError && error.code === 'NETWORK_ERROR') {
    return true;
  }
  return error instanceof ApiError && error.network;
}
